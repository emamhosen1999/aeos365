<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_create_system_settings_table(): void
    {
        $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
        $tableNames = array_map(fn ($t) => $t->name, $tables);
        $this->assertContains('system_settings', $tableNames, 'Tables: '.implode(', ', $tableNames));
    }
}
