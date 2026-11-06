<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Models\BusinessUnit;
use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\LototoBoard;
use App\Models\LototoBoardSlot;
use App\Models\LototoActivity;
use App\Models\LototoTool;
use App\Models\SpareLock;
use App\Support\LototoSlotGrid;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LototoBoardsController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeManageSafety($request);
        $returnBoard = $request->query('board')
            ? LototoBoard::query()->find($request->query('board'))
            : null;
        $returnBoard ??= LototoBoard::query()->where('code', 'mtn_tr')->first();

        $boards = LototoBoard::query()->with('businessUnit')->orderBy('division_name')->orderBy('id')->get();
        // Activities still IN per board: deactivating such a board asks first (they can still be finished). Personal
        // locks IN are in_progress activities; a spare lock is IN while it holds its current activity (outstanding).
        $openIds = LototoActivity::query()->where('status', 'in_progress')->whereIn('board', $boards->pluck('code'))->pluck('board', 'id');
        SpareLock::query()->whereIn('board_id', $boards->modelKeys())->whereNotNull('current_activity_id')->get(['board_id', 'current_activity_id'])
            ->each(function (SpareLock $lock) use ($boards, $openIds) {
                $openIds[$lock->current_activity_id] = $boards->firstWhere('id', $lock->board_id)->code;
            });
        $activeActivities = $openIds->countBy();

        return view('safety.lototo.boards.index', [
            'boards' => $boards,
            'activeActivities' => $activeActivities,
            'businessUnits' => BusinessUnit::query()->where('is_active', true)->orderBy('name')->get(),
            'returnBoard' => $returnBoard,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManageSafety($request);
        $data = $request->validate([
            'division_name' => ['required', 'string', 'max:100'],
            'business_unit_id' => ['required', 'integer', Rule::exists('business_units', 'id')->where('is_active', true)],
            'spare_lock_count' => ['required', 'integer', 'min:0', 'max:200'],
        ]);
        // New boards start with the standard 20 columns; Edit Mode on the board changes them.
        $boardColumns = 20;
        $spareSlotCount = (int) $data['spare_lock_count'];

        $divisionName = trim($data['division_name']);
        $businessUnit = BusinessUnit::query()->whereKey($data['business_unit_id'])->firstOrFail();
        $request->merge(['division_name' => $divisionName]);
        $request->validate([
            'division_name' => [Rule::unique('lototo_boards', 'division_name')->where('business_unit_id', $businessUnit->id)],
        ]);

        try {
            $board = $this->createBoard($divisionName, $businessUnit, $spareSlotCount, $boardColumns);
        } catch (UniqueConstraintViolationException) {
            // Another admin took the same division or spare lock codes at the same moment; nothing was saved.
            throw ValidationException::withMessages(['division_name' => 'Board atau kode gembok Spare yang sama baru saja dibuat oleh proses lain. Tidak ada data yang tersimpan; periksa daftar board lalu coba lagi.']);
        }

        $codes = SpareLock::query()->where('board_id', $board->id)->orderBy('code')->pluck('code');

        return to_route('safety.lototo.boards.index')
            ->with('status', $codes->isNotEmpty()
                ? "Board LOTOTO {$board->division_name} berhasil dibuat dengan {$codes->count()} gembok Spare ({$codes->first()} s.d. {$codes->last()}). Cetak QR-nya dari tombol QR Code di board."
                : "Board LOTOTO {$board->division_name} berhasil dibuat.")
            ->with('created_board', $board->id);
    }

    /** Board, its starting slots and its spare locks in one transaction: all of it is saved or none. */
    private function createBoard(string $divisionName, BusinessUnit $businessUnit, int $spareSlotCount, int $boardColumns): LototoBoard
    {
        return DB::transaction(function () use ($divisionName, $businessUnit, $spareSlotCount, $boardColumns) {
            // 30 characters, so the code also fits lototo_activities.board on databases without the widening migration.
            $code = 'board-'.Str::lower(Str::random(24));
            $board = LototoBoard::query()->create([
                'business_unit_id' => $businessUnit->id,
                'division_name' => $divisionName,
                'code' => $code,
                'title' => mb_strtoupper($divisionName).' · '.$businessUnit->name,
                'subtitle' => 'Daftar LOTOTO karyawan dan spare',
                'layout' => [
                    'board_columns' => $boardColumns,
                    'technician_columns' => $boardColumns,
                    'supervisor_columns' => $boardColumns,
                    'section_order' => ['spare', 'other'],
                ],
                'component_settings' => ['technician' => true, 'supervisor' => true, 'other' => true, 'spare' => true],
                'is_active' => true,
            ]);

            $employees = Employee::query()
                ->where('business_unit_id', $businessUnit->id)
                ->when(
                    ($activeStatusId = EmploymentStatus::query()->where('code', 'ACTIVE')->value('id')) !== null,
                    fn ($query) => $query->where('employment_status_id', $activeStatusId)
                )
                ->get(['id', 'lototo_number']);
            $technicians = $employees->filter(fn (Employee $employee) => preg_match('/^\d{1,2}$/', trim((string) $employee->lototo_number)) === 1);
            $technicianNumbers = $technicians->groupBy(fn (Employee $employee) => (int) $employee->lototo_number);
            $lastTechnicianNumber = (int) ($technicianNumbers->keys()->max() ?? 0);

            foreach (range(1, max(1, $lastTechnicianNumber)) as $position) {
                $matchingEmployees = $technicianNumbers->get($position, collect());
                LototoBoardSlot::query()->create([
                    'board_id' => $board->id,
                    'category' => 'technician',
                    'position' => $position,
                    'employee_id' => $matchingEmployees->count() === 1 ? $matchingEmployees->first()->id : null,
                    'is_enabled' => true,
                ]);
            }

            $supervisors = $employees
                ->filter(fn (Employee $employee) => preg_match('/^\d{3}$/', trim((string) $employee->lototo_number)) === 1)
                ->sortBy(fn (Employee $employee) => (int) $employee->lototo_number)
                ->values();
            foreach (range(1, max(10, $supervisors->count())) as $position) {
                LototoBoardSlot::query()->create([
                    'board_id' => $board->id,
                    'category' => 'supervisor',
                    'position' => $position,
                    'employee_id' => $supervisors->get($position - 1)?->id,
                    'is_enabled' => true,
                ]);
            }

            // Spare locks with codes made from the division (DRAMIX-SP-001, ...), OUT (no activity), each on its own Spare slot.
            $board->provisionSpareLocks($spareSlotCount > 0 ? range(1, $spareSlotCount) : []);

            foreach (range(1, 15) as $position) {
                LototoBoardSlot::query()->create([
                    'board_id' => $board->id,
                    'category' => 'tool',
                    'position' => $position,
                    'is_enabled' => true,
                ]);
            }
            LototoSlotGrid::sync($board, LototoSlotGrid::byNumber($board));

            return $board;
        });
    }

    /**
     * Active / Inactive from Manage Board. An inactive board takes no new LOTOTO activity, but activities that are
     * IN can still be finished (OUT, Handover); nothing of the board is removed. Turning a board on checks its
     * configuration first.
     */
    public function updateStatus(Request $request, LototoBoard $board): RedirectResponse
    {
        $this->authorizeManageSafety($request);
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
            'return_board' => ['nullable', 'integer', 'exists:lototo_boards,id'],
        ]);

        DB::transaction(function () use ($board, $data) {
            $lockedBoard = LototoBoard::query()->whereKey($board->id)->lockForUpdate()->firstOrFail();
            if ((bool) $data['is_active'] && ! $lockedBoard->is_active) {
                $unit = BusinessUnit::query()->whereKey($lockedBoard->business_unit_id)->where('is_active', true)->first();
                if (! $unit) {
                    throw ValidationException::withMessages(['is_active' => 'Business Unit board ini tidak aktif, sehingga board tidak dapat diaktifkan.']);
                }
                $this->validateBoardConfiguration($lockedBoard, $unit);
            }
            $lockedBoard->update(['is_active' => (bool) $data['is_active']]);
        });

        return to_route('safety.lototo.boards.index', array_filter(['board' => $data['return_board'] ?? null]))
            ->with('status', $data['is_active']
                ? "Board {$board->division_name} diaktifkan."
                : "Board {$board->division_name} dinonaktifkan. Aktivitas yang masih IN tetap dapat diselesaikan (OUT / Handover).");
    }

    public function destroy(Request $request, LototoBoard $board): RedirectResponse
    {
        $this->authorizeManageSafety($request);
        $returnBoardId = $request->validate([
            'return_board' => ['nullable', 'integer', 'exists:lototo_boards,id'],
        ])['return_board'] ?? null;

        $toolImages = DB::transaction(function () use ($board) {
            $lockedBoard = LototoBoard::query()->whereKey($board->id)->lockForUpdate()->firstOrFail();

            if ($lockedBoard->code === 'mtn_tr') {
                throw ValidationException::withMessages(['board' => 'Board LOTOTO legacy tidak dapat dihapus karena menjadi rujukan kompatibilitas.']);
            }
            if ($lockedBoard->is_active) {
                throw ValidationException::withMessages(['board' => "Nonaktifkan board {$lockedBoard->division_name} terlebih dahulu sebelum menghapusnya."]);
            }
            if (LototoActivity::query()->where('board', $lockedBoard->code)->exists()) {
                throw ValidationException::withMessages(['board' => "Board {$lockedBoard->division_name} memiliki histori aktivitas LOTOTO, sehingga tidak dapat dihapus. Biarkan board nonaktif agar histori tetap tersimpan."]);
            }
            $spareLocks = SpareLock::query()->where('board_id', $lockedBoard->id)->withCount('activities')->lockForUpdate()->get();
            $usedLocks = $spareLocks->filter(fn (SpareLock $lock) => $lock->current_activity_id || $lock->activities_count > 0);
            if ($usedLocks->isNotEmpty()) {
                throw ValidationException::withMessages(['board' => "Board {$lockedBoard->division_name} tidak dapat dihapus: gembok Spare {$usedLocks->pluck('code')->implode(', ')} sedang digunakan atau memiliki riwayat. Biarkan board nonaktif agar riwayat tetap tersimpan."]);
            }
            $tools = LototoTool::query()->where('board_id', $lockedBoard->id)->withCount('activities')->lockForUpdate()->get();
            $usedTools = $tools->filter(fn (LototoTool $tool) => $tool->activities_count > 0);
            if ($usedTools->isNotEmpty()) {
                throw ValidationException::withMessages(['board' => "Board {$lockedBoard->division_name} tidak dapat dihapus: Tools {$usedTools->pluck('code')->implode(', ')} memiliki riwayat penggunaan. Biarkan board nonaktif agar riwayat tetap tersimpan."]);
            }

            // Nothing of this board was ever used: its slots, then its never-used spare locks and tools (the slots
            // reference them, lototo_board_slots.board_id is RESTRICT). Employees and other boards are untouched.
            $lockedBoard->slots()->lockForUpdate()->get();
            $lockedBoard->slots()->delete();
            SpareLock::query()->whereKey($spareLocks->modelKeys())->delete();
            LototoTool::query()->whereKey($tools->modelKeys())->delete();
            $lockedBoard->delete();

            return $tools->pluck('image_path')->filter()->all();
        });
        // Tool images only after the commit, when no row references them any more.
        foreach ($toolImages as $imagePath) {
            Storage::disk('local')->delete($imagePath);
        }

        $fallbackBoardId = LototoBoard::query()->where('code', 'mtn_tr')->value('id');
        if ((int) $returnBoardId === (int) $board->id) {
            $returnBoardId = $fallbackBoardId;
        }

        return to_route('safety.lototo.boards.index', array_filter(['board' => $returnBoardId]))
            ->with('status', "Board {$board->division_name} berhasil dihapus beserta konfigurasi slot, gembok Spare, dan Tools yang belum pernah digunakan. Data karyawan dan board lain tidak berubah.");
    }

    private function authorizeManageSafety(Request $request): void
    {
        abort_unless($request->user()?->can(PermissionName::ManageSafety->value), 403);
    }

    private function validateBoardConfiguration(LototoBoard $board, BusinessUnit $businessUnit): void
    {
        // Once slots have cells, a number may repeat in a category (a slot moved by Edit Mode keeps its number);
        // two slots on one cell is what breaks the board.
        [$columns, $label] = LototoSlotGrid::numbersMayRepeat() ? [['category', 'grid_row', 'grid_column'], 'letak (sel) slot'] : [['category', 'position'], 'nomor slot'];
        $hasDuplicatePositions = LototoBoardSlot::query()
            ->where('board_id', $board->id)
            ->when(LototoSlotGrid::numbersMayRepeat(), fn ($query) => $query->whereNotNull('grid_row'))
            ->select($columns)
            ->groupBy($columns)
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        if ($hasDuplicatePositions) {
            throw ValidationException::withMessages(['is_active' => "Konfigurasi board memiliki {$label} duplikat. Perbaiki posisi sebelum mengaktifkan kembali."]);
        }

        $slots = LototoBoardSlot::query()
            ->where('board_id', $board->id)
            ->with(['employee', 'spareLock'])
            ->get();

        foreach ($slots as $slot) {
            if (! in_array($slot->category, ['technician', 'supervisor', 'spare', 'tool'], true)) {
                throw ValidationException::withMessages(['is_active' => 'Konfigurasi board memiliki kategori slot yang tidak valid. Periksa konfigurasi sebelum mengaktifkan kembali.']);
            }

            if ($slot->employee_id) {
                $employee = $slot->employee;
                $expectedCategory = $employee ? $this->categoryFor($employee) : null;
                if (! $employee
                    || (int) $employee->business_unit_id !== (int) $businessUnit->id
                    || $expectedCategory !== $slot->category) {
                    throw ValidationException::withMessages(['is_active' => "Assignment pada posisi {$slot->position} tidak valid untuk Business Unit board ini. Perbaiki assignment sebelum mengaktifkan kembali."]);
                }
            }

            if ($slot->spare_lock_id
                && ($slot->category !== 'spare'
                    || ! $slot->spareLock
                    || (int) $slot->spareLock->board_id !== (int) $board->id)) {
                throw ValidationException::withMessages(['is_active' => "Assignment Spare Lock pada posisi {$slot->position} tidak valid untuk board ini."]);
            }
        }
    }

    private function categoryFor(Employee $employee): ?string
    {
        $number = trim((string) $employee->lototo_number);

        return match (true) {
            preg_match('/^\d{1,2}$/', $number) === 1 => 'technician',
            preg_match('/^\d{3}$/', $number) === 1 => 'supervisor',
            default => null,
        };
    }
}
