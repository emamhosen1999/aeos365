<?php

declare(strict_types=1);

namespace Aero\Platform\Tests\Feature\Console;

use Aero\Platform\Database\Seeders\DemoStorySeeder;
use Aero\Platform\Tests\TestCase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Seeder;

class DemoResetCommandTest extends TestCase
{
    public function test_runs_clean_with_no_demo_tenants(): void
    {
        $this->artisan('demo:reset')
            ->expectsOutputToContain('No demo tenants')
            ->assertExitCode(0);
    }

    public function test_is_scheduled_every_six_hours(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command ?? '', 'demo:reset'));

        $this->assertNotNull($event, 'demo:reset is not scheduled');
        $this->assertSame('0 */6 * * *', $event->expression);
    }

    public function test_story_seeder_skips_modules_that_are_not_installed(): void
    {
        $seeder = new class extends DemoStorySeeder
        {
            /** @var array<int, string> */
            public array $called = [];

            protected function stories(): array
            {
                return [
                    SpyDemoStorySeeder::class,
                    'Aero\\NotInstalled\\Database\\Seeders\\GhostDemoStorySeeder',
                ];
            }

            public function call($class, $silent = false, array $parameters = [])
            {
                $this->called[] = is_array($class) ? implode(',', $class) : $class;

                return $this;
            }
        };

        $seeder->run();

        $this->assertSame([SpyDemoStorySeeder::class], $seeder->called);
    }
}

/** Stand-in for a module story seeder that IS installed. */
class SpyDemoStorySeeder extends Seeder
{
    public function run(): void {}
}
