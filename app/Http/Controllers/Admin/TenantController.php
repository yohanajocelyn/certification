<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Exceptions\TenantAlreadyDecidedException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin pages to review tenant registrations and accept or reject them after checking the KTP.
 */
class TenantController extends Controller
{
    public function __construct(private readonly TenantService $tenants) {}

    /**
     * List tenants with one status (pending by default), newest registration first.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(UserStatus::class)],
        ]);
        $selectedStatus = UserStatus::tryFrom($filters['status'] ?? '') ?? UserStatus::Pending;

        $overview = $this->tenants->overview($selectedStatus);

        return view('admin.tenants.index', [
            'tenants' => $overview->tenants->withQueryString(),
            'statuses' => UserStatus::cases(),
            'selectedStatus' => $selectedStatus,
            'countsByStatus' => $overview->countsByStatus,
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

        try {
            $decided = UserStatus::from($validated['status']) === UserStatus::Approved
                ? $this->tenants->approve($tenant)
                : $this->tenants->reject($tenant);
        } catch (TenantAlreadyDecidedException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $decision = mb_strtolower($decided->status->label());

        return back()->with('success', "Pendaftaran {$decided->name} {$decision}.");
    }
}
