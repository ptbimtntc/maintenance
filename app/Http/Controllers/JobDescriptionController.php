<?php

namespace App\Http\Controllers;

use App\Enums\MenuKey;
use App\Enums\PermissionName;
use App\Http\Requests\StoreJobDescriptionRequest;
use App\Models\JobDescription;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class JobDescriptionController extends Controller
{
    /**
     * Job Descriptions is the one menu that defaults to editable for
     * everyone (MenuKey::JobDescriptions->editableByDefault()), on top of
     * the pre-existing ManageJobDescriptions role permission (which
     * Administrator/Manager hold outright). Either grant is sufficient,
     * so an administrator can still revoke edit rights for a specific user
     * via the menu permission override without touching roles.
     */
    private function authorizeJobDescriptionEdit(Request $request): void
    {
        abort_unless(
            $request->user()->hasPermissionTo(PermissionName::ManageJobDescriptions->value)
                || $request->user()->canEditMenu(MenuKey::JobDescriptions),
            403
        );
    }

    public function index(Request $request): View
    {
        $jobDescriptions = JobDescription::query()
            ->with('position')
            ->when($request->filled('position_id'), fn ($q) => $q->where('position_id', $request->integer('position_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('position_id')
            ->orderByDesc('version')
            ->paginate(20)
            ->withQueryString();

        return view('job-descriptions.index', [
            'jobDescriptions' => $jobDescriptions,
            'positions' => Position::where('is_active', true)->orderBy('title')->get(),
            'statuses' => JobDescription::STATUSES,
            'filters' => $request->only(['position_id', 'status']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeJobDescriptionEdit($request);

        return view('job-descriptions.form', [
            'jobDescription' => null,
            'positions' => Position::where('is_active', true)->orderBy('title')->get(),
        ]);
    }

    public function store(StoreJobDescriptionRequest $request): RedirectResponse
    {
        $nextVersion = 1 + (int) JobDescription::where('position_id', $request->integer('position_id'))->max('version');

        $jobDescription = JobDescription::create([
            ...$request->validated(),
            'version' => $nextVersion,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('job-descriptions.show', $jobDescription)->with('status', 'Job description created.');
    }

    public function show(JobDescription $jobDescription): View
    {
        $jobDescription->load(['position.department', 'position.maintenanceArea', 'reportsToPosition', 'approvedBy', 'createdBy']);

        $versions = JobDescription::where('position_id', $jobDescription->position_id)
            ->orderByDesc('version')
            ->get(['id', 'version', 'status', 'effective_date']);

        return view('job-descriptions.show', [
            'jobDescription' => $jobDescription,
            'versions' => $versions,
        ]);
    }

    public function edit(Request $request, JobDescription $jobDescription): View
    {
        $this->authorizeJobDescriptionEdit($request);
        abort_if($jobDescription->status === 'archived', 403, 'Archived job descriptions cannot be edited. Create a new revision instead.');

        return view('job-descriptions.form', [
            'jobDescription' => $jobDescription,
            'positions' => Position::where('is_active', true)->orderBy('title')->get(),
        ]);
    }

    public function update(StoreJobDescriptionRequest $request, JobDescription $jobDescription): RedirectResponse
    {
        $this->authorizeJobDescriptionEdit($request);
        abort_if($jobDescription->status === 'archived', 403);

        $jobDescription->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('job-descriptions.show', $jobDescription)->with('status', 'Job description updated.');
    }

    public function newRevision(Request $request, JobDescription $jobDescription): RedirectResponse
    {
        $this->authorizeJobDescriptionEdit($request);

        $nextVersion = 1 + (int) JobDescription::where('position_id', $jobDescription->position_id)->max('version');

        $revision = JobDescription::create([
            ...$jobDescription->only([
                'position_id', 'reports_to_position_id', 'job_title', 'job_purpose', 'main_responsibilities',
                'detailed_duties', 'required_education', 'required_experience', 'required_technical_skills',
                'required_soft_skills', 'required_certifications', 'safety_responsibilities', 'direct_reports_summary',
                'remarks',
            ]),
            'version' => $nextVersion,
            'status' => 'draft',
            'effective_date' => null,
            'review_date' => null,
            'approved_by' => null,
            'approval_date' => null,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('job-descriptions.edit', $revision)->with('status', "Draft revision (v{$nextVersion}) created.");
    }

    public function submitForReview(Request $request, JobDescription $jobDescription): RedirectResponse
    {
        $this->authorizeJobDescriptionEdit($request);
        abort_unless($jobDescription->status === 'draft', 422, 'Only drafts can be submitted for review.');

        $jobDescription->update(['status' => 'pending_review', 'updated_by' => $request->user()->id]);

        return back()->with('status', 'Submitted for review.');
    }

    public function approve(Request $request, JobDescription $jobDescription): RedirectResponse
    {
        abort_unless($request->user()->hasPermissionTo(PermissionName::ManageJobDescriptions->value), 403);

        JobDescription::where('position_id', $jobDescription->position_id)
            ->where('status', 'active')
            ->where('id', '!=', $jobDescription->id)
            ->update(['status' => 'archived']);

        $jobDescription->update([
            'status' => 'active',
            'approved_by' => $request->user()->id,
            'approval_date' => now(),
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Job description approved and activated.');
    }

    public function archive(Request $request, JobDescription $jobDescription): RedirectResponse
    {
        $this->authorizeJobDescriptionEdit($request);

        $jobDescription->update(['status' => 'archived', 'updated_by' => $request->user()->id]);

        return back()->with('status', 'Job description archived.');
    }
}
