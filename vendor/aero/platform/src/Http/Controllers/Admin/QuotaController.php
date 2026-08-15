<?php

declare(strict_types=1);

namespace Aero\Platform\Http\Controllers\Admin;

use Aero\Platform\Http\Controllers\Controller;
use Aero\Platform\Models\QuotaEnforcementSetting;
use Aero\Platform\Models\QuotaWarning;
use Aero\Platform\Models\Tenant;
use Aero\Platform\Models\TenantQuotaOverride;
use Aero\Platform\Services\Quotas\QuotaResources;
use Aero\Platform\Services\QuotaAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class QuotaController extends Controller
{
    public function __construct(private QuotaAdminService $svc) {}

    /**
     * The Quota command centre — fleet utilisation, overrides, warnings and
     * enforcement policy in one console.
     */
    public function index(): Response
    {
        return Inertia::render('Platform/Admin/Quotas/P2/Quotas', [
            'overview' => $this->svc->overview(),
        ]);
    }

    /**
     * The console subsumed the standalone Enforcement Settings screen
     * (now the Policies tab).
     */
    public function settings(): RedirectResponse
    {
        return redirect('/quotas');
    }

    /* ---------------- overrides ---------------- */

    public function override(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'resource' => ['required', 'string', 'max:64', Rule::in(QuotaResources::keys())],
            'limit_value' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $override = $this->svc->setOverride(
            $tenant,
            $data['resource'],
            (int) $data['limit_value'],
            $data['reason'] ?? null,
            $data['expires_at'] ?? null
        );

        return response()->json(['override' => $override]);
    }

    public function removeOverride(Tenant $tenant, string $resource): JsonResponse
    {
        $this->svc->removeOverride($tenant, $resource);

        return response()->json(['removed' => true]);
    }

    public function extendOverride(Request $request, int $override): JsonResponse
    {
        $data = $request->validate([
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $model = TenantQuotaOverride::findOrFail($override);

        return response()->json([
            'override' => $this->svc->extendOverride($model, $data['expires_at'] ?? null),
        ]);
    }

    public function clearExpiredOverrides(): JsonResponse
    {
        $count = $this->svc->clearExpiredOverrides();

        return response()->json([
            'cleared' => $count,
            'message' => $count === 0
                ? 'No expired overrides to clear.'
                : "Cleared {$count} expired override".($count === 1 ? '.' : 's.'),
        ]);
    }

    /* ---------------- policies ---------------- */

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'resource' => ['required', 'string', 'max:64', Rule::in(QuotaResources::keys())],
            'default_limit' => ['required', 'integer', 'min:0'],
            'warning_threshold_pct' => ['nullable', 'integer', 'between:1,100'],
            'hard_limit_pct' => ['nullable', 'integer', 'between:1,200'],
            'action' => ['required', Rule::in([
                QuotaEnforcementSetting::ACTION_WARN,
                QuotaEnforcementSetting::ACTION_THROTTLE,
                QuotaEnforcementSetting::ACTION_BLOCK,
            ])],
        ]);

        if (($data['warning_threshold_pct'] ?? 80) >= ($data['hard_limit_pct'] ?? 100)) {
            return response()->json(['message' => 'The warning threshold must be below the hard limit.'], 422);
        }

        $setting = $this->svc->updateSettings($data['resource'], $data);

        return response()->json(['policy' => $setting]);
    }

    public function deletePolicy(int $policy): JsonResponse
    {
        $this->svc->deletePolicy(QuotaEnforcementSetting::findOrFail($policy));

        return response()->json(['deleted' => true]);
    }

    /** Impact preview shown in the policy editor before saving. */
    public function previewPolicy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'resource' => ['required', 'string', Rule::in(QuotaResources::keys())],
            'default_limit' => ['required', 'integer', 'min:0'],
            'warning_threshold_pct' => ['required', 'integer', 'between:1,100'],
            'hard_limit_pct' => ['required', 'integer', 'between:1,200'],
        ]);

        return response()->json($this->svc->previewPolicy(
            $data['resource'],
            (int) $data['default_limit'],
            (int) $data['warning_threshold_pct'],
            (int) $data['hard_limit_pct'],
        ));
    }

    /* ---------------- warnings ---------------- */

    public function scanBreaches(): JsonResponse
    {
        $result = $this->svc->scanBreaches();

        return response()->json($result + [
            'message' => "Scanned {$result['scanned']} rows — {$result['raised']} warning(s) raised, {$result['cleared']} cleared.",
        ]);
    }

    public function dismissWarning(int $warning): JsonResponse
    {
        return response()->json([
            'warning' => $this->svc->dismissWarning(QuotaWarning::findOrFail($warning)),
        ]);
    }

    public function reopenWarning(int $warning): JsonResponse
    {
        return response()->json([
            'warning' => $this->svc->reopenWarning(QuotaWarning::findOrFail($warning)),
        ]);
    }

    public function dismissWarnings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $count = $this->svc->dismissWarnings($data['ids']);

        return response()->json([
            'dismissed' => $count,
            'message' => "Dismissed {$count} warning".($count === 1 ? '.' : 's.'),
        ]);
    }
}
