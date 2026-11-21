<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Landing route after login: sends each role to its starting page.
 */
class HomeController extends Controller
{
    /**
     * Admins start at the garage list, tenants at their own rentals.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $startRoute = $request->user()->isAdmin() ? 'admin.garages.index' : 'rentals.index';

        return redirect()->route($startRoute);
    }
}
