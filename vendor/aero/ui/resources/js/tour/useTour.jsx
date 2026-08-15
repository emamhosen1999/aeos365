import { useEffect } from 'react';
import { router } from '@inertiajs/react';
import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import './tour.css';
import { getTour } from './registry.js';

/* ── Persistence keys ────────────────────────────────────────────────── */
const K_ACTIVE = 'aeos:tour:active';   // sessionStorage — id of the running tour
const K_STEP   = 'aeos:tour:step';     // sessionStorage — current step index
const K_SEEN   = 'aeos:tour:seen';     // localStorage   — this browser saw the welcome picker

/* ── Timings ─────────────────────────────────────────────────────────── */
const ANCHOR_ATTEMPTS = 24;    // × ANCHOR_INTERVAL ≈ 3.6s for a lazy anchor
const ANCHOR_INTERVAL = 150;
const NAV_TIMEOUT_MS  = 6000;  // give up waiting for a cross-page step to land

/* Single live driver instance (one highlight at a time; we drive steps
   ourselves so a tour can span Inertia page navigations). */
let d = null;
let curReduced = false;
let shownStep = -1;       // step index currently shown (avoids duplicate highlights)
let watchdog = null;      // re-asserts the popover if a lazy re-render drops it
let navTimer = null;      // rescues a step whose page never loads

/* Storage helpers — Safari private mode throws on write, and the tour must
   never take the page down with it. */
function ssGet(k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } }
function ssSet(k, v) { try { sessionStorage.setItem(k, v); } catch (e) { /* noop */ } }
function ssDel(k) { try { sessionStorage.removeItem(k); } catch (e) { /* noop */ } }
function lsGet(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
function lsSet(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* noop */ } }

function path() { return window.location.pathname; }

/** Id of the tour currently running ('' when idle). */
export function activeTourId() {
  return ssGet(K_ACTIVE) || '';
}

/** Steps of the running tour — the single source the engine reads. */
function activeSteps() {
  const t = getTour(activeTourId());
  return t ? t.steps : [];
}

function stepIndex() {
  const i = Number(ssGet(K_STEP) || 0);
  return Number.isFinite(i) && i >= 0 ? i : 0;
}

function makeDriver(reduced) {
  return driver({
    animate: !reduced,
    smoothScroll: !reduced,
    allowClose: true,
    stagePadding: 6,
    stageRadius: 8,
    overlayOpacity: 0.6,
    showProgress: false, // we render our own "Step X of N"
    popoverClass: 'aeos-tour',
  });
}

function destroyDriver() {
  if (d) { try { d.destroy(); } catch (e) { /* noop */ } d = null; }
  shownStep = -1;
}

function clearNavTimer() {
  if (navTimer) { clearTimeout(navTimer); navTimer = null; }
}

/** Move past a step we cannot show, or finish if it was the last one. */
function skipStep(i) {
  if (i + 1 < activeSteps().length) goToStep(i + 1, curReduced);
  else endTour();
}

/** Highlight step i once its anchor exists (polls briefly after Inertia nav). */
function highlightWhenReady(i, reduced, attempt = 0) {
  const steps = activeSteps();
  const step = steps[i];
  if (!step) { endTour(); return; }
  curReduced = reduced;
  clearNavTimer();

  // Already showing this exact step with a live popover → nothing to do.
  if (shownStep === i && document.querySelector('.driver-popover')) return;

  if (step.element && !document.querySelector(step.element) && attempt < ANCHOR_ATTEMPTS) {
    setTimeout(() => highlightWhenReady(i, reduced, attempt + 1), ANCHOR_INTERVAL);
    return;
  }

  destroyDriver();
  shownStep = i;
  const isLast = i === steps.length - 1;
  const progress = `<div class="aeos-tour__progress">Step ${i + 1} of ${steps.length}</div>`;

  try {
    d = makeDriver(reduced);
    d.highlight({
      // Anchor missing after the poll budget (page not yet instrumented, or the
      // widget is permission-hidden) → centered popover instead of a freeze.
      element: step.element && document.querySelector(step.element) ? step.element : undefined,
      popover: {
        title: step.title,
        description: step.description + progress,
        showButtons: ['next', 'previous', 'close'],
        disableButtons: i === 0 ? ['previous'] : [],
        nextBtnText: isLast ? 'Done' : 'Next',
        prevBtnText: 'Back',
        onNextClick: () => { if (isLast) { endTour(); } else { goToStep(i + 1, reduced); } },
        onPrevClick: () => goToStep(Math.max(0, i - 1), reduced),
        onCloseClick: () => endTour(),
      },
    });
  } catch (e) {
    // driver.js blew up on this step — never strand the visitor.
    destroyDriver();
    skipStep(i);
  }
}

