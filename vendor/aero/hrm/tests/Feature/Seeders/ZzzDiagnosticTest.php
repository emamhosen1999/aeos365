<?php

namespace Aero\HRM\Tests\Feature\Seeders;

use Aero\HRM\Models\Asset;
use Aero\HRM\Models\AssetAllocation;
use Aero\HRM\Models\ExpenseClaim;
use Aero\HRM\Models\Job;
use Aero\HRM\Models\JobApplication;
use Aero\HRM\Models\PerformanceReview;
use Aero\HRM\Tests\TestCase;

class ZzzDiagnosticTest extends TestCase
{
    public function test_factory_resolution(): void
    {
        $classes = [
            ExpenseClaim::class,
            Asset::class,
            AssetAllocation::class,
            Job::class,
            JobApplication::class,
            PerformanceReview::class,
        ];

        foreach ($classes as $class) {
            try {
                $factory = $class::factory();
                fwrite(STDERR, "$class => OK (".get_class($factory).")\n");
            } catch (\Throwable $e) {
                fwrite(STDERR, "$class => FAIL: ".$e->getMessage()."\n");
            }
        }

        $this->assertTrue(true);
    }
}
