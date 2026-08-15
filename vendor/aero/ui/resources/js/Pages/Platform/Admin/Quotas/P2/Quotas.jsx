import { useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';

import App from '@/Pages/App.jsx';
import {
  Card, CardBody,
  AreaTrend, AreaSpark, Donut,
  useWorkbench, useCtxMenu, useHRMAC, useToast,
  WbToolbar, WbSearch, WbViews, WbBulkBar, WbColumns, WbTable, WbFooter, WbDrawer,
} from '@aero/ui';

import '../../Products/products.css';
import '../../Billing/P2/subscriptions.css';
import './quotas.css';

/* ---------------- glyphs ---------------- */
const svg = (p) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" width="18" height="18" strokeLinecap="round" strokeLinejoin="round">{p}</svg>;
const Glyph = {
  export: svg(<><path d="M12 3v12M7 10l5 5 5-5" /><path d="M4 21h16" /></>),
  plus: svg(<><path d="M12 5v14M5 12h14" /></>),
  scan: svg(<><circle cx="12" cy="12" r="3" /><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17.1 17.1l2.1 2.1M4.9 19.1 7 17M17 7l2.1-2.1" /></>),
};

/* ---------------- domain maps ---------------- */
const STATUS = {
  ok: { label: 'OK', color: 'var(--aeos-success)', cls: 'qt-s-ok' },
  warning: { label: 'Warning', color: 'var(--aeos-warning)', cls: 'qt-s-warning' },
  critical: { label: 'Critical', color: '#fb923c', cls: 'qt-s-critical' },
  exceeded: { label: 'Exceeded', color: 'var(--aeos-danger)', cls: 'qt-s-exceeded' },
  unlimited: { label: 'Unlimited', color: '#22d3ee', cls: 'qt-s-unlimited' },
  unset: { label: 'Unset', color: 'var(--aeos-text-muted)', cls: 'qt-s-unset' },
};
const SOURCE = {
  override: { label: 'Override', cls: 'qt-src--override' },
  plan: { label: 'Plan', cls: 'qt-src--plan' },
  policy: { label: 'Policy', cls: 'qt-src--policy' },
  legacy: { label: 'Legacy', cls: 'qt-src--legacy' },
  unset: { label: 'Unset', cls: 'qt-src--unset' },
};
const ACTION = {
  warn: { label: 'Warn', color: '#22d3ee', cls: 'qt-act--warn' },
  throttle: { label: 'Throttle', color: 'var(--aeos-warning)', cls: 'qt-act--throttle' },
  block: { label: 'Block', color: 'var(--aeos-danger)', cls: 'qt-act--block' },
};
const LEVEL = {
  warning: { label: 'Warning', color: 'var(--aeos-warning)', cls: 'qt-lvl--warning' },
  critical: { label: 'Critical', color: '#fb923c', cls: 'qt-lvl--critical' },
  block: { label: 'Block', color: 'var(--aeos-danger)', cls: 'qt-lvl--block' },
};
const INF = '∞';

const initials = (n) => (n || '—').split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
const num = (n) => Number(n ?? 0).toLocaleString('en-US', { maximumFractionDigits: 2 });
const fmtDate = (s) => { if (!s) return '—'; try { return new Date(s).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }); } catch { return s; } };
const fmtShort = (s) => { if (!s) return '—'; try { return new Date(s).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }); } catch { return s; } };
const toLocalInput = (iso) => { if (!iso) return ''; const d = new Date(iso); if (Number.isNaN(d.getTime())) return ''; const pad = (n) => String(n).padStart(2, '0'); return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`; };
const relDays = (iso) => { if (!iso) return ''; const diff = Math.ceil((new Date(iso).getTime() - Date.now()) / 86400000); if (diff < 0) return 'expired'; if (diff === 0) return 'today'; return `in ${diff}d`; };
const pctColor = (status) => STATUS[status]?.color ?? STATUS.unset.color;
// Aggregate status for a resource bar. `over` / `at_risk` are COUNTS of
// outlier tenants, not flags — the bar shows fleet-wide utilisation, so its
// colour follows that aggregate against the policy thresholds (the outlier
// counts are surfaced separately in the bar's meta line).
const resStatus = (h) => {
  if (!((h.granted ?? 0) > 0)) return 'unlimited';
  const pct = h.pct ?? 0;
  const warn = h.warn_at ?? 80;
  const hard = h.hard_at ?? 100;
  const crit = warn + Math.round((hard - warn) / 2);
  if (pct >= hard) return 'exceeded';
  if (pct >= crit) return 'critical';
  if (pct >= warn) return 'warning';
  return 'ok';
};

/* ---------------- api helper ---------------- */
const xsrf = () => decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
const api = (method, url, body) => fetch(url, {
  method, credentials: 'same-origin',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf() },
  body: body ? JSON.stringify(body) : undefined,
}).then((r) => (r.ok ? r.json().catch(() => ({})) : r.json().then((j) => Promise.reject(new Error(j.message || `HTTP ${r.status}`)), () => Promise.reject(new Error(`HTTP ${r.status}`)))));
const getJson = (url) => fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then((r) => (r.ok ? r.json() : Promise.reject(new Error(String(r.status)))));
const reload = () => router.reload({ only: ['overview'] });

/* ---------------- rail ---------------- */
function QuotasRail({ overview }) {
  const o = overview ?? {};
  const s = o.stats ?? {};
  const pressure = o.pressure ?? [];
  const overrides = o.overrides ?? [];
  const expiring = overrides.filter((r) => r.expiring_soon).slice(0, 6);
  const top = pressure.slice(0, 3);
  return (
    <div className="pc-rail">
      <div>
        <div className="pc-panel-h__title">Capacity pulse</div>
        <div className="pc-rail__rows">
          <div className="pc-rail__row"><span>Fleet utilization</span><b>{Math.round(s.fleet_utilization ?? 0)}%</b></div>
          <div className="pc-rail__row"><span>At risk</span><b className="qt-amber">{s.at_risk ?? 0}</b></div>
          <div className="pc-rail__row"><span>Over limit</span><b className="qt-red">{s.over_limit ?? 0}</b></div>
          <div className="pc-rail__row"><span>Active overrides</span><b>{s.overrides_active ?? 0}</b></div>
          <div className="pc-rail__row"><span>Open warnings</span><b className="qt-cyan">{s.warnings_open ?? 0}</b></div>
        </div>
      </div>
      {top.length > 0 && (
        <div>
          <div className="pc-panel-h__title">Tightest headroom</div>
          <div className="qt-railq">
            {top.map((p) => (
              <div key={`${p.tenant_id}-${p.resource}`} className="qt-railq__it">
                <span className="qt-railq__nm">{p.tenant}</span>
                <span className="qt-railq__sub" style={{ color: pctColor(p.status) }}>{Math.round(p.pct)}%</span>
              </div>
            ))}
          </div>
        </div>
      )}
      {expiring.length > 0 && (
        <div>
          <div className="pc-panel-h__title">Expiring overrides</div>
          <div className="qt-railq">
            {expiring.map((r) => (
              <div key={r.id} className="qt-railq__it">
                <span className="qt-railq__nm">{r.tenant} · {r.resource_label ?? r.resource}</span>
                <span className="qt-railq__sub">{relDays(r.expires_at)}</span>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

/* ---------------- override modal ---------------- */
function OverrideModal({ tenantId, resource, tenants, resources, matrix, overrides, lockTenant, lockResource, onClose, onDone }) {
  const [d, setD] = useState(() => {
    const existing = overrides.find((o) => o.tenant_id === tenantId && o.resource === resource);
    return {
      tenant_id: tenantId ?? '',
      resource: resource ?? '',
      unlimited: existing?.unlimited ?? false,
      limit_value: existing ? existing.limit_value : '',
      reason: existing?.reason ?? '',
      expires_at: existing ? toLocalInput(existing.expires_at) : '',
    };
  });
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState(null);
  const set = (k, v) => setD((p) => ({ ...p, [k]: v }));

  const row = matrix.find((r) => r.tenant_id === d.tenant_id && r.resource === d.resource);
  const res = resources.find((r) => r.key === d.resource);
  const editing = row?.source === 'override';

  const submit = (e) => {
    e.preventDefault();
    if (!d.tenant_id) { setErr('Choose a tenant.'); return; }
    if (!d.resource) { setErr('Choose a resource.'); return; }
    if (!d.unlimited && (d.limit_value === '' || Number.isNaN(Number(d.limit_value)) || Number(d.limit_value) < 0)) { setErr('Enter a valid limit.'); return; }
    setBusy(true); setErr(null);
    const body = {
      resource: d.resource,
      limit_value: d.unlimited ? 0 : Number(d.limit_value),
      reason: d.reason.trim() || undefined,
      expires_at: d.expires_at || undefined,
    };
    api('POST', `/quotas/${d.tenant_id}/override`, body)
      .then(() => onDone(editing ? 'Override updated.' : 'Override saved.'))
      .catch((e2) => { setErr(e2.message); setBusy(false); });
  };

  return (
    <div className="pc-modal-overlay" onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
      <div className="pc-modal" role="dialog" aria-modal="true">
        <h2 className="pc-modal__title">{editing ? 'Edit override' : 'New override'}</h2>
        <div className="pc-modal__sub">Set a per-tenant limit that overrides the plan or policy default.</div>
        <form className="pc-form" onSubmit={submit}>
          <div className="qt-form-grid">
            <div className="pc-field">
              <label className="pc-field__label">Tenant *</label>
              <select className="pc-input" value={d.tenant_id} onChange={(e) => set('tenant_id', e.target.value)} disabled={lockTenant} autoFocus={!lockTenant}>
                <option value="" disabled>Choose…</option>
                {tenants.map((t) => <option key={t.id} value={t.id}>{t.name}{t.subdomain ? ` — ${t.subdomain}` : ''}</option>)}
              </select>
            </div>
            <div className="pc-field">
              <label className="pc-field__label">Resource *</label>
              <select className="pc-input" value={d.resource} onChange={(e) => set('resource', e.target.value)} disabled={lockResource}>
                <option value="" disabled>Choose…</option>
                {resources.map((r) => <option key={r.key} value={r.key}>{r.label}</option>)}
              </select>
            </div>
            <div className="pc-field">
              <label className="pc-field__label">New limit{res?.unit ? ` (${res.unit})` : ''}</label>
              <input className="pc-input" type="number" min="0" value={d.unlimited ? '' : d.limit_value} disabled={d.unlimited} onChange={(e) => set('limit_value', e.target.value)} placeholder={d.unlimited ? INF : '0'} />
            </div>
            <div className="pc-field">
              <label className="pc-check"><input type="checkbox" checked={d.unlimited} onChange={(e) => set('unlimited', e.target.checked)} /> Unlimited</label>
            </div>
            <div className="pc-field">
              <label className="pc-field__label">Reason</label>
              <input className="pc-input" value={d.reason} onChange={(e) => set('reason', e.target.value)} placeholder="Why this override exists…" />
            </div>
            <div className="pc-field">
              <label className="pc-field__label">Expires</label>
              <input className="pc-input" type="datetime-local" value={d.expires_at} onChange={(e) => set('expires_at', e.target.value)} />
              <span className="pc-field__hint">Leave empty for a permanent override.</span>
            </div>
          </div>
          {row && (
            <div className="qt-usagehint">
              Current usage — <b>{num(row.used)} {row.unit}</b> ({row.unlimited ? '—' : `${Math.round(row.pct)}%`}) · resulting headroom{' '}
              {d.unlimited || d.limit_value === '' ? INF : `${num(Math.max(Number(d.limit_value) - row.used, 0))} ${row.unit}`}.
              <div className="qt-usagehint__note">Limit can't be set below current usage.</div>
            </div>
          )}
          {err && <span className="pc-field__err">{err}</span>}
          <div className="pc-modal__actions"><span className="pc-spacer" /><button type="button" className="pc-btn" onClick={onClose}>Cancel</button><button type="submit" className="pc-btn pc-btn--primary" disabled={busy}>{busy ? 'Saving…' : 'Save override'}</button></div>
        </form>
      </div>
    </div>
  );
}

/* ---------------- extend / expiry modal ---------------- */
function ExtendModal({ override, onClose, onDone }) {
  const [expires, setExpires] = useState(() => toLocalInput(override.expires_at));
  const [permanent, setPermanent] = useState(!override.expires_at);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState(null);
  const submit = (e) => {
    e.preventDefault();
    setBusy(true); setErr(null);
    api('PUT', `/quotas/overrides/${override.id}/extend`, { expires_at: permanent ? null : (expires || null) })
      .then(() => onDone(permanent ? 'Override made permanent.' : 'Expiry updated.'))
      .catch((e2) => { setErr(e2.message); setBusy(false); });
  };
  return (
    <div className="pc-modal-overlay" onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
      <div className="pc-modal pc-modal--sm" role="dialog" aria-modal="true">
        <h2 className="pc-modal__title">Extend override</h2>
        <div className="pc-modal__sub">{override.tenant} — {override.resource_label ?? override.resource}</div>
        <form className="pc-form" onSubmit={submit}>
          <div className="pc-field"><label className="pc-check"><input type="checkbox" checked={permanent} onChange={(e) => setPermanent(e.target.checked)} /> Make permanent</label></div>
          {!permanent && (
            <div className="pc-field">
              <label className="pc-field__label">New expiry</label>
              <input className="pc-input" type="datetime-local" value={expires} onChange={(e) => setExpires(e.target.value)} autoFocus />
            </div>
          )}
          {err && <span className="pc-field__err">{err}</span>}
          <div className="pc-modal__actions"><span className="pc-spacer" /><button type="button" className="pc-btn" onClick={onClose}>Cancel</button><button type="submit" className="pc-btn pc-btn--primary" disabled={busy}>{busy ? 'Saving…' : 'Save'}</button></div>
        </form>
      </div>
    </div>
  );
}

/* ---------------- policy modal ---------------- */
function PolicyModal({ policy, resources, policies, actionOptions, onClose, onDone }) {
  const editing = !!policy;
  const [d, setD] = useState(() => ({
    resource: policy?.resource ?? '',
    unlimited: policy ? policy.unlimited : false,
    default_limit: policy?.default_limit ?? '',
    warning_threshold_pct: policy?.warning_threshold_pct ?? 80,
    hard_limit_pct: policy?.hard_limit_pct ?? 100,
    action: policy?.action ?? actionOptions[0] ?? 'warn',
  }));
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState(null);
  const [preview, setPreview] = useState(null);
  const set = (k, v) => setD((p) => ({ ...p, [k]: v }));

  const available = editing ? resources.filter((r) => r.key === policy.resource) : resources.filter((r) => !policies.some((p) => p.resource === r.key));
  const res = resources.find((r) => r.key === d.resource);

  useEffect(() => {
    if (!d.resource) { setPreview(null); return undefined; }
    const t = setTimeout(() => {
      api('POST', '/quotas/policies/preview', {
        resource: d.resource,
        default_limit: d.unlimited ? 0 : (Number(d.default_limit) || 0),
        warning_threshold_pct: Number(d.warning_threshold_pct) || 0,
        hard_limit_pct: Number(d.hard_limit_pct) || 0,
      }).then(setPreview).catch(() => setPreview(null));
    }, 350);
    return () => clearTimeout(t);
  }, [d.resource, d.unlimited, d.default_limit, d.warning_threshold_pct, d.hard_limit_pct]);

  const submit = (e) => {
    e.preventDefault();
    if (!d.resource) { setErr('Choose a resource.'); return; }
    if (Number(d.warning_threshold_pct) >= Number(d.hard_limit_pct)) { setErr('Warn-at % must be lower than hard-at %.'); return; }
    setBusy(true); setErr(null);
    api('PUT', '/quotas/settings', {
      resource: d.resource,
      default_limit: d.unlimited ? 0 : (Number(d.default_limit) || 0),
      warning_threshold_pct: Number(d.warning_threshold_pct),
      hard_limit_pct: Number(d.hard_limit_pct),
      action: d.action,
    }).then(() => onDone(editing ? 'Policy updated.' : 'Policy created.')).catch((e2) => { setErr(e2.message); setBusy(false); });
  };

  return (
    <div className="pc-modal-overlay" onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
      <div className="pc-modal" role="dialog" aria-modal="true">
        <h2 className="pc-modal__title">{editing ? `Edit policy — ${policy.label ?? policy.resource}` : 'New policy'}</h2>
        <div className="pc-modal__sub">Default limit, thresholds and the enforcement action for this resource, fleet-wide.</div>
        <form className="pc-form" onSubmit={submit}>
          <div className="qt-form-grid">
            <div className="pc-field">
              <label className="pc-field__label">Resource *</label>
              <select className="pc-input" value={d.resource} onChange={(e) => set('resource', e.target.value)} disabled={editing} autoFocus={!editing}>
                <option value="" disabled>Choose…</option>
                {available.map((r) => <option key={r.key} value={r.key}>{r.label}</option>)}
              </select>
            </div>
            <div className="pc-field">
              <label className="pc-field__label">Action *</label>
              <select className="pc-input" value={d.action} onChange={(e) => set('action', e.target.value)}>
                {actionOptions.map((k) => <option key={k} value={k}>{ACTION[k]?.label ?? k}</option>)}
              </select>
            </div>
            <div className="pc-field">
              <label className="pc-field__label">Default limit{res?.unit ? ` (${res.unit})` : ''}</label>
              <input className="pc-input" type="number" min="0" value={d.unlimited ? '' : d.default_limit} disabled={d.unlimited} onChange={(e) => set('default_limit', e.target.value)} placeholder={d.unlimited ? INF : '0'} />
            </div>
            <div className="pc-field">
              <label className="pc-check"><input type="checkbox" checked={d.unlimited} onChange={(e) => set('unlimited', e.target.checked)} /> Unlimited</label>
            </div>
            <div className="pc-field">
              <label className="pc-field__label">Warn at %</label>
              <input className="pc-input" type="number" min="0" max="100" value={d.warning_threshold_pct} onChange={(e) => set('warning_threshold_pct', e.target.value)} />
            </div>
            <div className="pc-field">
              <label className="pc-field__label">Hard limit at %</label>
              <input className="pc-input" type="number" min="0" max="100" value={d.hard_limit_pct} onChange={(e) => set('hard_limit_pct', e.target.value)} />
            </div>
          </div>
          {preview && (
            <div className="qt-usagehint">
              {preview.rows ?? 0} row{preview.rows === 1 ? '' : 's'} · <b>{preview.exceeded ?? 0}</b> exceeded · {preview.at_risk ?? 0} at risk
              {(preview.newly_exceeded ?? 0) > 0 && <span className="qt-usagehint__bad"> · {preview.newly_exceeded} newly breaching</span>}
            </div>
          )}
          {err && <span className="pc-field__err">{err}</span>}
          <div className="pc-modal__actions"><span className="pc-spacer" /><button type="button" className="pc-btn" onClick={onClose}>Cancel</button><button type="submit" className="pc-btn pc-btn--primary" disabled={busy}>{busy ? 'Saving…' : (editing ? 'Save changes' : 'Create policy')}</button></div>
        </form>
      </div>
    </div>
  );
}

/* ---------------- tenant capacity drawer ---------------- */
function TenantDrawer({ tenantId, overview, onClose, actions, can }) {
  const [tab, setTab] = useState('resources');
  useEffect(() => { setTab('resources'); }, [tenantId]);
  const matrix = overview.matrix ?? [];
  const overrides = overview.overrides ?? [];
  const warningsList = overview.warnings ?? [];
  const policies = overview.policies ?? [];
  const tenantMeta = (overview.tenants ?? []).find((t) => t.id === tenantId);
  const rows = matrix.filter((r) => r.tenant_id === tenantId);
  const ov = overrides.filter((r) => r.tenant_id === tenantId);
  const wr = warningsList.filter((r) => r.tenant_id === tenantId);
  const openWr = wr.filter((r) => !r.dismissed);
  const name = tenantMeta?.name ?? rows[0]?.tenant ?? tenantId;
  const subdomain = tenantMeta?.subdomain ?? rows[0]?.subdomain ?? '';
  const plan = rows[0]?.plan ?? '—';
  const overCount = rows.filter((r) => r.status === 'exceeded').length;
  const peakPct = rows.filter((r) => !r.unlimited).reduce((m, r) => Math.max(m, r.pct), 0);
  const tabs = [{ id: 'resources', label: 'Resources' }, { id: 'overrides', label: 'Overrides' }, { id: 'warnings', label: 'Warnings' }];
  const policyFor = (resource) => policies.find((p) => p.resource === resource);
  const overrideFor = (resource) => ov.find((o2) => o2.resource === resource);

  return (
    <WbDrawer open onClose={onClose} ariaLabel={`Tenant — ${name}`} tabs={tabs} activeTab={tab} onTab={setTab}
      head={(
        <>
          <div className="sc-dr-top">
            <div className="sc-av">{initials(name)}</div>
            <div><div className="sc-dr-title">{name}</div><div className="sc-dr-code">{subdomain ? `${subdomain}.aeos365.com` : tenantId}</div></div>
            <button type="button" className="wb-drawer__x" onClick={onClose} aria-label="Close">✕</button>
          </div>
          <span className={`pc-chip ${overCount > 0 ? STATUS.exceeded.cls : STATUS.ok.cls}`}>
            <span className="pc-chip__dot" style={{ background: overCount > 0 ? STATUS.exceeded.color : STATUS.ok.color }} />
            {overCount > 0 ? `${overCount} over limit` : 'Healthy'}
          </span>
          <div className="sc-dr-kpis">
            <div className="sc-dr-kpi"><div className="l">Peak</div><div className="v">{Math.round(peakPct)}%</div></div>
            <div className="sc-dr-kpi"><div className="l">Overrides</div><div className="v">{ov.length}</div></div>
            <div className="sc-dr-kpi"><div className="l">Warnings</div><div className="v">{openWr.length}</div></div>
            <div className="sc-dr-kpi"><div className="l">Plan</div><div className="v">{plan}</div></div>
          </div>
          <div className="sc-dr-acts">
            {can.override && <button type="button" className="pc-btn pc-btn--sm pc-btn--primary" onClick={() => actions.override(tenantId)}>Set override</button>}
            {can.dismiss && openWr.length > 0 && <button type="button" className="pc-btn pc-btn--sm" onClick={() => actions.dismissAll(openWr.map((w) => w.id))}>Dismiss warnings</button>}
            <button type="button" className="pc-btn pc-btn--sm" onClick={() => router.visit('/tenants')}>Open tenant</button>
          </div>
        </>
      )}
    >
      {tab === 'resources' && (
        rows.length === 0 ? <div className="wb-empty">No tracked resources for this tenant.</div>
          : (
            <div className="qt-drrows">
              {rows.map((r) => {
                const pol = policyFor(r.resource);
                const ovr = overrideFor(r.resource);
                return (
                  <div key={r.id} className="qt-ubar-row">
                    <div className="qt-ubar-top">
                      <span className="qt-ubar-label">{r.resource_label}</span>
                      <span className={`qt-src ${SOURCE[r.source]?.cls ?? ''}`}>{SOURCE[r.source]?.label ?? r.source}</span>
                    </div>
                    <div className="qt-ubar-track">
                      {!r.unlimited && <span className="qt-ubar-fill" style={{ width: `${Math.min(r.pct, 100)}%`, background: pctColor(r.status) }} />}
                    </div>
                    <div className="qt-ubar-foot">
                      <span>{r.unlimited ? INF : `${num(r.used)} / ${num(r.limit)} ${r.unit} · ${Math.round(r.pct)}%`}</span>
                      <span>
                        {pol && <span className="qt-ubar-note">policy: {ACTION[pol.action]?.label ?? pol.action}</span>}
                        {ovr?.expires_at && <span className="qt-ubar-note"> · expires {fmtShort(ovr.expires_at)}</span>}
                      </span>
                    </div>
                  </div>
                );
              })}
            </div>
          )
      )}
      {tab === 'overrides' && (
        ov.length === 0 ? <div className="wb-empty">No overrides for this tenant.</div>
          : <table className="sc-minit"><tbody>{ov.map((r) => (
            <tr key={r.id}>
              <td><div className="pc-mname">{r.resource_label ?? r.resource}</div><div className="sc-kind">{r.unlimited ? INF : `${num(r.limit_value)} ${r.unit}`}{r.expires_at ? ` · expires ${fmtShort(r.expires_at)}` : ' · permanent'}</div></td>
              <td className="pc-r">
                <div className="sc-actions">
                  {can.override && <button type="button" className="pc-btn pc-btn--sm" onClick={() => actions.extend(r)}>Extend</button>}
                  {can.override && <button type="button" className="pc-btn pc-btn--sm" onClick={() => actions.remove(r.tenant_id, r.resource, r.tenant)}>Remove</button>}
                </div>
              </td>
            </tr>
          ))}</tbody></table>
      )}
      {tab === 'warnings' && (
        wr.length === 0 ? <div className="wb-empty">No warnings for this tenant.</div>
          : <table className="sc-minit"><tbody>{wr.map((r) => (
            <tr key={r.id}>
              <td><div className="pc-mname">{r.resource_label ?? r.resource}</div><div className="sc-kind">{Math.round(r.percentage)}% · {LEVEL[r.threshold_type]?.label ?? r.threshold_type} · {r.warning_count}×</div></td>
              <td className="pc-r">
                {can.dismiss && (r.dismissed
                  ? <button type="button" className="pc-btn pc-btn--sm" onClick={() => actions.reopen(r.id)}>Reopen</button>
                  : <button type="button" className="pc-btn pc-btn--sm" onClick={() => actions.dismiss(r.id)}>Dismiss</button>)}
              </td>
            </tr>
          ))}</tbody></table>
      )}
    </WbDrawer>
  );
}

/* ---------------- page ---------------- */
export default function Quotas({ overview }) {
  const toast = useToast();
  const o = overview ?? {};
  const s = o.stats ?? {};
  const matrix = useMemo(() => o.matrix ?? [], [o.matrix]);
  const overrides = useMemo(() => o.overrides ?? [], [o.overrides]);
  const warningsList = useMemo(() => o.warnings ?? [], [o.warnings]);
  const policies = useMemo(() => o.policies ?? [], [o.policies]);
  const headroom = o.headroom ?? [];
  const policyMix = o.policyMix ?? [];
  const pressure = o.pressure ?? [];
  const resources = o.resources ?? [];
  const tenantsList = o.tenants ?? [];
  const actionOptions = o.actions ?? Object.keys(ACTION);

  const canView = useHRMAC('quota-management.quota-dashboard.view');
  const canOverride = useHRMAC('quota-management.quota-dashboard.override');
  const canDismiss = useHRMAC('quota-management.quota-dashboard.dismiss-warnings');
  const canEditPolicy = useHRMAC('quota-management.quota-settings.edit');

  const [tab, setTab] = useState('matrix');
  const [drawerTenantId, setDrawerTenantId] = useState(null);
  const [overrideM, setOverrideM] = useState(null);
  const [extendM, setExtendM] = useState(null);
  const [policyM, setPolicyM] = useState(undefined);
  const ctx = useCtxMenu();

  /* actions */
  const removeOverride = (tenantId, resource, tenantName) => {
    if (!window.confirm(`Remove the override for ${tenantName} — ${resource}? The tenant reverts to the plan/policy default.`)) return;
    api('DELETE', `/quotas/${tenantId}/override/${resource}`).then(() => { toast.success('Override removed.'); reload(); }).catch((e) => toast.error(e.message));
  };
  const quickUnlimit = (r) => {
    if (!window.confirm(`Make ${r.resource_label} unlimited for ${r.tenant}?`)) return;
    api('POST', `/quotas/${r.tenant_id}/override`, { resource: r.resource, limit_value: 0 }).then(() => { toast.success('Set to unlimited.'); reload(); }).catch((e) => toast.error(e.message));
  };
  const clearExpired = () => {
    api('POST', '/quotas/overrides/clear-expired').then((j) => { toast.success(j.message ?? `Cleared ${j.cleared ?? 0} expired override(s).`); reload(); }).catch((e) => toast.error(e.message));
  };
  const scanBreaches = () => {
    api('POST', '/quotas/warnings/scan').then((j) => { toast.success(j.message ?? `Scanned — ${j.raised ?? 0} raised, ${j.cleared ?? 0} cleared.`); reload(); }).catch((e) => toast.error(e.message));
  };
  const dismissWarning = (id) => api('POST', `/quotas/warnings/${id}/dismiss`).then(() => { toast.success('Warning dismissed.'); reload(); }).catch((e) => toast.error(e.message));
  const reopenWarning = (id) => api('POST', `/quotas/warnings/${id}/reopen`).then(() => { toast.success('Warning reopened.'); reload(); }).catch((e) => toast.error(e.message));
  const bulkDismiss = (ids) => api('POST', '/quotas/warnings/dismiss', { ids }).then(() => { toast.success(`${ids.length} warning${ids.length === 1 ? '' : 's'} dismissed.`); reload(); }).catch((e) => toast.error(e.message));
  const deletePolicy = (p) => {
    if (!window.confirm(`Delete the policy for ${p.label}? Tenants fall back to plan defaults.`)) return;
    api('DELETE', `/quotas/policies/${p.id}`).then(() => { toast.success('Policy deleted.'); reload(); }).catch((e) => toast.error(e.message));
  };

  const drawerActions = {
    override: (tid) => setOverrideM({ tenantId: tid }),
    dismissAll: (ids) => bulkDismiss(ids),
    extend: (r) => setExtendM(r),
    remove: (tid, resource, tenantName) => removeOverride(tid, resource, tenantName),
    dismiss: dismissWarning,
    reopen: reopenWarning,
  };

  /* utilization workbench */
  const wbM = useWorkbench({
    rows: matrix, getId: (r) => r.id,
    searchText: (r) => `${r.tenant} ${r.subdomain ?? ''} ${r.plan ?? ''} ${r.resource} ${r.resource_label}`,
    views: [
      { id: 'all', label: 'All' },
      { id: 'at_risk', label: 'At risk', test: (r) => r.status === 'warning' || r.status === 'critical' },
      { id: 'over', label: 'Over limit', test: (r) => r.status === 'exceeded' },
      { id: 'overridden', label: 'Overridden', test: (r) => r.source === 'override' },
      { id: 'unlimited', label: 'Unlimited', test: (r) => r.status === 'unlimited' },
    ],
    facets: {
      resource: { value: 'all', test: (r, v) => r.resource === v },
      status: { value: 'all', test: (r, v) => r.status === v },
    },
    sortKey: 'pct', sortVal: (r, k) => (['pct', 'used', 'limit', 'headroom'].includes(k) ? (r[k] ?? -1) : String(r[k] ?? '')),
    perPage: 12, storageKey: 'platform.quota.matrix',
  });
  // Tightest capacity first — an operator opens this console to see what is
  // about to break, not an alphabetical roster.
  useEffect(() => { wbM.toggleSort('pct'); }, []); // eslint-disable-line react-hooks/exhaustive-deps

  /* overrides workbench */
  const wbO = useWorkbench({
    rows: overrides, getId: (r) => r.id,
    searchText: (r) => `${r.tenant} ${r.resource} ${r.resource_label} ${r.reason ?? ''} ${r.set_by ?? ''}`,
    views: [
      { id: 'all', label: 'All' },
      { id: 'active', label: 'Active', test: (r) => r.active },
      { id: 'expiring', label: 'Expiring soon', test: (r) => r.expiring_soon },
      { id: 'expired', label: 'Expired', test: (r) => !r.active },
    ],
    facets: { resource: { value: 'all', test: (r, v) => r.resource === v } },
    sortKey: 'created_at', sortVal: (r, k) => (k === 'expires_at' ? new Date(r.expires_at ?? '9999-12-31').getTime() : (k === 'created_at' ? new Date(r.created_at ?? 0).getTime() : String(r[k] ?? ''))),
    perPage: 12, storageKey: 'platform.quota.overrides',
  });

  /* warnings workbench */
  const wbW = useWorkbench({
    rows: warningsList, getId: (r) => r.id,
    searchText: (r) => `${r.tenant} ${r.resource} ${r.resource_label}`,
    views: [
      { id: 'all', label: 'All' },
      { id: 'open', label: 'Open', test: (r) => !r.dismissed },
      { id: 'critical', label: 'Critical', test: (r) => r.threshold_type === 'block' || r.threshold_type === 'critical' },
      { id: 'dismissed', label: 'Dismissed', test: (r) => r.dismissed },
    ],
    facets: { resource: { value: 'all', test: (r, v) => r.resource === v } },
    sortKey: 'last_warned_at', sortVal: (r, k) => (k === 'percentage' || k === 'warning_count' ? r[k] : new Date(r[k] ?? 0).getTime()),
    perPage: 12, storageKey: 'platform.quota.warnings',
  });

  /* policies workbench */
  const wbP = useWorkbench({
    rows: policies, getId: (r) => r.id,
    searchText: (r) => `${r.resource} ${r.label}`,
    views: [{ id: 'all', label: 'All' }],
    sortKey: 'resource', sortVal: (r, k) => (['default_limit', 'warning_threshold_pct', 'hard_limit_pct'].includes(k) ? r[k] : String(r[k] ?? '')),
    perPage: 12, storageKey: 'platform.quota.policies',
  });

  const matrixMenu = (r) => [
    { label: 'Open tenant', onClick: () => setDrawerTenantId(r.tenant_id) },
    ...(canOverride ? [{ label: r.source === 'override' ? 'Edit override…' : 'Set override…', onClick: () => setOverrideM({ tenantId: r.tenant_id, resource: r.resource }) }] : []),
    ...(canOverride && r.source === 'override' ? [{ label: 'Remove override', danger: true, onClick: () => removeOverride(r.tenant_id, r.resource, r.tenant) }] : []),
    ...(canOverride && !r.unlimited ? [{ label: 'Make unlimited', onClick: () => quickUnlimit(r) }] : []),
  ];
  const overrideMenu = (r) => [
    ...(canOverride ? [{ label: 'Edit…', onClick: () => setOverrideM({ tenantId: r.tenant_id, resource: r.resource }) }] : []),
    ...(canOverride ? [{ label: r.expires_at ? 'Extend / make permanent…' : 'Set expiry…', onClick: () => setExtendM(r) }] : []),
    ...(canOverride ? [{ label: 'Remove', danger: true, onClick: () => removeOverride(r.tenant_id, r.resource, r.tenant) }] : []),
  ];
  const warningMenu = (r) => [
    ...(canDismiss ? [r.dismissed ? { label: 'Reopen', onClick: () => reopenWarning(r.id) } : { label: 'Dismiss', onClick: () => dismissWarning(r.id) }] : []),
  ];
  const policyMenu = (r) => [
    ...(canEditPolicy ? [{ label: 'Edit…', onClick: () => setPolicyM(r) }] : []),
    ...(canEditPolicy ? [{ label: 'Delete', danger: true, onClick: () => deletePolicy(r) }] : []),
  ];

  const matrixColumns = [
    { key: 'tenant', label: 'Tenant', sortable: true, render: (r) => <div className="pc-mrow"><div className="sc-av">{initials(r.tenant)}</div><div><div className="pc-mname">{r.tenant}</div><div className="sc-kind">{r.subdomain ? `${r.subdomain}.aeos365.com` : r.tenant_id} · {r.plan || '—'}</div></div></div> },
    { key: 'resource', label: 'Resource', sortable: true, render: (r) => <div><div className="pc-mname">{r.resource_label}</div><div className="qt-mono">{r.resource} · {r.unit}</div></div> },
    { key: 'limit', label: 'Limit', align: 'r', sortable: true, render: (r) => (r.unlimited || r.limit === -1 ? <span className="qt-inf">{INF}</span> : <span className="num">{num(r.limit)} {r.unit}</span>) },
    { key: 'source', label: 'Source', sortable: true, render: (r) => <span className={`qt-src ${SOURCE[r.source]?.cls ?? ''}`}>{SOURCE[r.source]?.label ?? r.source}</span> },
    { key: 'used', label: 'Used', align: 'r', sortable: true, render: (r) => <span className="num">{num(r.used)} {r.unit}</span> },
    { key: 'pct', label: 'Utilization', align: 'r', sortable: true, render: (r) => (r.unlimited ? <span className="pc-free">—</span> : (
      <span className="qt-rate"><span className="qt-rate__track"><span className="qt-rate__fill" style={{ width: `${Math.min(r.pct, 100)}%`, background: pctColor(r.status) }} /></span><span className="num">{Math.round(r.pct)}%</span></span>
    )) },
    { key: 'headroom', label: 'Headroom', align: 'r', sortable: true, hideSm: true, render: (r) => (r.unlimited || r.headroom === -1 ? <span className="qt-inf">{INF}</span> : <span className={`num${r.headroom < 0 ? ' qt-neg' : ''}`}>{num(r.headroom)} {r.unit}</span>) },
    { key: 'status', label: 'Status', sortable: true, render: (r) => <span className={`pc-chip ${STATUS[r.status]?.cls ?? ''}`}><span className="pc-chip__dot" style={{ background: STATUS[r.status]?.color }} />{STATUS[r.status]?.label ?? r.status}</span> },
    { key: 'actions', label: '', align: 'r', width: 44, render: (r) => <button type="button" className="wb-kebab" aria-label={`Actions for ${r.tenant}`} onClick={(e) => ctx.open(e.currentTarget, matrixMenu(r))}>⋯</button> },
  ];

  const overrideColumns = [
    { key: 'tenant', label: 'Tenant', sortable: true, render: (r) => <div className="pc-mrow"><div className="sc-av">{initials(r.tenant)}</div><div className="pc-mname">{r.tenant}</div></div> },
    { key: 'resource', label: 'Resource', sortable: true, render: (r) => <div><div className="pc-mname">{r.resource_label ?? r.resource}</div><div className="qt-mono">{r.resource} · {r.unit}</div></div> },
    { key: 'limit_value', label: 'Limit', align: 'r', sortable: true, render: (r) => (r.unlimited ? <span className="qt-inf">{INF}</span> : <span className="num">{num(r.limit_value)} {r.unit}</span>) },
    { key: 'reason', label: 'Reason', hideSm: true, render: (r) => <span className="qt-trunc">{r.reason || '—'}</span> },
    { key: 'set_by', label: 'Set by', hideSm: true, render: (r) => r.set_by || '—' },
    { key: 'expires_at', label: 'Expires', align: 'r', sortable: true, render: (r) => (r.expires_at ? <span className={r.expiring_soon ? 'qt-amber' : ''}>{fmtDate(r.expires_at)}</span> : <span className="pc-free">—</span>) },
    { key: 'active', label: 'Status', sortable: true, render: (r) => <span className={`pc-chip ${r.active ? STATUS.ok.cls : STATUS.unset.cls}`}><span className="pc-chip__dot" style={{ background: r.active ? STATUS.ok.color : STATUS.unset.color }} />{r.active ? 'Active' : 'Expired'}</span> },
    { key: 'actions', label: '', align: 'r', width: 44, render: (r) => <button type="button" className="wb-kebab" aria-label={`Actions for ${r.tenant} override`} onClick={(e) => ctx.open(e.currentTarget, overrideMenu(r))}>⋯</button> },
  ];

  const warningColumns = [
    { key: 'tenant', label: 'Tenant', sortable: true, render: (r) => <div className="pc-mrow"><div className="sc-av">{initials(r.tenant)}</div><div className="pc-mname">{r.tenant}</div></div> },
    { key: 'resource', label: 'Resource', sortable: true, render: (r) => <span className="qt-mono">{r.resource_label ?? r.resource}</span> },
    { key: 'percentage', label: 'Usage %', align: 'r', sortable: true, render: (r) => <span className="num" style={{ color: r.percentage >= 100 ? STATUS.exceeded.color : (r.percentage >= 90 ? STATUS.critical.color : STATUS.warning.color) }}>{Math.round(r.percentage)}%</span> },
    { key: 'threshold_type', label: 'Level', sortable: true, render: (r) => <span className={`pc-chip ${LEVEL[r.threshold_type]?.cls ?? ''}`}><span className="pc-chip__dot" style={{ background: LEVEL[r.threshold_type]?.color }} />{LEVEL[r.threshold_type]?.label ?? r.threshold_type}</span> },
    { key: 'warning_count', label: 'Count', align: 'r', sortable: true, hideSm: true, render: (r) => <span className="num">{r.warning_count}</span> },
    { key: 'last_warned_at', label: 'Last seen', align: 'r', sortable: true, hideSm: true, render: (r) => <span className="sc-kind">{fmtShort(r.last_warned_at)}</span> },
    { key: 'dismissed', label: 'Status', sortable: true, render: (r) => <span className={`pc-chip ${r.dismissed ? STATUS.unset.cls : STATUS.warning.cls}`}><span className="pc-chip__dot" style={{ background: r.dismissed ? STATUS.unset.color : STATUS.warning.color }} />{r.dismissed ? 'Dismissed' : 'Open'}</span> },
    { key: 'actions', label: '', align: 'r', width: 44, render: (r) => <button type="button" className="wb-kebab" aria-label={`Actions for ${r.tenant} warning`} onClick={(e) => ctx.open(e.currentTarget, warningMenu(r))}>⋯</button> },
  ];

  const policyColumns = [
    { key: 'resource', label: 'Resource', sortable: true, render: (r) => <div><div className="pc-mname">{r.label}</div><div className="qt-mono">{r.resource} · {r.unit}</div></div> },
    { key: 'default_limit', label: 'Default limit', align: 'r', sortable: true, render: (r) => (r.unlimited || !r.default_limit ? <span className="qt-inf">{INF}</span> : <span className="num">{num(r.default_limit)} {r.unit}</span>) },
    { key: 'warning_threshold_pct', label: 'Warn at', align: 'r', sortable: true, render: (r) => <span className="num">{r.warning_threshold_pct}%</span> },
    { key: 'hard_limit_pct', label: 'Hard at', align: 'r', sortable: true, render: (r) => <span className="num">{r.hard_limit_pct}%</span> },
    { key: 'action', label: 'Action', sortable: true, render: (r) => <span className={`pc-chip ${ACTION[r.action]?.cls ?? ''}`}><span className="pc-chip__dot" style={{ background: ACTION[r.action]?.color }} />{ACTION[r.action]?.label ?? r.action}</span> },
    { key: 'updated_at', label: 'Updated', align: 'r', sortable: true, hideSm: true, render: (r) => <span className="sc-kind">{fmtShort(r.updated_at)}</span> },
    { key: 'actions', label: '', align: 'r', width: 44, render: (r) => <button type="button" className="wb-kebab" aria-label={`Actions for ${r.label} policy`} onClick={(e) => ctx.open(e.currentTarget, policyMenu(r))}>⋯</button> },
  ];

  const kpis = [
    { label: 'Fleet utilization', value: `${Math.round(s.fleet_utilization ?? 0)}%`, delta: `${s.tenants_tracked ?? 0} tenants tracked`, color: 'var(--aeos-primary)' },
    { label: 'At risk', value: s.at_risk ?? 0, delta: 'approaching limits', color: 'var(--aeos-warning)' },
    { label: 'Over hard limit', value: s.over_limit ?? 0, delta: `${s.tenants_over ?? 0} tenants`, color: 'var(--aeos-danger)' },
    { label: 'Active overrides', value: s.overrides_active ?? 0, delta: `${s.overrides_tenants ?? 0} tenants`, color: 'var(--aeos-primary)' },
    { label: 'Expiring 7d', value: s.expiring_soon ?? 0, delta: 'overrides expiring soon', color: 'var(--aeos-warning)' },
    { label: 'Open warnings', value: s.warnings_open ?? 0, delta: `${s.warnings_critical ?? 0} critical`, color: '#fb923c' },
  ];

  const policyTotal = policyMix.reduce((a, b) => a + (b.count ?? 0), 0);
  const headroomOver = headroom.filter((h) => h.over).length;
  const headroomAtRisk = headroom.filter((h) => h.at_risk).length;

  const exportMatrixCsv = () => {
    const header = 'tenant,subdomain,plan,resource,unit,limit,source,used,pct,headroom,status';
    const lines = matrix.map((r) => [r.tenant, r.subdomain ?? '', r.plan ?? '', r.resource, r.unit, r.unlimited ? 'unlimited' : r.limit, r.source, r.used, r.pct, r.unlimited ? 'unlimited' : r.headroom, r.status].map((v) => `"${String(v).replaceAll('"', '""')}"`).join(','));
    const blob = new Blob([`${header}\n${lines.join('\n')}`], { type: 'text/csv' });
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = `quota-utilization-${new Date().toISOString().slice(0, 10)}.csv`; a.click(); URL.revokeObjectURL(a.href);
  };
  const exportOverridesCsv = () => {
    const header = 'tenant,resource,limit,unlimited,reason,set_by,expires_at,status,created_at';
    const lines = overrides.map((r) => [r.tenant, r.resource, r.limit_value, r.unlimited ? 'yes' : 'no', r.reason ?? '', r.set_by ?? '', r.expires_at ?? '', r.active ? 'active' : 'expired', fmtDate(r.created_at)].map((v) => `"${String(v).replaceAll('"', '""')}"`).join(','));
    const blob = new Blob([`${header}\n${lines.join('\n')}`], { type: 'text/csv' });
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = `quota-overrides-${new Date().toISOString().slice(0, 10)}.csv`; a.click(); URL.revokeObjectURL(a.href);
  };

  return (
    <div className="pc qt">
      <div className="pc-head">
        <div>
          <div className="pc-eyebrow"><span className="pc-eyebrow__dot" /> Tenants &amp; Onboarding · Capacity</div>
          <h1 className="pc-title">Quota Management</h1>
          <div className="pc-sub">Fleet-wide resource capacity — plan and policy defaults, per-tenant overrides, breach warnings and the enforcement policy that drives them, in one console.</div>
        </div>
        <div className="pc-actions">
          <button type="button" className="pc-btn" onClick={(e) => ctx.open(e.currentTarget, [{ label: 'Export CSV — utilization', onClick: exportMatrixCsv }, { label: 'Export CSV — overrides', onClick: exportOverridesCsv }, { label: 'Print this view', onClick: () => window.print() }])}>{Glyph.export}<span>Export</span></button>
          {canDismiss && <button type="button" className="pc-btn" onClick={scanBreaches}>{Glyph.scan}<span>Scan breaches</span></button>}
          {canOverride && <button type="button" className="pc-btn pc-btn--primary" onClick={() => setOverrideM({})}>{Glyph.plus}<span>New override</span></button>}
        </div>
      </div>

      {/* KPI band */}
      <div className="pc-kpis sc-kpis6">
        {kpis.map((c) => (
          <Card key={c.label}><CardBody>
            <div className="pc-kpi">
              <div className="pc-kpi__label">{c.label}</div>
              <div className="pc-kpi__value">{c.value}</div>
              <div className="pc-kpi__delta">{c.delta}</div>
              {Array.isArray(c.spark) && c.spark.length > 1 && <div className="sc-kpi-spark"><AreaSpark data={c.spark} color={c.color} /></div>}
            </div>
          </CardBody></Card>
        ))}
      </div>

      {/* analytics band */}
      <div className="sc-band">
        <Card><CardBody>
          <div className="pc-panel-h"><div><h2 className="pc-panel-h__title">Fleet headroom by resource</h2><div className="pc-panel-h__sub">Usage vs granted capacity — warn / hard thresholds marked</div></div></div>
          <div className="qt-ubars">
            {headroom.length === 0 && <div className="wb-empty">No tracked resources yet.</div>}
            {headroom.map((h) => {
              const st = resStatus(h);
              // `unlimited_rows` is a COUNT of uncapped tenants, not a flag —
              // a resource only has no bar when nothing is capped at all.
              const capped = (h.granted ?? 0) > 0;
              return (
                <div key={h.resource} className="qt-ubar-row">
                  <div className="qt-ubar-top"><span className="qt-ubar-label">{h.label}</span><span className="qt-ubar-val">{capped ? `${num(h.used)} / ${num(h.granted)} ${h.unit}` : INF}</span></div>
                  <div className="qt-ubar-track">
                    {capped && <span className="qt-ubar-fill" style={{ width: `${Math.min(h.pct, 100)}%`, background: pctColor(st) }} />}
                    {capped && h.warn_at != null && <span className="qt-ubar-tick" style={{ left: `${Math.min(h.warn_at, 100)}%` }} />}
                    {capped && h.hard_at != null && <span className="qt-ubar-tick qt-ubar-tick--hard" style={{ left: `${Math.min(h.hard_at, 100)}%` }} />}
                  </div>
                  <div className="qt-ubar-foot">
                    <span>{capped ? `${Math.round(h.pct)}% used` : 'no capped tenants'}</span>
                    <span className="qt-ubar-meta">
                      {capped ? `${num(h.headroom)} ${h.unit} free` : ''}
                      {h.over > 0 ? ` · ${h.over} over` : ''}
                      {h.at_risk > 0 ? ` · ${h.at_risk} at risk` : ''}
                      {h.unlimited_rows > 0 ? ` · ${h.unlimited_rows} unlimited` : ''}
                    </span>
                  </div>
                </div>
              );
            })}
          </div>
          {headroom.length > 0 && <div className="qt-ubar-summary">{headroomOver} over · {headroomAtRisk} at risk</div>}
        </CardBody></Card>

        <Card><CardBody>
          <div className="pc-panel-h"><div><h2 className="pc-panel-h__title">Enforcement policy</h2><div className="pc-panel-h__sub">{policyTotal} polic{policyTotal === 1 ? 'y' : 'ies'} configured</div></div></div>
          <div className="sc-donut-row">
            <Donut segments={policyMix.map((m) => ({ color: ACTION[m.action]?.color ?? 'var(--aeos-text-muted)', value: m.count }))} centerValue={policyTotal} centerLabel="policies" size={112} />
            <div className="sc-dl">
              {policyMix.filter((m) => m.count > 0).map((m) => (
                <div key={m.action} className="li"><span className="d" style={{ background: ACTION[m.action]?.color ?? 'var(--aeos-text-muted)' }} />{m.label}<b>{m.count}</b></div>
              ))}
              {policyMix.every((m) => !m.count) && <div className="li">No policies configured yet.</div>}
            </div>
          </div>
          <div className="qt-note">Thresholds &amp; action now drive live status.</div>
        </CardBody></Card>

        <Card><CardBody>
          <div className="pc-panel-h"><div><h2 className="pc-panel-h__title">Pressure leaderboard</h2><div className="pc-panel-h__sub">Tightest headroom, fleet-wide</div></div></div>
          <div className="qt-lb">
            {pressure.length === 0 && <div className="wb-empty">No pressure signals.</div>}
            {pressure.map((p, i) => (
              <button key={`${p.tenant_id}-${p.resource}`} type="button" className="qt-lbrow" onClick={() => setDrawerTenantId(p.tenant_id)}>
                <span className="qt-lbrow__rk">{i + 1}</span>
                <span className="qt-lbrow__who">
                  <span className="qt-lbrow__nm">{p.tenant}<small>{p.label}</small></span>
                  <span className="qt-lbrow__track"><span className="qt-lbrow__fill" style={{ width: `${Math.max(Math.min(p.pct, 100), 4)}%`, background: pctColor(p.status) }} /></span>
                </span>
                <span className="qt-lbrow__amt">{Math.round(p.pct)}%</span>
              </button>
            ))}
          </div>
        </CardBody></Card>
      </div>

      {/* workbench */}
      <Card><CardBody>
        <div className="qt-tabs">
          <button type="button" className={`qt-tab${tab === 'matrix' ? ' is-on' : ''}`} onClick={() => setTab('matrix')}>Utilization <span>{matrix.length}</span></button>
          <button type="button" className={`qt-tab${tab === 'overrides' ? ' is-on' : ''}`} onClick={() => setTab('overrides')}>Overrides <span>{overrides.length}</span></button>
          <button type="button" className={`qt-tab${tab === 'warnings' ? ' is-on' : ''}`} onClick={() => setTab('warnings')}>Warnings <span>{warningsList.length}</span></button>
          <button type="button" className={`qt-tab${tab === 'policies' ? ' is-on' : ''}`} onClick={() => setTab('policies')}>Policies <span>{policies.length}</span></button>
        </div>

        {tab === 'matrix' && (
          <>
            <WbToolbar>
              <WbSearch value={wbM.q} onChange={wbM.setQ} placeholder="Search tenant, subdomain or resource…" ariaLabel="Search utilization" />
              <select className="pc-input sc-statusfilter" value={wbM.facetValues.resource} onChange={(e) => wbM.setFacet('resource', e.target.value)} aria-label="Resource filter">
                <option value="all">All resources</option>{resources.map((r) => <option key={r.key} value={r.key}>{r.label}</option>)}
              </select>
              <select className="pc-input sc-statusfilter" value={wbM.facetValues.status} onChange={(e) => wbM.setFacet('status', e.target.value)} aria-label="Status filter">
                <option value="all">All statuses</option>{Object.keys(STATUS).map((k) => <option key={k} value={k}>{STATUS[k].label}</option>)}
              </select>
              <WbColumns wb={wbM} columns={matrixColumns} />
            </WbToolbar>
            <WbViews wb={wbM} />
            <WbTable wb={wbM} columns={matrixColumns} onRowClick={(r) => setDrawerTenantId(r.tenant_id)} rowAriaLabel={(r) => `${r.tenant}, ${r.resource_label}`}
              empty={<>No utilization rows match these filters.<br /><button type="button" className="pc-btn pc-btn--sm" onClick={wbM.resetFilters}>Clear filters</button></>} />
            <WbFooter wb={wbM} perOptions={[12, 25, 50]} />
          </>
        )}
        {tab === 'overrides' && (
          <>
            <WbToolbar>
              <WbSearch value={wbO.q} onChange={wbO.setQ} placeholder="Search tenant, resource or reason…" ariaLabel="Search overrides" />
              <select className="pc-input sc-statusfilter" value={wbO.facetValues.resource} onChange={(e) => wbO.setFacet('resource', e.target.value)} aria-label="Resource filter">
                <option value="all">All resources</option>{resources.map((r) => <option key={r.key} value={r.key}>{r.label}</option>)}
              </select>
              {canOverride && <button type="button" className="pc-btn pc-btn--sm" onClick={clearExpired}>Clear expired</button>}
              <WbColumns wb={wbO} columns={overrideColumns} />
            </WbToolbar>
            <WbViews wb={wbO} />
            <WbTable wb={wbO} columns={overrideColumns} onRowClick={(r) => setDrawerTenantId(r.tenant_id)}
              empty="No overrides match these filters." />
            <WbFooter wb={wbO} perOptions={[12, 25, 50]} />
          </>
        )}
        {tab === 'warnings' && (
          <>
            <WbToolbar>
              <WbSearch value={wbW.q} onChange={wbW.setQ} placeholder="Search tenant or resource…" ariaLabel="Search warnings" />
              <select className="pc-input sc-statusfilter" value={wbW.facetValues.resource} onChange={(e) => wbW.setFacet('resource', e.target.value)} aria-label="Resource filter">
                <option value="all">All resources</option>{resources.map((r) => <option key={r.key} value={r.key}>{r.label}</option>)}
              </select>
              <WbColumns wb={wbW} columns={warningColumns} />
            </WbToolbar>
            <WbViews wb={wbW} />
            <WbBulkBar wb={wbW}>
              {canDismiss && <button type="button" className="pc-btn pc-btn--sm pc-btn--primary" onClick={() => { bulkDismiss(Array.from(wbW.selection)); wbW.clearSelection(); }}>Dismiss selected</button>}
            </WbBulkBar>
            <WbTable wb={wbW} columns={warningColumns} selectable onRowClick={(r) => setDrawerTenantId(r.tenant_id)}
              empty="No warnings match these filters." />
            <WbFooter wb={wbW} perOptions={[12, 25, 50]} />
          </>
        )}
        {tab === 'policies' && (
          <>
            <WbToolbar>
              <WbSearch value={wbP.q} onChange={wbP.setQ} placeholder="Search resource…" ariaLabel="Search policies" />
              {canEditPolicy && <button type="button" className="pc-btn pc-btn--sm pc-btn--primary" onClick={() => setPolicyM(null)}>+ New policy</button>}
              <WbColumns wb={wbP} columns={policyColumns} />
            </WbToolbar>
            <WbTable wb={wbP} columns={policyColumns} empty="No policies configured yet." />
            <WbFooter wb={wbP} perOptions={[12, 25, 50]} />
          </>
        )}
      </CardBody></Card>

      {/* overlays */}
      {ctx.element}
      {canView && drawerTenantId && <TenantDrawer tenantId={drawerTenantId} overview={o} onClose={() => setDrawerTenantId(null)} actions={drawerActions} can={{ override: canOverride, dismiss: canDismiss }} />}
      {overrideM && <OverrideModal tenantId={overrideM.tenantId} resource={overrideM.resource} tenants={tenantsList} resources={resources} matrix={matrix} overrides={overrides}
        lockTenant={!!overrideM.tenantId} lockResource={!!overrideM.resource}
        onClose={() => setOverrideM(null)} onDone={(m) => { setOverrideM(null); toast.success(m); reload(); }} />}
      {extendM && <ExtendModal override={extendM} onClose={() => setExtendM(null)} onDone={(m) => { setExtendM(null); toast.success(m); reload(); }} />}
      {policyM !== undefined && <PolicyModal policy={policyM} resources={resources} policies={policies} actionOptions={actionOptions}
        onClose={() => setPolicyM(undefined)} onDone={(m) => { setPolicyM(undefined); toast.success(m); reload(); }} />}
    </div>
  );
}

Quotas.layout = (page) => (
  <App title="Quota Management" railTitle="Tenants &amp; Onboarding" rail={<QuotasRail overview={page.props.overview} />}>
    {page}
  </App>
);
