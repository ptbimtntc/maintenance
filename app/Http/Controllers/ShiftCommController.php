<?php

namespace App\Http\Controllers;

use App\Models\ShiftComm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShiftCommController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ShiftComm::class);

        $filters = [
            'date' => $request->string('date')->toString(),
            'shift' => $request->string('shift')->toString(),
            'q' => trim($request->string('q')->toString()),
        ];

        $comms = ShiftComm::query()
            ->with('creator')
            ->when($filters['date'], fn ($q, $v) => $q->whereDate('comm_date', $v))
            ->when($filters['shift'], fn ($q, $v) => $q->where('shift', $v))
            ->when($filters['q'], fn ($q, $v) => $q->where(function ($w) use ($v) {
                $w->where('comm_number', 'like', "%{$v}%")
                    ->orWhere('machine_no', 'like', "%{$v}%")
                    ->orWhere('construction', 'like', "%{$v}%")
                    ->orWhere('problem', 'like', "%{$v}%");
            }))
            ->orderByDesc('comm_date')
            ->orderByDesc('comm_number')
            ->paginate(20)
            ->withQueryString();

        return view('shift-comm.index', ['comms' => $comms, 'filters' => $filters]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', ShiftComm::class);

        return view('shift-comm.form', [
            'comm' => null,
            'defaults' => [
                'comm_date' => now()->toDateString(),
                'shift' => ShiftComm::defaultShiftFor(now()),
                'team' => ShiftComm::defaultTeamFor($request->user()->employee),
            ],
            'constructions' => ShiftComm::constructionSuggestions(),
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

        return view('shift-comm.form', [
            'comm' => $shiftComm,
            'defaults' => [],
            'constructions' => ShiftComm::constructionSuggestions(),
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
            'problem' => ['required', 'string', 'max:5000'],
            'progress' => ['nullable', 'string', 'max:5000'],
            'construction' => ['nullable', 'string', 'max:150'],
            'length_m' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'remark' => ['nullable', 'string', 'max:5000'],
        ];

        if ($creating) {
            $rules['comm_date'] = ['required', 'date'];
            $rules['shift'] = ['required', Rule::in(array_keys(ShiftComm::SHIFTS))];
        }

        return $request->validate($rules);
    }
}
