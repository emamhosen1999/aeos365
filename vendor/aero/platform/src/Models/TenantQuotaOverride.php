<?php

declare(strict_types=1);

namespace Aero\Platform\Models;

use Aero\Auth\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantQuotaOverride extends CentralModel
{
    use HasFactory;

    protected $connection = 'central';

    protected $table = 'tenant_quota_overrides';

    protected $fillable = [
        'tenant_id',
        'resource',
        'limit_value',
        'reason',
        'expires_at',
        'set_by',
    ];

    protected function casts(): array
    {
        return [
            'limit_value' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * The platform staffer who set the override.
     *
     * set_by is FK'd to the `users` table, whose model is Aero\Auth\Models\User
     * (the landlord_users auth provider). This previously pointed at a relative
     * `User::class` resolving to Aero\Platform\Models\User — a class that has
     * never existed — so eager-loading `setter` fatalled the moment the table
     * had a single row.
     */
    public function setter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /** Overrides that have not expired. */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopeExpired(Builder $q): Builder
    {
        return $q->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }

    /**
     * The single source of truth for a tenant's effective override on a
     * resource: the newest non-expired row, or null.
     */
    public static function activeFor(string $tenantId, string $resource): ?self
    {
        return static::query()
            ->where('tenant_id', $tenantId)
            ->where('resource', $resource)
            ->active()
            ->orderByDesc('id')
            ->first();
    }
}
