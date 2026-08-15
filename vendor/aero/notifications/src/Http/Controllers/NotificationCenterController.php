<?php

declare(strict_types=1);

namespace Aero\Notifications\Http\Controllers;

use Aero\Contracts\AuditServiceInterface;
use Aero\Core\Models\EmailTemplate;
use Aero\HRMAC\Facades\HRMAC;
use Aero\Kernel\Http\Controllers\Controller;
use Aero\Notifications\Jobs\SendEmailJob;
use Aero\Notifications\Models\NotificationLog;
use Aero\Notifications\Models\NotificationSetting;
use Aero\Notifications\Models\UserNotificationPreference;
use Aero\Notifications\Services\ProviderResolutionService;
use Aero\Notifications\Services\TemplateResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * NotificationCenterController — the Notifications command centre, SHARED by the
 * SaaS platform, SaaS tenants and standalone.
 *
 * One page, eight HRMAC-gated tabs (?tab=): inbox, log, bounces, suppression,
 * deliverability, templates, channels, preferences. Replaces the five scattered
 * pages plus two JSON-only endpoints that previously made up this surface.
 *
 * CONTEXT-FREE (see [[dependency-decoupling]] / the aero-hrmac + aero-auth pattern):
 * this controller detects nothing and names no connection. Everything context-shaped
 * — the Inertia view, the HRMAC namespace, the URL base, the scope, which tabs are
 * mountable — arrives as ROUTE DEFAULTS supplied by the host that registers the
 * route (aero-core for tenant, aero-platform for platform). The models are likewise
 * context-free: they use the default connection, which the host's runtime decides
 * (stancl swaps it to the tenant DB; it is central for platform; the single DB in
 * standalone).
 *
 * Consequently this package registers NO web routes of its own.
 *
 * The tab is read from the QUERY STRING, never a route parameter — tenant routes
 * carry a leading {tenant} segment that Laravel would otherwise bind positionally
 * to a typed method argument.
 */
class NotificationCenterController extends Controller
{
    /** Tabs in nav order → the permission SUFFIX that gates each, under the host's namespace. */
    private const TABS = [
        'inbox' => 'in_app.inbox.view',
        'log' => 'email_engine.logs.view',
        'bounces' => 'email_engine.bounces.view',
        'suppression' => 'email_engine.suppression_list.view',
        'deliverability' => 'email_engine.deliverability.view',
        'templates' => 'email_engine.templates.view',
        'channels' => 'settings.channels.view',
        // Platform-only tabs — a host mounts them via notifications_tabs (the tenant
        // host does not). Fleet = cross-tenant delivery observability; Broadcasts =
        // push an announcement to tenants. Both degrade to hidden when the platform
        // services aren't installed (standalone) even if somehow mounted.
        'fleet' => 'email_engine.fleet.view',
        'broadcasts' => 'email_engine.broadcasts.send',
        // A user always governs their own inbox — preferences are never gated away.
        'preferences' => null,
    ];

    /** Action permission suffixes, exposed to the UI as the `can` map. */
    private const ACTIONS = [
        'logResend' => 'email_engine.logs.resend',
        'logExport' => 'email_engine.logs.export',
        'bounceSuppress' => 'email_engine.bounces.suppress',
        'suppressionAdd' => 'email_engine.suppression_list.add',
        'suppressionRemove' => 'email_engine.suppression_list.remove',
        'suppressionExport' => 'email_engine.suppression_list.export',
        'deliverabilityTest' => 'email_engine.deliverability.test_smtp',
        'templateCreate' => 'email_engine.templates.create',
        'templateUpdate' => 'email_engine.templates.update',
        'templateDelete' => 'email_engine.templates.delete',
        'templateDuplicate' => 'email_engine.templates.duplicate',
        'channelConfigure' => 'settings.channels.configure',
        'channelTest' => 'settings.channels.test',
        'inboxMarkRead' => 'in_app.inbox.mark_read',
        'inboxDelete' => 'in_app.inbox.delete',
    ];

    /** Events the preference matrix covers. */
    private const PREF_EVENTS = [
        ['key' => 'leave_request', 'label' => 'Leave requests', 'hint' => 'Approvals awaiting you'],
        ['key' => 'payroll', 'label' => 'Payroll', 'hint' => 'Payslips and pay runs'],
        ['key' => 'security_alert', 'label' => 'Security', 'hint' => 'Sign-ins, password changes'],
        ['key' => 'system_notification', 'label' => 'System', 'hint' => 'Maintenance and announcements'],
    ];

    private const PREF_CHANNELS = ['database' => 'In-app', 'mail' => 'Email', 'sms' => 'SMS', 'push' => 'Push'];

