<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Registers new tenants. They stay pending until an admin verifies their KTP.
 */
class RegisterController extends Controller
{
    public function __construct(private readonly TenantService $tenants) {}

    /**
     * Show the tenant registration form.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Validate and register a new pending tenant; role and status always come from the defaults.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nik' => ['required', 'digits:'.config('rules.user.nik_length'), 'unique:users,nik'],
            'name' => ['required', 'string', 'max:'.config('rules.user.name_max_length')],
            'phone' => ['required', 'string', 'max:'.config('rules.user.phone_max_length'), 'regex:'.User::PHONE_PATTERN],
            'email' => ['required', 'string', 'email', 'max:'.config('rules.user.email_max_length'), 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(config('rules.user.password_min_length'))],
        ]);

        $this->tenants->register($validated);

        return redirect()->route('login')
            ->with('success', 'Pendaftaran berhasil. Akun Anda menunggu verifikasi admin sebelum bisa digunakan.');
    }
}
