<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    /**
     * A small, focused settings form for the handful of values the app
     * actually reads (see Setting::DEFAULTS) - not a generic key/value
     * editor, since inventing arbitrary settings fields wouldn't reflect
     * a real requirement.
     */
    public function edit(): View
    {
        return view('settings.edit', [
            'certificateExpiringSoonDays' => Setting::getInt('certificate_expiring_soon_days', 60),
            'trainingReminderDaysBefore' => Setting::getInt('training_reminder_days_before', 7),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'certificate_expiring_soon_days' => ['required', 'integer', 'min:1', 'max:365'],
            'training_reminder_days_before' => ['required', 'integer', 'min:1', 'max:60'],
        ]);

        foreach ($data as $key => $value) {
            Setting::set($key, (string) $value);
        }

        return redirect()->route('settings.edit')->with('status', 'Settings updated.');
    }
}
