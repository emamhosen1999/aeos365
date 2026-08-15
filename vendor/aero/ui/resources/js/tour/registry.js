/**
 * Tour registry — every guided tour in the product, as data.
 *
 * Each tour: { id, title, tagline, icon, emoji, minutes, module, steps }.
 *   - `icon`   — an @aero/ui icon name (see resources/js/icons/icons.jsx).
 *   - `emoji`  — plain-text fallback glyph for surfaces that render text only.
 *   - `module` — gates visibility by the tenant's active modules (null = always).
 *
 * Steps: { route?, element?, title, description } — same shape as v1.
 *   - `route`   — the exact tenant URI the step lives on. VERIFIED against
 *                 `php artisan route:list`; do not guess new ones.
 *   - `element` — a stable [data-tour] anchor on that page. Omit for a centered
 *                 modal. A missing anchor degrades to a centered popover, so a
 *                 not-yet-anchored page never breaks the tour.
 *
 * RULES
 *   - ≤7 steps per tour (completion collapses past that — add tours, don't
 *     lengthen them).
 *   - Copy: 1–2 sentences, second person, benefit-first.
 *   - This module must stay dependency-free apart from `tourSteps.js` (the e2e
 *     suite imports it directly in Node — no React, no CSS, no browser globals).
 */
import { GRAND_TOUR } from './tourSteps.js';

/* Core platform — what every tenant gets, module-independent. */
const CORE_TOUR = [
  {
    // /settings is admin-domain only; the tenant settings command center is
    // reached at /settings/system (see aero-core config/module.php).
    route: '/settings/system',
    element: '[data-tour="settings-root"]',
    title: 'Make it yours',
    description: 'Branding, security, localization and email all live in one control room — shape the workspace around how your company actually works.',
  },
  {
    route: '/users',
    element: '[data-tour="users-table"]',
    title: 'People and permissions',
    description: 'Invite teammates and control exactly what each role can see and do.',
  },
  {
    route: '/help',
    element: '[data-tour="help-center"]',
    title: 'Help is always here',
    description: 'Docs, support and every guided tour live in the Help Center — including this one.',
  },
];

/* HRM deep dive — only for tenants with the `hrm` module. */
const HRM_TOUR = [
  {
    route: '/hrm/employees',
    element: '[data-tour="employees-table"]',
    title: 'Your people, organized',
    description: 'Search, filter and open any employee to see their full profile and records.',
  },
  {
    route: '/hrm/attendance',
    element: '[data-tour="attendance-view"]',
    title: 'Attendance without spreadsheets',
    description: 'Daily punches, lates and overtime roll up here automatically.',
  },
  {
    route: '/hrm/leave/applications',
    element: '[data-tour="leave-approve"]',
    title: 'Approve time-off in one click',
    description: 'Pending requests land here — go ahead and approve one.',
  },
  {
    route: '/hrm/payroll/runs',
    element: '[data-tour="payroll-run"]',
    title: 'Run payroll with confidence',
    description: 'Last month is finalized, this month is in draft — generate and preview from here.',
  },
  {
    route: '/hrm/performance',
    element: '[data-tour="performance-cycles"]',
    title: 'Reviews that actually happen',
    description: 'A live review cycle with real progress — no more chasing forms.',
  },
  {
    route: '/hrm/analytics',
    element: '[data-tour="hrm-analytics"]',
    title: 'The big picture',
    description: 'Headcount, attrition and attendance trends in one dashboard. That’s the deep dive!',
  },
];

/** Every registered tour, in the order the picker should show them. */
export const TOURS = [
  {
    id: 'highlights',
    title: 'Quick highlights',
    tagline: 'The two-minute aha tour',
    icon: 'sparkles',
    emoji: '⚡',
    minutes: 2,
    module: null,
    steps: GRAND_TOUR,
  },
  {
    id: 'core',
    title: 'Core platform',
    tagline: 'Settings, people, permissions',
    icon: 'settings',
    emoji: '🏛️',
    minutes: 3,
    module: null,
    steps: CORE_TOUR,
  },
  {
    id: 'hrm',
    title: 'HRM deep dive',
    tagline: 'From attendance to analytics',
    icon: 'users',
    emoji: '👥',
    minutes: 5,
    module: 'hrm',
    steps: HRM_TOUR,
  },
];

/** @returns {object|null} The registered tour with this id, or null. */
export const getTour = (id) => TOURS.find((t) => t.id === id) || null;

/**
 * Normalize whatever the tenant prop hands us into lowercase module codes.
 * `tenant.modules` is the `modules()` relation (the JSON column was dropped by
 * migration 2026_05_03_000001), so it arrives as an array of module OBJECTS —
 * accept both that and a plain array of code strings.
 */
const moduleCodes = (modules) =>
  (Array.isArray(modules) ? modules : [])
    .map((m) => (typeof m === 'string' ? m : m && (m.code || m.module_code)))
    .filter(Boolean)
    .map((c) => String(c).toLowerCase());

/**
 * Tours visible to a tenant, given its active modules.
 *
 * An EMPTY list means "no module information", not "no modules": the shipped
 * `tenant.modules` prop reads the deprecated `tenant_module` pivot, which is
 * empty even for tenants that are fully entitled via product subscriptions
 * (verified on democorp — pivot []; subscribed_product_modules includes 'hrm').
 * Gating on that would silently hide the HRM track from the live demo, so with
 * no information we gate nothing.
 */
export const availableTours = (modules = []) => {
  const codes = moduleCodes(modules);
  if (codes.length === 0) return TOURS.slice();
  return TOURS.filter((t) => !t.module || codes.includes(t.module));
};
