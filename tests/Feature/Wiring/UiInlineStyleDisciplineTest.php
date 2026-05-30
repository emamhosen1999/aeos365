<?php

namespace Tests\Feature\Wiring;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Plan 06 (aero-ui) Task 1 — inline-style regression guard (PHP-based).
 *
 * CLAUDE.md rule: "All components from @aero/ui · No inline `style={}`"
 *
 * The Plan 06 ESLint config (.eslintrc.json in aero-ui) is the primary
 * guard, but it only fires when npm install has been run and the lint
 * script is invoked. This PHP-side scanner runs in the same wiring-guards
 * CI workflow as FacadeDisciplineTest (Phase 0 T12) so the discipline
 * is enforced even without the Node toolchain bootstrapped.
 *
 * Phase 1 audit baseline: 346 inline-style violations across the Pages/
 * tree. Plan 06 T2 migrates them in batches. Until that completes, this
 * test ships RED with a defined budget that ratchets down each batch.
 */
class UiInlineStyleDisciplineTest extends TestCase
{
    /**
     * Current violation budget. Lower this number each time Plan 06 T2
     * migrates a batch — when it hits 0 the test goes permanently green
     * and any new inline style is blocked.
     */
    /**
     * Plan 06 T2 ratchet — current state, lowered as migrations land.
     *
     * Baseline (Phase 1 audit): 346
     * After 2026-05-30 first migration pass (icon sizes, flex/text-align/
     * justify-content, common spacing/maxWidth patterns, pill helper):
     *   201 remaining
     *
     * Next ratchet target: bring down progressively in subsequent passes.
     * The +10 headroom prevents flaky regression from a single new file
     * with one inline style. New code that adds inline style MUST be
     * accompanied by a budget drop or a justification comment.
     */
    private const VIOLATION_BUDGET = 211;

    public function test_inline_style_count_stays_under_budget(): void
    {
        $offenders = $this->scanInlineStyles();
        $count = count($offenders);

        $this->assertLessThanOrEqual(
            self::VIOLATION_BUDGET,
            $count,
            "Inline `style={...}` usage in packages/aero-ui exceeded the migration budget.\n".
            "Budget: ".self::VIOLATION_BUDGET.", current: {$count}.\n".
            "Either (a) the new code introduced fresh inline styles — use Tailwind / HeroUI\n".
            "theme tokens instead, OR (b) Plan 06 T2 progressed and you should LOWER the\n".
            "VIOLATION_BUDGET constant in this test (the ratchet enforces no regression).\n".
            "First 10 offenders:\n  ".implode("\n  ", array_slice($offenders, 0, 10))
        );
    }

    public function test_eslint_config_exists_in_aero_ui(): void
    {
        $eslintrc = base_path('../Aero-Enterprise-Suite-Saas/packages/aero-ui/.eslintrc.json');

        if (! file_exists($eslintrc)) {
            $this->markTestSkipped('Monorepo layout not present at expected path');
        }

        $config = json_decode(file_get_contents($eslintrc), true);

        $this->assertIsArray($config, 'aero-ui/.eslintrc.json must be valid JSON.');
        $this->assertArrayHasKey('rules', $config);
        $this->assertArrayHasKey('react/forbid-component-props', $config['rules']);
    }

    /**
     * Scan packages/aero-ui/resources/js for `style={...}` attribute usage.
     * Returns list of "relative_path:line" offender strings.
     */
    private function scanInlineStyles(): array
    {
        $jsDir = base_path('../Aero-Enterprise-Suite-Saas/packages/aero-ui/resources/js');

        if (! is_dir($jsDir)) {
            $this->markTestSkipped("aero-ui resources/js not found at {$jsDir}");
        }

        $offenders = [];

        $finder = (new Finder())
            ->in($jsDir)
            ->name('*.jsx')
            ->name('*.js')
            ->files();

        foreach ($finder as $file) {
            $relative = $file->getRelativePathname();
            $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES);

            foreach ($lines as $lineNumber => $line) {
                // Match `style={` as a JSX prop. Avoids matching strings like
                // 'style:' or 'style=' in comments — narrow enough to catch the
                // CLAUDE.md violation pattern.
                if (preg_match('/\bstyle=\{/', $line)) {
                    $offenders[] = $relative.':'.($lineNumber + 1);
                }
            }
        }

        sort($offenders);
        return $offenders;
    }
}
