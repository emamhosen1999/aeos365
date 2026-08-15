/**
 * Notifications command centre — SHARED by platform, tenant and standalone.
 *
 * One page owns the whole surface: inbox, delivery log, bounces, suppression,
 * deliverability, templates, channels and per-user preferences. Replaces five
 * scattered pages plus two endpoints that returned raw JSON to an Inertia visit
 * (the bell in AppChrome navigates here — it used to land on a JSON dump).
 *
 * Context-free, exactly like the backend: this component hardcodes NO url and NO
 * context. The host mounts the route and states where it lives; the controller
 * passes that down as props:
 *
 *   base   — url prefix every action path is built from ('/notifications',
 *            '/admin/notifications', …). Never hand-write a path in here.
 *   tabs   — which tabs this host mounts (platform may mount a subset).
 *   scope  — 'tenant' | 'platform' | 'standalone', for copy that must differ.
 *   can    — what this user may do. The route middleware is the real enforcement;
 *            this only decides what the UI bothers to render.
 *
 * Backend: NotificationCenterController (aero-notifications).
 */
import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Card, CardBody, WbDrawer, useToast } from '@aero/ui';
import App from '@/Pages/App.jsx';

import '../../Platform/Admin/Products/products.css';
import '../../Core/AuditLogs/audit.css';
import './notifications.css';

import {
  InboxTab, LogTab, BouncesTab, SuppressionTab,
  DeliverabilityTab, TemplatesTab, ChannelsTab, PreferencesTab,
  FleetTab, BroadcastsTab, fmtTime,
} from './tabs.jsx';

const TABS = [
  { id: 'inbox', label: 'Inbox' },
  { id: 'log', label: 'Delivery log' },
  { id: 'bounces', label: 'Bounces' },
  { id: 'suppression', label: 'Suppression' },
  { id: 'deliverability', label: 'Deliverability' },
  { id: 'templates', label: 'Templates' },
  { id: 'channels', label: 'Channels' },
  { id: 'fleet', label: 'Fleet' },
  { id: 'broadcasts', label: 'Broadcasts' },
  { id: 'preferences', label: 'Preferences' },
];

/* ------------------------------------------------------------------- rail */
function Rail({ stats }) {
  const s = stats ?? {};
  const rate = s.deliveryRate;
  const q = s.emailQuota;

  return (
    <div className="nc-rail">
      {q && !q.unlimited && (
        <div>
          <div className="nc-rail__h">Email this month</div>
          <div className="nc-rail__rows">
            <div className="nc-rail__row"><span>Used</span><b className={q.exhausted ? 'is-bad' : ''}>{q.used.toLocaleString()} / {q.limit.toLocaleString()}</b></div>
          </div>
          <div className="nc-track" style={{ marginTop: 8 }}>
            <i className={q.exhausted ? 'is-bad' : q.used / q.limit > 0.85 ? 'is-warn' : ''} style={{ width: `${Math.min(100, Math.round((q.used / q.limit) * 100))}%` }} />
          </div>
        </div>
      )}
      <div>
        <div className="nc-rail__h">Live now</div>
        <div className="nc-rail__rows">
          <div className="nc-rail__row"><span>Unread</span><b>{s.unread ?? 0}</b></div>
          <div className="nc-rail__row"><span>Queued</span><b>{s.queued ?? 0}</b></div>
          <div className="nc-rail__row"><span>Failed (24h)</span><b className={(s.failed24h ?? 0) > 0 ? 'is-bad' : ''}>{s.failed24h ?? 0}</b></div>
          <div className="nc-rail__row"><span>Suppressed</span><b className={(s.suppressed ?? 0) > 0 ? 'is-warn' : ''}>{s.suppressed ?? 0}</b></div>
        </div>
      </div>
      <div>
        <div className="nc-rail__h">Health</div>
        <div className="nc-rail__rows">
          <div className="nc-rail__row">
            <span>Delivery rate</span>
            <b className={rate != null && rate < 95 ? 'is-warn' : ''}>{rate == null ? '—' : `${rate}%`}</b>
          </div>
          <div className="nc-rail__row">
            <span>Deliverability</span>
            <b className={(s.deliverabilityScore ?? 0) < 80 ? 'is-warn' : ''}>{s.deliverabilityScore ?? 0}/100</b>
          </div>
          <div className="nc-rail__row"><span>Sent (24h)</span><b>{s.sent24h ?? 0}</b></div>
        </div>
      </div>
    </div>
  );
}

