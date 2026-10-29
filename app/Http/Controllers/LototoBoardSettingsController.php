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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LototoBoardSettingsController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeManageSafety($request);
        $board = $this->resolveBoard($request);

        $slots = LototoBoardSlot::query()
            ->where('board_id', $board->id)
            ->with(['employee', 'spareLock'])
            ->orderBy('position')
            ->get()
            ->toBase()
            ->groupBy('category');
        $assignedEmployeeIds = $slots
            ->only(['technician', 'supervisor'])
            ->flatten()
            ->pluck('employee_id')
            ->filter()
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
        $technicians = $employees
            ->filter(fn (Employee $employee) => $this->categoryFor($employee) === 'technician')
            ->values();
        $supervisors = $employees
            ->filter(fn (Employee $employee) => $this->categoryFor($employee) === 'supervisor')
            ->values();
        $spareLocks = SpareLock::query()
            ->where('board_id', $board->id)
            ->where(function ($query) use ($slots) {
                $query->where('is_active', true)
                    ->orWhereIn('id', $slots->get('spare', collect())->pluck('spare_lock_id')->filter());
            })
            ->orderBy('code')
            ->get();
        $qrSpareLocks = SpareLock::query()->where('board_id', $board->id)->withCount('activities')->orderBy('code')->get();
        $sparePositions = $slots->get('spare', collect())->whereNotNull('spare_lock_id')->pluck('position', 'spare_lock_id');
        $tools = LototoTool::query()->where('board_id', $board->id)->with('openActivity')->withCount('activities')->orderBy('code')->get();
        $toolPositions = $slots->get('tool', collect())->whereNotNull('tool_id')->pluck('position', 'tool_id');

        return view('safety.lototo.settings', [
            'board' => $board,
            'selectedSection' => in_array($request->query('section'), ['technician', 'supervisor', 'spare', 'tool'], true)
                ? $request->query('section')
                : null,
            'qrGroups' => [
                ['label' => 'Technician Locks', 'employees' => $technicians],
                ['label' => 'Supervisor Locks', 'employees' => $supervisors],
            ],
            'qrSpareLocks' => $qrSpareLocks,
            'sparePositions' => $sparePositions,
            'tools' => $tools,
            'toolPositions' => $toolPositions,
            'groups' => [
                'technician' => [
                    'label' => 'Technician',
                    'slots' => $slots->get('technician', collect()),
                    'assignment_field' => 'employee_id',
                    'assignment_label' => 'Employee',
                    'options' => $technicians,
                    'option_label' => fn (Employee $employee) => $employee->lototo_number.' — '.($employee->preferred_name ?: $employee->full_name),
                ],
                'supervisor' => [
                    'label' => 'Supervisor',
                    'slots' => $slots->get('supervisor', collect()),
                    'assignment_field' => 'employee_id',
                    'assignment_label' => 'Employee',
                    'options' => $supervisors,
                    'option_label' => fn (Employee $employee) => $employee->lototo_number.' — '.($employee->preferred_name ?: $employee->full_name),
                ],
                'spare' => [
                    'label' => 'Spare',
                    'slots' => $slots->get('spare', collect()),
                    'assignment_field' => 'spare_lock_id',
                    'assignment_label' => 'Gembok spare',
                    'options' => $spareLocks,
                    'option_label' => fn (SpareLock $spareLock) => $spareLock->code.($spareLock->is_active ? '' : ' — nonaktif'),
                ],
                'tool' => [
                    'label' => 'Other / Tools',
                    'slots' => $slots->get('tool', collect()),
                    'assignment_field' => 'tool_label',
                    'assignment_label' => 'Nama tool',
                    'options' => collect(),
                ],
            ],
        ]);
    }

    public function addPosition(Request $request, string $category): RedirectResponse
    {
        $this->authorizeManageSafety($request);
        $board = $this->resolveBoard($request);
        abort_unless(in_array($category, ['technician', 'supervisor', 'spare', 'tool'], true), 404);

        $data = $request->validate([
            'position' => ['nullable', 'integer', 'min:1', 'max:99999'],
        ]);
        $position = (int) ($data['position'] ?? (LototoBoardSlot::query()->where('board_id', $board->id)->where('category', $category)->max('position') + 1));
        if (LototoBoardSlot::query()->where('board_id', $board->id)->where('category', $category)->where('position', $position)->exists()) {
            throw ValidationException::withMessages(['position' => 'Nomor posisi sudah digunakan pada area ini.']);
        }

        DB::transaction(function () use ($board, $category, $position) {
            LototoBoard::query()->whereKey($board->id)->lockForUpdate()->firstOrFail();
            LototoBoardSlot::query()
                ->where('board_id', $board->id)
                ->where('category', $category)
                ->orderByDesc('position')
                ->lockForUpdate()
                ->first();

            LototoBoardSlot::query()->create([
                'board_id' => $board->id,
                'category' => $category,
                'position' => $position,
                'is_enabled' => true,
            ]);
        });

        return to_route('safety.lototo.settings', ['board' => $board->id, 'section' => $category])
            ->with('status', 'Posisi baru berhasil ditambahkan tanpa mengubah posisi yang sudah ada.');
    }

    public function update(Request $request, string $category): RedirectResponse
    {
        $this->authorizeManageSafety($request);
        $board = $this->resolveBoard($request);
        abort_unless(in_array($category, ['technician', 'supervisor', 'spare', 'tool'], true), 404);

        $assignmentField = match ($category) {
            'spare' => 'spare_lock_id',
            'tool' => 'tool_label',
            default => 'employee_id',
        };
        $assignmentRules = $category === 'tool'
            ? ['present', 'nullable', 'string', 'max:100']
            : ['present', 'nullable', 'integer', 'exists:'.($category === 'spare' ? 'spare_locks' : 'employees').',id'];
        $slotFields = $category === 'tool'
            ? $assignmentField.',tool_column_span,is_enabled,position'
            : $assignmentField.',is_enabled,position';
        $data = $request->validate([
            'slots' => ['required', 'array'],
            'slots.*' => ['required', 'array:'.$slotFields],
            'slots.*.'.$assignmentField => $assignmentRules,
            ...($category === 'tool' ? ['slots.*.tool_column_span' => ['nullable', 'integer', 'in:1,2,3']] : []),
            'slots.*.is_enabled' => ['required', 'boolean'],
            'slots.*.position' => ['sometimes', 'integer', 'min:1', 'max:99999'],
        ]);

        $slotIds = array_map('intval', array_keys($data['slots']));
        sort($slotIds);
        $expectedSlotIds = LototoBoardSlot::query()
            ->where('board_id', $board->id)
            ->where('category', $category)
            ->orderBy('id')
            ->pluck('id')
            ->all();
        sort($expectedSlotIds);

        if ($slotIds !== $expectedSlotIds) {
            throw ValidationException::withMessages([
                'slots' => 'Daftar posisi berubah. Muat ulang halaman sebelum menyimpan.',
            ]);
        }

        $currentPositions = LototoBoardSlot::query()
            ->where('board_id', $board->id)
            ->where('category', $category)
            ->pluck('position', 'id');
        foreach ($data['slots'] as $slotId => &$slotData) {
            $slotData['position'] = (int) ($slotData['position'] ?? $currentPositions->get((int) $slotId));
        }
        unset($slotData);

        $assignments = collect($data['slots'])
            ->map(fn (array $slot) => $category === 'tool'
                ? (filled($slot[$assignmentField]) ? trim($slot[$assignmentField]) : null)
                : $this->nullableId($slot[$assignmentField]));
        $assignedIds = $assignments->filter()->values();

        if ($category !== 'tool' && $assignedIds->unique()->count() !== $assignedIds->count()) {
            throw ValidationException::withMessages([
                'slots' => 'Satu employee atau gembok tidak boleh ditempatkan pada lebih dari satu posisi.',
            ]);
        }

        $positions = collect($data['slots'])->map(fn (array $slot) => (int) $slot['position']);
        if ($positions->unique()->count() !== $positions->count()) {
            throw ValidationException::withMessages(['slots' => 'Nomor posisi tidak boleh duplikat dalam satu area.']);
        }

        $removedToolImages = DB::transaction(function () use ($board, $category, $assignmentField, $data, $assignments) {
            $lockedBoard = LototoBoard::query()->whereKey($board->id)->lockForUpdate()->firstOrFail();
            $currentSlots = LototoBoardSlot::query()
                ->where('board_id', $board->id)
                ->where('category', $category)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($currentSlots->count() !== count($data['slots'])) {
                throw ValidationException::withMessages([
                    'slots' => 'Daftar posisi berubah. Muat ulang halaman sebelum menyimpan.',
                ]);
            }

            if (! $lockedBoard->is_active && $category !== 'tool') {
                foreach ($currentSlots as $slotId => $slot) {
                    $newAssignment = $assignments->get((int) $slotId);
                    $oldAssignment = $this->nullableId($slot->getAttribute($assignmentField));
                    if ($newAssignment !== null && $newAssignment !== $oldAssignment) {
                        throw ValidationException::withMessages([
                            'slots' => 'Board nonaktif tidak dapat menerima assignment Technician, Supervisor, atau Spare Lock baru. Aktifkan kembali board sebelum menambahkan atau mengganti assignment.',
                        ]);
                    }
                }
            }

            if ($category === 'spare') {
                $this->validateSpareAssignments($board, $currentSlots, $data['slots']);
            } elseif ($category !== 'tool') {
                $this->validateEmployeeAssignments($board, $category, $currentSlots, $data['slots']);
            }

            foreach ($currentSlots as $slotId => $slot) {
                $newPosition = (int) $data['slots'][$slotId]['position'];
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

            $temporaryBase = (int) $currentSlots->max('position') + $currentSlots->count() + 1;
            $temporaryOffset = 0;
            foreach ($currentSlots as $slotId => $slot) {
                LototoBoardSlot::query()->where('board_id', $board->id)->whereKey($slotId)->update(['position' => $temporaryBase + $temporaryOffset]);
                $temporaryOffset++;
            }

            LototoBoardSlot::query()
                ->where('board_id', $board->id)
                ->where('category', $category)
                ->update([$assignmentField => null]);

            foreach ($data['slots'] as $slotId => $slotData) {
                $slotUpdates = [
                    'position' => (int) $slotData['position'],
                    $assignmentField => $assignments->get((int) $slotId),
                    'is_enabled' => (bool) $slotData['is_enabled'],
                ];
                if ($category === 'tool') {
                    $slotUpdates['tool_column_span'] = (int) ($slotData['tool_column_span'] ?? $currentSlots->get((int) $slotId)->tool_column_span ?? 1);
                }
                LototoBoardSlot::query()->where('board_id', $board->id)->whereKey($slotId)->update($slotUpdates);
            }

            return $category === 'tool'
                ? $this->syncToolRecords($board, $currentSlots, $data['slots'], $assignments)
                : [];
        });
        // Images of tools removed by clearing their label are deleted only after the commit.
        foreach ($removedToolImages as $imagePath) {
            Storage::disk('local')->delete($imagePath);
        }

        return to_route('safety.lototo.settings', ['board' => $board->id, 'section' => $category])
            ->with('status', 'Pengaturan posisi berhasil disimpan.');
    }

    public function destroyPosition(Request $request, string $category, LototoBoardSlot $slot): RedirectResponse
    {
        $this->authorizeManageSafety($request);
        $board = $this->resolveBoard($request);
        abort_unless(in_array($category, ['technician', 'supervisor', 'spare', 'tool'], true), 404);
        abort_unless($slot->category === $category, 404);
        abort_unless((int) $slot->board_id === (int) $board->id, 404);
        if ($board->code === 'mtn_tr' && $category === 'tool' && $slot->position <= 15) {
            throw ValidationException::withMessages([
                'slot' => 'Posisi ini masih dilindungi fallback legacy agar board tidak kehilangan slot sebelum konfigurasi lama diverifikasi. Nonaktifkan posisi kosong jika perlu.',
            ]);
        }

        DB::transaction(function () use ($slot, $category, $board) {
            LototoBoard::query()->whereKey($board->id)->lockForUpdate()->firstOrFail();
            $locked = LototoBoardSlot::query()->where('board_id', $board->id)->whereKey($slot->id)->lockForUpdate()->firstOrFail();
            if ($locked->employee_id || $locked->spare_lock_id || $locked->tool_id || filled($locked->tool_label)) {
                throw ValidationException::withMessages(['slot' => 'Slot masih memiliki penempatan. Kosongkan atau nonaktifkan slot; data operasional tidak dihapus.']);
            }
            $locked->delete();
        });

        return to_route('safety.lototo.settings', ['board' => $board->id, 'section' => $category])->with('status', 'Slot kosong berhasil dihapus. Posisi lain tidak diubah.');
    }

    /**
     * Keeps lototo_tools in step with the slot labels edited here: a renamed label renames its tool, a new label
     * gets its own tool, and a cleared label removes the tool only when it was never used. Codes never change here.
     * Returns the image paths of removed tools so the caller can delete the files after the commit.
     */
    private function syncToolRecords(LototoBoard $board, Collection $slots, array $submittedSlots, Collection $labels): array
    {
        $removedImages = [];
        $tools = LototoTool::query()
            ->where('board_id', $board->id)
            ->whereKey($slots->pluck('tool_id')->filter())
            ->withCount('activities')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        $usedCodes = LototoTool::query()->where('board_id', $board->id)->pluck('code')->flip();

        foreach ($slots as $slotId => $slot) {
            $label = $labels->get((int) $slotId);
            $tool = $slot->tool_id ? $tools->get($slot->tool_id) : null;

            if ($tool && $label === null) {
                if ($tool->activities_count > 0) {
                    throw ValidationException::withMessages(['slots' => "Tools {$tool->name} memiliki riwayat penggunaan sehingga nama pada posisi {$slot->position} tidak dapat dikosongkan."]);
                }
                LototoBoardSlot::query()->where('board_id', $board->id)->whereKey($slotId)->update(['tool_id' => null]);
                $tool->delete();
                if ($tool->image_path) {
                    $removedImages[] = $tool->image_path;
                }
            } elseif ($tool && $tool->name !== $label) {
                $tool->update(['name' => $label]);
            } elseif (! $tool && $label !== null) {
                $number = (int) $submittedSlots[$slotId]['position'];
                $code = sprintf('TL-%02d', $number);
                while ($usedCodes->has($code)) {
                    $code = sprintf('TL-%02d', ++$number);
                }
                $usedCodes->put($code, true);
                $newTool = LototoTool::query()->create(['board_id' => $board->id, 'code' => $code, 'name' => $label, 'is_active' => true]);
                LototoBoardSlot::query()->where('board_id', $board->id)->whereKey($slotId)->update(['tool_id' => $newTool->id]);
            }
        }

        return $removedImages;
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
