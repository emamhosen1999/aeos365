<?php

declare(strict_types=1);

namespace Aero\Platform\Services;

use Aero\Auth\Models\User;
use Aero\Contracts\AuditServiceInterface;
use Aero\Core\Models\Announcement;
use Aero\Platform\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fans a platform-authored broadcast out to tenants as an in-app Announcement.
 *
 * Stateless by design — there is no broadcast-history table. Each call
 * creates one Announcement row per target tenant, inside that tenant's own
 * database; the tenant dashboard's existing Announcement widget then renders
 * it exactly like a locally authored notice. The only record of "a broadcast
 * happened" is the set of Announcement rows it created plus the single
 * platform-side audit entry this service writes once the fan-out finishes —
 * nothing further is persisted centrally.
 *
 * Model choice: writes through Aero\Core\Models\Announcement rather than
 * pulling in aero-hrm's copy, to avoid adding a new aero-platform -> aero-hrm
 * dependency edge for a single write. The live `announcements` table (created
 * by aero-hrm's tenant migration) does not match this model's own $fillable
 * list — real columns are title/content/type/priority/published_at/
 * expires_at/created_by/department_id/is_pinned, but $fillable also lists
 * phantom body/status/audience columns that do not exist (see the model's own
 * class docblock — tracked as cross-package finding C-4). Mass-assignment
 * through create()/fill() would throw "Unknown column" for those. This
 * service instead sets only the real, live columns directly on a new model
 * instance and calls save() — $fillable only guards mass-assignment, not
 * direct property assignment, so this stays correct regardless of that stale
 * list.
 *
 * Author handling: `announcements.created_by` is a plain unsignedBigInteger
 * FK into the TARGET TENANT's own local `users` table — it is NOT a
 * polymorphic (author_type/author_id) column, so there is no morph type to
 * derive via getMorphClass() here (the "never hardcode the User FQN in a
 * polymorphic column" rule has no column to apply to on this table). The
 * platform staffer issuing the broadcast has no row in any given tenant's
 * `users` table (central platform staff and tenant users are logically
 * distinct rows, even where they share the same Aero\Auth\Models\User class
 * across connections) — writing their central id into a tenant's created_by
 * FK would silently misattribute the announcement to an unrelated (or
 * non-existent) tenant user, which is worse than leaving it unset.
 * `created_by` is therefore always left null, and the acting platform user's
 * name is appended to the announcement body as a signature line instead so
 * tenants still see who it came from.
 */
class TenantBroadcastService
{
    /** @var list<string> */
    private const TYPES = ['info', 'warning', 'success', 'danger'];

    /** @var list<string> */
    private const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public function __construct(private readonly AuditServiceInterface $audit) {}

    /**
     * Create the announcement in every target tenant.
     *
     * @param  array{title?:string,body?:string,content?:string,type?:string,priority?:string,is_pinned?:bool,expires_at?:string|null}  $data
     * @param  list<string>  $tenantIds  Explicit tenant ids to target. Empty = every active tenant.
     * @return int Number of tenants the announcement was successfully created in.
     */
    public function broadcast(array $data, array $tenantIds = []): int
    {
        $title = trim((string) ($data['title'] ?? ''));
        $body = trim((string) ($data['body'] ?? $data['content'] ?? ''));

        if ($title === '' || $body === '') {
            throw new \InvalidArgumentException('A broadcast requires both a title and a body.');
        }

        $type = in_array($data['type'] ?? null, self::TYPES, true) ? $data['type'] : 'info';
        $priority = in_array($data['priority'] ?? null, self::PRIORITIES, true) ? $data['priority'] : 'normal';
        $isPinned = (bool) ($data['is_pinned'] ?? false);
        $expiresAt = $data['expires_at'] ?? null;

        /** @var User|null $actor */
        $actor = Auth::guard('landlord')->user();
        $content = $this->withSignature($body, $actor);

        $targets = $this->resolveTargets($tenantIds);
        $created = 0;
        $failedTenantIds = [];

        foreach ($targets as $target) {
            try {
                tenancy()->initialize($target);

                DB::transaction(function () use ($title, $content, $type, $priority, $isPinned, $expiresAt): void {
                    $announcement = new Announcement;
                    $announcement->title = $title;
                    $announcement->content = $content;
                    $announcement->type = $type;
                    $announcement->priority = $priority;
                    $announcement->is_pinned = $isPinned;
                    $announcement->published_at = now();
                    $announcement->expires_at = $expiresAt;
                    // created_by intentionally left null — see class docblock.
                    $announcement->save();
                });

                $created++;
            } catch (Throwable $e) {
                $failedTenantIds[] = $target->id;
                Log::error('TenantBroadcastService: failed to broadcast to tenant', [
                    'tenant_id' => $target->id,
                    'error' => $e->getMessage(),
                ]);
            } finally {
                if (function_exists('tenancy') && tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        // One platform-side audit entry for the whole fan-out (the business
        // action is "a platform admin issued a broadcast", not N per-tenant
        // actions) — written after tenancy()->end() so it lands in the
        // central/platform audit log, not a tenant's.
        try {
            $this->audit->log(
                event: 'platform.broadcast.sent',
                action: 'broadcast',
                subject: null,
                description: sprintf('Platform broadcast "%s" sent to %d of %d tenant(s).', $title, $created, $targets->count()),
                metadata: [
                    'title' => $title,
                    'type' => $type,
                    'priority' => $priority,
                    'requested_tenant_ids' => $tenantIds,
                    'targeted' => $targets->count(),
                    'created' => $created,
                    'failed_tenant_ids' => $failedTenantIds,
                ],
            );
        } catch (Throwable) {
            // audit failure must never break the fan-out result
        }

        return $created;
    }

    /**
     * Append the acting platform user's name to the body as a signature line.
     * No actor in context (e.g. console/queue) -> body is left unchanged.
     */
    private function withSignature(string $body, ?User $actor): string
    {
        if (! $actor) {
            return $body;
        }

        return $body."\n\n— {$actor->name}, Aeos365 Platform Team";
    }

    /**
     * @param  list<string>  $tenantIds
     * @return Collection<int, Tenant>
     */
    private function resolveTargets(array $tenantIds): Collection
    {
        if ($tenantIds !== []) {
            // An explicit selection is respected as-is (e.g. notifying a
            // suspended tenant of its own suspension), not filtered to active.
            return Tenant::query()->whereIn('id', $tenantIds)->get();
        }

        return Tenant::query()->active()->get();
    }
}
