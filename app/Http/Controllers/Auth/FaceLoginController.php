<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Face Login is a convenience shortcut, never a replacement for the
 * password: it only works on a device the employee has explicitly
 * enrolled (see enroll()), and matching happens 1-to-1 against that
 * device's owner - never 1-to-many against every enrolled employee. Only
 * a face descriptor (128 floats extracted client-side by face-api.js) is
 * ever stored or transmitted, never the photo/video itself.
 */
class FaceLoginController extends Controller
{
    private const COOKIE_NAME = 'face_trusted_device';

    private const COOKIE_DAYS = 30;

    /**
     * Match distance threshold for face-api.js's recognition descriptors -
     * the library's own examples use 0.6; this is deliberately a little
     * stricter since a failed match always has "type your password"
     * available, so there's no cost to erring toward more false-rejects.
     */
    private const MATCH_THRESHOLD = 0.5;

    public function enroll(Request $request): JsonResponse
    {
        $data = $request->validate([
            'descriptor' => ['required', 'array', 'size:128'],
            'descriptor.*' => ['numeric'],
            'consent' => ['accepted'],
            'label' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();
        $user->forceFill([
            'face_descriptor' => json_encode(array_values($data['descriptor'])),
            'face_enrolled_at' => now(),
        ])->save();

        $token = Str::random(64);

        $user->trustedDevices()->create([
            'token_hash' => hash('sha256', $token),
            'label' => $data['label'] ?? $request->userAgent(),
            'expires_at' => now()->addDays(self::COOKIE_DAYS),
        ]);

        return response()->json(['message' => 'Face Login enabled for this device.'])
            ->withCookie($this->makeCookie($token, $request->secure()));
    }

    /**
     * Disables Face Login everywhere for this user (not just this device):
     * clears the stored descriptor and every trusted device row, so a lost
     * or shared device can no longer be used to sign in as them.
     */
    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->trustedDevices()->delete();
        $user->forceFill(['face_descriptor' => null, 'face_enrolled_at' => null])->save();

        return redirect()->back()
            ->with('status', 'Face Login disabled.')
            ->withCookie(Cookie::forget(self::COOKIE_NAME));
    }

    public function attempt(Request $request): JsonResponse
    {
        $request->validate([
            'descriptor' => ['required', 'array', 'size:128'],
            'descriptor.*' => ['numeric'],
        ]);

        $device = $this->resolveTrustedDevice($request);

        if (! $device || ! $device->user->hasFaceLoginEnabled()) {
            return response()->json(['message' => 'This device is not enrolled for Face Login. Please use your password.'], 422);
        }

        $stored = json_decode($device->user->face_descriptor, true);
        $distance = $this->euclideanDistance($stored, $request->array('descriptor'));

        if ($distance > self::MATCH_THRESHOLD) {
            return response()->json(['message' => 'Face not recognized. Please use your password.'], 422);
        }

        Auth::login($device->user);
        $request->session()->regenerate();
        $device->forceFill(['last_used_at' => now()])->save();

        return response()->json(['redirect' => route('dashboard')]);
    }

    /**
     * Whether the current browser is enrolled for Face Login, for the
     * login page to decide whether to show the option at all - it never
     * reveals which employee, only that *some* enrolled device sent the
     * request, matching the trust already implied by holding this cookie.
     */
    public function status(Request $request): JsonResponse
    {
        $device = $this->resolveTrustedDevice($request);

        return response()->json(['available' => $device !== null && $device->user->hasFaceLoginEnabled()]);
    }

    private function resolveTrustedDevice(Request $request): ?TrustedDevice
    {
        $token = $request->cookie(self::COOKIE_NAME);

        if (! $token) {
            return null;
        }

        return TrustedDevice::with('user')
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->first();
    }

    private function euclideanDistance(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            return PHP_FLOAT_MAX;
        }

        $sum = 0.0;

        foreach ($a as $i => $value) {
            $sum += ($value - $b[$i]) ** 2;
        }

        return sqrt($sum);
    }

    private function makeCookie(string $token, bool $secure): \Symfony\Component\HttpFoundation\Cookie
    {
        return Cookie::make(self::COOKIE_NAME, $token, self::COOKIE_DAYS * 24 * 60, null, null, $secure, true, false, 'lax');
    }
}
