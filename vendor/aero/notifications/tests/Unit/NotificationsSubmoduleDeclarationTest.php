<?php

declare(strict_types=1);

namespace Aero\Notifications\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Plan 08 (aero-notifications) Task 1 — submodule declaration regression pin.
 *
 * Phase 1 audit found aero-notifications had ZERO submodule declarations
 * despite implementing a substantial surface (Email Engine, SMS Engine,
 * Push Engine, In-App Inbox, User Preferences, Global Settings).
 *
 * Without declarations, modules:sync wrote NOTHING for notifications,
 * HRMAC had no permissions to enforce, and the sidebar registry showed
 * nothing — the admin email/SMS/push pages were unreachable through
 * normal navigation.
 *
 * Also resolves the aero-core Task 12 ownership question — email_engine
 * was previously declared in aero-core/config/module.php where no
 * controllers exist for it; the canonical home is here.
 */
class NotificationsSubmoduleDeclarationTest extends TestCase
{
    private function config(): array
    {
        return require dirname(__DIR__, 2).'/config/module.php';
    }

    public function test_submodules_array_is_declared(): void
    {
        $config = $this->config();

        $this->assertArrayHasKey('submodules', $config,
            'aero-notifications/config/module.php MUST declare submodules — without ".
            "them modules:sync silently writes nothing.');

        $this->assertNotEmpty($config['submodules']);
    }

    public function test_email_engine_submodule_declared(): void
    {
        $codes = array_column($this->config()['submodules'], 'code');

        $this->assertContains('email_engine', $codes,
            'email_engine submodule MUST be declared here (moved from aero-core ".
            "Task 12). It is the canonical home for the email infrastructure ".
            "(EmailTemplateController, EmailLogController, SuppressionController, ".
            "DeliverabilityController, BounceController).');
    }

    /**
     * A submodule component becomes a sidebar link AND an HRMAC permission set.
     * sms_engine/push_engine were declared with gateway/log/topic PAGES that have
     * no controller, no route and no JSX behind them — five phantom nav links that
     * 404'd. SMS and push are real as *channels* (services + pipeline adapters) and
     * are configured through the `settings` → Channels tab, which exists.
     *
     * Re-declare them only when the pages are actually built.
     */
    public function test_phantom_engine_submodules_are_not_declared(): void
    {
        $codes = array_column($this->config()['submodules'], 'code');

        $this->assertNotContains('sms_engine', $codes,
            'sms_engine declares gateway/log/template PAGES that do not exist — '.
            'it produces dead sidebar links. Configure SMS via the Channels tab.');

        $this->assertNotContains('push_engine', $codes,
            'push_engine declares FCM-config/topic PAGES that do not exist — '.
            'it produces dead sidebar links. Configure push via the Channels tab.');
    }

    /** Every declared component must be a real tab of the command centre. */
    public function test_every_declared_component_maps_to_a_real_tab(): void
    {
        $tabs = ['inbox', 'log', 'bounces', 'suppression', 'deliverability', 'templates', 'channels', 'preferences'];

        foreach ($this->config()['submodules'] as $submodule) {
            foreach ($submodule['components'] as $component) {
                $route = $component['route'] ?? '';
                $tab = str_contains($route, 'tab=') ? explode('tab=', $route)[1] : null;

                $this->assertContains($tab, $tabs,
                    "Component '{$submodule['code']}.{$component['code']}' points at '{$route}', ".
                    'which is not a tab of the notifications command centre. A component with no '.
                    'page behind it becomes a dead sidebar link.');
            }
        }
    }

    public function test_channels_submodule_covers_sms_and_push_config(): void
    {
        $settings = collect($this->config()['submodules'])->firstWhere('code', 'settings');
        $channels = collect($settings['components'])->firstWhere('code', 'channels');
        $actions = array_column($channels['actions'], 'code');

        foreach (['view', 'configure', 'test'] as $required) {
            $this->assertContains($required, $actions,
                "Channels must expose '{$required}' — it is where email, SMS, push and ".
                'in-app are enabled, credentialed and test-fired.');
        }
    }

    public function test_in_app_inbox_declared(): void
    {
        $codes = array_column($this->config()['submodules'], 'code');
        $this->assertContains('in_app', $codes,
            'in_app submodule MUST be declared for the user-facing notification inbox.');
    }

    public function test_preferences_declared(): void
    {
        $codes = array_column($this->config()['submodules'], 'code');
        $this->assertContains('preferences', $codes,
            'preferences submodule MUST be declared (UserNotificationPreference + ".
            "NotificationPreferenceController).');
    }

    public function test_settings_declared(): void
    {
        $codes = array_column($this->config()['submodules'], 'code');
        $this->assertContains('settings', $codes,
            'settings submodule MUST be declared (NotificationSettingController + ".
            "tenant-level NotificationSetting model).');
    }

    public function test_email_engine_has_required_components(): void
    {
        $emailEngine = collect($this->config()['submodules'])->firstWhere('code', 'email_engine');
        $componentCodes = array_column($emailEngine['components'], 'code');

        foreach (['templates', 'logs', 'suppression_list', 'deliverability', 'bounces'] as $required) {
            $this->assertContains($required, $componentCodes,
                "email_engine must include '{$required}' component — matches ".
                "the controllers shipped in src/Http/Controllers/Admin/ and ".
                "the previous aero-core declaration this fix is moving here.");
        }
    }
}
