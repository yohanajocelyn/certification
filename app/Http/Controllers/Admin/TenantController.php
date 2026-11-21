<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin pages to review tenant registrations and accept or reject them after checking the KTP.
 */
class TenantController extends Controller
{
    private const TENANTS_PER_PAGE = 15;

    /**
     * List tenants with one status (pending by default), newest registration first.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(UserStatus::class)],
        ]);
        $selectedStatus = UserStatus::tryFrom($filters['status'] ?? '') ?? UserStatus::Pending;

        $tenants = User::tenants()
            ->where('status', $selectedStatus)
            ->latest()
            ->paginate(self::TENANTS_PER_PAGE)
            ->withQueryString();

        $countsByStatus = User::tenants()
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.tenants.index', [
            'tenants' => $tenants,
            'statuses' => UserStatus::cases(),
            'selectedStatus' => $selectedStatus,
            'countsByStatus' => $countsByStatus,
        ]);
    }

    /**
     * Accept or reject a pending registration. Decided registrations cannot be changed.
     */
    public function update(Request $request, User $tenant): RedirectResponse
    {
        // Admin accounts are not registrations, so they do not exist on this page.
        abort_if($tenant->isAdmin(), 404);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(UserStatus::class)->only([UserStatus::Approved, UserStatus::Rejected])],
        ]);

        if (! $tenant->isPending()) {
            return back()->with('error', "Pendaftaran {$tenant->name} sudah diputuskan sebelumnya.");
        }

        $tenant->update(['status' => $validated['status']]);
        $decision = mb_strtolower($tenant->status->label());

        return back()->with('success', "Pendaftaran {$tenant->name} {$decision}.");
    }
}
