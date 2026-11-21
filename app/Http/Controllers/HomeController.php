<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Landing page after login: admins go to their garage list, tenants see a welcome page.
 */
class HomeController extends Controller
{
    /**
     * Send the user to the starting page for their role.
     */
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return redirect()->route('admin.garages.index');
        }

        return view('home', ['user' => $user]);
    }
}
