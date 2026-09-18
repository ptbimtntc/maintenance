<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Placeholder for the LOTOTO (Lock Out Tag Out) module under the Safety
 * menu. Scaffolded now (route, permissions, sidebar entry) so the menu
 * structure is in place; the actual feature is built out separately.
 */
class LototoController extends Controller
{
    public function index(): View
    {
        return view('safety.lototo.index');
    }
}
