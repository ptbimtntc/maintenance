<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Models\Signatory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Reusable trainer/authorizer names + signature images, picked from a
 * Training Program's form instead of re-typing the name and re-uploading
 * the same signature image on every program (see
 * TrainingProgramController::storeSignatories()).
 */
class SignatoryController extends Controller
{
    public function index(): View
    {
        return view('signatories.index', [
            'signatories' => Signatory::orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->authorize(PermissionName::ManageCertificates->value);

        return view('signatories.form', ['signatory' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize(PermissionName::ManageCertificates->value);

        $data = $this->validated($request);
        $signatory = Signatory::create($data);
        $this->storeSignatureImage($request, $signatory);

        return redirect()->route('signatories.index')->with('status', 'Signatory added.');
    }

    public function edit(Signatory $signatory): View
    {
        $this->authorize(PermissionName::ManageCertificates->value);

        return view('signatories.form', ['signatory' => $signatory]);
    }

    public function update(Request $request, Signatory $signatory): RedirectResponse
    {
        $this->authorize(PermissionName::ManageCertificates->value);

        $signatory->update($this->validated($request));
        $this->storeSignatureImage($request, $signatory);

        return redirect()->route('signatories.index')->with('status', 'Signatory updated.');
    }

    public function destroy(Signatory $signatory): RedirectResponse
    {
        $this->authorize(PermissionName::ManageCertificates->value);

        if ($signatory->signature_path) {
            Storage::disk('public')->delete($signatory->signature_path);
        }

        $signatory->delete();

        return redirect()->route('signatories.index')->with('status', 'Signatory removed.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'signature' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        unset($data['signature']);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    private function storeSignatureImage(Request $request, Signatory $signatory): void
    {
        if (! $request->hasFile('signature')) {
            return;
        }

        if ($signatory->signature_path) {
            Storage::disk('public')->delete($signatory->signature_path);
        }

        $signatory->forceFill(['signature_path' => $request->file('signature')->store('signatories', 'public')])->save();
    }
}
