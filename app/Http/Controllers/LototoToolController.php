<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Models\Employee;
use App\Models\LototoBoard;
use App\Models\LototoBoardSlot;
use App\Models\LototoTool;
use App\Models\LototoToolActivity;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class LototoToolController extends Controller
{
    private const IMAGE_DISK = 'local';

    private const IMAGE_RULES = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'];

    private const MESSAGES = [
        'code.regex' => 'Kode Tools hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
        'code.unique' => 'Kode Tools sudah digunakan pada board ini.',
        'image.mimes' => 'Gambar Tools harus berformat JPG, PNG, atau WEBP.',
        'image.mimetypes' => 'Gambar Tools harus berformat JPG, PNG, atau WEBP.',
        'image.max' => 'Ukuran gambar Tools maksimal 2 MB.',
        'location.required' => 'Lokasi wajib diisi untuk IN.',
    ];

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code'))), 'name' => trim((string) $request->input('name'))]);
        $board = $this->resolveBoard($request);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('lototo_tools', 'code')->where('board_id', $board->id)],
            'name' => ['required', 'string', 'max:100'],
            'image' => self::IMAGE_RULES,
        ], self::MESSAGES);

        $imagePath = $this->storeImage($request->file('image'), $board);
        try {
            $position = DB::transaction(function () use ($board, $data, $imagePath) {
                LototoBoard::query()->whereKey($board->id)->lockForUpdate()->firstOrFail();

                $tool = LototoTool::query()->create([
                    'board_id' => $board->id,
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'image_path' => $imagePath,
                    'is_active' => true,
                ]);

                // The board's own Tools positions decide where it shows: the first empty, enabled position, or a new
                // position after the last one (as Add Position does). Order and size are then set in the positions table.
                $toolSlots = LototoBoardSlot::query()->where('board_id', $board->id)->where('category', 'tool')->orderBy('position')->lockForUpdate()->get();
                $slot = $toolSlots->first(fn (LototoBoardSlot $slot) => $slot->is_enabled && ! $slot->tool_id && blank($slot->tool_label))
                    ?? LototoBoardSlot::query()->create([
                        'board_id' => $board->id,
                        'category' => 'tool',
                        'position' => (int) $toolSlots->max('position') + 1,
                        'is_enabled' => true,
                    ]);
                $slot->update(['tool_id' => $tool->id, 'tool_label' => $tool->name]);

                return $slot->position;
            });
        } catch (Throwable $exception) {
            $this->deleteImage($imagePath);
            $this->rethrowDuplicateCode($exception);
        }

        return to_route('safety.lototo.settings', ['board' => $board->id, 'section' => 'tool'])
            ->with('status', sprintf('Tools %s berhasil ditambahkan dan dipasang di posisi %02d. Urutan dan ukuran kolomnya dapat diatur pada tabel posisi Tools.', $data['code'], $position));
    }

    public function update(Request $request, LototoTool $tool): RedirectResponse
    {
        $board = $this->resolveBoard($request);
        abort_unless((int) $tool->board_id === (int) $board->id, 404);
        $request->merge(['code' => strtoupper(trim((string) $request->input('code'))), 'name' => trim((string) $request->input('name'))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('lototo_tools', 'code')->where('board_id', $board->id)->ignore($tool->id)],
            'name' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'remove_image' => ['nullable', 'boolean'],
            'image' => self::IMAGE_RULES,
        ], self::MESSAGES);

        $newImagePath = $this->storeImage($request->file('image'), $board);
        try {
            $oldImagePath = DB::transaction(function () use ($board, $tool, $data, $newImagePath) {
                $locked = LototoTool::query()->where('board_id', $board->id)->whereKey($tool->id)->lockForUpdate()->firstOrFail();
                if (! (bool) $data['is_active'] && $locked->openActivity()->exists()) {
                    throw ValidationException::withMessages(['is_active' => "Tools {$locked->code} sedang IN sehingga tidak dapat dinonaktifkan. Selesaikan OUT terlebih dahulu."]);
                }

                $replacesImage = $newImagePath !== null || (bool) ($data['remove_image'] ?? false);
                $previousImage = $locked->image_path;
                $locked->update([
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'location' => filled($data['location'] ?? null) ? trim($data['location']) : null,
                    'is_active' => (bool) $data['is_active'],
                    'image_path' => $replacesImage ? $newImagePath : $previousImage,
                ]);
                // The board shows the slot label, so the linked slot follows the tool name.
                LototoBoardSlot::query()->where('board_id', $board->id)->where('tool_id', $locked->id)->update(['tool_label' => $locked->name]);

                return $replacesImage ? $previousImage : null;
            });
        } catch (Throwable $exception) {
            $this->deleteImage($newImagePath);
            $this->rethrowDuplicateCode($exception);
        }
        // Only after the commit: the old file is no longer referenced by any row.
        $this->deleteImage($oldImagePath);

        return to_route('safety.lototo.settings', ['board' => $board->id, 'section' => 'tool'])
            ->with('status', "Tools {$data['code']} berhasil diperbarui.");
    }

    public function destroy(Request $request, LototoTool $tool): RedirectResponse
    {
        $board = $this->resolveBoard($request);
        abort_unless((int) $tool->board_id === (int) $board->id, 404);

        $imagePath = DB::transaction(function () use ($board, $tool) {
            $locked = LototoTool::query()->where('board_id', $board->id)->whereKey($tool->id)->lockForUpdate()->firstOrFail();
            if ($locked->openActivity()->exists()) {
                throw ValidationException::withMessages(['tool' => "Tools {$locked->code} sedang IN sehingga tidak dapat dihapus."]);
            }
            if ($locked->activities()->exists()) {
                throw ValidationException::withMessages(['tool' => "Tools {$locked->code} memiliki riwayat penggunaan sehingga tidak dapat dihapus. Nonaktifkan Tools agar riwayat tetap tersimpan."]);
            }

            // The position stays on the board as an empty slot.
            LototoBoardSlot::query()->where('board_id', $board->id)->where('tool_id', $locked->id)->update(['tool_id' => null, 'tool_label' => null]);
            $locked->delete();

            return $locked->image_path;
        });
        $this->deleteImage($imagePath);

        return to_route('safety.lototo.settings', ['board' => $board->id, 'section' => 'tool'])
            ->with('status', "Tools {$tool->code} berhasil dihapus. Posisinya pada board kini kosong.");
    }

    public function image(Request $request, LototoTool $tool): StreamedResponse
    {
        $tool->load('board');
        abort_unless($tool->board && $this->canAccessBoard($request, $tool->board), 404);
        abort_unless($tool->image_path && Storage::disk(self::IMAGE_DISK)->exists($tool->image_path), 404);

        return Storage::disk(self::IMAGE_DISK)->response($tool->image_path, null, [
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function form(Request $request, LototoTool $tool): View
    {
        $board = $this->toolBoard($request, $tool);
        $tool->load('openActivity.startedBy');

        return view('safety.lototo.tool-form', [
            'tool' => $tool,
            'board' => $board->load('businessUnit'),
            'openActivity' => $tool->openActivity,
            'canFinish' => $tool->openActivity && $this->canFinish($request, $tool->openActivity),
            'activities' => $tool->activities()->with(['startedBy', 'endedBy'])->latest('started_at')->latest('id')->paginate(20),
        ]);
    }

    public function storeForm(Request $request, LototoTool $tool): RedirectResponse
    {
        $board = $this->toolBoard($request, $tool);
        $data = $request->validate([
            'board_id' => ['required', 'integer', Rule::in([$board->id])],
            'action' => ['required', Rule::in(['in', 'out'])],
            'location' => [Rule::requiredIf($request->input('action') === 'in'), 'nullable', 'string', 'max:255'],
            'activity_id' => [Rule::requiredIf($request->input('action') === 'out'), 'nullable', 'integer'],
        ], self::MESSAGES);
        $user = $request->user();

        try {
            $message = DB::transaction(function () use ($board, $tool, $data, $request, $user) {
                // Serialises every status change of this tool; a second user waits here and then sees the new state.
                $locked = LototoTool::query()->where('board_id', $board->id)->whereKey($tool->id)->lockForUpdate()->firstOrFail();
                $lockedBoard = LototoBoard::query()->whereKey($board->id)->firstOrFail();
                $open = $locked->openActivity()->first();

                if ($data['action'] === 'in') {
                    if (! $lockedBoard->is_active) {
                        throw ValidationException::withMessages(['action' => 'Board nonaktif tidak menerima IN Tools baru.']);
                    }
                    if (! $locked->is_active) {
                        throw ValidationException::withMessages(['action' => "Tools {$locked->code} nonaktif sehingga tidak dapat di-IN."]);
                    }
                    if ($open) {
                        throw ValidationException::withMessages(['action' => "Tools {$locked->code} sudah IN di {$open->location}. Muat ulang halaman untuk melihat status terkini."]);
                    }
                    LototoToolActivity::query()->create([
                        'tool_id' => $locked->id,
                        'board_id' => $board->id,
                        'location' => trim($data['location']),
                        'status' => 'in_progress',
                        'started_by' => $user->id,
                        'started_at' => now(),
                    ]);

                    return "Tools {$locked->code} berstatus IN di ".trim($data['location']).'.';
                }

                if (! $open || (int) $open->id !== (int) $data['activity_id']) {
                    throw ValidationException::withMessages(['action' => "Status Tools {$locked->code} sudah berubah. Muat ulang halaman untuk melihat status terkini."]);
                }
                if (! $this->canFinish($request, $open)) {
                    abort(403, 'OUT hanya dapat dilakukan oleh pengguna yang melakukan IN atau pengguna dengan izin Manage Safety.');
                }
                $open->update(['status' => 'finished', 'ended_by' => $user->id, 'ended_at' => now()]);

                return "Tools {$locked->code} berstatus OUT dan kembali ke board.";
            });
        } catch (UniqueConstraintViolationException) {
            // The database refused a second open activity for the same tool.
            throw ValidationException::withMessages(['action' => "Tools {$tool->code} baru saja di-IN oleh pengguna lain. Muat ulang halaman untuk melihat status terkini."]);
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) !== 1205) {
                throw $exception;
            }
            throw ValidationException::withMessages(['action' => "Tools {$tool->code} sedang diproses oleh pengguna lain. Coba lagi beberapa saat."]);
        }

        return to_route('safety.lototo.tools.form', ['tool' => $tool->id, 'board' => $board->id])->with('status', $message);
    }

    /**
     * Two managers saving the same code at once both pass the unique rule; the database index on (board_id, code)
     * then refuses the second one, which is reported like the validation error instead of a server error.
     */
    private function rethrowDuplicateCode(Throwable $exception): never
    {
        if ($exception instanceof UniqueConstraintViolationException && str_contains($exception->getMessage(), 'lototo_tools')) {
            throw ValidationException::withMessages(['code' => self::MESSAGES['code.unique']]);
        }

        throw $exception;
    }

    /** The QR always carries the board; a tool is only reachable through the board that owns it. */
    private function toolBoard(Request $request, LototoTool $tool): LototoBoard
    {
        $requested = $request->input('board_id') ?? $request->query('board');
        abort_unless(filled($requested), 404);
        $board = LototoBoard::query()->where('id', $requested)->orWhere('code', $requested)->first();
        abort_unless($board && (int) $board->id === (int) $tool->board_id, 404);
        abort_unless($this->canAccessBoard($request, $board), 404);

        return $board;
    }

    private function canAccessBoard(Request $request, LototoBoard $board): bool
    {
        if ($request->user()->can(PermissionName::ManageSafety->value)) {
            return true;
        }

        return Employee::query()
            ->where('business_unit_id', $board->business_unit_id)
            ->visibleTo($request->user())
            ->exists();
    }

    private function canFinish(Request $request, LototoToolActivity $activity): bool
    {
        return (int) $activity->started_by === (int) $request->user()->id
            || $request->user()->can(PermissionName::ManageSafety->value);
    }

    private function storeImage(?UploadedFile $file, LototoBoard $board): ?string
    {
        return $file ? $file->store('lototo-tools/'.$board->id, self::IMAGE_DISK) : null;
    }

    private function deleteImage(?string $path): void
    {
        if ($path) {
            Storage::disk(self::IMAGE_DISK)->delete($path);
        }
    }

    private function resolveBoard(Request $request): LototoBoard
    {
        $requested = $request->input('board_id') ?? $request->query('board');
        abort_unless(filled($requested), 404);

        return LototoBoard::query()->where('id', $requested)->orWhere('code', $requested)->firstOrFail();
    }
}
