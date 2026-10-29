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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LototoBoardsController extends Controller
{
    // Generated codes are "{PREFIX}-NN" and must stay valid spare lock codes (letters, digits, - and _ only).
    public const SPARE_PREFIX_RULES = ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+(?:[-_][A-Za-z0-9]+)*$/'];

    public const SPARE_PREFIX_MESSAGES = [
        'spare_code_prefix.regex' => 'Prefix kode Spare hanya boleh berisi huruf dan angka, boleh dipisah tanda hubung atau garis bawah (contoh: SP atau SPH).',
        'spare_code_prefix.max' => 'Prefix kode Spare maksimal 20 karakter.',
    ];

    public function index(Request $request): View
    {
        $this->authorizeManageSafety($request);
        $returnBoard = $request->query('board')
            ? LototoBoard::query()->find($request->query('board'))
            : null;
        $returnBoard ??= LototoBoard::query()->where('code', 'mtn_tr')->first();

        return view('safety.lototo.boards.index', [
            'boards' => LototoBoard::query()->with('businessUnit')->orderBy('division_name')->orderBy('id')->get(),
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
            'board_columns' => ['nullable', 'integer', Rule::in([10, 15, 20])],
            'technician_columns' => ['nullable', 'integer', Rule::in([10, 15, 20])],
            'supervisor_columns' => ['nullable', 'integer', Rule::in([10, 15, 20])],
            'spare_lock_count' => ['nullable', 'integer', 'min:0', 'max:200'],
            'spare_code_prefix' => self::SPARE_PREFIX_RULES,
        ], self::SPARE_PREFIX_MESSAGES);
        $boardColumns = (int) ($data['board_columns'] ?? $data['technician_columns'] ?? 20);
        $spareSlotCount = (int) ($data['spare_lock_count'] ?? 20);
        $sparePrefix = self::normalizeSparePrefix($data['spare_code_prefix'] ?? null);

        $divisionName = trim($data['division_name']);
        $businessUnit = BusinessUnit::query()->whereKey($data['business_unit_id'])->firstOrFail();
        $request->merge(['division_name' => $divisionName]);
        $request->validate([
            'division_name' => [Rule::unique('lototo_boards', 'division_name')->where('business_unit_id', $businessUnit->id)],
        ]);

        $board = DB::transaction(function () use ($divisionName, $businessUnit, $spareSlotCount, $boardColumns, $sparePrefix) {
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

            $board->provisionSpareLocks(range(1, $spareSlotCount), $sparePrefix);

            foreach (range(1, 15) as $position) {
                LototoBoardSlot::query()->create([
                    'board_id' => $board->id,
                    'category' => 'tool',
                    'position' => $position,
                    'is_enabled' => true,
                ]);
            }

            return $board;
        });

        return to_route('safety.lototo.boards.index')
            ->with('status', $spareSlotCount > 0
                ? "Board LOTOTO berhasil dibuat menggunakan Business Unit terpilih, dengan {$spareSlotCount} gembok Spare ({$sparePrefix}-01 dst.) yang siap dicetak QR-nya di Settings."
                : 'Board LOTOTO berhasil dibuat menggunakan Business Unit terpilih.')
            ->with('created_board', $board->id);
    }

    public function update(Request $request, LototoBoard $board): RedirectResponse
    {
        $this->authorizeManageSafety($request);
        $request->merge(['division_name' => trim((string) $request->input('division_name'))]);
        $data = $request->validate([
            'division_name' => ['required', 'string', 'max:100', Rule::unique('lototo_boards', 'division_name')->where('business_unit_id', $request->input('business_unit_id'))->ignore($board->id)],
            'business_unit_id' => ['required', 'integer', Rule::exists('business_units', 'id')],
            'is_active' => ['required', 'boolean'],
            'board_columns' => ['nullable', 'integer', Rule::in([10, 15, 20])],
            'spare_lock_count' => ['nullable', 'integer', 'min:0', 'max:200'],
            'spare_code_prefix' => self::SPARE_PREFIX_RULES,
            'return_board' => ['nullable', 'integer', 'exists:lototo_boards,id'],
        ], self::SPARE_PREFIX_MESSAGES);
        $spareSlotCount = (int) ($data['spare_lock_count'] ?? $board->slots()->where('category', 'spare')->max('position'));
        $sparePrefix = self::normalizeSparePrefix($data['spare_code_prefix'] ?? $board->defaultSparePrefix());

        DB::transaction(function () use ($board, $data, $spareSlotCount, $sparePrefix) {
            $lockedBoard = LototoBoard::query()->whereKey($board->id)->lockForUpdate()->firstOrFail();
            $unitChanged = (int) $lockedBoard->business_unit_id !== (int) $data['business_unit_id'];
            if ($unitChanged && (
                $lockedBoard->slots()->where(function ($query) {
                    $query->whereNotNull('employee_id')->orWhereNotNull('spare_lock_id');
                })->exists()
                || SpareLock::query()->where('board_id', $lockedBoard->id)->exists()
                || LototoActivity::query()->where('board', $lockedBoard->code)->exists()
            )) {
                throw ValidationException::withMessages(['business_unit_id' => 'Business Unit tidak dapat diganti selama board memiliki assignment, gembok, atau histori LOTOTO. Buat board baru untuk unit tujuan.']);
            }

            $oldUnitName = $lockedBoard->businessUnit()->value('name');
            $oldGeneratedTitle = mb_strtoupper($lockedBoard->division_name).' · '.$oldUnitName;
            $newUnit = BusinessUnit::query()
                ->whereKey($data['business_unit_id'])
                ->when((bool) $data['is_active'] || $unitChanged, fn ($query) => $query->where('is_active', true))
                ->lockForUpdate()
                ->first();
            if (! $newUnit) {
                throw ValidationException::withMessages(['business_unit_id' => 'Business Unit harus aktif untuk board aktif atau saat mengganti Business Unit.']);
            }

            if (! $lockedBoard->is_active && (bool) $data['is_active']) {
                $this->validateBoardConfiguration($lockedBoard, $newUnit);
            }
            $newTitle = $lockedBoard->title === $oldGeneratedTitle
                ? mb_strtoupper($data['division_name']).' · '.$newUnit->name
                : $lockedBoard->title;

            $updateData = [
                'division_name' => $data['division_name'],
                'business_unit_id' => $data['business_unit_id'],
                'title' => $newTitle,
                'is_active' => (bool) $data['is_active'],
            ];

            if (isset($data['board_columns'])) {
                $cols = (int) $data['board_columns'];
                $updateData['layout'] = array_merge($lockedBoard->layout ?? [], [
                    'board_columns' => $cols,
                    'technician_columns' => $cols,
                    'supervisor_columns' => $cols,
                ]);
            }

            $lockedBoard->update($updateData);

            $this->reconcileSpareSlots($lockedBoard, $spareSlotCount, $sparePrefix);
        });

        return to_route('safety.lototo.boards.index', array_filter(['board' => $data['return_board'] ?? null]))
            ->with('status', $data['is_active'] ? 'Board LOTOTO berhasil diperbarui dan diaktifkan.' : 'Board LOTOTO berhasil diperbarui.');
    }

    /**
     * Makes the board have exactly $slotCount Spare positions. Only positions that do not exist yet get a new
     * slot and a new physical spare lock, so saving the same count again creates nothing. Removed positions take
     * their lock with them only when that lock was never used; existing codes are never renamed.
     */
    private function reconcileSpareSlots(LototoBoard $board, int $slotCount, string $prefix): void
    {
        $slots = LototoBoardSlot::query()
            ->where('board_id', $board->id)
            ->where('category', 'spare')
            ->lockForUpdate()
            ->get()
            ->keyBy('position');

        $removedSlots = $slots->filter(fn (LototoBoardSlot $slot) => $slot->position > $slotCount)->sortBy('position');
        $removedLocks = SpareLock::query()
            ->where('board_id', $board->id)
            ->whereKey($removedSlots->pluck('spare_lock_id')->filter())
            ->withCount('activities')
            ->lockForUpdate()
            ->get();
        $blockedLocks = $removedLocks->filter(fn (SpareLock $lock) => $lock->current_activity_id || $lock->activities_count > 0);
        if ($blockedLocks->isNotEmpty()) {
            throw ValidationException::withMessages([
                'spare_lock_count' => 'Jumlah Spare tidak dapat dikurangi menjadi '.$slotCount.' karena gembok '.$blockedLocks->pluck('code')->implode(', ')
                    .' sedang digunakan atau memiliki riwayat aktivitas. Gembok dan riwayatnya tetap tersimpan; pindahkan gembok tersebut ke posisi yang lebih kecil di Settings → Spare atau gunakan jumlah yang lebih besar.',
            ]);
        }

        // Slots go first: lototo_board_slots.spare_lock_id references the locks being removed.
        LototoBoardSlot::query()->whereKey($removedSlots->modelKeys())->delete();
        SpareLock::query()->whereKey($removedLocks->modelKeys())->delete();

        $newPositions = array_values(array_filter($slotCount > 0 ? range(1, $slotCount) : [], fn (int $position) => ! $slots->has($position)));
        if ($newPositions !== [] && ! $board->is_active) {
            throw ValidationException::withMessages([
                'spare_lock_count' => 'Board nonaktif tidak dapat menerima gembok Spare baru. Aktifkan kembali board sebelum menambah jumlah Spare.',
            ]);
        }

        $board->provisionSpareLocks($newPositions, $prefix);
    }

    public static function normalizeSparePrefix(?string $prefix): string
    {
        $prefix = strtoupper(trim((string) $prefix));

        return $prefix === '' ? 'SP' : $prefix;
    }

    public function updatePresentation(Request $request, LototoBoard $board): RedirectResponse
    {
        $this->authorizeManageSafety($request);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'subtitle' => ['required', 'string', 'max:255'],
            'section_order' => ['required', 'array', 'size:2'],
            'section_order.*' => ['required', Rule::in(['other', 'spare']), 'distinct'],
            'board_columns' => ['nullable', 'integer', Rule::in([10, 15, 20])],
            'components' => ['required', 'array'],
            'components.technician' => ['required', 'boolean'],
            'components.supervisor' => ['required', 'boolean'],
            'components.other' => ['required', 'boolean'],
            'components.spare' => ['required', 'boolean'],
        ]);

        $boardColumns = (int) ($data['board_columns'] ?? $board->layout['board_columns'] ?? $board->layout['technician_columns'] ?? 20);

        $layout = array_merge($board->layout ?? [], [
            'section_order' => $data['section_order'],
            'board_columns' => $boardColumns,
            'technician_columns' => $boardColumns,
            'supervisor_columns' => $boardColumns,
        ]);

        $board->update([
            'title' => trim($data['title']),
            'subtitle' => trim($data['subtitle']),
            'layout' => $layout,
            'component_settings' => $data['components'],
        ]);

        return to_route('safety.lototo.boards.edit', $board)
            ->with('status', 'Tampilan board berhasil disimpan.');
    }

    public function edit(Request $request, LototoBoard $board): View
    {
        $this->authorizeManageSafety($request);

        return view('safety.lototo.boards.edit', [
            'board' => $board->load('businessUnit'),
            'spareSlotCount' => (int) $board->slots()->where('category', 'spare')->max('position'),
            'sparePrefix' => $board->defaultSparePrefix(),
            'businessUnits' => BusinessUnit::query()
                ->where(fn ($query) => $query->where('is_active', true)->orWhereKey($board->business_unit_id))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function destroy(Request $request, LototoBoard $board): RedirectResponse
    {
        $this->authorizeManageSafety($request);
        $returnBoardId = $request->validate([
            'return_board' => ['nullable', 'integer', 'exists:lototo_boards,id'],
        ])['return_board'] ?? null;

        DB::transaction(function () use ($board) {
            $lockedBoard = LototoBoard::query()->whereKey($board->id)->lockForUpdate()->firstOrFail();

            if ($lockedBoard->code === 'mtn_tr') {
                throw ValidationException::withMessages(['board' => 'Board LOTOTO legacy tidak dapat dihapus karena menjadi rujukan kompatibilitas.']);
            }
            if ($lockedBoard->is_active) {
                throw ValidationException::withMessages(['board' => 'Nonaktifkan board melalui Edit Board sebelum menghapusnya.']);
            }
            if (LototoActivity::query()->where('board', $lockedBoard->code)->exists()) {
                throw ValidationException::withMessages(['board' => "Board {$lockedBoard->division_name} memiliki histori aktivitas LOTOTO, sehingga tidak dapat dihapus. Biarkan board nonaktif agar histori tetap tersimpan."]);
            }
            if (SpareLock::query()->where('board_id', $lockedBoard->id)->exists()) {
                throw ValidationException::withMessages(['board' => "Board {$lockedBoard->division_name} masih memiliki Spare Lock terdaftar (beserta QR-nya), sehingga tidak dapat dihapus. Hapus gembok yang belum pernah digunakan di Settings → Spare terlebih dahulu, atau biarkan board nonaktif."]);
            }
            if (LototoTool::query()->where('board_id', $lockedBoard->id)->exists()) {
                throw ValidationException::withMessages(['board' => "Board {$lockedBoard->division_name} masih memiliki Tools terdaftar, sehingga tidak dapat dihapus. Biarkan board nonaktif."]);
            }

            // Only this board's slot configuration remains: positions, employee assignments and empty Other/Tools slots.
            // Employees and other boards are untouched; slots must go first because lototo_board_slots.board_id is RESTRICT.
            $lockedBoard->slots()->lockForUpdate()->get();
            $lockedBoard->slots()->delete();
            $lockedBoard->delete();
        });

        $fallbackBoardId = LototoBoard::query()->where('code', 'mtn_tr')->value('id');
        if ((int) $returnBoardId === (int) $board->id) {
            $returnBoardId = $fallbackBoardId;
        }

        return to_route('safety.lototo.boards.index', array_filter(['board' => $returnBoardId]))
            ->with('status', "Board {$board->division_name} berhasil dihapus beserta konfigurasi slotnya. Data karyawan dan board lain tidak berubah.");
    }

    private function authorizeManageSafety(Request $request): void
    {
        abort_unless($request->user()?->can(PermissionName::ManageSafety->value), 403);
    }

    private function validateBoardConfiguration(LototoBoard $board, BusinessUnit $businessUnit): void
    {
        $hasDuplicatePositions = LototoBoardSlot::query()
            ->where('board_id', $board->id)
            ->select('category', 'position')
            ->groupBy('category', 'position')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        if ($hasDuplicatePositions) {
            throw ValidationException::withMessages(['is_active' => 'Konfigurasi board memiliki nomor slot duplikat. Perbaiki posisi sebelum mengaktifkan kembali.']);
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
