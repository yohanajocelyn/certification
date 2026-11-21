<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Logs users in and out. Only approved accounts may log in.
 */
class AuthController extends Controller
{
    /**
     * Show the login form.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Log in with email and password. Pending and rejected tenants get their own message.
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::validate($credentials)) {
            return $this->failedLogin(__('auth.failed'));
        }

        $user = Auth::getLastAttempted();

        if (! $user->isApproved()) {
            return $this->failedLogin(__('auth.status.'.$user->status->value));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    /**
     * Log out and end the session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Back to the login form with the email kept and an error under it.
     */
    private function failedLogin(string $message): RedirectResponse
    {
        return back()->withErrors(['email' => $message])->onlyInput('email');
    }
}
