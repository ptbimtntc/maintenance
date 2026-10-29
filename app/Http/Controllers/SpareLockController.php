<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Models\Employee;
use App\Models\SpareLock;
use App\Models\LototoBoard;
use App\Models\LototoBoardSlot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SpareLockController extends Controller
{
    public function show(Request $request, SpareLock $spareLock): View
    {
        $spareLock->load(['board.businessUnit', 'currentActivity.employee']);
        $board = $spareLock->board;
        abort_unless($board, 404);

        $canManageSafety = $request->user()->can(PermissionName::ManageSafety->value);
        if (! $canManageSafety) {
            $canSeeBoard = Employee::query()
                ->where('business_unit_id', $board->business_unit_id)
                ->visibleTo($request->user())
                ->exists();
            abort_unless($canSeeBoard, 404);

            $visibleEmployee = $spareLock->currentActivity?->employee
                ? Employee::query()->visibleTo($request->user())->whereKey($spareLock->currentActivity->employee_id)->exists()
                : true;
            if (! $visibleEmployee) {
                $spareLock->currentActivity->setRelation('employee', null);
            }
        }

        return view('safety.lototo.spare-lock-activity', [
            'spareLock' => $spareLock,
            'board' => $board,
            'activities' => $spareLock->activities()
                ->when(! $canManageSafety, fn ($query) => $query->whereHas('employee', fn ($employees) => $employees->visibleTo($request->user())))
                ->with(['employee', 'spareLock'])
                ->latest('started_at')
                ->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $board = $this->resolveBoard($request);

        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('spare_locks', 'code')->where('board_id', $board->id),
            ],
        ], [
            'code.regex' => 'Kode hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
        ]);

        SpareLock::query()->create([
            'board_id' => $board->id,
            'code' => strtoupper($data['code']),
            'is_active' => true,
        ]);

        return to_route('safety.lototo.settings', ['board' => $board->id, 'section' => 'spare'])
            ->with('status', 'Gembok spare berhasil ditambahkan.');
    }

    /**
     * Adds $count spare locks to the end of the board's Spare section: each gets the next free code for the prefix
     * (SP-21, SP-22, ...) and its own new position, exactly as raising the Spare count in Edit Board does.
     */
    public function storeBatch(Request $request): RedirectResponse
    {
        $board = $this->resolveBoard($request);
        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:50'],
            'spare_code_prefix' => LototoBoardsController::SPARE_PREFIX_RULES,
        ], LototoBoardsController::SPARE_PREFIX_MESSAGES);
        $prefix = LototoBoardsController::normalizeSparePrefix($data['spare_code_prefix'] ?? $board->defaultSparePrefix());
        $count = (int) $data['count'];

        $codes = DB::transaction(function () use ($board, $prefix, $count) {
            $lockedBoard = LototoBoard::query()->whereKey($board->id)->lockForUpdate()->firstOrFail();
            if (! $lockedBoard->is_active) {
                throw ValidationException::withMessages(['count' => 'Board nonaktif tidak dapat menerima gembok Spare baru. Aktifkan kembali board terlebih dahulu.']);
            }
            $lastPosition = (int) LototoBoardSlot::query()->where('board_id', $lockedBoard->id)->where('category', 'spare')->lockForUpdate()->max('position');
            if ($lastPosition + $count > 200) {
                throw ValidationException::withMessages(['count' => 'Jumlah posisi Spare maksimal 200; board ini sudah memiliki '.$lastPosition.' posisi.']);
            }
            $existingIds = SpareLock::query()->where('board_id', $lockedBoard->id)->pluck('id');

            $lockedBoard->provisionSpareLocks(range($lastPosition + 1, $lastPosition + $count), $prefix);

            return SpareLock::query()->where('board_id', $lockedBoard->id)->whereNotIn('id', $existingIds)->orderBy('id')->pluck('code');
        });

        return to_route('safety.lototo.settings', ['board' => $board->id, 'section' => 'spare'])
            ->with('status', $codes->count().' gembok Spare ditambahkan ('.$codes->first().($codes->count() > 1 ? ' s.d. '.$codes->last() : '').') beserta posisinya di board. Cetak QR-nya dari halaman QR Code.');
    }

    public function update(Request $request, SpareLock $spareLock): RedirectResponse
    {
        $board = $this->resolveBoard($request);
        abort_unless((int) $spareLock->board_id === (int) $board->id, 404);
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('spare_locks', 'code')->where('board_id', $board->id)->ignore($spareLock->id),
            ],
            'is_active' => ['required', 'boolean'],
        ], [
            'code.regex' => 'Kode hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
        ]);

        if ($spareLock->current_activity_id
            && ($data['code'] !== $spareLock->code || ! (bool) $data['is_active'])) {
            return back()->withErrors(['code' => 'Kode atau status gembok tidak dapat diubah saat gembok masih digunakan.'])->withInput();
        }

        $oldCode = $spareLock->code;
        $spareLock->update([
            'code' => $data['code'],
            'is_active' => (bool) $data['is_active'],
        ]);

        return to_route('safety.lototo.settings', ['board' => $board->id, 'section' => 'spare'])
            ->with('status', $oldCode !== $data['code']
                ? "Kode gembok diubah dari {$oldCode} menjadi {$data['code']}. QR lama tidak berlaku lagi: cetak ulang QR {$data['code']} dari halaman Settings board ini. Riwayat aktivitas tetap terhubung ke gembok yang sama."
                : 'Data gembok spare berhasil diperbarui.');
    }

    public function destroy(Request $request, SpareLock $spareLock): RedirectResponse
    {
        $board = $this->resolveBoard($request);
        abort_unless((int) $spareLock->board_id === (int) $board->id, 404);

        DB::transaction(function () use ($spareLock, $board) {
            $locked = SpareLock::query()->where('board_id', $board->id)->whereKey($spareLock->id)->lockForUpdate()->firstOrFail();
            if ($locked->current_activity_id) {
                throw ValidationException::withMessages(['spare_lock' => "Gembok {$locked->code} sedang digunakan sehingga tidak dapat dihapus."]);
            }
            if ($locked->activities()->exists()) {
                throw ValidationException::withMessages(['spare_lock' => "Gembok {$locked->code} memiliki riwayat aktivitas sehingga tidak dapat dihapus. Nonaktifkan gembok agar riwayat tetap tersimpan."]);
            }

            // The position stays on the board as an empty slot; only the physical lock record is removed.
            LototoBoardSlot::query()->where('board_id', $board->id)->where('spare_lock_id', $locked->id)->update(['spare_lock_id' => null]);
            $locked->delete();
        });

        return to_route('safety.lototo.settings', ['board' => $board->id, 'section' => 'spare'])
            ->with('status', "Gembok {$spareLock->code} berhasil dihapus. Posisinya pada board kini kosong dan QR {$spareLock->code} tidak berlaku lagi.");
    }

    private function resolveBoard(Request $request): LototoBoard
    {
        $requested = $request->input('board_id') ?? $request->query('board');

        return $requested
            ? LototoBoard::query()->where('id', $requested)->orWhere('code', $requested)->firstOrFail()
            : LototoBoard::query()->where('code', 'mtn_tr')->firstOrFail();
    }
}
