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
use Illuminate\Http\JsonResponse;
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
        'name.required' => 'Nama Tools wajib diisi.',
        'image.mimes' => 'Gambar Tools harus berformat JPG, PNG, atau WEBP.',
        'image.mimetypes' => 'Gambar Tools harus berformat JPG, PNG, atau WEBP.',
        'image.max' => 'Ukuran gambar Tools maksimal 2 MB.',
        'location.required' => 'Lokasi wajib diisi untuk IN.',
    ];

    /**
     * "Tambah Tools" in Edit Mode: a tool for the board with its name and, optionally, a picture; its code is the
     * first unused TL-NN of the board. It is not placed on a slot; Edit Slot offers it. A name the board already
     * has is refused, so saving the same form twice makes one tool.
     */
    public function store(Request $request, LototoBoard $board): JsonResponse
    {
        $this->authorizeManageSafety($request);
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'image' => self::IMAGE_RULES,
        ], self::MESSAGES);

        $imagePath = $this->storeImage($request->file('image'), $board);
        try {
            $tool = DB::transaction(function () use ($board, $data, $imagePath) {
                LototoBoard::query()->whereKey($board->id)->lockForUpdate()->firstOrFail();
                $this->refuseDuplicateName($board, $data['name']);
                $used = LototoTool::query()->where('board_id', $board->id)->pluck('code')->map(fn ($code) => strtoupper($code))->flip();
                $number = 0;
                do {
                    $code = sprintf('TL-%02d', ++$number);
                } while ($used->has($code));

                return LototoTool::query()->create([
                    'board_id' => $board->id,
                    'code' => $code,
                    'name' => $data['name'],
                    'image_path' => $imagePath,
                    'is_active' => true,
                ]);
            });
        } catch (Throwable $exception) {
            $this->deleteImage($imagePath);
            throw $exception;
        }

        return response()->json([
            'message' => "Tools {$tool->code} \"{$tool->name}\" dibuat. Pilih di popup Edit Slot untuk menempatkannya.",
            'tool' => LototoBoardSettingsController::presentTool($tool->loadCount('activities'), null),
        ], 201);
    }

    /**
     * "Edit" in the Tools popup: a new name and, optionally, a new picture (without one the old picture stays).
     * Slots holding the tool show the new name; its history is untouched.
     */
    public function update(Request $request, LototoBoard $board, LototoTool $tool): JsonResponse
    {
        $this->authorizeManageSafety($request);
        abort_unless((int) $tool->board_id === (int) $board->id, 404);
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'image' => self::IMAGE_RULES,
        ], self::MESSAGES);

        $newImagePath = $this->storeImage($request->file('image'), $board);
        try {
            [$updated, $oldImagePath] = DB::transaction(function () use ($board, $tool, $data, $newImagePath) {
                $locked = LototoTool::query()->where('board_id', $board->id)->whereKey($tool->id)->lockForUpdate()->firstOrFail();
                $this->refuseDuplicateName($board, $data['name'], $locked->id);
                $previousImage = $locked->image_path;
                $locked->update([
                    'name' => $data['name'],
                    'image_path' => $newImagePath ?? $previousImage,
                ]);
                // The board shows the slot label, so a slot holding the tool follows its name.
                LototoBoardSlot::query()->where('board_id', $board->id)->where('tool_id', $locked->id)->update(['tool_label' => $locked->name]);

                return [$locked, $newImagePath ? $previousImage : null];
            });
        } catch (Throwable $exception) {
            $this->deleteImage($newImagePath);
            throw $exception;
        }
        // Only after the commit, and only when no other tool points at the same file.
        $this->deleteUnusedImage($oldImagePath);
        $slot = LototoBoardSlot::query()->where('board_id', $board->id)->where('tool_id', $updated->id)->first();

        return response()->json([
            'message' => "Tools {$updated->code} diperbarui.",
            'tool' => LototoBoardSettingsController::presentTool($updated->loadCount('activities'), $slot ? LototoBoardSettingsController::categoryLabel($slot->category).' '.sprintf('%02d', $slot->position) : null),
        ]);
    }

    /**
     * "Hapus" in the Tools popup. A tool that is IN, has history (kept with it) or still sits in a slot is refused
     * with the reason; its picture goes only when no other tool uses it.
     */
    public function destroy(Request $request, LototoBoard $board, LototoTool $tool): JsonResponse
    {
        $this->authorizeManageSafety($request);
        abort_unless((int) $tool->board_id === (int) $board->id, 404);

        $imagePath = DB::transaction(function () use ($board, $tool) {
            $locked = LototoTool::query()->where('board_id', $board->id)->whereKey($tool->id)->lockForUpdate()->firstOrFail();
            if ($locked->openActivity()->exists()) {
                throw ValidationException::withMessages(['tool' => "Tools {$locked->code} sedang IN sehingga tidak dapat dihapus."]);
            }
            if ($locked->activities()->exists()) {
                throw ValidationException::withMessages(['tool' => "Tools {$locked->code} memiliki riwayat penggunaan sehingga tidak dapat dihapus; riwayatnya tetap tersimpan."]);
            }
            $slot = LototoBoardSlot::query()->where('board_id', $board->id)->where('tool_id', $locked->id)->first();
            if ($slot) {
                throw ValidationException::withMessages(['tool' => sprintf('Tools %s masih terpasang di slot %s %02d. Kosongkan slot itu lewat Edit Slot dan simpan, lalu hapus Tools-nya.', $locked->code, LototoBoardSettingsController::categoryLabel($slot->category), $slot->position)]);
            }
            $locked->delete();

            return $locked->image_path;
        });
        $this->deleteUnusedImage($imagePath);

        return response()->json(['message' => "Tools {$tool->code} dihapus; QR {$tool->code} tidak berlaku lagi."]);
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
                        'started_at' => now('UTC'),
                    ]);

                    return "Tools {$locked->code} berstatus IN di ".trim($data['location']).'.';
                }

                if (! $open || (int) $open->id !== (int) $data['activity_id']) {
                    throw ValidationException::withMessages(['action' => "Status Tools {$locked->code} sudah berubah. Muat ulang halaman untuk melihat status terkini."]);
                }
                if (! $this->canFinish($request, $open)) {
                    abort(403, 'OUT hanya dapat dilakukan oleh pengguna yang melakukan IN atau pengguna dengan izin Manage Safety.');
                }
                $open->update(['status' => 'finished', 'ended_by' => $user->id, 'ended_at' => now('UTC')]);

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

    private function refuseDuplicateName(LototoBoard $board, string $name, ?int $exceptId = null): void
    {
        $taken = LototoTool::query()->where('board_id', $board->id)->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->value('code');
        if ($taken) {
            throw ValidationException::withMessages(['name' => "Tools \"{$name}\" sudah ada pada board ini ({$taken})."]);
        }
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

    /** Deletes a tool picture that no tool references any more (a path is never shared today, this keeps it so). */
    private function deleteUnusedImage(?string $path): void
    {
        if ($path && ! LototoTool::query()->where('image_path', $path)->exists()) {
            $this->deleteImage($path);
        }
    }

    private function authorizeManageSafety(Request $request): void
    {
        abort_unless($request->user()?->can(PermissionName::ManageSafety->value), 403);
    }
}
