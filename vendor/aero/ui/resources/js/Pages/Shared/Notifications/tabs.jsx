/**
 * Notifications command-centre tabs.
 *
 * Each tab owns one surface and every operation on it. All writes go through
 * Inertia router.* against NotificationCenterController; each route is HRMAC-gated
 * server-side, and the `can` map only decides what the UI bothers to render.
 */
import { useState, useMemo } from 'react';
import { router } from '@inertiajs/react';
import { Card, CardBody, Donut, useToast } from '@aero/ui';

/* ------------------------------------------------------------------ helpers */
export const fmtTime = (s) => {
  if (!s) return '—';
  try {
    return new Date(s).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
  } catch { return s; }
};
const truncate = (s, n) => (s ? (String(s).length > n ? `${String(s).slice(0, n)}…` : String(s)) : '—');

const STATUS_INTENT = { delivered: 'success', sent: 'success', pending: 'warning', failed: 'danger', bounced: 'danger' };
const CHANNEL_LABEL = { mail: 'Email', sms: 'SMS', push: 'Push', database: 'In-app' };

const Chip = ({ intent = 'neutral', children }) => (
  <span className={`nc-chip nc-chip--${intent}`}><i />{children}</span>
);

const opts = { preserveScroll: true, preserveState: true };

/** Shared select-all / bulk-action strip. */
function SelectionBar({ count, onClear, children }) {
  if (!count) return null;
  return (
    <div className="nc-selbar">
      <span className="nc-selbar__n">{count} selected</span>
      <button type="button" className="pc-btn pc-btn--sm" onClick={onClear}>Clear</button>
      <span className="nc-selbar__acts">{children}</span>
    </div>
  );
}

function Pager({ page, onPage }) {
  if (!page || page.last_page <= 1) return null;
  return (
    <div className="nc-pager">
      <span>Page {page.current_page} of {page.last_page} · {page.total} total</span>
      <span className="nc-pager__b">
        <button type="button" className="pc-btn pc-btn--sm" disabled={page.current_page <= 1} onClick={() => onPage(page.current_page - 1)}>Prev</button>
        <button type="button" className="pc-btn pc-btn--sm" disabled={page.current_page >= page.last_page} onClick={() => onPage(page.current_page + 1)}>Next</button>
      </span>
    </div>
  );
}

/** Row-selection state shared by every table tab. */
function useSelection() {
  const [ids, setIds] = useState([]);
  return {
    ids,
    clear: () => setIds([]),
    has: (id) => ids.includes(id),
    toggle: (id) => setIds((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id])),
    toggleAll: (all) => setIds((s) => (s.length === all.length ? [] : all)),
  };
}

