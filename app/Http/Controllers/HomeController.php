<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Landing page after login.
 */
class HomeController extends Controller
{
    /**
     * Show the welcome page for the logged-in user.
     */
    public function __invoke(Request $request): View
    {
        return view('home', ['user' => $request->user()]);
    }
}
