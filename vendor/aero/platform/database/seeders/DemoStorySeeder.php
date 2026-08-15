<?php

declare(strict_types=1);

namespace Aero\Platform\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Runs every module's demo story seeder inside the CURRENT tenant context.
 *
 * New modules add themselves to STORIES with one line (Finance in Phase 3 etc.).
 * Each entry is resolved through class_exists() so aero-platform stays decoupled
 * from feature packages: a standalone or partial install that ships without
 * aero-hrm simply seeds nothing instead of fatalling.
 *
 * Transactions are deliberately owned by the individual story seeders — each one
 * wipes and rebuilds its own module and knows its own consistency boundary; a
 * single outer transaction spanning every module would be pointless anyway
 * because MySQL implicitly commits on the TRUNCATE/DDL those seeders use.
 */
class DemoStorySeeder extends Seeder
{
    /** @var array<int, class-string> */
    protected const STORIES = [
        \Aero\HRM\Database\Seeders\HrmDemoStorySeeder::class,
        // \Aero\Finance\Database\Seeders\FinanceDemoStorySeeder::class, // Phase 3
    ];

    public function run(): void
    {
        foreach ($this->stories() as $story) {
            if (class_exists($story)) {
                $this->call($story);
            }
        }
    }

    /**
     * Overridable seam (tests substitute the module list).
     *
     * @return array<int, class-string>
     */
    protected function stories(): array
    {
        return static::STORIES;
    }
}
