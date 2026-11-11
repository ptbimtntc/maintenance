<?php

namespace App\Http\Controllers;

use App\Concerns\ExportsSpreadsheet;
use App\Models\Machine;
use App\Models\ShiftComm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShiftCommController extends Controller
{
    use ExportsSpreadsheet;
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ShiftComm::class);

        $filters = [
            'date' => $request->string('date')->toString(),
            'shift' => $request->string('shift')->toString(),
            'torsion' => $request->string('torsion')->toString(),
            'relay' => $request->string('relay')->toString(),
            'q' => trim($request->string('q')->toString()),
        ];

        $comms = ShiftComm::query()
            ->with('creator')
            ->when($filters['date'], fn ($q, $v) => $q->whereDate('comm_date', $v))
            ->when($filters['shift'], fn ($q, $v) => $q->where('shift', $v))
            ->when(in_array($filters['torsion'], ['1', '0'], true), fn ($q) => $q->where('is_torsion_shaft', (bool) $filters['torsion']))
            ->when(array_key_exists($filters['relay'], ShiftComm::TORSION_RELAY_STATUSES), fn ($q) => $q->where('torsion_relay_status', $filters['relay']))
            ->when($filters['q'], fn ($q, $v) => $q->where(function ($w) use ($v) {
                $w->where('comm_number', 'like', "%{$v}%")
                    ->orWhere('machine_no', 'like', "%{$v}%")
                    ->orWhere('machine_type', 'like', "%{$v}%")
                    ->orWhere('construction', 'like', "%{$v}%")
                    ->orWhere('problem', 'like', "%{$v}%");
            }))
            ->orderByDesc('comm_date')
            ->orderByDesc('comm_number')
            ->paginate(20)
            ->withQueryString();

        return view('shift-comm.index', ['comms' => $comms, 'filters' => $filters, 'repeatWarnings' => ShiftComm::repeatTorsionWarnings()]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', ShiftComm::class);

        $filters = [
            'date' => $request->string('date')->toString(),
            'shift' => $request->string('shift')->toString(),
            'torsion' => $request->string('torsion')->toString(),
            'relay' => $request->string('relay')->toString(),
            'q' => trim($request->string('q')->toString()),
        ];

        $comms = ShiftComm::query()
            ->with('creator')
            ->when($filters['date'], fn ($q, $v) => $q->whereDate('comm_date', $v))
            ->when($filters['shift'], fn ($q, $v) => $q->where('shift', $v))
            ->when(in_array($filters['torsion'], ['1', '0'], true), fn ($q) => $q->where('is_torsion_shaft', (bool) $filters['torsion']))
            ->when(array_key_exists($filters['relay'], ShiftComm::TORSION_RELAY_STATUSES), fn ($q) => $q->where('torsion_relay_status', $filters['relay']))
            ->when($filters['q'], fn ($q, $v) => $q->where(function ($w) use ($v) {
                $w->where('comm_number', 'like', "%{$v}%")
                    ->orWhere('machine_no', 'like', "%{$v}%")
                    ->orWhere('machine_type', 'like', "%{$v}%")
                    ->orWhere('construction', 'like', "%{$v}%")
                    ->orWhere('problem', 'like', "%{$v}%");
            }))
            ->orderByDesc('comm_date')
            ->orderByDesc('comm_number')
            ->get();

        $headers = [
            'ID',
            'Date',
            'Shift',
            'Team',
            'Machine No.',
            'Machine Type',
            'Problem',
            'Progress',
            'Construction',
            'Torsion Shaft',
            'Torsion Relay Status',
            'Length (m)',
            'Remark',
            'Created By',
            'Created At',
        ];

        $rows = $comms->map(fn (ShiftComm $c) => [
            $c->comm_number,
            $c->comm_date?->format('Y-m-d'),
            $c->shiftName(),
            $c->team,
            $c->machine_no,
            $c->machine_type,
            $c->problem,
            $c->progress,
            $c->construction,
            $c->is_torsion_shaft ? 'YA' : ($c->is_torsion_shaft === null ? '' : 'TIDAK'),
            $c->torsionRelayLabel(),
            $c->length_m,
            $c->remark,
            $c->creator?->name,
            $c->created_at?->format('Y-m-d H:i:s'),
        ]);

        return $this->streamXlsx('shift-comm-'.now()->format('Y-m-d').'.xlsx', $headers, $rows);
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorize('import', ShiftComm::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx'],
        ]);

        $rows = (new XlsxReader)->load($request->file('file')->getRealPath())->getActiveSheet()->toArray(null, true, true, false);
        $header = array_map(fn ($cell) => strtolower(trim((string) $cell)), array_shift($rows) ?? []);

        $findCol = function (array $candidates) use ($header): ?int {
            foreach ($candidates as $cand) {
                $idx = array_search(strtolower($cand), $header, true);
                if ($idx !== false) {
                    return $idx;
                }
            }
            return null;
        };

        $idCol = $findCol(['id', 'wo', 'no id', 'comm number', 'comm_number', 'no. id (wo)']);
        $dateCol = $findCol(['date', 'tanggal', 'comm date', 'comm_date']);
        $shiftCol = $findCol(['shift']);
        $teamCol = $findCol(['team', 'tim']);
        $machineNoCol = $findCol(['machine no.', 'machine no', 'machine number', 'machine_no', 'nomor mesin']);
        $machineTypeCol = $findCol(['machine type', 'machine_type', 'tipe mesin']);
        $problemCol = $findCol(['problem', 'deskripsi problem']);
        $progressCol = $findCol(['progress']);
        $constructionCol = $findCol(['construction', 'konstruksi']);
        $torsionCol = $findCol(['torsion shaft', 'is_torsion_shaft', 'torsion_shaft', 'ts']);
        $relayCol = $findCol(['torsion relay status', 'estafet torsion shaft', 'torsion_relay_status', 'relay status', 'status estafet']);
        $lengthCol = $findCol(['length (m)', 'length', 'length_m', 'panjang']);
        $remarkCol = $findCol(['remark', 'catatan']);

        if ($machineNoCol === null || $problemCol === null) {
            return redirect()->route('shift-comm.index')
                ->with('import_errors', 'File Excel wajib memiliki kolom "Machine No." dan "Problem".');
        }

        $parseDate = function ($value): ?Carbon {
            if ($value === null || $value === '') {
                return null;
            }
            if (is_numeric($value)) {
                try {
                    return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value));
                } catch (\Throwable) {}
            }
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                return null;
            }
        };

        $parseShift = function ($value): int {
            if (is_numeric($value)) {
                $int = (int) $value;
                return in_array($int, [1, 2, 3], true) ? $int : 1;
            }
            $str = strtolower(trim((string) $value));
            if (str_contains($str, '1')) return 1;
            if (str_contains($str, '2')) return 2;
            if (str_contains($str, '3')) return 3;
            return 1;
        };

        $parseTeam = function ($value): string {
            $val = strtoupper(trim((string) $value));
            return in_array($val, ShiftComm::TEAMS, true) ? $val : 'A';
        };

        $parseTorsion = function ($value): bool {
            if ($value === null || $value === '') return false;
            $v = strtolower(trim((string) $value));
            return in_array($v, ['1', 'ya', 'yes', 'true', 'y'], true);
        };

        $parseRelay = function ($value): ?string {
            if ($value === null || $value === '') return null;
            $v = strtolower(trim((string) $value));
            if (str_contains($v, 'belum') || $v === 'not_started') return 'not_started';
            if (str_contains($v, 'sedang') || $v === 'in_progress') return 'in_progress';
            if (str_contains($v, 'estafet') || str_contains($v, 'next') || $v === 'next_shift') return 'next_shift';
            return in_array($v, array_keys(ShiftComm::TORSION_RELAY_STATUSES), true) ? $v : null;
        };

        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;
            $machineNo = isset($row[$machineNoCol]) ? trim((string) $row[$machineNoCol]) : '';
            $problem = isset($row[$problemCol]) ? trim((string) $row[$problemCol]) : '';

            if ($machineNo === '' && $problem === '') {
                continue;
            }

            if ($machineNo === '') {
                $errors[] = "Baris {$rowNum}: Nomor mesin wajib diisi.";
                continue;
            }

            if ($problem === '') {
                $errors[] = "Baris {$rowNum}: Problem wajib diisi.";
                continue;
            }

            $dateRaw = $dateCol !== null && isset($row[$dateCol]) ? $row[$dateCol] : null;
            $commDate = $parseDate($dateRaw) ?? now();

            $shift = $shiftCol !== null && isset($row[$shiftCol]) ? $parseShift($row[$shiftCol]) : 1;
            $team = $teamCol !== null && isset($row[$teamCol]) ? $parseTeam($row[$teamCol]) : 'A';

            $machineType = $machineTypeCol !== null && isset($row[$machineTypeCol]) ? trim((string) $row[$machineTypeCol]) : null;
            if (! $machineType) {
                $machineType = ShiftComm::lookupMachineType($machineNo);
            }

            $isTorsion = $torsionCol !== null && isset($row[$torsionCol]) ? $parseTorsion($row[$torsionCol]) : false;
            $relayStatus = $relayCol !== null && isset($row[$relayCol]) ? $parseRelay($row[$relayCol]) : null;
            if (! $isTorsion) {
                $relayStatus = null;
            }

            $progress = $progressCol !== null && isset($row[$progressCol]) ? trim((string) $row[$progressCol]) : null;
            $construction = $constructionCol !== null && isset($row[$constructionCol]) ? trim((string) $row[$constructionCol]) : null;
            $lengthM = $lengthCol !== null && isset($row[$lengthCol]) && is_numeric($row[$lengthCol]) ? (int) $row[$lengthCol] : null;
            $remark = $remarkCol !== null && isset($row[$remarkCol]) ? trim((string) $row[$remarkCol]) : null;

            $commNumber = $idCol !== null && isset($row[$idCol]) ? trim((string) $row[$idCol]) : null;

            $record = null;
            if ($commNumber) {
                $record = ShiftComm::where('comm_number', $commNumber)->first();
            }

            if ($record) {
                $record->update([
                    'machine_no' => $machineNo,
                    'machine_type' => $machineType,
                    'problem' => $problem,
                    'progress' => $progress,
                    'construction' => $construction,
                    'is_torsion_shaft' => $isTorsion,
                    'torsion_relay_status' => $relayStatus,
                    'length_m' => $lengthM,
                    'remark' => $remark,
                ]);
                $updated++;
            } else {
                ShiftComm::createNumbered([
                    'comm_date' => $commDate->toDateString(),
                    'shift' => $shift,
                    'team' => $team,
                    'machine_no' => $machineNo,
                    'machine_type' => $machineType,
                    'problem' => $problem,
                    'progress' => $progress,
                    'construction' => $construction,
                    'is_torsion_shaft' => $isTorsion,
                    'torsion_relay_status' => $relayStatus,
                    'length_m' => $lengthM,
                    'remark' => $remark,
                    'created_by' => $request->user()->id,
                ]);
                $created++;
            }
        }

        $msg = "Import Shift Comm selesai: {$created} entri baru dibuat, {$updated} entri diperbarui.";
        $redirect = redirect()->route('shift-comm.index')->with('status', $msg);

        if ($errors !== []) {
            $shown = array_slice($errors, 0, 8);
            $suffix = count($errors) > 8 ? ' …dan '.(count($errors) - 8).' baris lainnya.' : '';
            $redirect->with('import_errors', implode(' ', $shown).$suffix);
        }

        return $redirect;
    }

    public function create(Request $request): View
    {
        $this->authorize('create', ShiftComm::class);

        $machineCatalog = Machine::lookupCatalog();
        if (empty($machineCatalog)) {
            $machineCatalog = ShiftComm::MACHINE_CATALOG;
        }

        return view('shift-comm.form', [
            'comm' => null,
            'defaults' => [
                'comm_date' => now()->toDateString(),
                'shift' => ShiftComm::defaultShiftFor(now()),
                'team' => ShiftComm::defaultTeamFor($request->user()->employee),
            ],
            'constructions' => ShiftComm::constructionSuggestions(),
            'machineCatalog' => $machineCatalog,
            'machinePrefixRules' => ShiftComm::MACHINE_PREFIX_RULES,
            'machineSuggestions' => array_keys($machineCatalog),
        ]);
    }

    public function lookupMachine(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ShiftComm::class);

        $machineNo = $request->string('no')->toString();
        $type = ShiftComm::lookupMachineType($machineNo);

        return response()->json([
            'machine_no' => $machineNo,
            'machine_type' => $type,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ShiftComm::class);

        $comm = ShiftComm::createNumbered($this->validated($request, true) + ['created_by' => $request->user()->id]);

        return redirect()->route('shift-comm.show', $comm)->with('status', "Shift Comm {$comm->comm_number} created.");
    }

    public function show(ShiftComm $shiftComm): View
    {
        $this->authorize('viewAny', ShiftComm::class);

        return view('shift-comm.show', ['comm' => $shiftComm->load('creator')]);
    }

    public function edit(ShiftComm $shiftComm): View
    {
        $this->authorize('update', $shiftComm);

        $machineCatalog = Machine::lookupCatalog();
        if (empty($machineCatalog)) {
            $machineCatalog = ShiftComm::MACHINE_CATALOG;
        }

        return view('shift-comm.form', [
            'comm' => $shiftComm,
            'defaults' => [],
            'constructions' => ShiftComm::constructionSuggestions(),
            'machineCatalog' => $machineCatalog,
            'machinePrefixRules' => ShiftComm::MACHINE_PREFIX_RULES,
            'machineSuggestions' => array_keys($machineCatalog),
        ]);
    }

    public function update(Request $request, ShiftComm $shiftComm): RedirectResponse
    {
        $this->authorize('update', $shiftComm);

        // Date and shift are baked into the ID, so they stay fixed after creation.
        $shiftComm->update($this->validated($request, false));

        return redirect()->route('shift-comm.show', $shiftComm)->with('status', 'Shift Comm updated.');
    }

    public function destroy(ShiftComm $shiftComm): RedirectResponse
    {
        $this->authorize('delete', $shiftComm);

        $shiftComm->delete();

        return redirect()->route('shift-comm.index')->with('status', 'Shift Comm deleted.');
    }

    private function validated(Request $request, bool $creating): array
    {
        $rules = [
            'team' => ['required', Rule::in(ShiftComm::TEAMS)],
            'machine_no' => ['required', 'string', 'max:50'],
            'machine_type' => ['nullable', 'string', 'max:50'],
            'problem' => ['required', 'string', 'max:5000'],
            'is_torsion_shaft' => ['required', 'boolean'],
            'progress' => ['nullable', 'string', 'max:5000'],
            'torsion_relay_status' => ['exclude_unless:is_torsion_shaft,1', 'required', Rule::in(array_keys(ShiftComm::TORSION_RELAY_STATUSES))],
            'construction' => ['nullable', 'string', 'max:150'],
            'length_m' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'remark' => ['nullable', 'string', 'max:5000'],
        ];

        if ($creating) {
            $rules['comm_date'] = ['required', 'date'];
            $rules['shift'] = ['required', Rule::in(array_keys(ShiftComm::SHIFTS))];
        }

        $data = $request->validate($rules);

        // Auto-detect machine_type if omitted but machine_no is provided
        if (empty($data['machine_type']) && ! empty($data['machine_no'])) {
            $data['machine_type'] = ShiftComm::lookupMachineType($data['machine_no']);
        }

        // A non-Torsion Shaft entry carries no estafet status.
        $data['torsion_relay_status'] = $data['is_torsion_shaft'] ? $data['torsion_relay_status'] : null;

        return $data;
    }
}
