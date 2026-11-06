<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\LototoActivity;
use App\Models\LototoBoard;
use App\Models\LototoBoardSlot;
use App\Models\LototoTool;
use App\Models\SpareLock;
use App\Support\LototoSlotGrid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LototoBoardSettingsController extends Controller
{
    private const CATEGORIES = ['technician', 'supervisor', 'spare', 'tool'];

    // Column counts the inline editor may save per category. Cards keep the 20-column size, so wider rows scroll.
    public const MIN_COLUMNS = 5;

    public const MAX_COLUMNS = 30;

    /**
     * The QR Code page of a board: the QR of every technician, supervisor, spare lock and tool, each with the print
     * designs popup (_print-designs). Layout, slots, tools and spare locks are managed in Edit Mode on the board.
     */
    public function qrCodes(Request $request): View
    {
        $this->authorizeQrViewer($request);
        $board = $this->resolveBoard($request);

        $assignedEmployeeIds = LototoBoardSlot::query()
            ->where('board_id', $board->id)
            ->whereIn('category', ['technician', 'supervisor'])
            ->whereNotNull('employee_id')
            ->pluck('employee_id')
            ->unique()
            ->all();
        $activeStatusId = EmploymentStatus::query()->where('code', 'ACTIVE')->value('id');
        $employees = Employee::withTrashed()
            ->where(function ($query) use ($activeStatusId, $assignedEmployeeIds, $board) {
                $query->where(function ($activeEmployees) use ($activeStatusId, $board) {
                    $activeEmployees->where('business_unit_id', $board->business_unit_id);

                    if ($activeStatusId !== null) {
                        $activeEmployees->where('employment_status_id', $activeStatusId);
                    }
                });

                if ($assignedEmployeeIds !== []) {
                    $query->orWhereIn('id', $assignedEmployeeIds);
                }
            })
            ->orderBy('lototo_number')
            ->get();

        return view('safety.lototo.qr-codes', [
            'board' => $board,
            'qrGroups' => [
                ['label' => 'Technician Locks', 'kind' => 'technician', 'employees' => $employees->filter(fn (Employee $employee) => $this->categoryFor($employee) === 'technician')->values()],
                ['label' => 'Supervisor Locks', 'kind' => 'supervisor', 'employees' => $employees->filter(fn (Employee $employee) => $this->categoryFor($employee) === 'supervisor')->values()],
            ],
            'qrSpareLocks' => SpareLock::query()->where('board_id', $board->id)->orderBy('code')->get(),
            'tools' => LototoTool::query()->where('board_id', $board->id)->orderBy('code')->get(),
        ]);
    }

    /** The whole board configuration for the inline editor, with the version its save must send back. */
    public function layout(Request $request, LototoBoard $board): JsonResponse
    {
        $this->authorizeManageSafety($request);
        $slots = LototoBoardSlot::query()->where('board_id', $board->id)->with(['spareLock', 'tool'])->orderBy('id')->get();
        $activeEmployeeIds = LototoActivity::query()
            ->whereIn('employee_id', $slots->pluck('employee_id')->filter())
            ->where('status', 'in_progress')
            ->pluck('employee_id')
            ->flip();
        $activeStatusId = EmploymentStatus::query()->where('code', 'ACTIVE')->value('id');
        $employees = Employee::query()
            ->where('business_unit_id', $board->business_unit_id)
            ->when($activeStatusId !== null, fn ($query) => $query->where('employment_status_id', $activeStatusId))
            ->orderBy('lototo_number')
            ->get();

        return response()->json([
            'version' => self::layoutVersion($board, $slots),
            'title' => $board->title,
            'subtitle' => $board->subtitle,
            // One column count for the whole board (also set in Settings); columns are what each band draws today,
            // which differ only on a board saved with per-band widths before.
            'board_columns' => self::boardColumns($board),
            'columns' => self::layoutColumns($board),
            'section_order' => self::layoutSectionOrder($board->layout['section_order'] ?? null),
            'components' => collect(['technician', 'supervisor', 'other', 'spare'])
                ->mapWithKeys(fn ($key) => [$key => (bool) ($board->component_settings[$key] ?? true)]),
            'column_limits' => ['min' => self::MIN_COLUMNS, 'max' => self::MAX_COLUMNS],
            // The board's tools and spare locks for the Kelola Tools / Kelola Spare Lock popups.
            'manage' => self::manageData($board),
            // Cells decide where a slot is drawn (only once every slot has one); numbers may then repeat per category.
            'coordinates' => LototoSlotGrid::isStored() && $slots->every(fn ($slot) => $slot->grid_row !== null && $slot->grid_column !== null),
            'numbers_may_repeat' => LototoSlotGrid::numbersMayRepeat(),
            // A slot's band is its own (section); without the section column it is its category.
            'sections' => LototoSlotGrid::hasSections(),
            'slots' => collect(self::CATEGORIES)->mapWithKeys(fn ($category) => [$category => $slots->where('category', $category)->sortBy(['position', 'id'])->map(fn (LototoBoardSlot $slot) => [
                'id' => $slot->id,
                'position' => (int) $slot->position,
                'section' => LototoSlotGrid::sectionOf($slot),
                'grid_row' => LototoSlotGrid::isStored() ? $slot->grid_row : null,
                'grid_column' => LototoSlotGrid::isStored() ? $slot->grid_column : null,
                'employee_id' => $this->nullableId($slot->employee_id),
                'spare_lock_id' => $this->nullableId($slot->spare_lock_id),
                'tool_id' => $this->nullableId($slot->tool_id),
                'tool_label' => $slot->tool_label,
                // Shown in the popup only; the save does not take it.
                'tool_code' => $slot->tool?->code,
                'tool_column_span' => (int) ($slot->tool_column_span ?? 1),
                'is_enabled' => (bool) $slot->is_enabled,
                // Slots whose lock is IN: the editor shows them locked, the save validates them again.
                'locked' => ($slot->employee_id && $activeEmployeeIds->has($slot->employee_id)) || (bool) $slot->spareLock?->current_activity_id,
            ])->values()]),
            'options' => [
                'technician' => $employees->filter(fn (Employee $employee) => $this->categoryFor($employee) === 'technician')->map(fn (Employee $employee) => [
                    'id' => $employee->id, 'lototo_number' => $employee->lototo_number, 'name' => $employee->preferred_name ?: $employee->full_name,
                ])->values(),
                'supervisor' => $employees->filter(fn (Employee $employee) => $this->categoryFor($employee) === 'supervisor')->map(fn (Employee $employee) => [
                    'id' => $employee->id, 'lototo_number' => $employee->lototo_number, 'name' => $employee->preferred_name ?: $employee->full_name,
                ])->values(),
                'spare' => SpareLock::query()->where('board_id', $board->id)->where('is_active', true)->orderBy('code')->get(['id', 'code']),
                'tool' => LototoTool::query()->where('board_id', $board->id)->orderBy('code')->get(['id', 'code', 'name']),
            ],
        ]);
    }

    /**
     * An employee's photo for the QR print designs, from this application's own address: the designs are drawn on a
     * canvas, which only exports images of the same origin (the public storage URL may carry another host).
     */
    public function printPhoto(Request $request, Employee $employee)
    {
        $this->authorizeQrViewer($request);
        abort_unless(filled($employee->photo_path) && Storage::disk('public')->exists($employee->photo_path), 404);

        return Storage::disk('public')->response($employee->photo_path, null, ['Cache-Control' => 'private, max-age=300']);
    }

    /**
     * Saves the inline editor's draft in one transaction: presentation, layout and every slot of every category.
     * Each category list must hold all of that category's current slots (by id) plus new ones without an id; an
     * empty non-Technician slot may be listed under another category to change its category. A stale version or
     * any failed rule saves nothing.
     */
    public function updateLayout(Request $request, LototoBoard $board): JsonResponse
    {
        $this->authorizeManageSafety($request);
        $employeeRules = ['present', 'nullable', 'integer', 'exists:employees,id'];
        $data = $request->validate([
            'version' => ['required', 'string'],
            'title' => ['required', 'string', 'max:160'],
            'subtitle' => ['required', 'string', 'max:255'],
            'board_columns' => ['required', 'integer', 'min:'.self::MIN_COLUMNS, 'max:'.self::MAX_COLUMNS],
            'apply_columns' => ['required', 'boolean'],
            'section_order' => ['required', 'array', 'size:3'],
            'section_order.*' => ['required', 'string', Rule::in(['spare', 'supervisor', 'other']), 'distinct'],
            'components' => ['required', 'array:technician,supervisor,other,spare'],
            'components.technician' => ['required', 'boolean'],
            'components.supervisor' => ['required', 'boolean'],
            'components.other' => ['required', 'boolean'],
            'components.spare' => ['required', 'boolean'],
            'slots' => ['required', 'array:technician,supervisor,spare,tool'],
            'slots.technician' => ['present', 'array'],
            'slots.supervisor' => ['present', 'array'],
            'slots.spare' => ['present', 'array'],
            'slots.tool' => ['present', 'array'],
            'slots.*.*' => ['required', 'array:id,position,section,grid_row,grid_column,employee_id,spare_lock_id,tool_id,tool_label,tool_code,tool_column_span,is_enabled,locked'],
            'slots.*.*.id' => ['nullable', 'integer'],
            'slots.*.*.position' => ['required', 'integer', 'min:1', 'max:99999'],
            'slots.*.*.grid_row' => ['nullable', 'integer', 'min:1', 'max:999'],
            'slots.*.*.grid_column' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_COLUMNS],
            'slots.*.*.section' => ['nullable', Rule::in(self::CATEGORIES)],
            'slots.*.*.is_enabled' => ['required', 'boolean'],
            'slots.technician.*.employee_id' => $employeeRules,
            'slots.supervisor.*.employee_id' => $employeeRules,
            'slots.spare.*.spare_lock_id' => ['present', 'nullable', 'integer', 'exists:spare_locks,id'],
            'slots.tool.*.tool_id' => ['nullable', 'integer', Rule::exists('lototo_tools', 'id')->where('board_id', $board->id)],
            'slots.tool.*.tool_label' => ['nullable', 'string', 'max:100'],
            'slots.tool.*.tool_column_span' => ['nullable', 'integer', 'in:1,2,3'],
            // Saved slots removed in Edit Mode (their records, history and lock or tool data stay).
            'removed' => ['nullable', 'array'],
            'removed.*' => ['integer', 'distinct'],
        ]);

        DB::transaction(function () use ($board, $data) {
            $lockedBoard = LototoBoard::query()->whereKey($board->id)->lockForUpdate()->firstOrFail();
            $currentSlots = LototoBoardSlot::query()->where('board_id', $board->id)->with('spareLock')->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless(hash_equals(self::layoutVersion($lockedBoard, $currentSlots), $data['version']), 409,
                'Konfigurasi board sudah diubah oleh pengguna lain. Muat ulang board lalu ulangi perubahan Anda.');

            $submittedIds = collect($data['slots'])->flatten(1)->pluck('id')->filter()->map(fn ($id) => (int) $id);
            $removedIds = collect($data['removed'] ?? [])->map(fn ($id) => (int) $id);
            if ($submittedIds->duplicates()->isNotEmpty() || $submittedIds->intersect($removedIds)->isNotEmpty()
                || $submittedIds->merge($removedIds)->sort()->values()->all() !== $currentSlots->keys()->sort()->values()->all()) {
                throw ValidationException::withMessages(['slots' => 'Daftar posisi berubah. Muat ulang halaman sebelum menyimpan.']);
            }
            $this->validateRemovedSlots($lockedBoard, $currentSlots->only($removedIds->all()));

            // Per category: the slots it will hold (current ones keep their saved values for the checks below,
            // new or re-categorised ones start empty) and the submitted values, both keyed by slot id or "new-N".
            $plans = [];
            foreach (self::CATEGORIES as $category) {
                $field = self::assignmentField($category);
                $existing = collect();
                $incoming = collect();
                $submitted = [];
                foreach ($data['slots'][$category] as $index => $entry) {
                    $slot = filled($entry['id'] ?? null) ? $currentSlots->get((int) $entry['id']) : null;
                    if ($slot && $slot->category !== $category) {
                        if ($slot->category === 'technician' || $category === 'technician'
                            || $slot->employee_id || $slot->spare_lock_id || $slot->tool_id || filled($slot->tool_label)) {
                            throw ValidationException::withMessages(['slots' => "Kategori posisi {$slot->position} hanya dapat diganti jika slot kosong dan bukan posisi Technician."]);
                        }
                    }
                    // A slot stays in its band: the category only changes what it holds.
                    $currentSection = $slot ? LototoSlotGrid::sectionOf($slot) : null;
                    $section = $currentSection ?? ($entry['section'] ?? null) ?: $category;
                    if ($currentSection !== null && filled($entry['section'] ?? null) && $entry['section'] !== $currentSection) {
                        throw ValidationException::withMessages(['slots' => "Slot {$slot->position} tetap berada di pita ".self::categoryLabel($currentSection).'; letaknya tidak dapat dipindah ke pita lain.']);
                    }
                    if (($section === 'technician') !== ($category === 'technician')) {
                        throw ValidationException::withMessages(['slots' => 'Pita Technician hanya berisi slot Technician, dan slot Technician hanya berada di pita Technician.']);
                    }
                    $key = $slot ? $slot->id : 'new-'.$index;
                    // A slot keeps its cell (and moves with it to a new category) unless the editor sends another.
                    $hasCell = array_key_exists('grid_row', $entry) && array_key_exists('grid_column', $entry);
                    $submitted[$key] = [
                        'position' => (int) $entry['position'],
                        'original_position' => $slot ? (int) $slot->position : null,
                        'section' => $section,
                        'grid_row' => $hasCell ? $this->nullableId($entry['grid_row']) : $this->nullableId($slot?->grid_row),
                        'grid_column' => $hasCell ? $this->nullableId($entry['grid_column']) : $this->nullableId($slot?->grid_column),
                        $field => $this->nullableId($entry[$field] ?? null),
                        'is_enabled' => (bool) $entry['is_enabled'],
                        'tool_column_span' => (int) ($entry['tool_column_span'] ?? $slot?->tool_column_span ?? 1),
                    ];
                    if ($slot && $slot->category === $category) {
                        $existing->put($key, $slot);
                    } else {
                        $incoming->put($key, new LototoBoardSlot(['board_id' => $board->id, 'category' => $category, 'position' => (int) $entry['position'], 'is_enabled' => true]));
                    }
                }
                $slots = $existing->union($incoming);
                $this->validateCategoryPlan($lockedBoard, $category, $existing, $slots, $submitted);
                $plans[$category] = ['existing' => $existing, 'slots' => $slots, 'submitted' => $submitted];
            }

            // One column count for the whole board. Changed here, every band takes it; otherwise each band keeps the
            // width it has (a board saved with per-band widths before stays as it is).
            $boardColumns = (int) $data['board_columns'];
            $applyColumns = (bool) $data['apply_columns'];
            if (! $applyColumns && $boardColumns !== self::boardColumns($lockedBoard)) {
                throw ValidationException::withMessages(['board_columns' => 'Jumlah kolom per baris berubah tanpa diterapkan. Muat ulang board lalu ulangi.']);
            }
            $columns = collect($applyColumns ? array_fill_keys(self::CATEGORIES, $boardColumns) : self::layoutColumns($lockedBoard));
            if ($applyColumns && LototoSlotGrid::isStored()) {
                $this->reflowPlans($plans, $boardColumns);
            }
            if (LototoSlotGrid::isStored()) {
                $this->validateCells($plans, $currentSlots, $columns->all(), $applyColumns);
            }
            $lockedBoard->update([
                'title' => trim($data['title']),
                'subtitle' => trim($data['subtitle']),
                'layout' => array_merge($lockedBoard->layout ?? [], $applyColumns ? [
                    'board_columns' => $boardColumns,
                    'technician_columns' => $boardColumns,
                    'supervisor_columns' => $boardColumns,
                    'spare_columns' => $boardColumns,
                    'tool_columns' => $boardColumns,
                ] : [], [
                    'section_order' => self::layoutSectionOrder($data['section_order']),
                ]),
                'component_settings' => collect($data['components'])->map(fn ($visible) => (bool) $visible)->all(),
            ]);

            // Removed slots go first; their employee, lock or tool simply has no place on the board any more.
            if ($removedIds->isNotEmpty()) {
                LototoBoardSlot::query()->where('board_id', $board->id)->whereKey($removedIds->all())->delete();
            }

            // Free every position and employee/spare/tool assignment first so swaps and moves never hit the unique keys.
            $temporaryPosition = (int) $currentSlots->max('position') + $currentSlots->count() + 1;
            foreach ($currentSlots as $slotId => $slot) {
                if ($removedIds->contains($slotId)) {
                    continue;
                }
                LototoBoardSlot::query()->where('board_id', $board->id)->whereKey($slotId)->update([
                    'position' => $temporaryPosition++, 'employee_id' => null, 'spare_lock_id' => null, 'tool_id' => null,
                    // A slot changing category takes its old cell along; clear it so it cannot meet the cell index.
                    ...(LototoSlotGrid::isStored() ? ['grid_row' => null, 'grid_column' => null] : []),
                ]);
            }

            // A Tools slot shows the chosen tool's name; tools themselves are created with "+ Tools", never here.
            $toolNames = LototoTool::query()->where('board_id', $board->id)->pluck('name', 'id');
            foreach ($plans as $category => $plan) {
                $field = self::assignmentField($category);
                foreach ($plan['submitted'] as $key => $values) {
                    $attributes = [
                        'category' => $category,
                        'position' => $values['position'],
                        $field => $values[$field],
                        'is_enabled' => $values['is_enabled'],
                        ...($category === 'tool' ? ['tool_column_span' => $values['tool_column_span'], 'tool_label' => $toolNames->get($values['tool_id'])] : ['tool_label' => null]),
                        ...(LototoSlotGrid::isStored() ? ['grid_row' => $values['grid_row'], 'grid_column' => $values['grid_column']] : []),
                        ...(LototoSlotGrid::hasSections() ? ['section' => $values['section']] : []),
                    ];
                    if (is_int($key)) {
                        LototoBoardSlot::query()->where('board_id', $board->id)->whereKey($key)->update($attributes);
                    } else {
                        LototoBoardSlot::query()->create(['board_id' => $board->id, ...$attributes]);
                    }
                }
            }
            LototoSlotGrid::sync($board);
        });

        return response()->json([
            'message' => 'Konfigurasi board berhasil disimpan.',
            'version' => self::layoutVersion($board->fresh()),
            'redirect' => route('safety.lototo.boards.show', $board),
        ]);
    }

    /**
     * Checks one category of an inline-editor save with the same rules as the per-category Settings form.
     * $existing are the category's current slots; $slots adds the new or re-categorised ones (empty).
     */
    private function validateCategoryPlan(LototoBoard $board, string $category, Collection $existing, Collection $slots, array $submitted): void
    {
        $field = self::assignmentField($category);
        $assignments = collect($submitted)->map(fn (array $values) => $values[$field]);
        $assignedIds = $assignments->filter()->values();
        if ($assignedIds->unique()->count() !== $assignedIds->count()) {
            throw ValidationException::withMessages(['slots' => 'Satu employee, gembok, atau Tool tidak boleh ditempatkan pada lebih dari satu posisi.']);
        }
        $positions = collect($submitted)->pluck('position');
        if (LototoSlotGrid::numbersMayRepeat()) {
            // A slot keeps its number when it changes category, even if the new category already uses it: where a
            // slot sits is checked by cell. New slots and renumbered slots still need a number of their own.
            foreach ($submitted as $key => $values) {
                $renumbered = $values['original_position'] === null || $values['original_position'] !== $values['position'];
                if ($renumbered && $positions->filter(fn ($position) => $position === $values['position'])->count() > 1) {
                    throw ValidationException::withMessages(['slots' => "Nomor posisi {$values['position']} sudah dipakai pada ".self::categoryLabel($category).'. Slot baru atau slot yang diberi nomor lain memerlukan nomor yang belum dipakai.']);
                }
            }
        } elseif ($positions->unique()->count() !== $positions->count()) {
            throw ValidationException::withMessages(['slots' => 'Nomor posisi tidak boleh duplikat dalam satu area.']);
        }
        if (! $board->is_active && $category !== 'tool') {
            foreach ($slots as $key => $slot) {
                $newAssignment = $assignments->get($key);
                if ($newAssignment !== null && $newAssignment !== $this->nullableId($slot->getAttribute($field))) {
                    throw ValidationException::withMessages([
                        'slots' => 'Board nonaktif tidak dapat menerima assignment Technician, Supervisor, atau Spare Lock baru. Aktifkan kembali board sebelum menambahkan atau mengganti assignment.',
                    ]);
                }
            }
        }

        if ($category === 'spare') {
            $this->validateSpareAssignments($board, $slots, $submitted);
        } elseif ($category !== 'tool') {
            $this->validateEmployeeAssignments($board, $category, $slots, $submitted);
        }
        $this->validatePositionChanges($category, $existing, $submitted);
    }

    /**
     * Cells of an inline-editor save: every slot inside its section's columns, no two slots (Tools with their span)
     * on the same cell, and Technician slots and slots whose lock is IN keep their cell. Slots without a cell yet
     * are left to LototoSlotGrid::sync(), which gives them a free one.
     */
    private function validateCells(array $plans, Collection $currentSlots, array $columns, bool $reflowed = false): void
    {
        $messages = [];
        $bySection = [];
        foreach ($plans as $category => $plan) {
            $label = self::categoryLabel($category);
            foreach ($plan['submitted'] as $key => $values) {
                $current = is_int($key) ? $currentSlots->get($key) : null;
                $moved = $current && ((int) $current->grid_row !== (int) $values['grid_row'] || (int) $current->grid_column !== (int) $values['grid_column']);
                if (! $reflowed && $moved && $current->grid_row !== null && $current->category === $category) {
                    if ($category === 'technician') {
                        $messages[] = "Posisi Technician {$current->position} dikunci dan tidak dapat dipindahkan.";
                    } elseif (($current->spareLock?->current_activity_id) || ($current->employee_id && LototoActivity::query()
                        ->where('employee_id', $current->employee_id)->where('status', 'in_progress')->exists())) {
                        $messages[] = "{$label} {$current->position} memiliki aktivitas LOTOTO aktif dan tidak dapat dipindahkan.";
                    }
                }
                if ($values['grid_row'] !== null && $values['grid_column'] !== null) {
                    // Spans follow what the slot holds; cells and widths follow the band it sits in.
                    $span = $category === 'tool' ? max(1, min(3, (int) $values['tool_column_span'])) : 1;
                    $name = $current && $current->category !== $category
                        ? self::categoryLabel($current->category)." {$values['position']} (menjadi {$label})"
                        : "{$label} {$values['position']}";
                    $bySection[$values['section']][] = [$name, $values['grid_row'], $values['grid_column'], $span];
                }
            }
        }
        foreach ($bySection as $section => $items) {
            $messages = [...$messages, ...LototoSlotGrid::conflicts('pita '.self::categoryLabel($section), $items, $columns[$section])];
        }
        if ($messages !== []) {
            throw ValidationException::withMessages(['slots' => array_values(array_unique($messages))]);
        }
    }

    /**
     * A new column count packs each section's slots (as the draft holds them, new ones included) in reading order,
     * with the same rule Settings uses (LototoSlotGrid::reflow).
     */
    private function reflowPlans(array &$plans, int $columns): void
    {
        foreach (self::CATEGORIES as $section) {
            $items = [];
            foreach ($plans as $category => $plan) {
                foreach ($plan['submitted'] as $key => $values) {
                    if ($values['section'] === $section) {
                        $items[$category.'|'.$key] = [$values['grid_row'], $values['grid_column'], $category === 'tool' ? max(1, min(3, (int) $values['tool_column_span'])) : 1];
                    }
                }
            }
            foreach (LototoSlotGrid::reflow($items, $columns) as $item => [$row, $column]) {
                [$category, $key] = explode('|', $item, 2);
                $key = ctype_digit($key) ? (int) $key : $key;
                $plans[$category]['submitted'][$key]['grid_row'] = $row;
                $plans[$category]['submitted'][$key]['grid_column'] = $column;
            }
        }
    }

    /**
     * Slots removed in Edit Mode: not a Technician slot, nothing IN on them, and not the protected legacy Tools
     * positions of mtn_tr (the same rules as removing a position in Settings, which also requires it empty there;
     * Edit Mode asks the admin to confirm a filled one). Activities, locks and tools are separate records and stay.
     */
    private function validateRemovedSlots(LototoBoard $board, Collection $slots): void
    {
        foreach ($slots as $slot) {
            $label = self::categoryLabel($slot->category)." {$slot->position}";
            if ($slot->category === 'technician') {
                throw ValidationException::withMessages(['slots' => "{$label} tidak dapat dihapus: posisi Technician dikunci."]);
            }
            if ($board->code === 'mtn_tr' && $slot->category === 'tool' && $slot->position <= 15) {
                throw ValidationException::withMessages(['slots' => "{$label} masih dilindungi fallback legacy dan tidak dapat dihapus."]);
            }
            if ($slot->spareLock?->current_activity_id || ($slot->employee_id && LototoActivity::query()
                ->where('employee_id', $slot->employee_id)->where('status', 'in_progress')->exists())) {
                throw ValidationException::withMessages(['slots' => "{$label} memiliki aktivitas LOTOTO aktif (IN) dan tidak dapat dihapus."]);
            }
        }
    }

    /**
     * Tools and spare locks of a board as the Edit Mode popups list them: whether each is IN, has history, or sits
     * in a slot (all of which block deleting it), and the code the next spare lock will get.
     */
    public static function manageData(LototoBoard $board): array
    {
        $placed = LototoBoardSlot::query()->where('board_id', $board->id)
            ->get(['category', 'position', 'tool_id', 'spare_lock_id']);
        $slotOf = fn (string $field, int $id) => ($slot = $placed->firstWhere($field, $id))
            ? self::categoryLabel($slot->category).' '.sprintf('%02d', $slot->position)
            : null;

        return [
            'tools' => LototoTool::query()->where('board_id', $board->id)->withCount('activities')->with('openActivity')->orderBy('code')->get()
                ->map(fn (LototoTool $tool) => self::presentTool($tool, $slotOf('tool_id', $tool->id)))->values(),
            'spare_locks' => SpareLock::query()->where('board_id', $board->id)->withCount('activities')->orderBy('code')->get()
                ->map(fn (SpareLock $lock) => [
                    'id' => $lock->id,
                    'code' => $lock->code,
                    'in' => (bool) $lock->current_activity_id,
                    'history' => $lock->activities_count > 0,
                    'slot' => $slotOf('spare_lock_id', $lock->id),
                ])->values(),
            'next_spare_code' => $board->nextSpareCodes(1)[0],
        ];
    }

    /** One tool as the Tools popup shows it. */
    public static function presentTool(LototoTool $tool, ?string $slot): array
    {
        return [
            'id' => $tool->id,
            'code' => $tool->code,
            'name' => $tool->name,
            'image' => $tool->image_path ? route('safety.lototo.tools.image', $tool, absolute: false).'?v='.$tool->updated_at?->timestamp : null,
            'in' => $tool->relationLoaded('openActivity') ? (bool) $tool->openActivity : $tool->openActivity()->exists(),
            'history' => ($tool->activities_count ?? $tool->activities()->count()) > 0,
            'slot' => $slot,
        ];
    }

    /** Changes whenever the board's presentation or any of its slots changes; a save with an older one is refused. */
    public static function layoutVersion(LototoBoard $board, ?Collection $slots = null): string
    {
        $slots ??= LototoBoardSlot::query()->where('board_id', $board->id)->get();

        return hash('sha256', json_encode([
            (int) $board->business_unit_id, $board->title, $board->subtitle, (bool) $board->is_active, $board->layout, $board->component_settings,
            $slots->sortBy('id')->map(fn (LototoBoardSlot $slot) => [
                (int) $slot->id, $slot->category, (int) $slot->position, (int) $slot->employee_id, (int) $slot->spare_lock_id,
                (int) $slot->tool_id, (string) $slot->tool_label, (int) $slot->tool_column_span, (bool) $slot->is_enabled,
                (int) $slot->grid_row, (int) $slot->grid_column, (string) $slot->section,
            ])->values(),
        ]));
    }

    /** The board's one column count ("Kolom per baris — Seluruh Board"), shared by Settings and Edit Mode. */
    public static function boardColumns(LototoBoard $board): int
    {
        return min(self::MAX_COLUMNS, max(self::MIN_COLUMNS, (int) ($board->layout['board_columns'] ?? $board->layout['technician_columns'] ?? 20)));
    }

    /** Columns per category; a board saved before per-category columns uses its one board column count everywhere. */
    public static function layoutColumns(LototoBoard $board): array
    {
        $boardColumns = (int) ($board->layout['board_columns'] ?? $board->layout['technician_columns'] ?? 20);

        return collect(self::CATEGORIES)
            ->mapWithKeys(fn ($category) => [$category => min(self::MAX_COLUMNS, max(self::MIN_COLUMNS, (int) ($board->layout[$category.'_columns'] ?? $boardColumns)))])
            ->all();
    }

    /**
     * The order of the bands below Technician: Spare, Supervisor and Other / Tools in any saved order. An order
     * saved before Supervisor had its own band (only spare and other) gets Supervisor right after Spare.
     */
    public static function layoutSectionOrder(?array $order): array
    {
        $order = array_values(array_unique(array_intersect($order ?? [], ['spare', 'supervisor', 'other'])));
        $order = array_values(array_unique([...$order, ...array_diff(['spare', 'other'], $order)]));
        if (! in_array('supervisor', $order, true)) {
            array_splice($order, (int) array_search('spare', $order, true) + 1, 0, 'supervisor');
        }

        return $order;
    }

    public static function categoryLabel(string $category): string
    {
        return ['technician' => 'Technician', 'supervisor' => 'Supervisor', 'spare' => 'Spare', 'tool' => 'Other / Tools'][$category] ?? $category;
    }

    private static function assignmentField(string $category): string
    {
        return match ($category) {
            'spare' => 'spare_lock_id',
            'tool' => 'tool_id',
            default => 'employee_id',
        };
    }

    private function validatePositionChanges(string $category, Collection $slots, array $submittedSlots): void
    {
        foreach ($slots as $slotId => $slot) {
            $newPosition = (int) $submittedSlots[$slotId]['position'];
            if ($newPosition === (int) $slot->position) {
                continue;
            }
            if ($category === 'technician') {
                throw ValidationException::withMessages(['slots' => 'Posisi Technician saat ini mengikuti LOTOTO number karyawan. Perubahan posisi ditahan sampai mapping konfigurasi lama dapat diverifikasi.']);
            }
            if ($category === 'spare' && $slot->spareLock?->current_activity_id) {
                throw ValidationException::withMessages(['slots' => "Gembok pada posisi {$slot->position} sedang digunakan dan tidak dapat dipindahkan."]);
            }
            if ($category !== 'spare' && $slot->employee_id && LototoActivity::query()
                ->where('employee_id', $slot->employee_id)->where('status', 'in_progress')->exists()) {
                throw ValidationException::withMessages(['slots' => "Posisi {$slot->position} memiliki aktivitas LOTOTO aktif dan tidak dapat dipindahkan."]);
            }
        }
    }

    private function validateEmployeeAssignments(LototoBoard $board, string $category, Collection $slots, array $submittedSlots): void
    {
        $activeStatusId = EmploymentStatus::query()->where('code', 'ACTIVE')->value('id');
        $changedEmployeeIds = [];

        foreach ($slots as $slotId => $slot) {
            $newEmployeeId = $this->nullableId($submittedSlots[$slotId]['employee_id']);
            $enabled = (bool) $submittedSlots[$slotId]['is_enabled'];
            $assignmentChanged = $this->nullableId($slot->employee_id) !== $newEmployeeId;
            $isBeingDisabled = $slot->is_enabled && ! $enabled;

            if (! $assignmentChanged && ! $isBeingDisabled) {
                continue;
            }

            if ($slot->employee_id) {
                $changedEmployeeIds[] = $slot->employee_id;
            }
        }

        if ($changedEmployeeIds !== []) {
            Employee::withTrashed()->whereKey($changedEmployeeIds)->lockForUpdate()->get();
        }

        foreach ($slots as $slotId => $slot) {
            $newEmployeeId = $this->nullableId($submittedSlots[$slotId]['employee_id']);
            $assignmentChanged = $this->nullableId($slot->employee_id) !== $newEmployeeId;
            $isBeingDisabled = $slot->is_enabled && ! (bool) $submittedSlots[$slotId]['is_enabled'];

            if (($assignmentChanged || $isBeingDisabled)
                && $slot->employee_id
                && LototoActivity::query()
                    ->where('employee_id', $slot->employee_id)
                    ->where('status', 'in_progress')
                    ->exists()) {
                throw ValidationException::withMessages([
                    'slots' => "Posisi {$slot->position} masih memiliki aktivitas LOTOTO aktif dan tidak dapat dipindahkan atau dinonaktifkan.",
                ]);
            }
        }

        foreach ($slots as $slotId => $slot) {
            $newEmployeeId = $this->nullableId($submittedSlots[$slotId]['employee_id']);

            if ($newEmployeeId === null || $newEmployeeId === $this->nullableId($slot->employee_id)) {
                continue;
            }

            $employee = Employee::withTrashed()->find($newEmployeeId);
            $isActive = $activeStatusId === null || (int) $employee?->employment_status_id === (int) $activeStatusId;

            if (! $employee
                || (int) $employee->business_unit_id !== (int) $board->business_unit_id
                || $employee->trashed()
                || ! $isActive
                || $this->categoryFor($employee) !== $category) {
                throw ValidationException::withMessages([
                    'slots' => 'Employee yang dipilih tidak aktif atau tidak sesuai kategori posisi.',
                ]);
            }
        }
    }

    private function validateSpareAssignments(LototoBoard $board, Collection $slots, array $submittedSlots): void
    {
        foreach ($slots as $slotId => $slot) {
            $newSpareLockId = $this->nullableId($submittedSlots[$slotId]['spare_lock_id']);
            $enabled = (bool) $submittedSlots[$slotId]['is_enabled'];
            $assignmentChanged = $this->nullableId($slot->spare_lock_id) !== $newSpareLockId;
            $isBeingDisabled = $slot->is_enabled && ! $enabled;

            if ((! $assignmentChanged && ! $isBeingDisabled) || ! $slot->spare_lock_id) {
                continue;
            }

            $spareLock = SpareLock::query()->whereKey($slot->spare_lock_id)->lockForUpdate()->first();

            if ($spareLock?->current_activity_id) {
                throw ValidationException::withMessages([
                    'slots' => "Gembok {$spareLock->code} sedang digunakan dan tidak dapat dipindahkan atau dinonaktifkan.",
                ]);
            }
        }

        $changedIds = $slots->mapWithKeys(fn (LototoBoardSlot $slot, $slotId) => [
            $slotId => $slot->spare_lock_id,
        ]);

        foreach ($submittedSlots as $slotId => $slotData) {
            $newId = $this->nullableId($slotData['spare_lock_id']);
            $oldId = $this->nullableId($changedIds->get($slotId));

            if ($newId === null || $newId === $oldId) {
                continue;
            }

            $lock = SpareLock::query()->find($newId);
            if (! $lock || ! $lock->is_active || (int) $lock->board_id !== (int) $board->id) {
                throw ValidationException::withMessages([
                    'slots' => 'Gembok spare yang dipilih tidak aktif atau tidak ditemukan.',
                ]);
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

    private function nullableId(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    /**
     * The QR Code page (view and download only): Safety managers, and the read-only Guest viewer, who has no
     * Manage Safety permission and reaches nothing else of board management.
     */
    public static function canViewQrCodes(?\App\Models\User $user): bool
    {
        return $user !== null && ($user->can(PermissionName::ManageSafety->value) || $user->hasRole(\App\Enums\RoleName::Guest->value));
    }

    private function authorizeQrViewer(Request $request): void
    {
        abort_unless(self::canViewQrCodes($request->user()), 403);
    }

    private function authorizeManageSafety(Request $request): void
    {
        abort_unless($request->user()?->can(PermissionName::ManageSafety->value), 403);
    }

    private function resolveBoard(Request $request): LototoBoard
    {
        $requested = $request->input('board_id') ?? $request->query('board');

        return $requested
            ? LototoBoard::query()->where('id', $requested)->orWhere('code', $requested)->firstOrFail()
            : LototoBoard::query()->where('code', 'mtn_tr')->firstOrFail();
    }
}
