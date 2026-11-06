<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Models\Employee;
use App\Models\SpareLock;
use App\Models\LototoBoard;
use App\Models\LototoBoardSlot;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    /**
     * "Tambah Spare Lock" in Edit Mode: one new physical lock for the board, its code made from the division
     * (LototoBoard::nextSpareCodes), OUT (no activity). It is not placed on a slot; Edit Slot offers it.
     */
    public function store(Request $request, LototoBoard $board): JsonResponse
    {
        $this->authorizeManageSafety($request);
        try {
            $lock = DB::transaction(function () use ($board) {
                $lockedBoard = LototoBoard::query()->whereKey($board->id)->lockForUpdate()->firstOrFail();
                if (! $lockedBoard->is_active) {
                    throw ValidationException::withMessages(['spare_lock' => 'Board nonaktif tidak dapat menerima gembok Spare baru. Aktifkan kembali board terlebih dahulu.']);
                }

                return SpareLock::query()->create(['board_id' => $lockedBoard->id, 'code' => $lockedBoard->nextSpareCodes(1)[0], 'is_active' => true]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['spare_lock' => 'Kode gembok berikutnya baru saja dipakai proses lain. Tidak ada data yang tersimpan; coba lagi.']);
        }

        return response()->json([
            'message' => "Gembok Spare {$lock->code} dibuat (OUT). Pilih di popup Edit Slot untuk menempatkannya.",
            'spare_lock' => ['id' => $lock->id, 'code' => $lock->code, 'in' => false, 'history' => false, 'slot' => null],
            'next_spare_code' => $board->nextSpareCodes(1)[0],
        ], 201);
    }

    /**
     * "Hapus" in the Spare Lock popup. A lock that is IN, has history (kept with it) or still sits in a slot is
     * refused with the reason; nothing else changes.
     */
    public function destroy(Request $request, LototoBoard $board, SpareLock $spareLock): JsonResponse
    {
        $this->authorizeManageSafety($request);
        abort_unless((int) $spareLock->board_id === (int) $board->id, 404);

        DB::transaction(function () use ($spareLock, $board) {
            $locked = SpareLock::query()->where('board_id', $board->id)->whereKey($spareLock->id)->lockForUpdate()->firstOrFail();
            if ($locked->current_activity_id) {
                throw ValidationException::withMessages(['spare_lock' => "Gembok {$locked->code} sedang IN sehingga tidak dapat dihapus."]);
            }
            if ($locked->activities()->exists()) {
                throw ValidationException::withMessages(['spare_lock' => "Gembok {$locked->code} memiliki riwayat penggunaan sehingga tidak dapat dihapus; riwayatnya tetap tersimpan."]);
            }
            $slot = LototoBoardSlot::query()->where('board_id', $board->id)->where('spare_lock_id', $locked->id)->first();
            if ($slot) {
                throw ValidationException::withMessages(['spare_lock' => sprintf('Gembok %s masih terpasang di slot %s %02d. Kosongkan slot itu lewat Edit Slot dan simpan, lalu hapus gemboknya.', $locked->code, LototoBoardSettingsController::categoryLabel($slot->category), $slot->position)]);
            }
            $locked->delete();
        });

        return response()->json([
            'message' => "Gembok {$spareLock->code} dihapus; QR {$spareLock->code} tidak berlaku lagi.",
            'next_spare_code' => $board->nextSpareCodes(1)[0],
        ]);
    }

    private function authorizeManageSafety(Request $request): void
    {
        abort_unless($request->user()?->can(PermissionName::ManageSafety->value), 403);
    }

}
