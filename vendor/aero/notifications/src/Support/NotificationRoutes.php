<?php

declare(strict_types=1);

namespace Aero\Notifications\Support;

use Aero\Notifications\Http\Controllers\NotificationCenterController as CC;
use Illuminate\Support\Facades\Route;

/**
 * Route registrar for the shared notifications command centre.
 *
 * aero-notifications is shared by the SaaS platform, SaaS tenants and standalone, so
 * it registers no routes itself — it cannot choose a domain or a context without
 * breaking one of the three. Instead each HOST calls this from inside its own
 * domain/middleware group and states the context via `$defaults`:
 *
 *   // aero-core (tenant): tenant domain + tenancy middleware
 *   Route::domain('{tenant}.'.$domain)->middleware(['web', InitializeTenancyIfNotCentral::class, 'tenant'])
 *       ->group(fn () => NotificationRoutes::register([
 *           'notifications_view'      => 'Shared/Notifications/Index',
 *           'notifications_base'      => '/notifications',
 *           'notifications_namespace' => 'notifications',
 *           'notifications_scope'     => 'tenant',
 *       ]));
 *
 *   // aero-platform: admin domain, central connection, subset of tabs
 *   NotificationRoutes::register([
 *       'notifications_view'      => 'Shared/Notifications/Index',
 *       'notifications_base'      => '/admin/notifications',
 *       'notifications_namespace' => 'notifications',
 *       'notifications_scope'     => 'platform',
 *       'notifications_tabs'      => ['log', 'bounces', 'suppression', 'deliverability', 'templates', 'channels'],
 *   ], prefix: 'notifications', name: 'platform.admin.notifications.');
 *
 * Permissions are namespaced from `notifications_namespace`, so a host can gate the
 * same surface under its own HRMAC paths without touching this package.
 *
 * Every {id} is read via $request->route('id') in the controller: tenant routes carry
 * a leading {tenant} segment which Laravel would otherwise bind positionally to a
 * typed controller argument.
 *
 * @param  array<string,mixed>  $defaults  context handed to the controller
 * @param  string  $prefix  URL prefix inside the host's group
 * @param  string  $name  route-name prefix (must end in a dot)
 */