    public function __construct(
        private AuditServiceInterface $audit,
        private TemplateResolverService $templates,
        private ProviderResolutionService $providers,
    ) {}

    /* ===================================================== page ============ */

    public function index(Request $request): Response
    {
        $ctx = $this->context($request);
        $can = $this->permissions($request, $ctx);

        $tab = $request->query('tab', $ctx['tabs'][0] ?? 'inbox');
        // Unknown tab, one this host doesn't mount, or one this user may not see:
        // fall back to the first tab they can actually open — never a blank page,
        // never a silent 403.
        if (! in_array($tab, $ctx['tabs'], true) || ! ($can[$tab] ?? false)) {
            $tab = collect($ctx['tabs'])->first(fn ($t) => $can[$t] ?? false) ?? 'preferences';
        }

        return Inertia::render($ctx['view'], array_merge([
            'tab' => $tab,
            'tabs' => $ctx['tabs'],
            'can' => $can,
            'scope' => $ctx['scope'],
            'base' => $ctx['base'],
            'stats' => $this->stats(),
            'filters' => $request->only('search', 'status', 'channel', 'reason', 'category', 'date_from', 'date_to'),
        ], $this->tabPayload($tab, $request)));
    }

    /**
     * Everything context-shaped, supplied by the host as route defaults.
     *
     * @return array{view:string, base:string, namespace:string, scope:string, tabs:array<int,string>}
     */
    private function context(Request $request): array
    {
        $d = $request->route()?->defaults ?? [];
        $scope = $d['notifications_scope'] ?? 'tenant';

        // A host may mount a subset — platform has no use for a personal inbox the
        // way a tenant does, and can drop tabs it hasn't earned.
        $tabs = $d['notifications_tabs'] ?? array_keys(self::TABS);

        return [
            'view' => $d['notifications_view'] ?? 'Shared/Notifications/Index',
            // The URL the frontend builds every action path from. No leading-slash
            // guessing in JS: the host owns the mount point, so the host states it.
            'base' => rtrim($d['notifications_base'] ?? '/notifications', '/'),
            'namespace' => $d['notifications_namespace'] ?? 'notifications',
            'scope' => $scope,
            'tabs' => array_values(array_filter($tabs, fn ($t) => array_key_exists($t, self::TABS))),
        ];
    }

    /**
     * Which tabs and destructive actions this user may use. The route middleware is
     * the real enforcement — this only decides what the UI bothers to render.
     */
    private function permissions(Request $request, array $ctx): array
    {
        $ns = $ctx['namespace'];
        $can = [];

        foreach (self::TABS as $tab => $suffix) {
            $mounted = in_array($tab, $ctx['tabs'], true);
            $can[$tab] = $mounted && ($suffix === null ? true : $this->allows("{$ns}.{$suffix}"));
        }

        foreach (self::ACTIONS as $key => $suffix) {
            $can[$key] = $this->allows("{$ns}.{$suffix}");
        }

        return $can;
    }

