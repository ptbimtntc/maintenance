<?php

namespace App\Http\Controllers;

use App\Concerns\ExportsSpreadsheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Generic CRUD for the simple "lookup" master data tables (departments,
 * positions, shifts, etc.) described in config/master_data.php. One
 * controller + one set of views instead of nine near-identical copies.
 */
class MasterDataController extends Controller
{
    use ExportsSpreadsheet;

    /**
     * How the landing page groups the master data types. Any type not
     * listed here still shows up, under "Other", so a newly added entry
     * in config/master_data.php is never silently hidden.
     */
    private const LANDING_GROUPS = [
        'Organization Structure' => ['departments', 'divisions', 'maintenance-areas', 'maintenance-teams', 'business-units', 'locations'],
        'Positions & Employment' => ['positions', 'skill-positions', 'employment-types', 'employment-sources', 'employment-statuses', 'shifts'],
        'Skills & Competency' => ['skill-categories', 'skills', 'competency-levels'],
        'Training & Certification' => ['training-categories', 'training-types', 'training-providers', 'certificate-types'],
    ];

    public function landing(): View
    {
        $types = Config::get('master_data');
        $groups = [];

        foreach (self::LANDING_GROUPS as $title => $slugs) {
            $items = collect($slugs)->filter(fn ($slug) => isset($types[$slug]))->mapWithKeys(fn ($slug) => [$slug => $types[$slug]]);

            if ($items->isNotEmpty()) {
                $groups[$title] = $items;
            }
        }

        $ungrouped = collect($types)->except(collect(self::LANDING_GROUPS)->flatten()->all());

        if ($ungrouped->isNotEmpty()) {
            $groups['Other'] = $ungrouped;
        }

        return view('organization.landing', ['groups' => $groups]);
    }

    public function index(string $type): View
    {
        $config = $this->configFor($type);
        $model = $config['model'];

        $records = $model::query()
            ->when($config['parent'] ?? null, fn ($q, $parent) => $q->with($this->parentRelationName($parent)))
            ->orderBy($config['name_field'])
            ->paginate(20);

        return view('organization.index', [
            'type' => $type,
            'config' => $config,
            'records' => $records,
        ]);
    }