class NotificationRoutes
{
    public static function register(array $defaults, string $prefix = 'notifications', string $name = 'notifications.'): void
    {
        $ns = $defaults['notifications_namespace'] ?? 'notifications';

        Route::prefix($prefix)->name($name)->group(function () use ($defaults, $ns) {
            $def = fn ($route) => $route->setDefaults(array_merge($route->defaults, $defaults));

            // ── Page ─────────────────────────────────────────────────────────────
            $def(Route::get('/', [CC::class, 'index'])->name('index'));

            // ── Inbox ────────────────────────────────────────────────────────────
            $def(Route::post('/inbox/read-all', [CC::class, 'markAllRead'])->name('inbox.read-all')
                ->middleware("hrmac:{$ns}.in_app.inbox.mark_read"));
            $def(Route::post('/inbox/bulk-delete', [CC::class, 'bulkDestroyNotifications'])->name('inbox.bulk-destroy')
                ->middleware("hrmac:{$ns}.in_app.inbox.delete"));
            $def(Route::post('/inbox/{id}/read', [CC::class, 'markRead'])->name('inbox.read')
                ->middleware("hrmac:{$ns}.in_app.inbox.mark_read"));
            $def(Route::post('/inbox/{id}/unread', [CC::class, 'markUnread'])->name('inbox.unread')
                ->middleware("hrmac:{$ns}.in_app.inbox.mark_read"));
            $def(Route::delete('/inbox/{id}', [CC::class, 'destroyNotification'])->name('inbox.destroy')
                ->middleware("hrmac:{$ns}.in_app.inbox.delete"));

            // ── Delivery log ─────────────────────────────────────────────────────
            $def(Route::get('/log/export', [CC::class, 'exportLogs'])->name('log.export')
                ->middleware("hrmac:{$ns}.email_engine.logs.export"));
            $def(Route::post('/log/bulk-resend', [CC::class, 'bulkResend'])->name('log.bulk-resend')
                ->middleware("hrmac:{$ns}.email_engine.logs.resend"));
            $def(Route::post('/log/retry-failed', [CC::class, 'retryAllFailed'])->name('log.retry-failed')
                ->middleware("hrmac:{$ns}.email_engine.logs.resend"));
            $def(Route::post('/log/{id}/resend', [CC::class, 'resend'])->name('log.resend')
                ->middleware("hrmac:{$ns}.email_engine.logs.resend"));

            // ── Bounces ──────────────────────────────────────────────────────────
            $def(Route::post('/bounces/suppress', [CC::class, 'suppressFromBounce'])->name('bounces.suppress')
                ->middleware("hrmac:{$ns}.email_engine.bounces.suppress"));

            // ── Suppression list ─────────────────────────────────────────────────
            $def(Route::get('/suppression/export', [CC::class, 'suppressionExport'])->name('suppression.export')
                ->middleware("hrmac:{$ns}.email_engine.suppression_list.export"));
            $def(Route::post('/suppression/bulk-delete', [CC::class, 'suppressionBulkDestroy'])->name('suppression.bulk-destroy')
                ->middleware("hrmac:{$ns}.email_engine.suppression_list.remove"));
            $def(Route::post('/suppression', [CC::class, 'suppressionStore'])->name('suppression.store')
                ->middleware("hrmac:{$ns}.email_engine.suppression_list.add"));
            $def(Route::delete('/suppression/{id}', [CC::class, 'suppressionDestroy'])->name('suppression.destroy')
                ->middleware("hrmac:{$ns}.email_engine.suppression_list.remove"));

            // ── Deliverability ───────────────────────────────────────────────────
            $def(Route::post('/deliverability/recheck', [CC::class, 'deliverabilityRecheck'])->name('deliverability.recheck')
                ->middleware("hrmac:{$ns}.email_engine.deliverability.test_smtp"));

            // ── Templates ────────────────────────────────────────────────────────
            $def(Route::post('/templates', [CC::class, 'templateStore'])->name('templates.store')
                ->middleware("hrmac:{$ns}.email_engine.templates.create"));
            $def(Route::put('/templates/{id}', [CC::class, 'templateUpdate'])->name('templates.update')
                ->middleware("hrmac:{$ns}.email_engine.templates.update"));
            $def(Route::delete('/templates/{id}', [CC::class, 'templateDestroy'])->name('templates.destroy')
                ->middleware("hrmac:{$ns}.email_engine.templates.delete"));
            $def(Route::post('/templates/{id}/duplicate', [CC::class, 'templateDuplicate'])->name('templates.duplicate')
                ->middleware("hrmac:{$ns}.email_engine.templates.duplicate"));
            $def(Route::post('/templates/{id}/clone', [CC::class, 'templateCloneGlobal'])->name('templates.clone')
                ->middleware("hrmac:{$ns}.email_engine.templates.duplicate"));
            $def(Route::post('/templates/{id}/toggle', [CC::class, 'templateToggle'])->name('templates.toggle')
                ->middleware("hrmac:{$ns}.email_engine.templates.update"));
            $def(Route::get('/templates/{id}/preview', [CC::class, 'templatePreview'])->name('templates.preview')
                ->middleware("hrmac:{$ns}.email_engine.templates.view"));

            // ── Broadcasts (platform-only surface; route harmlessly present on
            //    every mount but gated + soft-guarded in the controller) ──────────
            $def(Route::post('/broadcasts', [CC::class, 'broadcastSend'])->name('broadcasts.send')
                ->middleware("hrmac:{$ns}.email_engine.broadcasts.send"));

            // ── Channels ─────────────────────────────────────────────────────────
            $def(Route::post('/channels/test', [CC::class, 'channelTest'])->name('channels.test')
                ->middleware("hrmac:{$ns}.settings.channels.test"));
            $def(Route::post('/channels', [CC::class, 'channelsUpdate'])->name('channels.update')
                ->middleware("hrmac:{$ns}.settings.channels.configure"));

            // ── My preferences (no HRMAC — a user always governs their own inbox) ─
            $def(Route::post('/preferences/mute', [CC::class, 'preferencesMuteAll'])->name('preferences.mute'));
            $def(Route::post('/preferences/reset', [CC::class, 'preferencesReset'])->name('preferences.reset'));
            $def(Route::post('/preferences', [CC::class, 'preferencesUpdate'])->name('preferences.update'));
        });
    }
}
