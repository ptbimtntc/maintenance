<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Public, unauthenticated self-service flow: a new employee scans the QR
 * code shown on their profile (see EmployeeController::store()) and fills
 * in their own personal details, without needing a user account. Only the
 * fields listed in Employee::SELF_SERVICE_FIELDS are ever touched here -
 * organizational fields (position, department, supervisor, ...) stay
 * HR-controlled since there's no login to protect them with on this route.
 */
class EmployeeOnboardingController extends Controller
{
    public function edit(string $token): View
    {
        $employee = Employee::where('profile_completion_token', $token)->first();

        return view('onboarding.edit', [
            'employee' => $employee,
            'valid' => $employee?->onboardingLinkIsActive() ?? false,
        ]);
    }

    public function update(Request $request, string $token): View|RedirectResponse
    {
        $employee = Employee::where('profile_completion_token', $token)->first();

        if (! $employee || ! $employee->onboardingLinkIsActive()) {
            return view('onboarding.edit', ['employee' => $employee, 'valid' => false]);
        }

        $data = $request->validate([
            'preferred_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(['Male', 'Female'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('employees', 'email')->ignore($employee->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'education' => ['nullable', 'string', 'max:255'],
            'technical_background' => ['nullable', 'string', 'max:255'],
            'years_of_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);

        $employee->update(collect($data)->except('photo')->toArray());

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('employee-photos', 'public');
            $employee->update(['photo_path' => $path]);
        }

        // The token is kept (not nulled) so revisiting the same link after
        // completion still resolves to this employee and shows a clear
        // "already completed" message instead of a generic "not found".
        // onboardingLinkIsActive() is what actually blocks re-submission.
        $employee->forceFill(['profile_completed_at' => now()])->save();

        return view('onboarding.edit', ['employee' => $employee, 'valid' => false, 'justCompleted' => true]);
    }
}
