<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Generic CRUD for the simple "lookup" master data tables (departments,
 * positions, shifts, etc.) described in config/master_data.php. One
 * controller + one set of views instead of nine near-identical copies.
 */
class MasterDataController extends Controller
{
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
