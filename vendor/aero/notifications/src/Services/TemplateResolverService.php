<?php

declare(strict_types=1);

namespace Aero\Notifications\Services;

use Aero\Core\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Resolves email templates against the Global Template Library.
 *
 * Every tenant DB carries its own `email_templates` table (dual-mode via
 * Aero\Core\Models\EmailTemplate::TenantModel). Two kinds of rows can live
 * in that same table:
 *   - "own":    owner_tenant_id is set to the current tenant  -> authored
 *               locally or cloned from a global via cloneGlobalToTenant().
 *   - "global": is_global = true, owner_tenant_id is null     -> a
 *               platform-curated default synced into every tenant DB.
 *
 * A tenant's own copy of a slug always wins over the global default for
 * that same slug. No cross-database joins are performed (CLAUDE.md) — the
 * "global" rows already live inside the current tenant's own database.
 */
class TemplateResolverService
{
    /**
     * Current tenant id, dual-mode-safe. On central/admin or standalone
     * (no stancl tenant() helper / no active tenant) this is null, which
     * correctly means "no tenant-owned rows possible here — globals only".
     */
    private function currentTenantId(): ?string
    {
        return function_exists('tenant') && tenant() ? (string) tenant('id') : null;
    }

    /**
     * Resolve the effective template for a slug: the tenant's own template
     * if it has one, otherwise the platform-curated global default.
     */
    public function resolve(string $slug): ?EmailTemplate
    {
        // Every row already lives in the CURRENT connection's email_templates
        // (tenant DB in tenant context; central in platform; the single DB in
        // standalone) — no cross-database read. A local, non-global row for the
        // slug always wins over the platform-curated global default for it.
        $local = EmailTemplate::query()
            ->where('slug', $slug)
            ->where('is_global', false)
            ->first();

        if ($local !== null) {
            return $local;
        }

        return EmailTemplate::query()
            ->where('slug', $slug)
            ->where('is_global', true)
            ->first();
    }

    /**
     * The tenant-visible template set: the tenant's own templates plus the
     * platform globals, deduped by slug with the tenant's own copy winning.
     *
     * Supported filters:
     *   - search:   matches name or subject (LIKE)
     *   - category: exact match
     */
    public function list(array $filters = []): Collection
    {
        // The visible set is every row in the current connection's table — the
        // local (non-global) ones plus the platform-curated globals — deduped by
        // slug with the local copy winning. In a tenant DB that is the tenant's
        // own templates + any synced globals; on central it is the platform's
        // curated set. No owner_tenant_id filter: pre-library rows (owner null,
        // is_global false) are legitimately local and must stay visible.
        $query = EmailTemplate::query();

        if (! empty($filters['category'])) {
            $query->where('category', (string) $filters['category']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        return $query->get()
            // Local (non-global) rows sort ahead of globals for the same slug,
            // so unique() below keeps the local override.
            ->sortBy(fn (EmailTemplate $template): int => $template->is_global ? 1 : 0)
            ->unique('slug')
            ->values();
    }

    /**
     * Replicate a global (platform-curated) template into an editable,
     * tenant-owned copy. The clone starts inactive and unlocked so the
     * tenant must explicitly review and activate it.
     */
    public function cloneGlobalToTenant(EmailTemplate $global, ?string $tenantId): EmailTemplate
    {
        if (! $global->is_global) {
            throw new InvalidArgumentException('cloneGlobalToTenant() requires a global template (is_global=true).');
        }

        try {
            return DB::transaction(function () use ($global, $tenantId) {
                $clone = $global->replicate();
                $clone->is_global = false;
                $clone->owner_tenant_id = $tenantId;
                $clone->is_locked = false;
                $clone->is_active = false;
                $clone->slug = $this->uniqueSlug($global->slug);
                $clone->save();

                return $clone;
            });
        } catch (Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Slugs are globally unique on the email_templates table (unique index),
     * so a cloned copy of a global needs its own slug even though it now
     * lives alongside the original in the same tenant database.
     */
    private function uniqueSlug(string $baseSlug): string
    {
        $slug = Str::slug($baseSlug).'-copy';
        $attempt = 1;

        while (EmailTemplate::query()->where('slug', $slug)->exists()) {
            $attempt++;
            $slug = Str::slug($baseSlug).'-copy-'.$attempt;
        }

        return $slug;
    }
}
