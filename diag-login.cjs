const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ viewport:{width:1600,height:1000}, ignoreHTTPSErrors:true });
  const page = await ctx.newPage();
  const netlog = [];
  page.on('response', r => { const u=r.url(); if(/login|dashboard|two-factor|sanctum|csrf/i.test(u)) netlog.push(`${r.status()} ${r.request().method()} ${u}`); });

  await page.goto('http://democorp.aeos365.test/login', { waitUntil:'networkidle', timeout:60000 });
  console.log('start url:', page.url());
  console.log('has email input:', await page.locator('input[type=email]').count());
  console.log('has "Enter the demo":', await page.locator('button:has-text("Enter the demo")').count());
  console.log('submit buttons:', await page.locator('button[type=submit]').count(), 'total buttons:', await page.locator('button').count());

  // fill + submit via button[type=submit]
  await page.fill('input[type=email]', 'admin@democorp.com').catch(e=>console.log('fill email err', e.message));
  await page.fill('input[type=password]', 'Aeos365!Admin').catch(e=>console.log('fill pw err', e.message));
  await page.locator('button[type=submit]').last().click().catch(e=>console.log('click err', e.message));
  await page.waitForTimeout(5000);

  console.log('after-submit url:', page.url());
  console.log('cookies:', (await ctx.cookies()).map(c=>c.name).join(','));
  const alert = await page.locator('[role=alert], .text-danger, .text-red-500, [class*="error"]').allInnerTexts().catch(()=>[]);
  console.log('alert/errors:', JSON.stringify(alert).slice(0,300));
  const body = (await page.locator('body').innerText().catch(()=>'')).replace(/\s+/g,' ').slice(0,400);
  console.log('body snippet:', body);
  console.log('NETLOG:\n' + netlog.join('\n'));
  await browser.close();
})();