    public function create(string $type): View
    {
        $config = $this->configFor($type);

        return view('organization.form', [
            'type' => $type,
            'config' => $config,
            'record' => null,
            'parentOptions' => $this->parentOptions($config),
        ]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $config = $this->configFor($type);
        $model = $config['model'];

        $data = $this->validatedData($request, $config);

        $model::create($data);

        return redirect()->route('organization.index', $type)->with('status', "{$config['singular']} created.");
    }

    public function edit(string $type, int $id): View
    {
        $config = $this->configFor($type);
        $record = $config['model']::findOrFail($id);

        return view('organization.form', [
            'type' => $type,
            'config' => $config,
            'record' => $record,
            'parentOptions' => $this->parentOptions($config),
        ]);
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        $config = $this->configFor($type);
        $record = $config['model']::findOrFail($id);

        $data = $this->validatedData($request, $config, $record->id);

        $record->update($data);

        return redirect()->route('organization.index', $type)->with('status', "{$config['singular']} updated.");
    }

    public function destroy(string $type, int $id): RedirectResponse
    {
        $config = $this->configFor($type);
        $record = $config['model']::findOrFail($id);

        $record->delete();

        return redirect()->route('organization.index', $type)->with('status', "{$config['singular']} removed.");
    }

    /**
     * Spreadsheet columns for a type, in order: ID, name, Code, Description,
     * the parent's name (if any), every extra field, then Active. The same
     * shape is used for export and import so a downloaded file can be edited
     * and uploaded straight back.
     *
     * @return array<int, array{header: string, key: string, kind: string}>
     */
    private function sheetColumns(array $config): array
    {
        $columns = [
            ['header' => 'ID', 'key' => 'id', 'kind' => 'id'],
            ['header' => $config['singular'].' Name', 'key' => $config['name_field'], 'kind' => 'text'],
            ['header' => 'Code', 'key' => 'code', 'kind' => 'text'],
            ['header' => 'Description', 'key' => 'description', 'kind' => 'text'],
        ];

        if (isset($config['parent'])) {
            $columns[] = ['header' => $config['parent']['label'], 'key' => $config['parent']['field'], 'kind' => 'parent'];
        }

        foreach ($config['extra_fields'] ?? [] as $field => $meta) {
            $columns[] = ['header' => $meta['label'], 'key' => $field, 'kind' => $meta['type']];
        }

        $columns[] = ['header' => 'Active', 'key' => 'is_active', 'kind' => 'boolean'];

        return $columns;
    }

    public function export(string $type): StreamedResponse
    {
        $config = $this->configFor($type);
        $columns = $this->sheetColumns($config);
        $parentNames = isset($config['parent'])
            ? $config['parent']['model']::withTrashed()->pluck('name', 'id')
            : collect();

        $rows = $config['model']::query()->orderBy($config['name_field'])->get()->map(function ($record) use ($columns, $config, $parentNames) {
            return array_map(function ($column) use ($record, $config, $parentNames) {
                $value = $record->{$column['key']};

                return match ($column['kind']) {
                    'parent' => $parentNames[$value] ?? null,
                    'boolean' => $value ? 'Yes' : 'No',
                    'select' => $config['extra_fields'][$column['key']]['options'][$value] ?? $value,
                    'time' => $value ? substr((string) $value, 0, 5) : null,
                    default => $value,
                };
            }, $columns);
        });

        return $this->streamXlsx($type.'-'.now()->format('Y-m-d').'.xlsx', array_column($columns, 'header'), $rows);
    }

    /**
     * Bulk create/update from an .xlsx shaped like export(). A row is matched
     * to an existing record by ID, else Code, else name; no match creates a
     * new record. On an update a blank cell leaves that field untouched.
     * Every row is validated with the same rules as the single-record form,
     * and a bad row is reported and skipped without stopping the rest.
     */
    public function import(Request $request, string $type): RedirectResponse
    {
        $config = $this->configFor($type);
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx']]);

        $columns = $this->sheetColumns($config);
        $model = $config['model'];
        $nameField = $config['name_field'];

        $rows = (new XlsxReader)->load($request->file('file')->getRealPath())->getActiveSheet()->toArray(null, true, true, false);
        $header = array_map(fn ($cell) => trim((string) $cell), array_shift($rows) ?? []);

        if (! in_array($config['singular'].' Name', $header, true)) {
            return redirect()->route('organization.index', $type)
                ->with('import_errors', "The file has no \"{$config['singular']} Name\" column - use the Export XLSX file as the template.");
        }

        $parentIds = isset($config['parent'])
            ? $config['parent']['model']::pluck('id', 'name')
            : collect();

        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $data = array_combine($header, array_pad($row, count($header), null));
            $cells = [];

            foreach ($columns as $column) {
                $raw = $data[$column['header']] ?? null;
                $raw = is_string($raw) ? trim($raw) : $raw;
                $cells[$column['key']] = ($raw === '' || $raw === null) ? null : $raw;
            }

            if (collect($cells)->filter(fn ($v) => $v !== null)->isEmpty()) {
                continue;
            }

            $record = null;

            if ($cells['id'] !== null) {
                $record = $model::find((int) $cells['id']);

                if (! $record) {
                    $errors[] = "Row {$rowNumber}: ID {$cells['id']} not found.";

                    continue;
                }
            } elseif ($cells['code'] !== null) {
                $record = $model::where('code', (string) $cells['code'])->first();
            }

            $record ??= $cells[$nameField] !== null ? $model::where($nameField, (string) $cells[$nameField])->first() : null;

            if (! $record && $cells[$nameField] === null) {
                $errors[] = "Row {$rowNumber}: {$config['singular']} Name is required to create a new record.";

                continue;
            }

            $payload = [];
            $rowFailed = false;

            foreach ($columns as $column) {
                $value = $cells[$column['key']];

                if ($column['kind'] === 'id' || $value === null) {
                    continue;
                }

                switch ($column['kind']) {
                    case 'parent':
                        if (! isset($parentIds[(string) $value])) {
                            $errors[] = "Row {$rowNumber}: {$column['header']} \"{$value}\" not found - row skipped.";
                            $rowFailed = true;
                        } else {
                            $payload[$column['key']] = $parentIds[(string) $value];
                        }
                        break;
                    case 'boolean':
                        $flag = strtolower((string) $value);
                        $payload[$column['key']] = in_array($flag, ['yes', 'y', '1', 'true', 'active'], true);
                        break;
                    case 'select':
                        $options = $config['extra_fields'][$column['key']]['options'];
                        $key = array_search(strtolower((string) $value), array_map('strtolower', $options), true);
                        $payload[$column['key']] = $key !== false ? $key : (array_key_exists($value, $options) ? $value : $value);
                        break;
                    case 'time':
                        try {
                            $payload[$column['key']] = is_numeric($value)
                                ? Carbon::createFromTime(0, 0)->addSeconds((int) round($value * 86400))->format('H:i')
                                : Carbon::parse((string) $value)->format('H:i');
                        } catch (\Throwable) {
                            $errors[] = "Row {$rowNumber}: {$column['header']} \"{$value}\" is not a valid time - row skipped.";
                            $rowFailed = true;
                        }
                        break;
                    case 'number':
                        $payload[$column['key']] = is_numeric($value) ? (int) $value : $value;
                        break;
                    default:
                        $payload[$column['key']] = (string) $value;
                }
            }

            if ($rowFailed) {
                continue;
            }

            if (! $record && ! array_key_exists('is_active', $payload)) {
                $payload['is_active'] = true;
            }

            $validator = Validator::make($payload, $this->importRules($config, $record?->id, (bool) $record));

            if ($validator->fails()) {
                $errors[] = "Row {$rowNumber}: ".$validator->errors()->first().' - row skipped.';

                continue;
            }

            if ($record) {
                $record->update($payload);
                $updated++;
            } else {
                $model::create($payload);
                $created++;
            }
        }

        $redirect = redirect()->route('organization.index', $type)
            ->with('status', "{$created} {$config['label']} created, {$updated} updated from import.");

        if ($errors !== []) {
            $shown = array_slice($errors, 0, 8);
            $suffix = count($errors) > 8 ? ' …and '.(count($errors) - 8).' more.' : '';
            $redirect->with('import_errors', count($errors).' issue(s) found: '.implode(' | ', $shown).$suffix);
        }

        return $redirect;
    }