/* ==================================================================== INBOX */
export function InboxTab({ inbox, filters, can, go, base }) {
  const toast = useToast();
  const sel = useSelection();
  const [search, setSearch] = useState(filters?.search ?? '');
  const [status, setStatus] = useState(filters?.status ?? '');

  const rows = inbox?.data ?? [];
  const allIds = rows.map((r) => r.id);

  const post = (url, data, msg) => router.post(url, data, {
    ...opts, onSuccess: () => { toast.success(msg); sel.clear(); }, onError: () => toast.error('That didn’t work.'),
  });

  return (
    <Card><CardBody>
      <div className="nc-ph">
        <div>
          <h3 className="nc-ph__t">Inbox</h3>
          <div className="nc-ph__s">{inbox?.unread ?? 0} unread of {inbox?.total ?? 0}</div>
        </div>
        <div className="pc-actions">
          {can?.inboxMarkRead && (inbox?.unread ?? 0) > 0 && (
            <button type="button" className="pc-btn pc-btn--sm" onClick={() => post(`${base}/inbox/read-all`, {}, 'All notifications marked as read.')}>
              Mark all read
            </button>
          )}
        </div>
      </div>

      <div className="nc-bar">
        <label className="pc-field nc-bar__grow"><span className="pc-field__label">Search</span>
          <input className="pc-input" value={search} onChange={(e) => setSearch(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && go({ search, status })} placeholder="Title or message…" />
        </label>
        <label className="pc-field"><span className="pc-field__label">Status</span>
          <select className="pc-input" value={status} onChange={(e) => { setStatus(e.target.value); go({ search, status: e.target.value }); }}>
            <option value="">All</option><option value="unread">Unread</option><option value="read">Read</option>
          </select>
        </label>
        <button type="button" className="pc-btn pc-btn--sm pc-btn--primary" onClick={() => go({ search, status })}>Filter</button>
        <button type="button" className="pc-btn pc-btn--sm" onClick={() => { setSearch(''); setStatus(''); go({}); }}>Reset</button>
      </div>

      <SelectionBar count={sel.ids.length} onClear={sel.clear}>
        {can?.inboxDelete && (
          <button type="button" className="pc-btn pc-btn--sm pc-btn--danger"
            onClick={() => post(`${base}/inbox/bulk-delete`, { ids: sel.ids }, `${sel.ids.length} notification(s) deleted.`)}>
            Delete selected
          </button>
        )}
      </SelectionBar>

      {rows.length === 0 ? (
        <div className="nc-empty">Nothing here. You’re all caught up.</div>
      ) : (
        <>
          <div className="nc-bar" style={{ marginBottom: 'var(--aeos-space-2)' }}>
            <label className="nc-dim" style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
              <input type="checkbox" checked={sel.ids.length === allIds.length && allIds.length > 0}
                onChange={() => sel.toggleAll(allIds)} /> Select all on this page
            </label>
          </div>
          <div className="nc-inbox">
            {rows.map((n) => (
              <div key={n.id} className={`nc-nrow${n.read ? '' : ' is-unread'}`}>
                <input type="checkbox" checked={sel.has(n.id)} onChange={() => sel.toggle(n.id)} aria-label={`Select ${n.title}`} />
                <span className={`nc-nico nc-nico--${n.severity}`} aria-hidden="true">
                  {n.severity === 'danger' ? '!' : n.severity === 'success' ? '✓' : n.severity === 'warning' ? '⏳' : '✦'}
                </span>
                <div className="nc-nbody">
                  <div className="nc-ntitle">
                    {n.title}
                    {!n.read && <Chip intent="info">New</Chip>}
                  </div>
                  {n.body && <div className="nc-ntext">{n.body}</div>}
                </div>
                <div className="nc-nmeta">
                  <span>{n.timeAgo}</span>
                  {can?.inboxMarkRead && (
                    <button type="button" className="pc-btn pc-btn--sm"
                      onClick={() => post(`${base}/inbox/${n.id}/${n.read ? 'unread' : 'read'}`, {}, n.read ? 'Marked as unread.' : 'Marked as read.')}>
                      {n.read ? 'Unread' : 'Read'}
                    </button>
                  )}
                  {can?.inboxDelete && (
                    <button type="button" className="pc-btn pc-btn--sm pc-btn--danger"
                      onClick={() => router.delete(`${base}/inbox/${n.id}`, { ...opts, onSuccess: () => toast.success('Notification deleted.') })}>
                      Delete
                    </button>
                  )}
                </div>
              </div>
            ))}
          </div>
          <Pager page={inbox} onPage={(p) => go({ search, status, page: p })} />
        </>
      )}
    </CardBody></Card>
  );
}

/* ============================================================ DELIVERY LOG */
export function LogTab({ logs, filters, can, go, onRow, base }) {
  const toast = useToast();
  const sel = useSelection();
  const [f, setF] = useState({
    search: filters?.search ?? '', channel: filters?.channel ?? '', status: filters?.status ?? '',
    date_from: filters?.date_from ?? '', date_to: filters?.date_to ?? '',
  });

  const rows = logs?.data ?? [];
  const allIds = rows.map((r) => r.id);
  const set = (k, v) => setF((s) => ({ ...s, [k]: v }));

  const post = (url, data, msg) => router.post(url, data, {
    ...opts, onSuccess: () => { toast.success(msg); sel.clear(); }, onError: () => toast.error('That didn’t work.'),
  });

  return (
    <Card><CardBody>
      <div className="nc-ph">
        <div>
          <h3 className="nc-ph__t">Delivery log</h3>
          <div className="nc-ph__s">Every notification sent across email, SMS, push and in-app</div>
        </div>
        <div className="pc-actions">
          {can?.logResend && (
            <button type="button" className="pc-btn pc-btn--sm"
              onClick={() => { if (window.confirm('Requeue every failed and bounced notification?')) post(`${base}/log/retry-failed`, {}, 'Failed notifications requeued.'); }}>
              Retry all failed
            </button>
          )}
          {can?.logExport && (
            <a className="pc-btn pc-btn--sm" href={`${base}/log/export?${new URLSearchParams(Object.entries(f).filter(([, v]) => v))}`}>
              Export CSV
            </a>
          )}
        </div>
      </div>

      <div className="nc-bar">
        <label className="pc-field nc-bar__grow"><span className="pc-field__label">Search</span>
          <input className="pc-input" value={f.search} onChange={(e) => set('search', e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && go(f)} placeholder="Recipient or subject…" />
        </label>
        <label className="pc-field"><span className="pc-field__label">Channel</span>
          <select className="pc-input" value={f.channel} onChange={(e) => set('channel', e.target.value)}>
            <option value="">All channels</option>
            {Object.entries(CHANNEL_LABEL).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
          </select>
        </label>
        <label className="pc-field"><span className="pc-field__label">Status</span>
          <select className="pc-input" value={f.status} onChange={(e) => set('status', e.target.value)}>
            <option value="">All statuses</option>
            {['delivered', 'sent', 'pending', 'failed', 'bounced'].map((s) => <option key={s} value={s}>{s}</option>)}
          </select>
        </label>
        <label className="pc-field"><span className="pc-field__label">From</span>
          <input type="date" className="pc-input" value={f.date_from} onChange={(e) => set('date_from', e.target.value)} /></label>
        <label className="pc-field"><span className="pc-field__label">To</span>
          <input type="date" className="pc-input" value={f.date_to} onChange={(e) => set('date_to', e.target.value)} /></label>
        <button type="button" className="pc-btn pc-btn--sm pc-btn--primary" onClick={() => go(f)}>Filter</button>
        <button type="button" className="pc-btn pc-btn--sm" onClick={() => { const e = { search: '', channel: '', status: '', date_from: '', date_to: '' }; setF(e); go({}); }}>Reset</button>
      </div>

      <SelectionBar count={sel.ids.length} onClear={sel.clear}>
        {can?.logResend && (
          <button type="button" className="pc-btn pc-btn--sm pc-btn--primary"
            onClick={() => post(`${base}/log/bulk-resend`, { ids: sel.ids }, `${sel.ids.length} notification(s) queued for resend.`)}>
            Resend selected
          </button>
        )}
      </SelectionBar>

      {rows.length === 0 ? (
        <div className="nc-empty">No notifications match these filters.</div>
      ) : (
        <>
          <div className="nc-tw">
            <table className="nc-table">
              <thead><tr>
                <th><input type="checkbox" aria-label="Select all"
                  checked={sel.ids.length === allIds.length && allIds.length > 0} onChange={() => sel.toggleAll(allIds)} /></th>
                <th>Recipient</th><th>Subject</th><th>Channel</th><th>Status</th><th>Tries</th><th>Sent</th><th className="nc-r" />
              </tr></thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.id} onClick={() => onRow(r)} style={{ cursor: 'pointer' }}>
                    <td onClick={(e) => e.stopPropagation()}>
                      <input type="checkbox" checked={sel.has(r.id)} onChange={() => sel.toggle(r.id)} aria-label={`Select ${r.recipient}`} />
                    </td>
                    <td className="nc-strong">{truncate(r.recipient, 30)}</td>
                    <td className="nc-dim">{truncate(r.subject, 36)}</td>
                    <td><Chip intent={r.channel === 'mail' ? 'info' : 'neutral'}>{CHANNEL_LABEL[r.channel] ?? r.channel}</Chip></td>
                    <td><Chip intent={STATUS_INTENT[r.status] ?? 'neutral'}>{r.status}</Chip></td>
                    <td className="nc-mono">{r.attempts}</td>
                    <td className="nc-dim">{fmtTime(r.created_at)}</td>
                    <td className="nc-r" onClick={(e) => e.stopPropagation()}>
                      {can?.logResend && (
                        <button type="button" className="pc-btn pc-btn--sm"
                          onClick={() => post(`${base}/log/${r.id}/resend`, {}, `Queued for resend to ${r.recipient}.`)}>
                          Resend
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pager page={logs} onPage={(p) => go({ ...f, page: p })} />
        </>
      )}
    </CardBody></Card>
  );
}

/* ================================================================= BOUNCES */
export function BouncesTab({ bounces, topDomains, filters, can, go, base }) {
  const toast = useToast();
  const sel = useSelection();
  const [search, setSearch] = useState(filters?.search ?? '');

  const rows = bounces?.data ?? [];
  const allIds = rows.map((r) => r.id);
  const max = Math.max(1, ...(topDomains ?? []).map((d) => Number(d.count ?? 0)));

  const suppress = (ids) => router.post(`${base}/bounces/suppress`, { ids }, {
    ...opts,
    onSuccess: () => { toast.success('Added to the suppression list.'); sel.clear(); },
    onError: () => toast.error('Could not suppress those addresses.'),
  });

  return (
    <Card><CardBody>
      <div className="nc-ph">
        <div>
          <h3 className="nc-ph__t">Bounces &amp; complaints</h3>
          <div className="nc-ph__s">Mail that never landed — suppress the dead addresses to protect your sender reputation</div>
        </div>
      </div>

      {(topDomains ?? []).length > 0 && (
        <>
          <div className="nc-ph__s" style={{ textTransform: 'uppercase', letterSpacing: '.06em', fontWeight: 650, marginBottom: 8 }}>
            Top bouncing domains
          </div>
          <div className="nc-dom">
            {topDomains.map((d) => (
              <div className="nc-dom__r" key={d.domain}>
                <span>{d.domain}</span>
                <div className="nc-track"><i className="is-warn" style={{ width: `${Math.round((d.count / max) * 100)}%` }} /></div>
                <b>{d.count}</b>
              </div>
            ))}
          </div>
        </>
      )}

      <div className="nc-bar">
        <label className="pc-field nc-bar__grow"><span className="pc-field__label">Search</span>
          <input className="pc-input" value={search} onChange={(e) => setSearch(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && go({ search })} placeholder="Recipient…" />
        </label>
        <button type="button" className="pc-btn pc-btn--sm pc-btn--primary" onClick={() => go({ search })}>Filter</button>
        <button type="button" className="pc-btn pc-btn--sm" onClick={() => { setSearch(''); go({}); }}>Reset</button>
      </div>

      <SelectionBar count={sel.ids.length} onClear={sel.clear}>
        {can?.bounceSuppress && (
          <button type="button" className="pc-btn pc-btn--sm pc-btn--danger" onClick={() => suppress(sel.ids)}>
            Suppress selected
          </button>
        )}
      </SelectionBar>

      {rows.length === 0 ? (
        <div className="nc-empty">No bounces. Your mail is landing.</div>
      ) : (
        <>
          <div className="nc-tw">
            <table className="nc-table">
              <thead><tr>
                <th><input type="checkbox" aria-label="Select all"
                  checked={sel.ids.length === allIds.length && allIds.length > 0} onChange={() => sel.toggleAll(allIds)} /></th>
                <th>Recipient</th><th>Subject</th><th>Status</th><th>Reason</th><th>When</th><th className="nc-r" />
              </tr></thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.id}>
                    <td><input type="checkbox" checked={sel.has(r.id)} onChange={() => sel.toggle(r.id)} aria-label={`Select ${r.recipient}`} /></td>
                    <td className="nc-strong">{truncate(r.recipient, 30)}</td>
                    <td className="nc-dim">{truncate(r.subject, 30)}</td>
                    <td><Chip intent="danger">{r.status}</Chip></td>
                    <td className="nc-dim">{truncate(r.error_message, 46)}</td>
                    <td className="nc-dim">{fmtTime(r.created_at)}</td>
                    <td className="nc-r">
                      {can?.bounceSuppress && (
                        <button type="button" className="pc-btn pc-btn--sm" onClick={() => suppress([r.id])}>Suppress</button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pager page={bounces} onPage={(p) => go({ search, page: p })} />
        </>
      )}
    </CardBody></Card>
  );
}

/* ============================================================= SUPPRESSION */
const REASONS = ['manual', 'bounce', 'complaint', 'unsubscribe'];
const REASON_INTENT = { bounce: 'danger', complaint: 'warning', unsubscribe: 'neutral', manual: 'info' };

export function SuppressionTab({ suppression, filters, can, go, base }) {
  const toast = useToast();
  const sel = useSelection();
  const [search, setSearch] = useState(filters?.search ?? '');
  const [reason, setReason] = useState(filters?.reason ?? '');
  const [form, setForm] = useState({ email: '', reason: 'manual', note: '' });

  const rows = suppression?.data ?? [];
  const allIds = rows.map((r) => r.id);

  const add = () => {
    if (!form.email) return toast.error('Enter an email address to suppress.');
    router.post(`${base}/suppression`, form, {
      ...opts,
      onSuccess: () => { toast.success(`${form.email} will no longer be emailed.`); setForm({ email: '', reason: 'manual', note: '' }); },
      onError: (e) => toast.error(e?.email ?? 'Could not add that address.'),
    });
  };

  return (
    <Card><CardBody>
      <div className="nc-ph">
        <div>
          <h3 className="nc-ph__t">Suppression list</h3>
          <div className="nc-ph__s">{suppression?.total ?? 0} address(es) this workspace will never email again</div>
        </div>
        {can?.suppressionExport && (
          <a className="pc-btn pc-btn--sm" href={`${base}/suppression/export?${new URLSearchParams({ ...(search && { search }), ...(reason && { reason }) })}`}>
            Export CSV
          </a>
        )}
      </div>

      {can?.suppressionAdd && (
        <div className="nc-bar">
          <label className="pc-field nc-bar__grow"><span className="pc-field__label">Email address</span>
            <input className="pc-input" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })}
              onKeyDown={(e) => e.key === 'Enter' && add()} placeholder="someone@example.com" />
          </label>
          <label className="pc-field"><span className="pc-field__label">Reason</span>
            <select className="pc-input" value={form.reason} onChange={(e) => setForm({ ...form, reason: e.target.value })}>
              {REASONS.map((r) => <option key={r} value={r}>{r}</option>)}
            </select>
          </label>
          <label className="pc-field nc-bar__grow"><span className="pc-field__label">Note (optional)</span>
            <input className="pc-input" value={form.note} onChange={(e) => setForm({ ...form, note: e.target.value })} placeholder="Why?" />
          </label>
          <button type="button" className="pc-btn pc-btn--sm pc-btn--primary" onClick={add}>Add to list</button>
        </div>
      )}

      <div className="nc-bar">
        <label className="pc-field nc-bar__grow"><span className="pc-field__label">Search</span>
          <input className="pc-input" value={search} onChange={(e) => setSearch(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && go({ search, reason })} placeholder="Filter the list…" />
        </label>
        <label className="pc-field"><span className="pc-field__label">Reason</span>
          <select className="pc-input" value={reason} onChange={(e) => { setReason(e.target.value); go({ search, reason: e.target.value }); }}>
            <option value="">All reasons</option>
            {REASONS.map((r) => <option key={r} value={r}>{r}</option>)}
          </select>
        </label>
        <button type="button" className="pc-btn pc-btn--sm pc-btn--primary" onClick={() => go({ search, reason })}>Filter</button>
      </div>

      <SelectionBar count={sel.ids.length} onClear={sel.clear}>
        {can?.suppressionRemove && (
          <button type="button" className="pc-btn pc-btn--sm pc-btn--danger"
            onClick={() => router.post(`${base}/suppression/bulk-delete`, { ids: sel.ids }, {
              ...opts, onSuccess: () => { toast.success('Removed.'); sel.clear(); },
            })}>
            Remove selected
          </button>
        )}
      </SelectionBar>

      {rows.length === 0 ? (
        <div className="nc-empty">No suppressed addresses.</div>
      ) : (
        <>
          <div className="nc-tw">
            <table className="nc-table">
              <thead><tr>
                <th><input type="checkbox" aria-label="Select all"
                  checked={sel.ids.length === allIds.length && allIds.length > 0} onChange={() => sel.toggleAll(allIds)} /></th>
                <th>Email</th><th>Reason</th><th>Note</th><th>Added</th><th className="nc-r" />
              </tr></thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.id}>
                    <td><input type="checkbox" checked={sel.has(r.id)} onChange={() => sel.toggle(r.id)} aria-label={`Select ${r.email}`} /></td>
                    <td className="nc-strong">{r.email}</td>
                    <td><Chip intent={REASON_INTENT[r.reason] ?? 'neutral'}>{r.reason}</Chip></td>
                    <td className="nc-dim">{truncate(r.note, 44)}</td>
                    <td className="nc-dim">{fmtTime(r.created_at)}</td>
                    <td className="nc-r">
                      {can?.suppressionRemove && (
                        <button type="button" className="pc-btn pc-btn--sm pc-btn--danger"
                          onClick={() => router.delete(`${base}/suppression/${r.id}`, {
                            ...opts, onSuccess: () => toast.success('Removed from the suppression list.'),
                          })}>
                          Remove
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pager page={suppression} onPage={(p) => go({ search, reason, page: p })} />
        </>
      )}
    </CardBody></Card>
  );
}

/* ========================================================== DELIVERABILITY */
const CHECK_INTENT = { pass: 'success', warn: 'warning', fail: 'danger' };
const CHECK_LABEL = { pass: 'Pass', warn: 'Missing', fail: 'Fail' };

export function DeliverabilityTab({ deliverability, can, base }) {
  const toast = useToast();
  const d = deliverability ?? {};
  const checks = Object.entries(d.checks ?? {});
  const score = d.score ?? 0;
  const intent = score >= 80 ? 'success' : score >= 50 ? 'warning' : 'danger';
  const color = `var(--aeos-${intent === 'success' ? 'success' : intent === 'warning' ? 'warning' : 'danger'})`;

  const copy = (value) => {
    navigator.clipboard?.writeText(value).then(
      () => toast.success('Record copied to your clipboard.'),
      () => toast.error('Could not copy.'),
    );
  };

  return (
    <Card><CardBody>
      <div className="nc-ph">
        <div>
          <h3 className="nc-ph__t">Deliverability</h3>
          <div className="nc-ph__s">DNS posture for <span className="nc-mono">{d.domain ?? '—'}</span> — will the internet trust your mail?</div>
        </div>
        {can?.deliverabilityTest && (
          <button type="button" className="pc-btn pc-btn--sm"
            onClick={() => router.post(`${base}/deliverability/recheck`, {}, {
              ...opts, preserveState: false, onSuccess: () => toast.success('DNS records re-checked.'),
            })}>
            Re-run checks
          </button>
        )}
      </div>

      <div className="nc-dgrid">
        <div style={{ display: 'grid', placeItems: 'center' }}>
          <Donut size={150} thickness={14}
            segments={[{ color, value: score }, { color: 'transparent', value: Math.max(0, 100 - score) }]}
            centerValue={String(score)} centerLabel="of 100" />
        </div>
        <div>
          {checks.map(([key, c]) => (
            <div className="nc-check" key={key}>
              <span className="nc-check__l">{c.label}</span>
              <Chip intent={CHECK_INTENT[c.status] ?? 'neutral'}>{CHECK_LABEL[c.status] ?? c.status}</Chip>
              <span className="nc-check__v" title={c.value || c.guide}>{c.value || c.guide}</span>
              {c.value && (
                <button type="button" className="pc-btn pc-btn--sm" onClick={() => copy(c.value)}>Copy</button>
              )}
            </div>
          ))}
        </div>
      </div>
    </CardBody></Card>
  );
}

/* =============================================================== TEMPLATES */
export function TemplatesTab({ templates, categories, filters, can, go, onEdit, onPreview, base, scope }) {
  const toast = useToast();
  const [search, setSearch] = useState(filters?.search ?? '');
  const [category, setCategory] = useState(filters?.category ?? '');
  const rows = templates ?? [];
  const isPlatform = scope === 'platform';

  const act = (url, msg) => router.post(url, {}, { ...opts, onSuccess: () => toast.success(msg) });

  return (
    <Card><CardBody>
      <div className="nc-ph">
        <div>
          <h3 className="nc-ph__t">Templates</h3>
          <div className="nc-ph__s">{rows.length} template(s) · {'{{variables}}'} are filled in when the message is sent</div>
        </div>
        {can?.templateCreate && (
          <button type="button" className="pc-btn pc-btn--sm pc-btn--primary" onClick={() => onEdit(isPlatform ? { is_global: true } : {})}>
            ＋ New{isPlatform ? ' shared template' : ' template'}
          </button>
        )}
      </div>

      <div className="nc-bar">
        <label className="pc-field nc-bar__grow"><span className="pc-field__label">Search</span>
          <input className="pc-input" value={search} onChange={(e) => setSearch(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && go({ search, category })} placeholder="Name or subject…" />
        </label>
        <label className="pc-field"><span className="pc-field__label">Category</span>
          <select className="pc-input" value={category} onChange={(e) => { setCategory(e.target.value); go({ search, category: e.target.value }); }}>
            <option value="">All categories</option>
            {(categories ?? []).map((c) => <option key={c} value={c}>{c}</option>)}
          </select>
        </label>
        <button type="button" className="pc-btn pc-btn--sm pc-btn--primary" onClick={() => go({ search, category })}>Filter</button>
      </div>

      {rows.length === 0 ? (
        <div className="nc-empty">No templates yet.</div>
      ) : (
        <div className="nc-tw">
          <table className="nc-table">
            <thead><tr>
              <th>Template</th><th>Category</th><th>Subject</th><th>Source</th><th>Active</th><th className="nc-r">Actions</th>
            </tr></thead>
            <tbody>
              {rows.map((t) => (
                <tr key={t.id}>
                  <td>
                    <div className="nc-strong">{t.name}</div>
                    <div className="nc-mono">{t.slug}</div>
                  </td>
                  <td><Chip intent="neutral">{t.category}</Chip></td>
                  <td className="nc-dim">{truncate(t.subject, 40)}</td>
                  <td>
                    {/* On a tenant, a global is a read-only shared-library entry until cloned. */}
                    {t.is_global
                      ? <Chip intent="success">Shared library</Chip>
                      : <Chip intent={t.is_locked ? 'info' : 'neutral'}>{t.is_locked ? 'Locked' : 'Custom'}</Chip>}
                  </td>
                  <td>
                    <button type="button" className="nc-sw" role="switch" aria-checked={!!t.is_active}
                      aria-label={`${t.is_active ? 'Deactivate' : 'Activate'} ${t.name}`}
                      disabled={!can?.templateUpdate || (t.is_global && !isPlatform)}
                      onClick={() => act(`${base}/templates/${t.id}/toggle`, t.is_active ? 'Template deactivated.' : 'Template activated.')} />
                  </td>
                  <td className="nc-r">
                    <span className="nc-rowacts">
                      <button type="button" className="pc-btn pc-btn--sm" onClick={() => onPreview(t)}>Preview</button>
                      {/* A tenant can't edit a shared-library global directly — it clones it first. */}
                      {t.is_global && !isPlatform ? (
                        can?.templateDuplicate && (
                          <button type="button" className="pc-btn pc-btn--sm pc-btn--primary"
                            onClick={() => act(`${base}/templates/${t.id}/clone`, 'Copied to your workspace — it starts inactive.')}>Copy to edit</button>
                        )
                      ) : (
                        <>
                          {can?.templateUpdate && <button type="button" className="pc-btn pc-btn--sm" onClick={() => onEdit(t)}>Edit</button>}
                          {can?.templateDuplicate && (
                            <button type="button" className="pc-btn pc-btn--sm"
                              onClick={() => act(`${base}/templates/${t.id}/duplicate`, 'Template duplicated.')}>Duplicate</button>
                          )}
                          {can?.templateDelete && !t.is_locked && (
                            <button type="button" className="pc-btn pc-btn--sm pc-btn--danger"
                              onClick={() => {
                                if (!window.confirm(`Delete "${t.name}"? This cannot be undone.`)) return;
                                router.delete(`${base}/templates/${t.id}`, { ...opts, onSuccess: () => toast.success('Template deleted.') });
                              }}>Delete</button>
                          )}
                        </>
                      )}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </CardBody></Card>
  );
}

/* ================================================================ CHANNELS */
const CHANNEL_META = {
  email: { label: 'Email', icon: '✉' },
  sms: { label: 'SMS', icon: '💬' },
  push: { label: 'Push', icon: '🔔' },
  inapp: { label: 'In-app', icon: '📥' },
};

export function ChannelsTab({ channels, can, base, inheritance }) {
  const toast = useToast();
  const [f, setF] = useState(() => ({
    email_enabled: !!channels?.email?.enabled,
    sms_enabled: !!channels?.sms?.enabled,
    push_enabled: !!channels?.push?.enabled,
    inapp_enabled: !!channels?.inapp?.enabled,
    mail_from_email: channels?.email?.from ?? '',
    mail_host: channels?.email?.host ?? '',
    sms_provider: channels?.sms?.provider ?? '',
    sms_from: channels?.sms?.from ?? '',
    sms_api_key: '',
    push_fcm_key: '',
    push_vapid_pub: channels?.push?.vapid_pub ?? '',
    inapp_retention_days: channels?.inapp?.retention_days ?? 90,
  }));
  const set = (k, v) => setF((s) => ({ ...s, [k]: v }));

  const save = () => router.post(`${base}/channels`, f, {
    ...opts, onSuccess: () => toast.success('Channel settings saved.'), onError: () => toast.error('Could not save.'),
  });

  const test = (channel) => router.post(`${base}/channels/test`, { channel }, {
    ...opts,
    onSuccess: () => toast.success(`Test queued via ${CHANNEL_META[channel].label}.`),
    onError: () => toast.error('Test failed.'),
  });

  const state = (key) => {
    const c = channels?.[key] ?? {};
    if (!c.configured) return <Chip intent="warning">Not configured</Chip>;
    return c.enabled ? <Chip intent="success">Live</Chip> : <Chip intent="neutral">Off</Chip>;
  };

  // Provider inheritance: is this channel using this workspace's own credentials,
  // or falling back to the platform/deployment default? (ProviderResolutionService)
  const inh = (key) => {
    const src = inheritance?.[key]?.source;
    if (!src) return null;
    return src === 'tenant'
      ? <div className="nc-dim" style={{ marginBottom: 'var(--aeos-space-2)' }}>Using this workspace’s own credentials</div>
      : <div className="nc-dim" style={{ marginBottom: 'var(--aeos-space-2)' }}>Inheriting the platform default — set your own to override</div>;
  };

  const Field = ({ label, k, type = 'text', placeholder }) => (
    <label className="pc-field" style={{ marginBottom: 'var(--aeos-space-3)' }}>
      <span className="pc-field__label">{label}</span>
      <input className="pc-input" type={type} value={f[k] ?? ''} disabled={!can?.channelConfigure}
        placeholder={placeholder} onChange={(e) => set(k, e.target.value)} />
    </label>
  );

  return (
    <Card><CardBody>
      <div className="nc-ph">
        <div>
          <h3 className="nc-ph__t">Channels</h3>
          <div className="nc-ph__s">Which delivery channels are live, and how they authenticate</div>
        </div>
        {can?.channelConfigure && (
          <button type="button" className="pc-btn pc-btn--sm pc-btn--primary" onClick={save}>Save changes</button>
        )}
      </div>

      <div className="nc-chgrid">
        {/* Email */}
        <div className="nc-chcard">
          <div className="nc-chhead">
            <div className="nc-chname">{CHANNEL_META.email.icon} Email {state('email')}</div>
            <button type="button" className="nc-sw" role="switch" aria-checked={f.email_enabled} aria-label="Enable email"
              disabled={!can?.channelConfigure} onClick={() => set('email_enabled', !f.email_enabled)} />
          </div>
          <Field label="From address" k="mail_from_email" type="email" placeholder="no-reply@yourcompany.com" />
          <Field label="SMTP host" k="mail_host" placeholder="smtp.provider.com" />
          {can?.channelTest && <button type="button" className="pc-btn pc-btn--sm" onClick={() => test('email')}>Send test email</button>}
        </div>

        {/* SMS */}
        <div className="nc-chcard">
          <div className="nc-chhead">
            <div className="nc-chname">{CHANNEL_META.sms.icon} SMS {state('sms')}</div>
            <button type="button" className="nc-sw" role="switch" aria-checked={f.sms_enabled} aria-label="Enable SMS"
              disabled={!can?.channelConfigure} onClick={() => set('sms_enabled', !f.sms_enabled)} />
          </div>
          <label className="pc-field" style={{ marginBottom: 'var(--aeos-space-3)' }}>
            <span className="pc-field__label">Provider</span>
            <select className="pc-input" value={f.sms_provider} disabled={!can?.channelConfigure}
              onChange={(e) => set('sms_provider', e.target.value)}>
              <option value="">Choose a provider…</option>
              {['twilio', 'vonage', 'local'].map((p) => <option key={p} value={p}>{p}</option>)}
            </select>
          </label>
          <Field label="API key" k="sms_api_key" type="password" placeholder={channels?.sms?.api_key ?? 'Paste your API key'} />
          <Field label="Sender ID" k="sms_from" placeholder="AEOS365" />
          {can?.channelTest && <button type="button" className="pc-btn pc-btn--sm" onClick={() => test('sms')}>Send test SMS</button>}
        </div>

        {/* Push */}
        <div className="nc-chcard">
          <div className="nc-chhead">
            <div className="nc-chname">{CHANNEL_META.push.icon} Push {state('push')}</div>
            <button type="button" className="nc-sw" role="switch" aria-checked={f.push_enabled} aria-label="Enable push"
              disabled={!can?.channelConfigure} onClick={() => set('push_enabled', !f.push_enabled)} />
          </div>
          <Field label="FCM server key" k="push_fcm_key" type="password" placeholder={channels?.push?.fcm_key ?? 'Paste your FCM key'} />
          <Field label="VAPID public key" k="push_vapid_pub" placeholder="BN…" />
          {can?.channelTest && <button type="button" className="pc-btn pc-btn--sm" onClick={() => test('push')}>Send test push</button>}
        </div>

        {/* In-app */}
        <div className="nc-chcard">
          <div className="nc-chhead">
            <div className="nc-chname">{CHANNEL_META.inapp.icon} In-app {state('inapp')}</div>
            <button type="button" className="nc-sw" role="switch" aria-checked={f.inapp_enabled} aria-label="Enable in-app"
              disabled={!can?.channelConfigure} onClick={() => set('inapp_enabled', !f.inapp_enabled)} />
          </div>
          <Field label="Keep notifications for (days)" k="inapp_retention_days" type="number" />
          {can?.channelTest && <button type="button" className="pc-btn pc-btn--sm" onClick={() => test('inapp')}>Send test notification</button>}
        </div>
      </div>
    </CardBody></Card>
  );
}

/* ============================================================= PREFERENCES */
export function PreferencesTab({ preferences, base }) {
  const toast = useToast();
  const [matrix, setMatrix] = useState(() => {
    const m = {};
    (preferences?.matrix ?? []).forEach((row) => { m[row.key] = { ...row.channels }; });
    return m;
  });
  const [digest, setDigest] = useState(preferences?.digest ?? 'immediate');
  const [quiet, setQuiet] = useState({
    start: preferences?.quietHours?.start ?? '',
    end: preferences?.quietHours?.end ?? '',
  });

  const channels = preferences?.channels ?? {};
  const rows = preferences?.matrix ?? [];

  const toggle = (event, channel) => setMatrix((m) => ({
    ...m, [event]: { ...m[event], [channel]: !m[event]?.[channel] },
  }));

  const save = () => router.post(`${base}/preferences`, {
    matrix, digest, quiet_hours_start: quiet.start || null, quiet_hours_end: quiet.end || null,
  }, { ...opts, onSuccess: () => toast.success('Preferences saved.'), onError: () => toast.error('Could not save.') });

  return (
    <Card><CardBody>
      <div className="nc-ph">
        <div>
          <h3 className="nc-ph__t">My preferences</h3>
          <div className="nc-ph__s">Choose how you personally want to be notified</div>
        </div>
        <div className="pc-actions">
          <button type="button" className="pc-btn pc-btn--sm"
            onClick={() => router.post(`${base}/preferences/reset`, {}, { ...opts, preserveState: false, onSuccess: () => toast.success('Reset to defaults.') })}>
            Reset to defaults
          </button>
          <button type="button" className="pc-btn pc-btn--sm pc-btn--danger"
            onClick={() => {
              if (!window.confirm('Turn off every notification on every channel?')) return;
              router.post(`${base}/preferences/mute`, {}, { ...opts, preserveState: false, onSuccess: () => toast.success('Everything muted.') });
            }}>
            Mute everything
          </button>
          <button type="button" className="pc-btn pc-btn--sm pc-btn--primary" onClick={save}>Save</button>
        </div>
      </div>

      <div className="nc-tw">
        <table className="nc-table nc-matrix">
          <thead><tr>
            <th>Event</th>
            {Object.entries(channels).map(([k, label]) => <th key={k}>{label}</th>)}
          </tr></thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.key}>
                <td>
                  <div className="nc-strong">{row.label}</div>
                  <div className="nc-dim">{row.hint}</div>
                </td>
                {Object.keys(channels).map((c) => (
                  <td key={c}>
                    <button type="button" className="nc-sw" role="switch"
                      aria-checked={!!matrix[row.key]?.[c]} aria-label={`${row.label} via ${channels[c]}`}
                      onClick={() => toggle(row.key, c)} />
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="nc-chgrid" style={{ marginTop: 'var(--aeos-space-4)' }}>
        <div className="nc-chcard">
          <div className="nc-chname" style={{ marginBottom: 'var(--aeos-space-3)' }}>Email digest</div>
          <label className="pc-field">
            <span className="pc-field__label">Instead of every message as it happens</span>
            <select className="pc-input" value={digest} onChange={(e) => setDigest(e.target.value)}>
              <option value="immediate">Send immediately</option>
              <option value="daily">Daily summary</option>
              <option value="weekly">Weekly summary</option>
              <option value="off">Don’t email me</option>
            </select>
          </label>
        </div>
        <div className="nc-chcard">
          <div className="nc-chname" style={{ marginBottom: 'var(--aeos-space-3)' }}>Quiet hours</div>
          <div className="pc-row2">
            <label className="pc-field"><span className="pc-field__label">From</span>
              <input type="time" className="pc-input" value={quiet.start} onChange={(e) => setQuiet({ ...quiet, start: e.target.value })} /></label>
            <label className="pc-field"><span className="pc-field__label">To</span>
              <input type="time" className="pc-input" value={quiet.end} onChange={(e) => setQuiet({ ...quiet, end: e.target.value })} /></label>
          </div>
        </div>
      </div>
    </CardBody></Card>
  );
}

/* ============================================ FLEET (platform-only) ======== */
export function FleetTab({ fleet }) {
  const summary = fleet?.summary;
  const worst = fleet?.worstOffenders ?? [];

  if (!summary) {
    return (
      <Card><CardBody>
        <div className="nc-ph"><div><h3 className="nc-ph__t">Fleet deliverability</h3>
          <div className="nc-ph__s">Cross-tenant mail health across the platform</div></div></div>
        <div className="nc-empty">No rollup data yet. It fills in once <span className="nc-mono">notifications:rollup</span> has run.</div>
      </CardBody></Card>
    );
  }

  const rate = summary.delivery_rate ?? 0;
  const kpis = [
    { l: 'Sent', v: (summary.sent + summary.delivered).toLocaleString() },
    { l: 'Delivery rate', v: `${rate}%`, mod: rate >= 95 ? 'up' : 'warn' },
    { l: 'Failed', v: (summary.failed ?? 0).toLocaleString(), mod: (summary.failed ?? 0) > 0 ? 'bad' : '' },
    { l: 'Bounced', v: (summary.bounced ?? 0).toLocaleString(), mod: (summary.bounced ?? 0) > 0 ? 'warn' : '' },
    { l: 'Suppressed', v: (summary.suppressed ?? 0).toLocaleString() },
  ];

  return (
    <Card><CardBody>
      <div className="nc-ph">
        <div><h3 className="nc-ph__t">Fleet deliverability</h3>
          <div className="nc-ph__s">Every tenant&rsquo;s mail health over the last {summary.days} days — read from the central rollup, never per-tenant queries</div></div>
      </div>

      <div className="nc-kpis" style={{ gridTemplateColumns: 'repeat(5,1fr)', marginBottom: 'var(--aeos-space-4)' }}>
        {kpis.map((k) => (
          <div className="nc-kpi" key={k.l} style={{ padding: '4px 0' }}>
            <div className="nc-kpi__label">{k.l}</div>
            <div className="nc-kpi__value">{k.v}</div>
            {k.mod && <div className={`nc-kpi__delta nc-kpi__delta--${k.mod}`}>{k.mod === 'up' ? 'healthy' : 'watch'}</div>}
          </div>
        ))}
      </div>

      <div className="nc-ph__s" style={{ textTransform: 'uppercase', letterSpacing: '.06em', fontWeight: 650, marginBottom: 8 }}>
        Worst offenders
      </div>
      {worst.length === 0 ? (
        <div className="nc-empty">No tenants are bouncing — the fleet is healthy.</div>
      ) : (
        <div className="nc-tw"><table className="nc-table">
          <thead><tr><th>Tenant</th><th className="nc-r">Sent</th><th className="nc-r">Delivered</th><th className="nc-r">Failed</th><th className="nc-r">Bounced</th><th className="nc-r">Bounce/fail</th></tr></thead>
          <tbody>
            {worst.map((t) => (
              <tr key={t.tenant_id}>
                <td className="nc-strong">{t.tenant_name}</td>
                <td className="nc-r nc-mono">{(t.sent + t.delivered).toLocaleString()}</td>
                <td className="nc-r nc-mono">{t.delivered.toLocaleString()}</td>
                <td className="nc-r nc-mono">{t.failed.toLocaleString()}</td>
                <td className="nc-r nc-mono">{t.bounced.toLocaleString()}</td>
                <td className="nc-r"><Chip intent={t.bounce_fail_rate > 10 ? 'danger' : t.bounce_fail_rate > 3 ? 'warning' : 'success'}>{t.bounce_fail_rate}%</Chip></td>
              </tr>
            ))}
          </tbody>
        </table></div>
      )}
    </CardBody></Card>
  );
}

/* ====================================== BROADCASTS (platform-only) ========= */
export function BroadcastsTab({ broadcast, can, base }) {
  const toast = useToast();
  const tenants = broadcast?.tenants ?? [];
  const [f, setF] = useState({ title: '', body: '', type: 'info', priority: 'normal', is_pinned: false });
  const [target, setTarget] = useState('all'); // 'all' | 'select'
  const [ids, setIds] = useState([]);
  const [sending, setSending] = useState(false);
  const set = (k, v) => setF((s) => ({ ...s, [k]: v }));

  const toggleTenant = (id) => setIds((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));

  const send = () => {
    if (!f.title.trim() || !f.body.trim()) return toast.error('A title and a message are required.');
    if (target === 'select' && ids.length === 0) return toast.error('Choose at least one tenant, or broadcast to all.');
    setSending(true);
    router.post(`${base}/broadcasts`, { ...f, tenant_ids: target === 'all' ? [] : ids }, {
      ...opts,
      onSuccess: () => { toast.success('Broadcast sent.'); setF({ title: '', body: '', type: 'info', priority: 'normal', is_pinned: false }); setIds([]); setTarget('all'); },
      onError: () => toast.error('The broadcast could not be sent.'),
      onFinish: () => setSending(false),
    });
  };

  return (
    <div className="nc-chgrid" style={{ gridTemplateColumns: 'minmax(0,1.3fr) minmax(0,1fr)', alignItems: 'start' }}>
      <Card><CardBody>
        <div className="nc-ph"><div><h3 className="nc-ph__t">Broadcast to tenants</h3>
          <div className="nc-ph__s">Push an announcement into tenant workspaces — it appears in their in-app notices</div></div></div>

        <label className="pc-field" style={{ marginBottom: 'var(--aeos-space-3)' }}>
          <span className="pc-field__label">Title</span>
          <input className="pc-input" value={f.title} onChange={(e) => set('title', e.target.value)} placeholder="Scheduled maintenance this weekend" maxLength={160} />
        </label>
        <label className="pc-field" style={{ marginBottom: 'var(--aeos-space-3)' }}>
          <span className="pc-field__label">Message</span>
          <textarea className="pc-input" rows={5} value={f.body} onChange={(e) => set('body', e.target.value)} placeholder="What do you want every tenant to know?" maxLength={5000} />
        </label>
        <div className="pc-row2">
          <label className="pc-field"><span className="pc-field__label">Type</span>
            <select className="pc-input" value={f.type} onChange={(e) => set('type', e.target.value)}>
              {['info', 'success', 'warning', 'danger'].map((t) => <option key={t} value={t}>{t}</option>)}
            </select></label>
          <label className="pc-field"><span className="pc-field__label">Priority</span>
            <select className="pc-input" value={f.priority} onChange={(e) => set('priority', e.target.value)}>
              {['low', 'normal', 'high', 'urgent'].map((p) => <option key={p} value={p}>{p}</option>)}
            </select></label>
        </div>
        <label className="pc-field" style={{ flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 'var(--aeos-space-3)' }}>
          <button type="button" className="nc-sw" role="switch" aria-checked={f.is_pinned} aria-label="Pin" onClick={() => set('is_pinned', !f.is_pinned)} />
          <span className="pc-field__label">Pin it to the top of their notices</span>
        </label>

        {can?.broadcasts && (
          <button type="button" className="pc-btn pc-btn--primary" style={{ width: '100%', justifyContent: 'center', marginTop: 'var(--aeos-space-4)' }}
            onClick={send} disabled={sending}>
            {sending ? 'Sending…' : target === 'all' ? `Send to all ${tenants.length} tenants` : `Send to ${ids.length} tenant(s)`}
          </button>
        )}
      </CardBody></Card>

      <Card><CardBody>
        <div className="nc-ph"><div><h3 className="nc-ph__t">Audience</h3><div className="nc-ph__s">{tenants.length} active tenants</div></div></div>
        <div className="pc-seg" role="tablist" style={{ marginBottom: 'var(--aeos-space-3)' }}>
          <button type="button" className="pc-seg__b" aria-pressed={target === 'all'} onClick={() => setTarget('all')}>All tenants</button>
          <button type="button" className="pc-seg__b" aria-pressed={target === 'select'} onClick={() => setTarget('select')}>Choose</button>
        </div>
        {target === 'select' && (
          <div className="nc-slist" style={{ maxHeight: '46vh', overflow: 'auto' }}>
            {tenants.map((t) => (
              <label className="nc-srow" key={t.id} style={{ cursor: 'pointer' }}>
                <input type="checkbox" checked={ids.includes(t.id)} onChange={() => toggleTenant(t.id)} />
                <span className="nc-srow__nm">{t.name}</span>
                <span className="nc-srow__ip nc-mono">{t.id}</span>
              </label>
            ))}
          </div>
        )}
        {target === 'all' && <div className="nc-dim">This message goes to every active tenant workspace.</div>}
      </CardBody></Card>
    </div>
  );
}
