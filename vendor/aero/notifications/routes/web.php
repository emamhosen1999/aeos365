<?php

declare(strict_types=1);

use Aero\Notifications\Http\Controllers\NotificationCenterController as CC;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Notifications command centre
|--------------------------------------------------------------------------
| One page (`/notifications?tab=…`) owns the whole surface: inbox, delivery
| log, bounces, suppression, deliverability, templates, channels and per-user
| preferences. It replaces five scattered pages plus two endpoints that used
| to return raw JSON to an Inertia visit (the bell in AppChrome navigates to
| /notifications — it was landing on a JSON dump).
|
| The tab is a QUERY parameter, and every {id} is read via $request->route('id'):
| tenant routes carry a leading {tenant} segment which Laravel would otherwise
| bind positionally to a typed controller argument.
*/

Route::middleware(['auth'])->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [CC::class, 'index'])->name('index');

    // ── Inbox ────────────────────────────────────────────────────────────────
    Route::post('/inbox/read-all', [CC::class, 'markAllRead'])->name('inbox.read-all')
        ->middleware('hrmac:notifications.in_app.inbox.mark_read');
    Route::post('/inbox/bulk-delete', [CC::class, 'bulkDestroyNotifications'])->name('inbox.bulk-destroy')
        ->middleware('hrmac:notifications.in_app.inbox.delete');
    Route::post('/inbox/{id}/read', [CC::class, 'markRead'])->name('inbox.read')
        ->middleware('hrmac:notifications.in_app.inbox.mark_read');
    Route::post('/inbox/{id}/unread', [CC::class, 'markUnread'])->name('inbox.unread')
        ->middleware('hrmac:notifications.in_app.inbox.mark_read');
    Route::delete('/inbox/{id}', [CC::class, 'destroyNotification'])->name('inbox.destroy')
        ->middleware('hrmac:notifications.in_app.inbox.delete');

    // ── Delivery log ─────────────────────────────────────────────────────────
    Route::get('/log/export', [CC::class, 'exportLogs'])->name('log.export')
        ->middleware('hrmac:notifications.email_engine.logs.export');
    Route::post('/log/bulk-resend', [CC::class, 'bulkResend'])->name('log.bulk-resend')
        ->middleware('hrmac:notifications.email_engine.logs.resend');
    Route::post('/log/retry-failed', [CC::class, 'retryAllFailed'])->name('log.retry-failed')
        ->middleware('hrmac:notifications.email_engine.logs.resend');
    Route::post('/log/{id}/resend', [CC::class, 'resend'])->name('log.resend')
        ->middleware('hrmac:notifications.email_engine.logs.resend');

    // ── Bounces ──────────────────────────────────────────────────────────────
    Route::post('/bounces/suppress', [CC::class, 'suppressFromBounce'])->name('bounces.suppress')
        ->middleware('hrmac:notifications.email_engine.bounces.suppress');

    // ── Suppression list ─────────────────────────────────────────────────────
    Route::get('/suppression/export', [CC::class, 'suppressionExport'])->name('suppression.export')
        ->middleware('hrmac:notifications.email_engine.suppression_list.export');
    Route::post('/suppression/bulk-delete', [CC::class, 'suppressionBulkDestroy'])->name('suppression.bulk-destroy')
        ->middleware('hrmac:notifications.email_engine.suppression_list.remove');
    Route::post('/suppression', [CC::class, 'suppressionStore'])->name('suppression.store')
        ->middleware('hrmac:notifications.email_engine.suppression_list.add');
    Route::delete('/suppression/{id}', [CC::class, 'suppressionDestroy'])->name('suppression.destroy')
        ->middleware('hrmac:notifications.email_engine.suppression_list.remove');

    // ── Deliverability ───────────────────────────────────────────────────────
    Route::post('/deliverability/recheck', [CC::class, 'deliverabilityRecheck'])->name('deliverability.recheck')
        ->middleware('hrmac:notifications.email_engine.deliverability.test_smtp');

    // ── Templates ────────────────────────────────────────────────────────────
    Route::post('/templates', [CC::class, 'templateStore'])->name('templates.store')
        ->middleware('hrmac:notifications.email_engine.templates.create');
    Route::put('/templates/{id}', [CC::class, 'templateUpdate'])->name('templates.update')
        ->middleware('hrmac:notifications.email_engine.templates.update');
    Route::delete('/templates/{id}', [CC::class, 'templateDestroy'])->name('templates.destroy')
        ->middleware('hrmac:notifications.email_engine.templates.delete');
    Route::post('/templates/{id}/duplicate', [CC::class, 'templateDuplicate'])->name('templates.duplicate')
        ->middleware('hrmac:notifications.email_engine.templates.duplicate');
    Route::post('/templates/{id}/toggle', [CC::class, 'templateToggle'])->name('templates.toggle')
        ->middleware('hrmac:notifications.email_engine.templates.update');
    Route::get('/templates/{id}/preview', [CC::class, 'templatePreview'])->name('templates.preview')
        ->middleware('hrmac:notifications.email_engine.templates.view');

    // ── Channels ─────────────────────────────────────────────────────────────
    Route::post('/channels/test', [CC::class, 'channelTest'])->name('channels.test')
        ->middleware('hrmac:notifications.settings.channels.test');
    Route::post('/channels', [CC::class, 'channelsUpdate'])->name('channels.update')
        ->middleware('hrmac:notifications.settings.channels.configure');

    // ── My preferences (no HRMAC — a user always governs their own inbox) ─────
    Route::post('/preferences/mute', [CC::class, 'preferencesMuteAll'])->name('preferences.mute');
    Route::post('/preferences/reset', [CC::class, 'preferencesReset'])->name('preferences.reset');
    Route::post('/preferences', [CC::class, 'preferencesUpdate'])->name('preferences.update');
});

/*
|--------------------------------------------------------------------------
| Legacy route names → command-centre tabs
|--------------------------------------------------------------------------
| Kept so existing nav entries, bookmarks and any Ziggy route() call in the
| frontend keep resolving. Each lands on the tab that now owns that surface.
*/
$tab = fn (string $t) => fn () => redirect()->route('notifications.index', ['tab' => $t]);

Route::middleware(['auth'])->group(function () use ($tab) {
    Route::get('/notifications/preferences', $tab('preferences'))->name('notifications.preferences.index');

    Route::get('/email/logs', $tab('log'))->name('core.email.logs.index');
    Route::get('/email/deliverability', $tab('deliverability'))->name('core.email.deliverability.index');
    Route::get('/email/suppression', $tab('suppression'))->name('core.email.suppression.index');
    Route::get('/email/bounces', $tab('bounces'))->name('core.email.bounces.index');

    Route::get('/admin/notifications/settings', $tab('channels'))->name('admin.notifications.settings.index');
    Route::get('/admin/notifications/channels', $tab('channels'))->name('admin.notifications.channels.index');
    Route::get('/admin/notifications/templates', $tab('templates'))->name('admin.notifications.templates.index');
    Route::get('/notifications/admin/channels', $tab('channels'))->name('notifications.admin.channels.index');
    Route::get('/notifications/admin/templates', $tab('templates'))->name('notifications.admin.templates.index');
});
