<?php

declare(strict_types=1);

namespace Aero\Platform\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Central per-(tenant, date, channel) notification deliverability summary,
 * written by RollUpNotificationDeliverabilityJob and read exclusively by
 * FleetDeliverabilityService for the platform "Fleet" observability tab.
 *
 * NEVER queried per-request against tenant DBs — this table IS the fleet view.
 */
class NotificationFleetRollup extends CentralModel
{
    protected $connection = 'central';

    protected $table = 'notification_fleet_rollups';

    protected $fillable = [
        'tenant_id',
        'date',
        'channel',
        'sent',
        'delivered',
        'failed',
        'bounced',
        'suppressed',
    ];

    protected $casts = [
        'date' => 'date',
        'sent' => 'integer',
        'delivered' => 'integer',
        'failed' => 'integer',
        'bounced' => 'integer',
        'suppressed' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
