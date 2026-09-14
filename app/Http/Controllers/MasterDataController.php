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
    public function landing(): View
    {
        return view('organization.landing', ['types' => Config::get('master_data')]);
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
            $rules[$field] = match ($meta['type']) {
                'number' => ['nullable', 'integer'],
                'boolean' => ['sometimes', 'boolean'],
                'time' => ['nullable', 'date_format:H:i'],
                default => ['nullable', 'string', 'max:255'],
            };
        }

        return $rules;
    }
}
