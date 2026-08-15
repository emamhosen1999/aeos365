<?php

declare(strict_types=1);

namespace Aero\Platform\Services\Quotas;

/**
 * The canonical quota resource registry.
 *
 * Before this existed, every layer invented its own resource vocabulary — the
 * override UI offered storage_gb/api_calls/users/modules/ai_messages, the
 * enforcement gate spoke api_calls_monthly, the legacy settings rows spoke
 * employees/projects, and QuotaAdminService mapped resources onto a
 * `tenant_stats.storage_mb` column that does not exist. This class is the one
 * place that says what a resource IS: its label, unit, where usage comes from,
 * and which plan keys grant it.
 *
 * `stat` is the tenant_stats column holding usage; `divisor` converts that
 * column into the resource's unit (storage is stored in MB, quoted in GB —
 * the old code compared a GB limit against an MB usage, off by 1024x).
 */
final class QuotaResources
{
    /**
     * @var array<string, array{label:string,unit:string,stat:?string,divisor:int,plan_key:string,plan_col:?string,monthly:bool,cache_key:?string,decimals:int}>
     */
    public const ALL = [
        'users' => [
            'label' => 'Users', 'unit' => 'seats', 'stat' => 'total_users', 'divisor' => 1,
            'plan_key' => 'max_users', 'plan_col' => 'max_users', 'monthly' => false, 'cache_key' => null, 'decimals' => 0,
        ],
        'storage_gb' => [
            'label' => 'Storage', 'unit' => 'GB', 'stat' => 'storage_used_mb', 'divisor' => 1024,
            'plan_key' => 'max_storage_gb', 'plan_col' => 'max_storage_gb', 'monthly' => false, 'cache_key' => null, 'decimals' => 1,
        ],
        'api_calls' => [
            'label' => 'API calls', 'unit' => 'calls/mo', 'stat' => 'api_requests', 'divisor' => 1,
            'plan_key' => 'max_api_calls_monthly', 'plan_col' => null, 'monthly' => true, 'cache_key' => null, 'decimals' => 0,
        ],
        'ai_messages' => [
            'label' => 'AI messages', 'unit' => 'msgs/mo', 'stat' => null, 'divisor' => 1,
            'plan_key' => 'max_ai_messages', 'plan_col' => null, 'monthly' => true, 'cache_key' => 'quota:ai_messages', 'decimals' => 0,
        ],
        'employees' => [
            'label' => 'Employees', 'unit' => 'people', 'stat' => 'total_employees', 'divisor' => 1,
            'plan_key' => 'max_employees', 'plan_col' => null, 'monthly' => false, 'cache_key' => null, 'decimals' => 0,
        ],
        'projects' => [
            'label' => 'Projects', 'unit' => 'projects', 'stat' => 'active_projects', 'divisor' => 1,
            'plan_key' => 'max_projects', 'plan_col' => null, 'monthly' => false, 'cache_key' => null, 'decimals' => 0,
        ],
    ];

    /**
     * Legacy spellings → canonical keys. The enforcement gate and the old
     * settings rows both used `api_calls_monthly`; plan limit keys arrive as
     * `max_*`. Normalising here keeps one vocabulary everywhere.
     */
    private const ALIASES = [
        'api_calls_monthly' => 'api_calls',
        'api' => 'api_calls',
        'storage' => 'storage_gb',
        'ai' => 'ai_messages',
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }

    /** Normalise any known spelling of a resource to its canonical key. */
    public static function canonical(string $resource): string
    {
        $r = strtolower(trim($resource));
        $r = str_starts_with($r, 'max_') ? substr($r, 4) : $r;

        return self::ALIASES[$r] ?? $r;
    }

    public static function exists(string $resource): bool
    {
        return isset(self::ALL[self::canonical($resource)]);
    }

    /** @return array{label:string,unit:string,stat:?string,divisor:int,plan_key:string,plan_col:?string,monthly:bool,cache_key:?string,decimals:int}|null */
    public static function meta(string $resource): ?array
    {
        return self::ALL[self::canonical($resource)] ?? null;
    }

    public static function label(string $resource): string
    {
        return self::meta($resource)['label'] ?? $resource;
    }

    public static function unit(string $resource): string
    {
        return self::meta($resource)['unit'] ?? '';
    }

    /**
     * Convert a raw tenant_stats column value into the resource's own unit.
     *
     * Accepts strings because MySQL returns SUM()/DECIMAL aggregates as
     * strings through PDO.
     */
    public static function toUnit(string $resource, float|int|string|null $raw): float
    {
        $meta = self::meta($resource);
        $divisor = max(1, (int) ($meta['divisor'] ?? 1));
        $value = (float) ($raw ?? 0) / $divisor;

        return round($value, (int) ($meta['decimals'] ?? 0));
    }

    /** Resources metered per calendar month (usage resets on the 1st). */
    public static function isMonthly(string $resource): bool
    {
        return (bool) (self::meta($resource)['monthly'] ?? false);
    }

    /** Shape the registry for the front end. */
    public static function forFrontend(): array
    {
        return array_map(static fn (array $m, string $k): array => [
            'key' => $k,
            'label' => $m['label'],
            'unit' => $m['unit'],
            'monthly' => $m['monthly'],
            'decimals' => $m['decimals'],
        ], self::ALL, array_keys(self::ALL));
    }
}
