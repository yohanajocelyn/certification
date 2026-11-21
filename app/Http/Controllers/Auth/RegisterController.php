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
            'nik' => ['required', 'digits:'.User::NIK_LENGTH, 'unique:users,nik'],
            'name' => ['required', 'string', 'max:'.User::NAME_MAX_LENGTH],
            'phone' => ['required', 'string', 'max:'.User::PHONE_MAX_LENGTH, 'regex:'.User::PHONE_PATTERN],
            'email' => ['required', 'string', 'email', 'max:'.User::EMAIL_MAX_LENGTH, 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(User::PASSWORD_MIN_LENGTH)],
        ]);

        $this->tenants->register($validated);

        return redirect()->route('login')
            ->with('success', 'Pendaftaran berhasil. Akun Anda menunggu verifikasi admin sebelum bisa digunakan.');
    }
}