/* ==================================================================== page */
export default function NotificationsIndex(props) {
  const { tab, can = {}, stats = {}, filters = {}, base = '/notifications', tabs: mounted } = props;
  const toast = useToast();

  const [detail, setDetail] = useState(null);     // delivery-log row drawer
  const [editing, setEditing] = useState(null);   // template editor modal
  const [preview, setPreview] = useState(null);   // template preview modal

  /** Every tab filters through here — the active tab always rides along. */
  const go = (params = {}) => router.get(base, { tab, ...params }, {
    preserveState: true, preserveScroll: true,
  });
  const switchTab = (next) => router.get(base, { tab: next }, { preserveScroll: true });

  const openPreview = (template) => {
    fetch(`${base}/templates/${template.id}/preview`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then((r) => (r.ok ? r.json() : Promise.reject(new Error('failed'))))
      .then((data) => setPreview({ ...data, name: template.name }))
      .catch(() => toast.error('Could not render that template.'));
  };

  // A tab shows only if this host mounts it AND this user may see it.
  const visibleTabs = TABS.filter((t) => (mounted ? mounted.includes(t.id) : true) && can[t.id]);
  const rate = stats.deliveryRate;
  const score = stats.deliverabilityScore ?? 0;

  const kpis = [
    { label: 'Sent (24h)', value: (stats.sent24h ?? 0).toLocaleString(), delta: 'delivered or accepted' },
    {
      label: 'Delivery rate',
      value: rate == null ? '—' : rate, unit: rate == null ? '' : '%',
      delta: rate == null ? 'Nothing sent yet' : rate >= 95 ? 'Healthy' : 'Below target',
      mod: rate == null ? '' : rate >= 95 ? 'up' : 'warn',
      bar: rate == null ? null : { value: rate, mod: rate >= 95 ? 'is-ok' : 'is-warn' },
    },
    {
      label: 'Failed (24h)', value: (stats.failed24h ?? 0).toLocaleString(),
      delta: (stats.failed24h ?? 0) > 0 ? 'Needs attention' : 'None',
      mod: (stats.failed24h ?? 0) > 0 ? 'bad' : '',
    },
    { label: 'Queued', value: (stats.queued ?? 0).toLocaleString(), delta: (stats.queued ?? 0) > 0 ? 'Draining' : 'Empty' },
    { label: 'Suppressed', value: (stats.suppressed ?? 0).toLocaleString(), delta: 'addresses blocked' },
    {
      label: 'Deliverability', value: score, unit: '/100',
      delta: score >= 80 ? 'Trusted' : 'Improve DNS',
      mod: score >= 80 ? 'up' : 'warn',
      bar: { value: score, mod: score >= 80 ? 'is-ok' : 'is-warn' },
    },
  ];

  const tabProps = { ...props, can, filters, go, base };

  return (
    <div className="pc nc">
      <div className="pc-head">
        <div>
          <div className="pc-eyebrow"><span className="pc-eyebrow__dot" /> Workspace · Communications</div>
          <h1 className="pc-title">Notifications</h1>
          <div className="pc-sub">
            Every message this workspace sends and receives — your inbox, the delivery log across all
            channels, bounces, suppression, deliverability, templates, channels, and your own preferences.
          </div>
        </div>
      </div>

      <div className="nc-kpis">
        {kpis.map((k) => (
          <Card key={k.label}><CardBody>
            <div className="nc-kpi__label">{k.label}</div>
            <div className="nc-kpi__value">{k.value}{k.unit && <small>{k.unit}</small>}</div>
            <div className={`nc-kpi__delta${k.mod ? ` nc-kpi__delta--${k.mod}` : ''}`}>{k.delta}</div>
            {k.bar && <div className="nc-track"><i className={k.bar.mod} style={{ width: `${Math.min(100, k.bar.value)}%` }} /></div>}
          </CardBody></Card>
        ))}
      </div>

      <div className="nc-tabs" role="tablist">
        {visibleTabs.map((t) => (
          <button key={t.id} type="button" role="tab" aria-selected={tab === t.id}
            className={`nc-tab${tab === t.id ? ' is-active' : ''}`} onClick={() => switchTab(t.id)}>
            {t.label}
            {t.id === 'inbox' && (stats.unread ?? 0) > 0 && <span className="nc-tab__n">{stats.unread}</span>}
          </button>
        ))}
      </div>

      {tab === 'inbox' && <InboxTab {...tabProps} />}
      {tab === 'log' && <LogTab {...tabProps} onRow={setDetail} />}
      {tab === 'bounces' && <BouncesTab {...tabProps} />}
      {tab === 'suppression' && <SuppressionTab {...tabProps} />}
      {tab === 'deliverability' && <DeliverabilityTab {...tabProps} />}
      {tab === 'templates' && <TemplatesTab {...tabProps} onEdit={setEditing} onPreview={openPreview} />}
      {tab === 'channels' && <ChannelsTab {...tabProps} />}
      {tab === 'fleet' && <FleetTab {...tabProps} />}
      {tab === 'broadcasts' && <BroadcastsTab {...tabProps} />}
      {tab === 'preferences' && <PreferencesTab {...tabProps} />}

      {detail && <LogDrawer row={detail} onClose={() => setDetail(null)} />}
      {editing && <TemplateModal template={editing} base={base} onClose={() => setEditing(null)} />}
      {preview && <PreviewModal preview={preview} onClose={() => setPreview(null)} />}
    </div>
  );
}

/* ------------------------------------------------------- delivery-log drawer */
function LogDrawer({ row, onClose }) {
  const fields = [
    ['Recipient', row.recipient], ['Subject', row.subject], ['Channel', row.channel],
    ['Status', row.status], ['Attempts', `${row.attempts} of ${row.max_attempts}`],
    ['Event', row.event_type], ['Sent', fmtTime(row.sent_at)], ['Delivered', fmtTime(row.delivered_at)],
    ['Created', fmtTime(row.created_at)], ['Idempotency key', row.idempotency_key],
  ];

  return (
    <WbDrawer open onClose={onClose} ariaLabel="Notification detail"
      head={
        <div className="au-dr-top">
          <div>
            <div className="au-dr-title">{row.subject || 'Notification'}</div>
            <div className="au-dr-code">{row.recipient}</div>
          </div>
          <button type="button" className="wb-drawer__x" onClick={onClose} aria-label="Close">✕</button>
        </div>
      }
    >
      {fields.filter(([, v]) => v != null && v !== '' && v !== '—').map(([k, v]) => (
        <div className="pc-drow" key={k}>
          <span className="pc-drow__k">{k}</span>
          <span className="pc-drow__v au-break">{v}</span>
        </div>
      ))}

      {row.error_message && (
        <>
          <div className="au-dr-sec">Why it failed</div>
          <pre className="au-json">{row.error_message}</pre>
        </>
      )}
      {row.content && (
        <>
          <div className="au-dr-sec">Message</div>
          <pre className="au-json">{row.content}</pre>
        </>
      )}
    </WbDrawer>
  );
}

/* --------------------------------------------------------- template editor */
const VARIABLES = ['user_name', 'first_name', 'email', 'company_name', 'app_name', 'period', 'code', 'action_url', 'date'];

function TemplateModal({ template, base, onClose }) {
  const toast = useToast();
  const isNew = !template.id;

  const [f, setF] = useState({
    name: template.name ?? '',
    subject: template.subject ?? '',
    category: template.category ?? 'transactional',
    body_html: template.body_html ?? '',
    body_text: template.body_text ?? '',
    is_active: template.is_active ?? true,
  });
  const [saving, setSaving] = useState(false);
  const set = (k, v) => setF((s) => ({ ...s, [k]: v }));

  const submit = () => {
    setSaving(true);
    const done = {
      preserveScroll: true,
      onSuccess: () => { toast.success(isNew ? 'Template created.' : 'Template updated.'); onClose(); },
      onError: () => toast.error('Check the fields and try again.'),
      onFinish: () => setSaving(false),
    };

    if (isNew) {
      router.post(`${base}/templates`, f, done);
    } else {
      router.put(`${base}/templates/${template.id}`, f, done);
    }
  };

  return (
    <div className="pc-modal-overlay" onClick={onClose}>
      <div className="pc-modal" role="dialog" aria-modal="true" onClick={(e) => e.stopPropagation()}>
        <h2 className="pc-modal__title">{isNew ? 'New template' : `Edit ${template.name}`}</h2>
        <div className="pc-modal__sub">
          {template.is_locked
            ? 'A locked template — the app itself sends this. You can change the wording; it cannot be deleted.'
            : isNew ? 'New custom template for this workspace.' : 'A custom template belonging to this workspace.'}
        </div>

        <div className="pc-form">
          <div className="pc-row2">
            <label className="pc-field"><span className="pc-field__label">Name</span>
              <input className="pc-input" value={f.name} onChange={(e) => set('name', e.target.value)} placeholder="Welcome email" /></label>
            <label className="pc-field"><span className="pc-field__label">Category</span>
              <select className="pc-input" value={f.category} onChange={(e) => set('category', e.target.value)}>
                {['transactional', 'system', 'marketing'].map((c) => <option key={c} value={c}>{c}</option>)}
              </select></label>
          </div>

          <label className="pc-field"><span className="pc-field__label">Subject</span>
            <input className="pc-input" value={f.subject} onChange={(e) => set('subject', e.target.value)}
              placeholder="Welcome to {{company_name}}" /></label>

          <label className="pc-field"><span className="pc-field__label">Body (HTML)</span>
            <textarea className="pc-input" rows={9} value={f.body_html}
              onChange={(e) => set('body_html', e.target.value)} placeholder="<p>Hello {{first_name}},</p>" /></label>

          <label className="pc-field"><span className="pc-field__label">Plain-text fallback (optional)</span>
            <textarea className="pc-input" rows={3} value={f.body_text}
              onChange={(e) => set('body_text', e.target.value)} placeholder="Hello {{first_name}}," /></label>

          <div>
            <span className="pc-field__label">Insert a variable</span>
            <div className="nc-varlist">
              {VARIABLES.map((v) => (
                <button type="button" key={v} className="nc-var"
                  onClick={() => set('body_html', `${f.body_html}{{${v}}}`)}>{`{{${v}}}`}</button>
              ))}
            </div>
          </div>

          <label className="pc-field" style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}>
            <button type="button" className="nc-sw" role="switch" aria-checked={!!f.is_active}
              aria-label="Active" onClick={() => set('is_active', !f.is_active)} />
            <span className="pc-field__label">Active — the app may send this template</span>
          </label>
        </div>

        <div className="pc-detail__actions">
          <button type="button" className="pc-btn" onClick={onClose}>Cancel</button>
          <button type="button" className="pc-btn pc-btn--primary" onClick={submit} disabled={saving}>
            {saving ? 'Saving…' : isNew ? 'Create template' : 'Save changes'}
          </button>
        </div>
      </div>
    </div>
  );
}

/* -------------------------------------------------------- template preview */
function PreviewModal({ preview, onClose }) {
  return (
    <div className="pc-modal-overlay" onClick={onClose}>
      <div className="pc-modal" role="dialog" aria-modal="true" onClick={(e) => e.stopPropagation()}>
        <h2 className="pc-modal__title">{preview.name}</h2>
        <div className="pc-modal__sub">Rendered with sample data — this is what the recipient sees.</div>

        <div className="pc-drow">
          <span className="pc-drow__k">Subject</span>
          <span className="pc-drow__v au-break">{preview.subject}</span>
        </div>

        {/* Template HTML is authored by tenant admins with the templates.create
            permission — the same trust level as any other admin-authored content. */}
        <div className="nc-preview" dangerouslySetInnerHTML={{ __html: preview.html }} />

        <div className="pc-detail__actions">
          <button type="button" className="pc-btn" onClick={onClose}>Close</button>
        </div>
      </div>
    </div>
  );
}

NotificationsIndex.layout = (page) => (
  <App title="Notifications" railTitle="Notifications" rail={<Rail stats={page.props.stats} />}>
    {page}
  </App>
);