function goToStep(i, reduced) {
  const step = activeSteps()[i];
  if (!step) { endTour(); return; }
  curReduced = reduced;
  ssSet(K_STEP, String(i));
  clearNavTimer();

  // Cross-page: navigate then resume on the destination page.
  if (step.route && path() !== step.route) {
    destroyDriver();
    // If the page never lands (redirect, 403 from HRMAC, missing route) the
    // tour would sit there forever — advance instead.
    navTimer = setTimeout(() => {
      navTimer = null;
      if (!isTourActive() || stepIndex() !== i || path() === step.route) return;
      skipStep(i);
    }, NAV_TIMEOUT_MS);
    router.visit(step.route);
    return;
  }
  highlightWhenReady(i, reduced);
}

/* Watchdog: while a tour is active and we're on the right page with the anchor
   present, ensure the popover is on screen. Recovers from lazy re-renders that
   detach driver.js's highlighted node (e.g. a table hydrating after fetch). */
function startWatchdog() {
  stopWatchdog();
  watchdog = setInterval(() => {
    if (!isTourActive()) { stopWatchdog(); return; }
    const i = stepIndex();
    const step = activeSteps()[i];
    if (!step) return;
    if (step.route && path() !== step.route) return;      // mid-navigation
    if (step.element && !document.querySelector(step.element)) return; // anchor not ready
    if (!document.querySelector('.driver-popover')) {
      highlightWhenReady(i, curReduced);
    }
  }, 600);
}

function stopWatchdog() {
  if (watchdog) { clearInterval(watchdog); watchdog = null; }
}

/**
 * Start (or restart) a registered tour from the beginning.
 *
 * @param {string}  tourId  id from the registry (`TOURS`)
 * @param {boolean} reduced respect the Theme Studio reduced-motion setting
 * @returns {boolean} false when the id is not registered (nothing happens)
 */
export function startTour(tourId, reduced = false) {
  if (typeof window === 'undefined' || !getTour(tourId)) return false;
  curReduced = reduced;
  ssSet(K_ACTIVE, tourId);
  ssSet(K_STEP, '0');
  startWatchdog();
  goToStep(0, reduced);
  return true;
}

/** End the tour and clear all state. */
export function endTour() {
  ssDel(K_ACTIVE);
  ssDel(K_STEP);
  stopWatchdog();
  clearNavTimer();
  destroyDriver();
}

/** True while a *registered* tour is running (an unknown id counts as idle). */
export function isTourActive() {
  return typeof window !== 'undefined' && !!getTour(activeTourId());
}

/** Has this browser already been offered the welcome picker? */
export function hasSeenWelcome() {
  return typeof window !== 'undefined' && lsGet(K_SEEN) === '1';
}

/** Remember that the welcome picker was shown (first-run is once per browser). */
export function markWelcomeSeen() {
  if (typeof window === 'undefined') return;
  lsSet(K_SEEN, '1');
}

/**
 * useTourEngine — mount in App. Its only job is to resume an in-progress tour
 * after an Inertia navigation; first-run is owned by the welcome picker, which
 * calls `startTour(id)` explicitly.
 *
 * @param {{ reduced?: boolean, url?: string }} opts
 */
export function useTourEngine({ reduced = false, url = '' }) {
  curReduced = reduced;

  // Resume across navigations (+ keep the watchdog running).
  useEffect(() => {
    if (!isTourActive()) return;
    startWatchdog();
    const i = stepIndex();
    const step = activeSteps()[i];
    if (!step) { endTour(); return; }
    if (step.route && path() !== step.route) return; // not on this step's page yet
    const t = setTimeout(() => highlightWhenReady(i, reduced), 300);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [url]);
}
