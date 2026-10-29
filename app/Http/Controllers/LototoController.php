<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\LototoActivity;
use App\Models\LototoBoard;
use App\Models\LototoBoardSlot;
use App\Models\SpareLock;
use App\Notifications\LototoSafetyAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LototoController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $boards = $this->accessibleActiveBoards($request);
        $board = $boards->first();

        if (! $board) {
            return view('safety.lototo.index-empty');
        }

        return to_route('safety.lototo.boards.show', $board);
    }

    public function board(Request $request, LototoBoard $board): View|RedirectResponse
    {
        if (! $board->is_active) {
            $fallback = $this->accessibleActiveBoards($request)->first();

            return $fallback
                ? to_route('safety.lototo.boards.show', $fallback)->with('status', 'Board ini sudah nonaktif. Board aktif lain dibuka.')
                : to_route('safety.lototo.index')->with('status', 'Board ini nonaktif dan tidak ada board aktif yang dapat diakses.');
        }

        $boards = $this->accessibleActiveBoards($request);
        abort_unless($boards->contains('id', $board->id), 404);

        return $this->renderBoard($request, $board, $boards);
    }

    private function accessibleActiveBoards(Request $request): \Illuminate\Support\Collection
    {
        $query = LototoBoard::query()
            ->with('businessUnit')
            ->where('is_active', true)
            ->whereHas('businessUnit', fn ($businessUnits) => $businessUnits->where('is_active', true));

        if (! $request->user()->can(PermissionName::ManageSafety->value)) {
            $visibleBusinessUnitIds = Employee::query()
                ->visibleTo($request->user())
                ->whereHas('employmentStatus', fn ($statuses) => $statuses->where('code', 'ACTIVE'))
                ->select('business_unit_id');
            $query->whereIn('business_unit_id', $visibleBusinessUnitIds);
        }

        return $query->orderBy('division_name')->orderBy('id')->get();
    }

    private function renderBoard(Request $request, LototoBoard $board, $boards): View
    {
        $activeStatusId = EmploymentStatus::query()
            ->where('code', 'ACTIVE')
            ->value('id');

        $employees = Employee::query()
            ->with(['maintenanceArea', 'maintenanceTeam', 'position'])
            ->where('business_unit_id', $board->business_unit_id)
            ->visibleTo($request->user())
            ->when(
                $activeStatusId !== null,
                fn ($query) => $query->where('employment_status_id', $activeStatusId)
            )
            ->get();

        // CREW = LOTOTO 1-2 digit
        $crew = $employees
            ->filter(function ($employee) {
                $number = trim((string) $employee->lototo_number);

                return preg_match('/^\d{1,2}$/', $number) === 1;
            })
            ->sortBy(function ($employee) {
                return (int) $employee->lototo_number;
            })
            ->values();

        // SUPERVISOR = LOTOTO 3 digit
        $supervisor = $employees
            ->filter(function ($employee) {
                $number = trim((string) $employee->lototo_number);

                return preg_match('/^\d{3}$/', $number) === 1;
            })
            ->sortBy(function ($employee) {
                return (int) $employee->lototo_number;
            })
            ->values();

        $rows = collect([
            'TECHNICIAN' => $crew,
            'SUPERVISOR' => $supervisor,
        ]);
        $lototoNumberConflicts = $crew
            ->groupBy(fn ($employee) => (int) $employee->lototo_number)
            ->filter(fn ($employeesWithNumber) => $employeesWithNumber->count() > 1)
            ->keys()
            ->values();
        $activeActivities = LototoActivity::query()
            ->whereIn('employee_id', $employees->modelKeys())
            ->where('board', $board->code)
            ->whereNull('spare_lock_id')
            ->where('status', 'in_progress')
            ->get()
            ->keyBy('employee_id');
        $pendingFollowUps = LototoActivity::query()
            ->whereIn('employee_id', $employees->modelKeys())
            ->where('board', $board->code)
            ->where('supervisor_follow_up_status', 'pending')
            ->latest('started_at')
            ->get()
            ->groupBy('employee_id');
        $boardSlots = LototoBoardSlot::query()
            ->where('board_id', $board->id)
            ->with(['employee', 'spareLock.currentActivity.employee', 'tool.openActivity'])
            ->orderBy('position')
            ->get()
            ->toBase()
            ->groupBy('category');
        $visibleEmployeeIds = $employees->modelKeys();

        foreach ($boardSlots->only(['technician', 'supervisor'])->flatten() as $slot) {
            if (! in_array($slot->employee_id, $visibleEmployeeIds)) {
                $slot->setRelation('employee', null);
            }
        }

        foreach ($boardSlots->get('spare', collect()) as $slot) {
            $activityEmployee = $slot->spareLock?->currentActivity?->employee;
            if ($activityEmployee && ! in_array($activityEmployee->id, $visibleEmployeeIds)) {
                $slot->spareLock->currentActivity->setRelation('employee', null);
            }
        }

        return view('safety.lototo.index', [
            'board' => $board,
            'boards' => $boards,
            'lototoNumberConflicts' => $lototoNumberConflicts,
            'rows' => $rows,
            'boardSlots' => $boardSlots,
            'activeActivities' => $activeActivities,
            'pendingFollowUps' => $pendingFollowUps,
            'employeeCount' => $crew->count() + $supervisor->count(),
            'spareLocks' => SpareLock::query()
                ->where('board_id', $board->id)
                ->with('currentActivity.employee')
                ->orderBy('code')
                ->limit(20)
                ->get(),
        ]);
    }

    public function form(Request $request): View
    {
        $filters = $request->validate([
            'spare_lock' => ['sometimes', 'required', 'string', 'max:50'],
            'lototo_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'employee_id' => ['sometimes', 'nullable', 'integer'],
        ]);
        $board = $this->resolveBoard($request);
        $canManageSafety = $request->user()->can(PermissionName::ManageSafety->value);
        $requestedLototoNumber = $filters['lototo_number'] ?? null;
        $spareLock = isset($filters['spare_lock'])
            ? SpareLock::query()
                ->where('code', $filters['spare_lock'])
                ->where('board_id', $board->id)
                ->with('currentActivity.employee')
                ->firstOrFail()
            : null;
        $requestedEmployee = isset($filters['employee_id'])
            ? Employee::query()->where('business_unit_id', $board->business_unit_id)->findOrFail($filters['employee_id'])
            : null;
        if ($requestedEmployee && filled($requestedLototoNumber)) {
            abort_unless((string) $requestedEmployee->lototo_number === (string) $requestedLototoNumber, 404);
        }
        $requestedLototoNumber ??= $requestedEmployee?->lototo_number;
        $activeActivity = $spareLock
            ? $spareLock->currentActivity
            : ($requestedEmployee
                ? LototoActivity::query()->where('employee_id', $requestedEmployee->id)
                    ->where('board', $board->code)
                    ->whereNull('spare_lock_id')->where('status', 'in_progress')->latest('started_at')->first()
                : (filled($requestedLototoNumber)
                ? LototoActivity::query()
                    ->whereHas('employee', fn ($query) => $query
                        ->where('business_unit_id', $board->business_unit_id)
                        ->where('lototo_number', $requestedLototoNumber))
                    ->where('board', $board->code)
                    ->whereNull('spare_lock_id')
                    ->where('status', 'in_progress')
                    ->latest('started_at')
                    ->first()
                : null));
        if ($spareLock && $activeActivity) {
            abort_unless((int) $activeActivity->employee?->business_unit_id === (int) $board->business_unit_id, 404);
            abort_unless($activeActivity->board === $board->code, 404);
        }
        if (! $board->is_active) {
            abort_unless($activeActivity && $activeActivity->board === $board->code, 404);
        }
        if (! $canManageSafety) {
            if ($spareLock) {
                abort_unless($this->isAuthorizedSpareTechnician($request, $activeActivity?->employee), 403);
                $requestedLototoNumber = $activeActivity->employee->lototo_number;
                $requestedEmployee = $activeActivity->employee;
            } else {
                $authenticatedEmployee = $request->user()->employee;
                abort_unless($authenticatedEmployee !== null, 403);
                abort_unless((int) $authenticatedEmployee->business_unit_id === (int) $board->business_unit_id, 403);
                abort_unless(
                    ! filled($requestedLototoNumber)
                        || (string) $requestedLototoNumber === (string) $authenticatedEmployee->lototo_number,
                    403
                );
                abort_unless(
                    ! $requestedEmployee || $requestedEmployee->is($authenticatedEmployee),
                    403
                );
                $requestedLototoNumber = $authenticatedEmployee->lototo_number;
                $requestedEmployee = $authenticatedEmployee;
            }
        }

        return view('safety.lototo.form', [
            'lototoNumber' => $activeActivity?->employee?->lototo_number ?? $requestedLototoNumber ?? '',
            'lototoBoard' => $activeActivity?->board ?? $board->code,
            'board' => $board,
            'requestedEmployee' => $activeActivity?->employee ?? $requestedEmployee,
            'spareLock' => $spareLock,
            'availableSpareLocks' => SpareLock::query()
                ->when($board->is_active, fn ($query) => $query
                    ->where('board_id', $board->id)
                    ->where('is_active', true)
                    ->whereNull('current_activity_id')
                    ->orderBy('code'))
                ->when(! $board->is_active, fn ($query) => $query->whereRaw('1 = 0'))
                ->get(),
            'allowOutstanding' => $board->is_active,
            'activeActivity' => $activeActivity,
        ]);
    }

    public function storeForm(Request $request): RedirectResponse
    {
        $board = $this->resolveBoard($request);
        $risks = ['kesetrum', 'tertabrak', 'terjatuh', 'terjepit', 'terpukul', 'tertimpa', 'lainnya'];
        $isSpareMode = $request->input('lock_mode') === 'spare';
        $canManageSafety = $request->user()->can(PermissionName::ManageSafety->value);
        if (! $board->is_active && $request->input('lock_status') !== 'out') {
            throw ValidationException::withMessages([
                'lock_status' => 'Board nonaktif tidak menerima aktivitas IN baru. Aktivitas yang sudah berjalan hanya dapat diselesaikan dengan OUT Finish.',
            ]);
        }
        if ($isSpareMode && $request->input('lock_status') === 'in') {
            abort_unless($canManageSafety, 403);
        }

        $isIn = $request->input('lock_status') === 'in';
        $isOut = $request->input('lock_status') === 'out';
        $hasOtherRisk = $isIn
            && is_array($request->input('risks'))
            && in_array('lainnya', $request->input('risks'), true);

        $data = $request->validate([
            'lototo_number' => ['required', 'string', 'max:50'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'board_id' => ['nullable', 'integer', 'exists:lototo_boards,id'],
            'lock_mode' => ['nullable', Rule::in(['personal', 'spare'])],
            'lock_status' => ['required', Rule::in(['in', 'out'])],
            'outcome' => [
                Rule::requiredIf($isOut),
                Rule::in($isSpareMode ? ['finish'] : ['finish', 'outstanding']),
            ],
            'spare_lock_id' => [
                Rule::requiredIf($isSpareMode || (! $isSpareMode && $isOut && $request->input('outcome') === 'outstanding')),
                'nullable',
                'integer',
                'exists:spare_locks,id',
            ],
            'location' => [Rule::requiredIf($isIn), 'nullable', 'string', 'max:255'],
            'activity' => [Rule::requiredIf($isIn), 'nullable', 'string', 'max:10000'],
            'risks' => [Rule::requiredIf($isIn), 'nullable', 'array', 'min:1'],
            'risks.*' => [Rule::in($risks)],
            'other_risk' => [
                Rule::requiredIf($hasOtherRisk),
                'nullable',
                'string',
                'max:255',
            ],
            'risk_controls' => [Rule::requiredIf($isIn), 'nullable', 'string', 'max:10000'],
            'knows_procedure' => [Rule::requiredIf($isIn), 'nullable', Rule::in(['ya', 'tidak'])],
            'follows_lototo' => [Rule::requiredIf($isIn), 'nullable', Rule::in(['ya', 'tidak'])],
            'uses_ppe' => [Rule::requiredIf($isIn), 'nullable', Rule::in(['ya', 'tidak'])],
            'safe_tools' => [Rule::requiredIf($isIn), 'nullable', Rule::in(['ya', 'tidak'])],
            'safe_hands' => [Rule::requiredIf($isIn), 'nullable', Rule::in(['ya', 'tidak'])],
            'supervisor_feedback' => ['nullable', 'string', 'max:5000'],
        ]);

        if (! $board->is_active
            && ($data['lock_status'] !== 'out'
                || ($data['outcome'] ?? null) !== 'finish')) {
            throw ValidationException::withMessages([
                'lock_status' => 'Board nonaktif tidak menerima aktivitas atau handover baru. Aktivitas yang sudah berjalan hanya dapat diselesaikan dengan OUT Finish.',
            ]);
        }

        $employee = Employee::query()
            ->where('business_unit_id', $board->business_unit_id)
            ->when($data['employee_id'] ?? null, fn ($query, $id) => $query->whereKey($id))
            ->where('lototo_number', $data['lototo_number'])
            ->firstOrFail();

        if (! $canManageSafety) {
            abort_unless($request->user()->employee?->is($employee), 403);
        }
        if ($isSpareMode
            && $data['lock_status'] === 'out'
            && ! $canManageSafety) {
            abort_unless($this->isAuthorizedSpareTechnician($request, $employee), 403);
        }
        $data['lototo_board'] = $board->code;

        $redirect = fn () => to_route('safety.lototo.form', $board->code === 'mtn_tr'
            ? ['lototo_number' => $employee->lototo_number]
            : ['lototo_number' => $employee->lototo_number, 'employee_id' => $employee->id, 'board' => $board->id]);
        $spareRedirect = fn () => to_route('safety.lototo.form', $board->code === 'mtn_tr'
            ? ['spare_lock' => SpareLock::query()->findOrFail($data['spare_lock_id'])->code]
            : ['spare_lock' => SpareLock::query()->findOrFail($data['spare_lock_id'])->code, 'board' => $board->id]);

        DB::transaction(function () use ($data, $employee, $isSpareMode, $board) {
            $lockedBoard = LototoBoard::query()->whereKey($board->id)->lockForUpdate()->firstOrFail();
            if (! $lockedBoard->is_active
                && ($data['lock_status'] !== 'out'
                    || ($data['outcome'] ?? null) !== 'finish')) {
                throw ValidationException::withMessages([
                    'lock_status' => 'Board nonaktif tidak menerima aktivitas atau handover baru. Aktivitas yang sudah berjalan hanya dapat diselesaikan dengan OUT Finish.',
                ]);
            }
            Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $spareLock = null;

            if ($isSpareMode) {
                $spareLock = SpareLock::query()
                    ->whereKey($data['spare_lock_id'] ?? null)
                    ->where('board_id', $board->id)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if (! $spareLock) {
                    throw ValidationException::withMessages([
                        'spare_lock_id' => 'Gembok spare tidak ditemukan atau sedang tidak aktif.',
                    ]);
                }

                if ($data['lock_status'] === 'in') {
                    if ($spareLock->current_activity_id) {
                        throw ValidationException::withMessages([
                            'spare_lock_id' => 'Gembok spare sedang digunakan. Scan QR gembok spare untuk melakukan OUT.',
                        ]);
                    }

                    $activeActivity = LototoActivity::query()
                        ->where('employee_id', $employee->id)
                        ->where('status', 'completed')
                        ->where('outcome', 'outstanding')
                        ->whereNull('spare_lock_id')
                        ->lockForUpdate()
                        ->latest('ended_at')
                        ->first();

                    if ($activeActivity) {
                        $outDetails = array_merge($activeActivity->out_details ?? [], [
                            'spare_lock_code' => $spareLock->code,
                            'spare_started_at' => now()->toISOString(),
                        ]);

                        $activeActivity->update([
                            'spare_lock_id' => $spareLock->id,
                            'out_details' => $outDetails,
                        ]);
                    } else {
                        $outDetails = [
                            'spare_lock_code' => $spareLock->code,
                            'spare_started_at' => now()->toISOString(),
                            'spare_only' => true,
                        ];
                        $activeActivity = LototoActivity::query()->create([
                            ...$this->activityAnswers($data),
                            'employee_id' => $employee->id,
                            'spare_lock_id' => $spareLock->id,
                            'board' => $data['lototo_board'],
                            'status' => 'in_progress',
                            'out_details' => $outDetails,
                            'started_at' => now(),
                        ]);

                        $this->notifySupervisorIfNeeded($employee, $activeActivity, $data);
                    }

                    $spareLock->update(['current_activity_id' => $activeActivity->id]);

                    return $activeActivity;
                }

                $activeActivity = $spareLock?->current_activity_id
                    ? LototoActivity::query()
                        ->whereKey($spareLock->current_activity_id)
                        ->where('employee_id', $employee->id)
                        ->where('board', $lockedBoard->code)
                        ->where('spare_lock_id', $spareLock->id)
                        ->lockForUpdate()
                        ->first()
                    : null;
            } else {
                $activeActivity = LototoActivity::query()
                    ->where('employee_id', $employee->id)
                    ->where('board', $lockedBoard->code)
                    ->where('status', 'in_progress')
                    ->whereNull('spare_lock_id')
                    ->lockForUpdate()
                    ->latest('started_at')
                    ->first();
            }

            if ($data['lock_status'] === 'in') {
                if ($activeActivity) {
                    throw ValidationException::withMessages([
                        'lock_status' => 'Gembok pribadi masih IN. Scan OUT terlebih dahulu sebelum memulai aktivitas pribadi yang baru.',
                    ]);
                }

                $activity = LototoActivity::query()->create([
                    ...$this->activityAnswers($data),
                    'employee_id' => $employee->id,
                    'board' => $data['lototo_board'],
                    'status' => 'in_progress',
                    'started_at' => now(),
                ]);

                $this->notifySupervisorIfNeeded($employee, $activity, $data);

                return $activity;
            }

            if (! $activeActivity) {
                throw ValidationException::withMessages([
                    'lock_status' => 'Tidak ditemukan aktivitas IN yang masih terbuka untuk nomor LOTOTO ini.',
                ]);
            }

            if ($isSpareMode) {
                if (! $activeActivity || $spareLock->current_activity_id !== $activeActivity->id || $activeActivity->employee_id !== $employee->id) {
                    throw ValidationException::withMessages([
                        'spare_lock_id' => 'Aktivitas IN untuk gembok spare ini tidak ditemukan atau sudah berubah.',
                    ]);
                }

                $outDetails = $activeActivity->out_details ?? [];
                $outDetails['spare_ended_at'] = now()->toISOString();
                $update = ['out_details' => $outDetails];
                if ($activeActivity->status === 'in_progress') {
                    $update['status'] = 'completed';
                    $update['outcome'] = 'finish';
                    $update['ended_at'] = now();
                }
                $activeActivity->update($update);
                $spareLock->update(['current_activity_id' => null]);

                return $activeActivity;
            }

            $outDetails = $activeActivity->out_details ?? [];
            $spareLock = null;
            if ($data['outcome'] === 'outstanding') {
                $spareLock = SpareLock::query()
                    ->whereKey($data['spare_lock_id'])
                    ->where('board_id', $board->id)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if (! $spareLock || $spareLock->current_activity_id) {
                    throw ValidationException::withMessages([
                        'spare_lock_id' => 'Gembok spare sudah tidak tersedia. Pilih gembok lain.',
                    ]);
                }

                $outDetails = array_merge($outDetails, [
                    'spare_lock_code' => $spareLock->code,
                    'spare_started_at' => now()->toISOString(),
                ]);
            }

            $activeActivity->fill([
                'outcome' => $data['outcome'],
                'out_details' => $outDetails === [] ? null : $outDetails,
                'status' => 'completed',
                'ended_at' => now(),
                'spare_lock_id' => $spareLock?->id,
            ])->save();
            if ($spareLock) {
                $spareLock->update(['current_activity_id' => $activeActivity->id]);
            }

            return $activeActivity;
        });

        return ($isSpareMode ? $spareRedirect() : $redirect())->with(
            'status',
            $data['lock_status'] === 'in'
                ? ($isSpareMode
                    ? 'Gembok spare berhasil di-IN.'
                    : 'Aktivitas LOTOTO berhasil dimulai.')
                : ($isSpareMode
                    ? 'Aktivitas gembok spare selesai dan gembok tersedia kembali.'
                    : ($data['outcome'] === 'finish'
                        ? 'Gembok pribadi sudah ditutup dan aktivitas selesai.'
                        : 'Gembok pribadi sudah ditutup dan gembok spare yang dipilih mulai digunakan.'))
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function activityAnswers(array $data): array
    {
        return [
            'location' => $data['location'],
            'activity' => $data['activity'],
            'risks' => $data['risks'],
            'other_risk' => $data['other_risk'] ?? null,
            'risk_controls' => $data['risk_controls'],
            'knows_procedure' => $data['knows_procedure'] === 'ya',
            'follows_lototo' => $data['follows_lototo'] === 'ya',
            'uses_ppe' => $data['uses_ppe'] === 'ya',
            'safe_tools' => $data['safe_tools'] === 'ya',
            'safe_hands' => $data['safe_hands'] === 'ya',
            'supervisor_feedback' => $data['supervisor_feedback'] ?? null,
        ];
    }

    private function isAuthorizedSpareTechnician(Request $request, ?Employee $employee): bool
    {
        $authenticatedEmployee = $request->user()?->employee;

        return $employee !== null
            && $authenticatedEmployee?->is($employee)
            && preg_match('/^\d{1,2}$/', trim((string) $employee->lototo_number)) === 1;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function notifySupervisorIfNeeded(Employee $employee, LototoActivity $activity, array $data): void
    {
        $answerLabels = [
            'knows_procedure' => 'Belum memahami cara kerja',
            'follows_lototo' => 'Tidak mengikuti standar LOTOTO',
            'uses_ppe' => 'APD tidak digunakan dengan benar',
            'safe_tools' => 'Tools tidak aman',
            'safe_hands' => 'Jari/tangan tidak aman',
        ];
        $unsafeAnswers = collect($answerLabels)
            ->filter(fn ($label, $field) => $data[$field] === 'tidak')
            ->values()
            ->all();
        $hasFeedback = filled($data['supervisor_feedback'] ?? null);

        if ($unsafeAnswers === [] && ! $hasFeedback) {
            return;
        }

        $supervisor = $employee->supervisor()->with('user')->first();
        $supervisorUser = $supervisor?->user;

        if (! $supervisorUser) {
            throw ValidationException::withMessages([
                'supervisor_feedback' => 'Data atasan atau akun supervisor belum tersedia. Hubungi administrator agar peringatan LOTOTO dapat diteruskan.',
            ]);
        }

        $activity->update(['supervisor_follow_up_status' => 'pending']);
        Notification::send($supervisorUser, new LototoSafetyAlert($activity, $unsafeAnswers, $hasFeedback));
    }

    public function followUp(Request $request, LototoActivity $activity): View
    {
        $this->authorizeFollowUp($request, $activity);
        $activity->load(['employee', 'spareLock', 'supervisorFollowUpBy']);

        return view('safety.lototo.follow-up', ['activity' => $activity]);
    }

    public function submitFollowUp(Request $request, LototoActivity $activity): RedirectResponse
    {
        $this->authorizeFollowUp($request, $activity);
        $data = $request->validate([
            'supervisor_follow_up' => ['required', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($activity, $data, $request) {
            $lockedActivity = LototoActivity::query()
                ->lockForUpdate()
                ->findOrFail($activity->id);

            if ($lockedActivity->supervisor_follow_up_status !== 'pending') {
                throw ValidationException::withMessages([
                    'supervisor_follow_up' => 'Tindak lanjut LOTOTO ini sudah dikirim atau tidak memerlukan tindak lanjut.',
                ]);
            }

            $lockedActivity->update([
                'supervisor_follow_up' => $data['supervisor_follow_up'],
                'supervisor_follow_up_by' => $request->user()->id,
                'supervisor_follow_up_at' => now(),
                'supervisor_follow_up_status' => 'completed',
            ]);
        });

        return to_route('safety.lototo.follow-up', $activity)
            ->with('status', 'Feedback tindak lanjut LOTOTO berhasil disimpan.');
    }

    private function authorizeFollowUp(Request $request, LototoActivity $activity): void
    {
        $supervisor = $request->user()->employee;

        abort_unless($supervisor && $activity->employee()->where('supervisor_id', $supervisor->id)->exists(), 403);
    }

    public function show(Request $request, Employee $employee): View
    {
        $board = $this->resolveBoard($request);
        $employee = Employee::query()
            ->with(['maintenanceArea', 'maintenanceTeam', 'position'])
            ->where('business_unit_id', $board->business_unit_id)
            ->visibleTo($request->user())
            ->findOrFail($employee->id);

        return view('safety.lototo.activity', [
            'employee' => $employee,
            'board' => $board,
            'activities' => LototoActivity::query()
                ->where('employee_id', $employee->id)
                ->where('board', $board->code)
                ->whereNull('spare_lock_id')
                ->with('spareLock')
                ->latest('started_at')
                ->paginate(20),
        ]);
    }

    private function resolveBoard(Request $request): LototoBoard
    {
        $requested = $request->input('board_id') ?? $request->query('board');

        return $requested
            ? LototoBoard::query()->where('id', $requested)->orWhere('code', $requested)->firstOrFail()
            : LototoBoard::query()->where('code', 'mtn_tr')->firstOrFail();
    }
}