    /**
     * Mirror CheckRoleModuleAccess::parsePath — module is the FIRST segment, sub-module
     * the SECOND, and the action the LAST. Everything between is the component, which
     * userCanAccessAction() ignores (it finds the action in any component of the
     * sub-module). Reading a fixed index instead would break the moment a host nests
     * the surface at a different depth, which is exactly what platform does:
     *
     *   tenant    notifications.email_engine.logs.view   → (notifications, email_engine, view)
     *   platform  platform.notifications.email_engine.logs.view → (platform, notifications, view)
     */
    private function allows(string $permission): bool
    {
        try {
            $p = explode('.', $permission);
            if (count($p) < 3) {
                return false;
            }

            return HRMAC::userCanAccessAction(Auth::user(), $p[0], $p[1], end($p));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Only the active tab's dataset is queried — the other seven cost nothing.
     */
    private function tabPayload(string $tab, Request $request): array
    {
        return match ($tab) {
            'log' => ['logs' => $this->logQuery($request)->paginate(25)->withQueryString()],
            'bounces' => [
                'bounces' => $this->bounceQuery($request)->paginate(25)->withQueryString(),
                'topDomains' => $this->topBouncingDomains(),
            ],
            'suppression' => ['suppression' => $this->suppressionQuery($request)->paginate(25)->withQueryString()],
            'deliverability' => ['deliverability' => $this->deliverability()],
            'templates' => [
                // Global Template Library: the tenant's own templates PLUS the
                // platform-curated globals, deduped by slug (own wins). On platform
                // this returns the globals it curates; on a tenant, globals appear
                // read-only until cloned to edit.
                'templates' => $this->templates->list($request->only('search', 'category'))->values(),
                'categories' => EmailTemplate::query()->select('category')->distinct()->pluck('category')->filter()->values(),
            ],
            'channels' => [
                'channels' => $this->channelSettings(),
                // Provider inheritance: is each channel using this workspace's own
                // config, or inheriting the platform/deployment default?
                'inheritance' => ['mail' => $this->providers->mail(), 'sms' => $this->providers->sms()],
            ],
            'fleet' => ['fleet' => $this->fleetPayload()],
            'broadcasts' => ['broadcast' => $this->broadcastPayload()],
            'preferences' => ['preferences' => $this->preferencePayload()],
            default => ['inbox' => $this->inboxPayload($request)],
        };
    }

    /* ============================================ platform: fleet =========== */

    /**
     * Cross-tenant delivery observability, resolved SOFTLY from aero-platform so
     * this shared controller keeps its one-way dependency (it must not require the
     * platform package). Absent (standalone) → null, and the tab simply isn't
     * mounted there anyway.
     */
    private function fleetPayload(): ?array
    {
        $service = 'Aero\\Platform\\Services\\FleetDeliverabilityService';
        if (! class_exists($service)) {
            return null;
        }

        try {
            $fleet = app($service);

            return [
                'summary' => $fleet->summary(14),
                'worstOffenders' => $fleet->worstOffenders(10),
            ];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /* ======================================= platform: broadcasts ========== */

    /** Active tenants the broadcast composer can target (soft platform dependency). */
    private function broadcastPayload(): array
    {
        $tenantClass = 'Aero\\Platform\\Models\\Tenant';
        if (! class_exists($tenantClass)) {
            return ['tenants' => []];
        }

        try {
            $tenants = $tenantClass::query()
                ->when(method_exists($tenantClass, 'scopeActive'), fn ($q) => $q->active())
                ->get(['id', 'name'])
                ->map(fn ($t) => ['id' => (string) $t->id, 'name' => $t->name ?? (string) $t->id])
                ->all();

            return ['tenants' => $tenants];
        } catch (\Throwable $e) {
            report($e);

            return ['tenants' => []];
        }
    }

    /**
     * Push an announcement to tenants. Platform-only; the service lives in
     * aero-platform and is resolved softly so aero-notifications stays decoupled.
     */
    public function broadcastSend(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
            'type' => ['nullable', 'in:info,warning,success,danger'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'is_pinned' => ['boolean'],
            'tenant_ids' => ['array'],
            'tenant_ids.*' => ['string'],
        ]);

        $service = 'Aero\\Platform\\Services\\TenantBroadcastService';
        if (! class_exists($service)) {
            return back()->with('error', 'Broadcasts are only available on the platform.');
        }

        try {
            $count = app($service)->broadcast($data, $data['tenant_ids'] ?? []);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'The broadcast could not be sent.');
        }

        $this->audit->log('notification.broadcast_sent', 'created', null, "Broadcast sent to {$count} tenant(s)", null, null, [
            'title' => $data['title'], 'tenants' => $count,
        ]);

        return back()->with('success', "Broadcast sent to {$count} tenant(s).");
    }

    /* ===================================================== KPIs ============ */

    private function stats(): array
    {
        $since = now()->subDay();

        try {
            $sent = NotificationLog::whereIn('status', ['sent', 'delivered'])->where('created_at', '>=', $since)->count();
            $failed = NotificationLog::whereIn('status', ['failed', 'bounced'])->where('created_at', '>=', $since)->count();
            $queued = NotificationLog::where('status', 'pending')->count();
            $attempted = $sent + $failed;

            return [
                'sent24h' => $sent,
                'failed24h' => $failed,
                'queued' => $queued,
                // No mail attempted yet is not a 0% delivery rate — it's "nothing to report".
                'deliveryRate' => $attempted > 0 ? round($sent / $attempted * 100, 1) : null,
                'suppressed' => DB::table('email_suppression_list')->count(),
                'deliverabilityScore' => $this->deliverability()['score'],
                'unread' => Auth::user()?->unreadNotifications()->count() ?? 0,
                // Monthly outbound-email allowance (plan quota; unlimited unless set).
                'emailQuota' => app(\Aero\Notifications\Services\NotificationQuotaService::class)->emailQuota(),
            ];
        } catch (\Throwable $e) {
            report($e);

            return ['sent24h' => 0, 'failed24h' => 0, 'queued' => 0, 'deliveryRate' => null,
                'suppressed' => 0, 'deliverabilityScore' => 0, 'unread' => 0,
                'emailQuota' => ['used' => 0, 'limit' => null, 'remaining' => null, 'unlimited' => true, 'exhausted' => false]];
        }
    }

    /* ==================================================== inbox ============ */

    private function inboxPayload(Request $request): array
    {
        $user = Auth::user();
        if (! $user) {
            return ['data' => [], 'total' => 0, 'unread' => 0];
        }

        $q = $user->notifications();

        if ($status = $request->query('status')) {
            $status === 'unread' ? $q->whereNull('read_at') : $q->whereNotNull('read_at');
        }

        $page = $q->paginate(25)->withQueryString();

        return [
            'data' => collect($page->items())->map(fn ($n) => $this->presentNotification($n))
                ->when($request->query('search'), fn ($c, $s) => $c->filter(
                    fn ($r) => str_contains(strtolower($r['title'].' '.$r['body']), strtolower($s))
                )->values())
                ->all(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'unread' => $user->unreadNotifications()->count(),
        ];
    }

    private function presentNotification(object $n): array
    {
        $data = is_array($n->data) ? $n->data : (json_decode((string) $n->data, true) ?: []);
        $type = class_basename((string) $n->type);

        return [
            'id' => (string) $n->id,
            'type' => $type,
            'title' => $data['title'] ?? $data['subject'] ?? ucwords(str_replace(['Notification', '_'], ['', ' '], $type)),
            'body' => $data['message'] ?? $data['body'] ?? '',
            'url' => $data['url'] ?? $data['action_url'] ?? null,
            'severity' => $data['severity'] ?? $this->severityFor($type),
            'read' => $n->read_at !== null,
            'timeAgo' => $n->created_at?->diffForHumans(),
        ];
    }

    private function severityFor(string $type): string
    {
        $t = strtolower($type);
        if (str_contains($t, 'fail') || str_contains($t, 'error') || str_contains($t, 'bounce')) {
            return 'danger';
        }
        if (str_contains($t, 'approv') || str_contains($t, 'request') || str_contains($t, 'pending')) {
            return 'warning';
        }
        if (str_contains($t, 'complete') || str_contains($t, 'success')) {
            return 'success';
        }

        return 'info';
    }

    public function markRead(Request $request): RedirectResponse
    {
        Auth::user()?->notifications()->where('id', $request->route('id'))->first()?->markAsRead();

        return back()->with('success', 'Marked as read.');
    }

    public function markUnread(Request $request): RedirectResponse
    {
        Auth::user()?->notifications()->where('id', $request->route('id'))->update(['read_at' => null]);

        return back()->with('success', 'Marked as unread.');
    }

    public function markAllRead(): RedirectResponse
    {
        Auth::user()?->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }

    public function destroyNotification(Request $request): RedirectResponse
    {
        Auth::user()?->notifications()->where('id', $request->route('id'))->delete();

        return back()->with('success', 'Notification deleted.');
    }

    public function bulkDestroyNotifications(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['string']])['ids'];
        $n = Auth::user()?->notifications()->whereIn('id', $ids)->delete() ?? 0;

        return back()->with('success', "{$n} notification(s) deleted.");
    }

    /* ============================================== delivery log =========== */

    private function logQuery(Request $request)
    {
        return NotificationLog::query()
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('recipient', 'like', "%{$s}%")
                ->orWhere('subject', 'like', "%{$s}%")))
            ->when($request->query('channel'), fn ($q, $c) => $q->where('channel', $c))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('date_from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->query('date_to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->orderByDesc('created_at');
    }

