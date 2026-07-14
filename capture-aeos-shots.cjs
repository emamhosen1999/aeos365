// One-off: capture authenticated AEOS365 screenshots for the FYP deck.
const { chromium } = require('playwright');
const OUT = 'c:/laragon/www/aeos365-Presentation/assets/img';
const VIEW = { width: 1600, height: 1000 };
const results = [];

async function login(ctx, page, base, email, password) {
  // Establish the session via a direct form POST (the React button doesn't submit under automation).
  await page.goto(base + '/login', { waitUntil: 'domcontentloaded', timeout: 60000 }).catch(()=>{});
  const cookies = await ctx.cookies();
  const xsrf = cookies.find(c => c.name === 'XSRF-TOKEN');
  const token = xsrf ? decodeURIComponent(xsrf.value) : '';
  let status = 'ERR';
  try {
    const resp = await ctx.request.post(base + '/login', {
      headers: { 'X-XSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json, text/html' },
      form: { email, password, remember: 'on' },
      maxRedirects: 0,
      failOnStatusCode: false,
    });
    status = resp.status();
  } catch (e) { status = 'ERR ' + e.message; }
  results.push(`  login POST -> ${status}`);
  await page.waitForTimeout(500);
}

async function killTour(page) {
  await page.keyboard.press('Escape').catch(()=>{});
  await page.evaluate(() => {
    document.querySelectorAll('.driver-overlay,.driver-popover,.driver-active,[class*="driver-"],[data-tour],.introjs-overlay,.introjs-tooltipReferenceLayer').forEach(e=>e.remove());
    document.documentElement.classList.remove('driver-active','driver-fade');
    try { localStorage.setItem('aeos.demo.tour.done','1'); localStorage.setItem('aeon.tour.seen','1'); } catch(e){}
  }).catch(()=>{});
  await page.waitForTimeout(400);
}

async function shot(page, url, slug, {tour=false}={}) {
  try {
    await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 });
    // Wait for async data to load — skeletons/spinners to clear (Inertia deferred props load after networkidle).
    await page.waitForFunction(
      () => document.querySelectorAll('.animate-pulse,[class*="skeleton"],[class*="Skeleton"],[aria-busy="true"],.loading,[data-loading="true"]').length === 0,
      { timeout: 15000 }
    ).catch(()=>{});
    await page.waitForLoadState('networkidle').catch(()=>{});
    await page.waitForTimeout(3500);
    if (tour) await killTour(page);
    // Guard: never overwrite a good asset with a login page.
    const onLogin = String(page.url()).includes('/login') || /Sign in to your account/i.test(await page.locator('body').innerText().catch(()=>''));
    if (onLogin) { results.push(`  ${slug}: SKIP (still on login)`); return; }
    await page.screenshot({ path: `${OUT}/${slug}.png` });
    results.push(`  ${slug}: ${page.url()} -> saved`);
  } catch (e) {
    results.push(`  ${slug}: ERROR ${e.message}`);
  }
}

(async () => {
  const browser = await chromium.launch({ headless: true });

  // ---- Platform admin ----
  let ctx = await browser.newContext({ viewport: VIEW, deviceScaleFactor: 1.5, ignoreHTTPSErrors: true });
  await ctx.addInitScript(() => { try { localStorage.setItem('aeos:tour:seen','1'); } catch(e){} });
  let page = await ctx.newPage();
  await login(ctx, page, 'http://admin.aeos365.test', 'landlord@aeos365.test', 'Password123!');
  results.push(`platform after login -> ${page.url()}`);
  await shot(page, 'http://admin.aeos365.test/dashboard', 'platform-dashboard', {tour:true});
  await shot(page, 'http://admin.aeos365.test/tenants', 'platform-tenants', {tour:true});
  await shot(page, 'http://admin.aeos365.test/analytics', 'platform-analytics', {tour:true});
  await ctx.close();

  // ---- Tenant (democorp) ----
  ctx = await browser.newContext({ viewport: VIEW, deviceScaleFactor: 1.5, ignoreHTTPSErrors: true });
  await ctx.addInitScript(() => { try { localStorage.setItem('aeos:tour:seen','1'); } catch(e){} });
  page = await ctx.newPage();
  await login(ctx, page, 'http://democorp.aeos365.test', 'admin@democorp.com', 'Aeos365!Admin');
  results.push(`tenant after login -> ${page.url()}`);
  await shot(page, 'http://democorp.aeos365.test/dashboard', 'tenant-dashboard', {tour:true});

  // ---- Aeon LIVE CHAT: trigger a generative-UI answer from live data ----
  try {
    await page.goto('http://democorp.aeos365.test/aeon', { waitUntil: 'networkidle', timeout: 60000 });
    await page.waitForTimeout(3000);
    await killTour(page);
    results.push('  aeon-chat: loaded, body len=' + (await page.locator('body').innerText().catch(()=>'')).length);
    const before = (await page.locator('body').innerText().catch(()=>'')).length;
    // Prefer clicking a suggested-prompt chip (guaranteed send + triggers generative UI: a chart).
    const chip = page.locator('button:has-text("share of employees by type"), button:has-text("Break down employees by department")').first();
    if (await chip.count().catch(()=>0)) {
      await chip.click().catch(()=>{});
      results.push('  aeon-chat: clicked suggestion chip');
    } else {
      const input = page.locator('textarea, input[placeholder*="Aeon"]').first();
      await input.click().catch(()=>{});
      await input.pressSequentially('Show the share of employees by type', { delay: 15 }).catch(()=>{});
      await input.press('Enter').catch(()=>{});
      results.push('  aeon-chat: typed + enter');
    }
    // Wait for the assistant answer to grow the transcript meaningfully (Gemini + tool loop).
    await page.waitForFunction((b) => document.body.innerText.length > b + 120, before, { timeout: 40000 }).catch(()=>{});
    await page.waitForTimeout(5000);
    await page.evaluate(() => window.scrollTo(0, 0)).catch(()=>{});
    results.push('  aeon-chat: after body len=' + (await page.locator('body').innerText().catch(()=>'')).length);
    await page.screenshot({ path: `${OUT}/aeon-chat.png` });
    results.push('  aeon-chat: captured');
  } catch (e) { results.push('  aeon-chat: ERROR ' + e.message); }
  await ctx.close();

  await browser.close();
  console.log(results.join('\n'));
})();
