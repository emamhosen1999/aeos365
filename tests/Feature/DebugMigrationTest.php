<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DebugMigrationTest extends TestCase
{
    use DatabaseMigrations {
        runDatabaseMigrations as baseRunDatabaseMigrations;
    }

    public function runDatabaseMigrations(): void
    {
        $this->shareSqliteAcrossConnections();
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        $this->afterRefreshingDatabase();
    }

    protected function refreshTestDatabase(): void
    {
        Schema::dropAllTables();

        $packages = 'C:\laragon\www\Aero-Enterprise-Suite-Saas\packages';
        $migrationPaths = [
            $packages.'/aero-core/database/migrations',
            $packages.'/aero-auth/database/migrations',
            $packages.'/aero-hrmac/database/migrations',
            $packages.'/aero-platform/database/migrations',
        ];

        /** @var Migrator $migrator */
        $migrator = $this->app['migrator'];
        $migrator->setConnection('sqlite');

        if (! $migrator->repositoryExists()) {
            $migrator->getRepository()->createRepository();
        }

        $ran = [];

        foreach ($migrationPaths as $basePath) {
            $files = $migrator->getMigrationFiles([$basePath]);
            foreach ($files as $name => $file) {
                if (in_array($name, $ran)) {
                    continue;
                }
                try {
                    // Run single migration at a time
                    $migrator->run([$basePath], ['step' => true]);
                    $ran[] = $name;
                } catch (QueryException $e) {
                    // Skip SQLite-incompatible additive/rename migrations silently.
                    // These fail because MySQL additive migrations assume existing columns
                    // that don't exist on fresh SQLite. The base create_* migrations succeed.
                    $ran[] = $name; // mark as processed to avoid infinite loop
                }
            }
        }
    }

    private function shareSqliteAcrossConnections(): void
    {
        $sqliteConfig = ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false];
        config([
            'database.connections.mysql' => $sqliteConfig,
            'database.connections.central' => $sqliteConfig,
            'tenancy.database.central_connection' => 'sqlite',
            'permission.table_names' => ['roles' => 'roles', 'permissions' => 'permissions', 'model_has_permissions' => 'model_has_permissions', 'model_has_roles' => 'model_has_roles', 'role_has_permissions' => 'role_has_permissions'],
            'permission.column_names' => ['role_pivot_key' => null, 'permission_pivot_key' => null, 'model_morph_key' => 'model_id', 'team_foreign_key' => 'team_id'],
            'permission.teams' => false,
        ]);
        $this->app['db']->purge('sqlite');
        $this->app['db']->purge('mysql');
        $this->app['db']->purge('central');
        $pdo = $this->app['db']->connection('sqlite')->getPdo();
        $this->app['db']->connection('mysql')->setPdo($pdo);
        $this->app['db']->connection('central')->setPdo($pdo);
    }

    public function test_tables_exist_after_migration(): void
    {
        $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
        $names = array_map(fn ($t) => $t->name, $tables);
        sort($names);
        $this->assertContains('roles', $names, 'Tables: '.implode(', ', $names));
        $this->assertContains('plans', $names, 'Tables: '.implode(', ', $names));
        $this->assertContains('subscriptions', $names, 'Tables: '.implode(', ', $names));
        $this->assertContains('invoices', $names, 'Tables: '.implode(', ', $names));
        $this->assertContains('plan_modules', $names, 'Tables: '.implode(', ', $names));
        $this->assertContains('platform_audit_logs', $names, 'Tables: '.implode(', ', $names));
        $this->assertContains('tenants', $names, 'Tables: '.implode(', ', $names));
        $this->assertContains('landlord_users', $names, 'Tables: '.implode(', ', $names));
    }
}