    public function resend(Request $request): RedirectResponse
    {
        $log = NotificationLog::findOrFail($request->route('id'));
        $this->dispatchResend($log);

        return back()->with('success', "Queued for resend to {$log->recipient}.");
    }

    public function bulkResend(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];
        $logs = NotificationLog::whereIn('id', $ids)->get();
        $logs->each(fn ($l) => $this->dispatchResend($l));

        return back()->with('success', "{$logs->count()} notification(s) queued for resend.");
    }

    public function retryAllFailed(): RedirectResponse
    {
        $logs = NotificationLog::whereIn('status', ['failed', 'bounced'])->get();
        $logs->each(fn ($l) => $this->dispatchResend($l));

        return back()->with('success', "{$logs->count()} failed notification(s) requeued.");
    }

    private function dispatchResend(NotificationLog $log): void
    {
        // Only mail has a resend job today; other channels are marked for the
        // pipeline to pick up rather than silently pretending they were sent.
        if ($log->channel === 'mail') {
            SendEmailJob::dispatch(['to' => $log->recipient, 'subject' => $log->subject, 'body' => $log->content]);
        }

        $log->update(['status' => 'pending', 'attempts' => (int) $log->attempts + 1, 'last_attempt_at' => now(), 'error_message' => null]);

        $this->audit->log('notification.resent', 'resent', null, "Notification resent to {$log->recipient}", null, null, [
            'log_id' => $log->id, 'channel' => $log->channel,
        ]);
    }

    public function exportLogs(Request $request): StreamedResponse
    {
        $this->audit->logAccess('notification_logs', null, null, ['recipient']);
        $rows = $this->logQuery($request)->limit(5000)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['ID', 'Recipient', 'Subject', 'Channel', 'Status', 'Attempts', 'Error', 'Created']);
            foreach ($rows as $r) {
                fputcsv($out, [$r->id, $r->recipient, $r->subject, $r->channel, $r->status,
                    $r->attempts, $r->error_message, $r->created_at?->toDateTimeString()]);
            }
            fclose($out);
        }, 'notification-log-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /* ================================================== bounces ============ */

    private function bounceQuery(Request $request)
    {
        return NotificationLog::where('channel', 'mail')
            ->whereIn('status', ['failed', 'bounced'])
            ->when($request->query('search'), fn ($q, $s) => $q->where('recipient', 'like', "%{$s}%"))
            ->orderByDesc('created_at');
    }

    private function topBouncingDomains(): array
    {
        try {
            return NotificationLog::where('channel', 'mail')
                ->whereIn('status', ['failed', 'bounced'])
                ->whereNotNull('recipient')
                ->selectRaw('SUBSTRING_INDEX(recipient, "@", -1) as domain, COUNT(*) as count')
                ->groupBy('domain')->orderByDesc('count')->limit(8)->get()->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    /** One click from a bounce to the suppression list. */
    public function suppressFromBounce(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];
        $emails = NotificationLog::whereIn('id', $ids)->whereNotNull('recipient')->pluck('recipient')->unique();

        $added = 0;
        foreach ($emails as $email) {
            if (DB::table('email_suppression_list')->where('email', $email)->exists()) {
                continue;
            }
            DB::table('email_suppression_list')->insert([
                'email' => $email, 'reason' => 'bounce', 'note' => 'Suppressed from bounce log',
                'added_by' => Auth::id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $added++;
        }

        $this->audit->log('notification.suppressed', 'created', null, 'Addresses suppressed from bounces', null, null, ['count' => $added]);

        return back()->with('success', "{$added} address(es) added to the suppression list.");
    }

    /* =============================================== suppression =========== */

    private function suppressionQuery(Request $request)
    {
        return DB::table('email_suppression_list')
            ->when($request->query('search'), fn ($q, $s) => $q->where('email', 'like', "%{$s}%"))
            ->when($request->query('reason'), fn ($q, $r) => $q->where('reason', $r))
            ->orderByDesc('created_at');
    }

    public function suppressionStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'unique:email_suppression_list,email'],
            'reason' => ['required', 'in:manual,bounce,complaint,unsubscribe'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        DB::table('email_suppression_list')->insert(array_merge($data, [
            'added_by' => Auth::id(), 'created_at' => now(), 'updated_at' => now(),
        ]));

        $this->audit->log('notification.suppressed', 'created', null, 'Email suppressed', null, null, ['email' => $data['email']]);

        return back()->with('success', "{$data['email']} added to the suppression list.");
    }

    public function suppressionDestroy(Request $request): RedirectResponse
    {
        $id = (int) $request->route('id');
        $entry = DB::table('email_suppression_list')->find($id);
        DB::table('email_suppression_list')->delete($id);

        $this->audit->log('notification.unsuppressed', 'deleted', null, 'Email un-suppressed', null, null, ['email' => $entry?->email]);

        return back()->with('success', 'Removed from the suppression list.');
    }

    public function suppressionBulkDestroy(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];
        $n = DB::table('email_suppression_list')->whereIn('id', $ids)->delete();

        $this->audit->log('notification.unsuppressed', 'deleted', null, 'Addresses un-suppressed', null, null, ['count' => $n]);

        return back()->with('success', "{$n} address(es) removed.");
    }

    public function suppressionExport(Request $request): StreamedResponse
    {
        $rows = $this->suppressionQuery($request)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['Email', 'Reason', 'Note', 'Added at']);
            foreach ($rows as $r) {
                fputcsv($out, [$r->email, $r->reason, $r->note, $r->created_at]);
            }
            fclose($out);
        }, 'suppression-list-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /* ============================================ deliverability =========== */

    /** DNS lookups are slow — cache for an hour; "Re-run checks" busts the cache. */
    private function deliverability(): array
    {
        return Cache::remember('notifications.deliverability', 3600, function () {
            $from = (string) (NotificationSetting::getValue('mail_from_email') ?? config('mail.from.address', ''));
            $domain = $from && str_contains($from, '@')
                ? substr(strrchr($from, '@'), 1)
                : (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost');

            $checks = $this->runDnsChecks($domain);

            return ['domain' => $domain, 'checks' => $checks, 'score' => $this->score($checks)];
        });
    }

    private function runDnsChecks(string $domain): array
    {
        $txt = @dns_get_record($domain, DNS_TXT) ?: [];
        $spf = collect($txt)->first(fn ($r) => str_contains($r['txt'] ?? '', 'v=spf1'));
        $dmarc = @dns_get_record("_dmarc.{$domain}", DNS_TXT)[0] ?? null;

        $dkim = false;
        foreach (['default._domainkey', 'mail._domainkey', 's1._domainkey'] as $sel) {
            if (! empty(@dns_get_record("{$sel}.{$domain}", DNS_TXT))) {
                $dkim = true;
                break;
            }
        }
        $mx = @dns_get_record($domain, DNS_MX) ?: [];

        return [
            'spf' => ['label' => 'SPF', 'status' => $spf ? 'pass' : 'fail', 'value' => $spf['txt'] ?? null,
                'guide' => 'Add a TXT record: v=spf1 include:yourprovider.com ~all'],
            'dmarc' => ['label' => 'DMARC', 'status' => $dmarc ? 'pass' : 'fail', 'value' => $dmarc['txt'] ?? null,
                'guide' => "Add a TXT record at _dmarc.{$domain}: v=DMARC1; p=quarantine;"],
            'dkim' => ['label' => 'DKIM', 'status' => $dkim ? 'pass' : 'warn', 'value' => null,
                'guide' => 'Enable DKIM signing with your mail provider, then publish the selector TXT record.'],
            'mx' => ['label' => 'MX', 'status' => ! empty($mx) ? 'pass' : 'warn',
                'value' => collect($mx)->pluck('target')->join(', ') ?: null,
                'guide' => 'Add MX records so the domain can receive replies and bounce reports.'],
        ];
    }

    private function score(array $checks): int
    {
        $weights = ['spf' => 30, 'dmarc' => 30, 'dkim' => 30, 'mx' => 10];
        $score = 0;
        foreach ($checks as $k => $c) {
            $w = $weights[$k] ?? 10;
            $score += match ($c['status']) {
                'pass' => $w, 'warn' => $w / 2, default => 0
            };
        }

        return (int) $score;
    }

    public function deliverabilityRecheck(): RedirectResponse
    {
        Cache::forget('notifications.deliverability');
        $this->deliverability();

        return back()->with('success', 'DNS records re-checked.');
    }

    /* =============================================== templates ============= */

    private function templateQuery(Request $request)
    {
        return EmailTemplate::query()
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")->orWhere('subject', 'like', "%{$s}%")))
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->orderBy('category')->orderBy('name');
    }

    public function templateStore(Request $request): RedirectResponse
    {
        $data = $this->validateTemplate($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['is_locked'] = false;

        DB::transaction(fn () => EmailTemplate::create($data));
        $this->audit->log('notification.template_created', 'created', null, "Template {$data['name']} created");

        return back()->with('success', 'Template created.');
    }

    public function templateUpdate(Request $request): RedirectResponse
    {
        $template = EmailTemplate::findOrFail($request->route('id'));
        $data = $this->validateTemplate($request);

        // The slug is the key the app sends by — renaming a template must never
        // silently repoint (or break) the code that dispatches it.
        unset($data['slug']);

        DB::transaction(fn () => $template->update($data));
        $this->audit->log('notification.template_updated', 'updated', null, "Template {$template->name} updated");

        return back()->with('success', 'Template updated.');
    }

    public function templateDestroy(Request $request): RedirectResponse
    {
        $template = EmailTemplate::findOrFail($request->route('id'));

        // Locked templates are what the app itself sends — deleting one silently
        // breaks a transactional email, so they are protected.
        if ($template->is_locked) {
            return back()->with('error', 'This template is locked because the app sends it. Deactivate it instead.');
        }

        DB::transaction(fn () => $template->delete());
        $this->audit->log('notification.template_deleted', 'deleted', null, "Template {$template->name} deleted");

        return back()->with('success', 'Template deleted.');
    }

    public function templateDuplicate(Request $request): RedirectResponse
    {
        $template = EmailTemplate::findOrFail($request->route('id'));

        DB::transaction(function () use ($template) {
            $copy = $template->replicate();
            $copy->name = $template->name.' (copy)';
            $copy->slug = $this->uniqueSlug($copy->name);
            $copy->is_locked = false;
            $copy->is_active = false;   // a fresh copy shouldn't start sending on its own
            $copy->save();
        });

        return back()->with('success', 'Template duplicated — it starts inactive.');
    }

    public function templateToggle(Request $request): RedirectResponse
    {
        $template = EmailTemplate::findOrFail($request->route('id'));
        $template->update(['is_active' => ! $template->is_active]);

        return back()->with('success', $template->is_active ? 'Template activated.' : 'Template deactivated.');
    }

    /**
     * Clone a platform-curated GLOBAL template into an editable copy owned by
     * the current workspace. The clone starts inactive so it must be reviewed
     * before it can send. (Global Template Library — TemplateResolverService.)
     */
    public function templateCloneGlobal(Request $request): RedirectResponse
    {
        $template = EmailTemplate::findOrFail($request->route('id'));

        if (! $template->is_global) {
            return back()->with('error', 'Only shared library templates can be cloned to your workspace.');
        }

        $tenantId = function_exists('tenant') && tenant() ? (string) tenant('id') : null;
        $this->templates->cloneGlobalToTenant($template, $tenantId);
        $this->audit->log('notification.template_cloned', 'created', null, "Template {$template->name} cloned to workspace");

        return back()->with('success', 'Template copied to your workspace — it starts inactive.');
    }

    /** Render the template with sample data so the operator sees the real thing. */
    public function templatePreview(Request $request): JsonResponse
    {
        $template = EmailTemplate::findOrFail($request->route('id'));
        $sample = $this->sampleData();

        return response()->json([
            'subject' => $this->interpolate((string) $template->subject, $sample),
            'html' => $this->interpolate((string) $template->body_html, $sample),
            'variables' => $template->variables ?? [],
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'template';
        $slug = $base;
        $n = 2;

        while (EmailTemplate::where('slug', $slug)->exists()) {
            $slug = "{$base}_{$n}";
            $n++;
        }

        return $slug;
    }

    private function interpolate(string $body, array $data): string
    {
        foreach ($data as $k => $v) {
            $body = str_replace(['{{'.$k.'}}', '{{ '.$k.' }}'], (string) $v, $body);
        }

        return $body;
    }

    private function sampleData(): array
    {
        $user = Auth::user();

        return [
            'user_name' => $user?->name ?? 'Jane Doe',
            'first_name' => explode(' ', (string) ($user?->name ?? 'Jane'))[0],
            'email' => $user?->email ?? 'jane@example.com',
            'company_name' => NotificationSetting::getValue('company_name') ?? config('app.name'),
            'app_name' => config('app.name'),
            'period' => now()->subMonth()->format('F Y'),
            'code' => '482913',
            'action_url' => url('/dashboard'),
            'date' => now()->format('F j, Y'),
        ];
    }

    private function validateTemplate(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'subject' => ['required', 'string', 'max:200'],
            'body_html' => ['required', 'string'],
            'body_text' => ['nullable', 'string'],
            'category' => ['required', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);
    }

    /* ================================================ channels ============= */

    private function channelSettings(): array
    {
        $s = NotificationSetting::all()->mapWithKeys(fn ($r) => [$r->key => $r->value]);

        $secret = fn ($k) => filled($s[$k] ?? null) ? '••••••••••••' : null;

        return [
            'email' => [
                'enabled' => (bool) ($s['email_enabled'] ?? true),
                'from' => $s['mail_from_email'] ?? config('mail.from.address'),
                'host' => $s['mail_host'] ?? config('mail.mailers.smtp.host'),
                'configured' => filled($s['mail_from_email'] ?? config('mail.from.address')),
            ],
            'sms' => [
                'enabled' => (bool) ($s['sms_enabled'] ?? false),
                'provider' => $s['sms_provider'] ?? null,
                'from' => $s['sms_from'] ?? null,
                'api_key' => $secret('sms_api_key'),
                'configured' => filled($s['sms_api_key'] ?? null),
            ],
            'push' => [
                'enabled' => (bool) ($s['push_enabled'] ?? false),
                'fcm_key' => $secret('push_fcm_key'),
                'vapid_pub' => $s['push_vapid_pub'] ?? null,
                'configured' => filled($s['push_fcm_key'] ?? null),
            ],
            'inapp' => [
                'enabled' => (bool) ($s['inapp_enabled'] ?? true),
                'retention_days' => $s['inapp_retention_days'] ?? 90,
                'configured' => true,
            ],
        ];
    }

    public function channelsUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email_enabled' => ['boolean'], 'sms_enabled' => ['boolean'],
            'push_enabled' => ['boolean'], 'inapp_enabled' => ['boolean'],
            'mail_from_email' => ['nullable', 'email'], 'mail_host' => ['nullable', 'string', 'max:190'],
            'sms_provider' => ['nullable', 'string', 'max:50'], 'sms_from' => ['nullable', 'string', 'max:50'],
            'sms_api_key' => ['nullable', 'string', 'max:255'],
            'push_fcm_key' => ['nullable', 'string', 'max:500'], 'push_vapid_pub' => ['nullable', 'string', 'max:255'],
            'inapp_retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        foreach ($data as $key => $value) {
            // A masked secret means "unchanged" — never write the mask back over the real key.
            if ($value === null || $value === '' || $value === '••••••••••••') {
                continue;
            }
            NotificationSetting::setValue($key, is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        }

        // Booleans must persist even when false, which the filled() guard above skips.
        foreach (['email_enabled', 'sms_enabled', 'push_enabled', 'inapp_enabled'] as $flag) {
            if ($request->has($flag)) {
                NotificationSetting::setValue($flag, $request->boolean($flag) ? '1' : '0');
            }
        }

        Cache::forget('notifications.deliverability');
        $this->audit->log('notification.channels_updated', 'updated', null, 'Notification channels updated');

        return back()->with('success', 'Channel settings saved.');
    }

    public function channelTest(Request $request): RedirectResponse
    {
        $channel = $request->validate(['channel' => ['required', 'in:email,sms,push,inapp']])['channel'];
        $user = $request->user();

        try {
            $pipeline = '\\Aero\\Notifications\\Services\\Pipeline\\NotificationPipeline';
            if (class_exists($pipeline) && method_exists(app($pipeline), 'send')) {
                app($pipeline)->send($channel, $user, 'Test notification',
                    'This is a test notification from AEOS365. If you can read this, the channel works.');
            } else {
                Log::info("Test {$channel} notification requested (pipeline unavailable)");
            }
        } catch (\Throwable $e) {
            return back()->with('error', "Test failed: {$e->getMessage()}");
        }

        return back()->with('success', "Test notification queued via {$channel}.");
    }

    /* ============================================= preferences ============= */

    private function preferencePayload(): array
    {
        $userId = (int) Auth::id();
        $saved = UserNotificationPreference::getForUser($userId);

        $matrix = [];
        foreach (self::PREF_EVENTS as $event) {
            $row = ['key' => $event['key'], 'label' => $event['label'], 'hint' => $event['hint'], 'channels' => []];
            foreach (array_keys(self::PREF_CHANNELS) as $channel) {
                // Absent row = opted in (the model's own default), so a fresh user
                // sees the real defaults rather than an all-off matrix.
                $row['channels'][$channel] = (bool) ($saved[$event['key']][$channel]['enabled'] ?? true);
            }
            $matrix[] = $row;
        }

        return [
            'matrix' => $matrix,
            'channels' => self::PREF_CHANNELS,
            'digest' => UserNotificationPreference::getDigestFrequency($userId) ?? 'immediate',
            'quietHours' => UserNotificationPreference::getQuietHours($userId),
        ];
    }

    public function preferencesUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'matrix' => ['required', 'array'],
            'digest' => ['nullable', 'in:immediate,daily,weekly,off'],
            'quiet_hours_start' => ['nullable', 'date_format:H:i'],
            'quiet_hours_end' => ['nullable', 'date_format:H:i'],
        ]);

        $userId = (int) Auth::id();

        DB::transaction(function () use ($data, $userId) {
            foreach ($data['matrix'] as $eventKey => $channels) {
                foreach ((array) $channels as $channel => $enabled) {
                    UserNotificationPreference::setForUser($userId, (string) $eventKey, (string) $channel, (bool) $enabled, [
                        'digest_frequency' => $data['digest'] ?? 'immediate',
                        'quiet_hours_start' => $data['quiet_hours_start'] ?? null,
                        'quiet_hours_end' => $data['quiet_hours_end'] ?? null,
                    ]);
                }
            }
        });

        return back()->with('success', 'Notification preferences saved.');
    }

    public function preferencesMuteAll(): RedirectResponse
    {
        $userId = (int) Auth::id();

        DB::transaction(function () use ($userId) {
            foreach (self::PREF_EVENTS as $event) {
                foreach (array_keys(self::PREF_CHANNELS) as $channel) {
                    UserNotificationPreference::setForUser($userId, $event['key'], $channel, false);
                }
            }
        });

        return back()->with('success', 'All notifications muted.');
    }

    public function preferencesReset(): RedirectResponse
    {
        UserNotificationPreference::resetForUser((int) Auth::id());

        return back()->with('success', 'Preferences reset to defaults.');
    }
}
