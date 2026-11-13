<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information. Blocked for the shared Guest
     * account - it has no "own profile" to speak of, and letting any
     * visitor rename/repassword a shared login would be a mess.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        abort_if($request->user()->hasRole(RoleName::Guest->value), 403);

        $user = $request->user();
        $user->fill($request->safe()->only(['name', 'email']));

        if ($request->hasFile('photo')) {
            $this->deletePhoto($user->photo_path);
            $user->photo_path = $request->file('photo')->store('user-photos', 'public');
        } elseif ($request->boolean('remove_photo')) {
            $this->deletePhoto($user->photo_path);
            $user->photo_path = null;
        }

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    private function deletePhoto(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
