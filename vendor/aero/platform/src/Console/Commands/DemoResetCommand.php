<?php

declare(strict_types=1);

namespace Aero\Platform\Console\Commands;

use Aero\Core\Support\DemoCredentials;
use Aero\Platform\Database\Seeders\DemoStorySeeder;
use Aero\Platform\Database\Seeders\PlatformDemoSeeder;
use Aero\Platform\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * demo:reset — restore the live-demo tenant(s) + landlord demo data to a clean,
 * realistic seeded state so public visitors can freely click/edit and it heals.
 *
 * Strategy (per tenant), most-faithful first:
 *  1. If a pristine SQL snapshot exists (deploy/<subdomain>.sql), re-import it
 *     (restores EXACT state incl. employees/leave/payroll transactional data).
 *  2. Otherwise re-run the module demo STORY seeders (DemoStorySeeder), each of
 *     which wipes and rebuilds its own module's narrative data.
 *
 * Scheduled every 6 hours (Asia/Dhaka) — see AeroPlatformServiceProvider::boot().
 */
class DemoResetCommand extends Command
{
    protected $signature = 'demo:reset {--tenant= : Limit to a single demo tenant subdomain}';

    protected $description = 'Reset live-demo tenant(s) + landlord demo data to seeded state';

    public function handle(): int
    {
        $this->info('🔄 demo:reset starting…');

        // 1. Landlord demo data (idempotent).
        try {
            Artisan::call('db:seed', ['--class' => PlatformDemoSeeder::class, '--force' => true]);
            $this->info('  ✓ landlord demo data reseeded');
        } catch (Throwable $e) {
            $this->error('  ✗ landlord reseed failed: '.$e->getMessage());
        }

        // 2. Each demo tenant.
        $query = Tenant::query()->where('is_demo', true);
        if ($sub = $this->option('tenant')) {
            $query->where('subdomain', $sub);
        }
        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->warn('  No demo tenants (is_demo=1) found.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $this->line("  → {$tenant->subdomain} ({$tenant->id})");
            $snapshot = base_path("deploy/{$tenant->subdomain}.sql");

            try {
                if (File::exists($snapshot)) {
                    $this->restoreSnapshot($tenant, $snapshot);
                    $this->info('    ✓ restored from snapshot');
                } else {
                    $tenant->run(function () {
                        Artisan::call('db:seed', ['--class' => DemoStorySeeder::class, '--force' => true]);
                    });
                    $this->info('    ✓ story reseeded (no snapshot found)');
                }

                // Guardrail: always restore the public demo personas so the
                // exposed logins keep working no matter what a visitor did.
                $this->ensureDemoUsers($tenant);
                $this->info('    ✓ demo persona credentials enforced');
            } catch (Throwable $e) {
                $this->error("    ✗ {$tenant->subdomain} failed: ".$e->getMessage());
            }
        }

        $this->info('✅ demo:reset complete.');

        return self::SUCCESS;
    }

    /**
     * Restore EVERY demo persona's credentials and login-ability inside the
     * tenant DB — the publicly exposed logins must keep working no matter what
     * a visitor did between resets (password change, lock-out, soft-delete).
     *
     * The payload is intersected with the live column list so the guardrail
     * survives schema drift and works in standalone as well as SaaS.
     */
    private function ensureDemoUsers(Tenant $tenant): void
    {
        $tenant->run(function () {
            if (! Schema::hasTable('users')) {
                return;
            }

            $columns = array_flip(Schema::getColumnListing('users'));
            $now = now();

            DB::transaction(function () use ($columns, $now) {
                foreach (DemoCredentials::personas() as $persona) {
                    $values = array_intersect_key([
                        'name' => $persona['name'],
                        'user_name' => $persona['name'],
                        'password' => Hash::make($persona['password']),
                        'active' => true,
                        'is_active' => true,
                        'force_password_reset' => false,
                        'account_locked_at' => null,
                        'locked_reason' => null,
                        'deleted_at' => null,
                        'email_verified_at' => $now,
                        'updated_at' => $now,
                    ], $columns);

                    DB::table('users')->updateOrInsert(['email' => $persona['email']], $values);
                }
            });
        });
    }

    /**
     * Re-import a tenant DB from a pristine SQL snapshot (mysql client).
     */
    private function restoreSnapshot(Tenant $tenant, string $snapshot): void
    {
        $tenant->run(function () use ($snapshot) {
            $conn = DB::connection();
            $db = $conn->getDatabaseName();
            $cfg = $conn->getConfig();

            $cmd = sprintf(
                'mysql -h%s -P%s -u%s %s %s < %s',
                escapeshellarg($cfg['host'] ?? '127.0.0.1'),
                escapeshellarg((string) ($cfg['port'] ?? 3306)),
                escapeshellarg($cfg['username'] ?? 'root'),
                ! empty($cfg['password']) ? '-p'.escapeshellarg($cfg['password']) : '',
                escapeshellarg($db),
                escapeshellarg($snapshot),
            );
            exec($cmd, $out, $code);
            if ($code !== 0) {
                throw new \RuntimeException("mysql import exited {$code}");
            }
        });
    }
}
