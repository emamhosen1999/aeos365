<?php

namespace Aero\Auth\Http\Controllers\Auth;

use Aero\Auth\Contracts\AuthContext;
use Aero\Auth\Http\Controllers\Controller;
use Aero\Core\Support\DemoCredentials;
use Aero\Kernel\Support\SafeRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        // On the demo tenant, expose the persona credentials so a visitor arriving
        // from the marketing "See Demo" CTA can enter with one click — as either
        // the admin (full product) or an employee (self-service view).
        $isDemo = function_exists('tenant') && tenant() && (bool) tenant('is_demo');

        return Inertia::render('Auth/Login', [
            'canResetPassword' => true,
            'status' => session('status'),
            'demo' => $isDemo ? ['personas' => $this->demoPersonas()] : null,
        ]);
    }

    /**
     * Login-page persona chips for the demo tenant (admin first).
     *
     * aero-auth declares no composer dependency on aero/core, so the canonical
     * DemoCredentials source is used when present and the same config keys are
     * read directly otherwise.
     *
     * @return array<int, array{label: string, name: string|null, role: string|null, email: string, password: string}>
     */
    protected function demoPersonas(): array
    {
        $personas = class_exists(DemoCredentials::class)
            ? DemoCredentials::personas()
            : [[
                'label' => 'Admin',
                'email' => config('aero.demo.email', 'admin@democorp.com'),
                'password' => config('aero.demo.password', 'Aeos365!Admin'),
            ]];

        return array_values(array_map(fn (array $p) => [
            'label' => $p['label'],
            'name' => $p['name'] ?? null,
            'role' => $p['role'] ?? null,
            'email' => $p['email'],
            'password' => $p['password'],
        ], $personas));
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(Request $request, AuthContext $context): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Resolve guard + post-login target from the active AuthContext. aero-auth
        // stays mode-agnostic: standalone/tenant binds TenantAuthContext (web ->
        // core.dashboard); the SaaS host rebinds LandlordAuthContext on the admin
        // domain (landlord -> admin.dashboard). No mode/guard branching here.
        if (! Auth::guard($context->guard())->attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        return SafeRedirect::intended($context->dashboardRoute(), true);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request, AuthContext $context): RedirectResponse
    {
        Auth::guard($context->guard())->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return SafeRedirect::toRoute($context->loginRoute(), [], 'login');
    }
}