    /**
     * The form's rules, minus "required" when updating: a blank cell on an
     * existing row just means "leave this field as it is".
     */
    private function importRules(array $config, ?int $ignoreId, bool $isUpdate): array
    {
        $rules = $this->rules($config, $ignoreId);

        if ($isUpdate) {
            $rules = array_map(fn ($fieldRules) => array_map(fn ($r) => $r === 'required' ? 'sometimes' : $r, (array) $fieldRules), $rules);
        }

        return $rules;
    }

    private function configFor(string $type): array
    {
        $config = Config::get("master_data.{$type}");

        if (! $config) {
            throw new NotFoundHttpException;
        }

        return $config;
    }

    private function parentRelationName(array $parent): string
    {
        return lcfirst(class_basename($parent['model']));
    }

    private function parentOptions(array $config): ?\Illuminate\Support\Collection
    {
        if (! isset($config['parent'])) {
            return null;
        }

        return $config['parent']['model']::where('is_active', true)->orderBy('name')->get();
    }

    /**
     * Validate the request and normalize checkbox fields, which are simply
     * absent from the payload when unchecked and would otherwise be silently
     * skipped by "sometimes" validation instead of turning the value off.
     */
    private function validatedData(Request $request, array $config, ?int $ignoreId = null): array
    {
        $data = $request->validate($this->rules($config, $ignoreId));

        $data['is_active'] = $request->boolean('is_active');

        foreach ($config['extra_fields'] ?? [] as $field => $meta) {
            if ($meta['type'] === 'boolean') {
                $data[$field] = $request->boolean($field);
            }
        }

        return $data;
    }

    private function rules(array $config, ?int $ignoreId = null): array
    {
        $nameField = $config['name_field'];

        $rules = [
            $nameField => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique($config['model']::make()->getTable(), 'code')->ignore($ignoreId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        if (isset($config['parent'])) {
            $rules[$config['parent']['field']] = ['nullable', 'exists:'.$config['parent']['model']::make()->getTable().',id'];
        }

        foreach ($config['extra_fields'] ?? [] as $field => $meta) {
            $fieldRules = match ($meta['type']) {
                'number' => [($meta['required'] ?? false) ? 'required' : 'nullable', 'integer'],
                'boolean' => ['sometimes', 'boolean'],
                'time' => ['nullable', 'date_format:H:i'],
                'select' => [($meta['required'] ?? false) ? 'required' : 'nullable', Rule::in(array_keys($meta['options'] ?? []))],
                default => [($meta['required'] ?? false) ? 'required' : 'nullable', 'string', 'max:255'],
            };

            if ($meta['unique'] ?? false) {
                $fieldRules[] = Rule::unique($config['model']::make()->getTable(), $field)->ignore($ignoreId);
            }

            $rules[$field] = $fieldRules;
        }

        return $rules;
    }
}
