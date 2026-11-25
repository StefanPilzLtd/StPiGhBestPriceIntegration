'use strict';
const { chromium, request } = require('../../artifacts/browser/node_modules/playwright');
const fs = require('node:fs');
const base = process.env.BP_BASE_URL;
const root = require('node:path').resolve(__dirname, '../..');
if (!base || !process.env.BP_TEST_PASSWORD) throw new Error('Disposable URL and synthetic admin credentials required');
const checks = [];
const check = (condition, name) => { if (!condition) throw new Error(name); checks.push(name); fs.writeFileSync(`${root}/artifacts/e2e-progress.json`,JSON.stringify({checks},null,2)); };
(async () => {
    const api = await request.newContext({ baseURL: base, timeout: 120000 });
    const login = await api.post('/api/oauth/token', { data: { grant_type: 'password', client_id: 'administration', scopes: 'write', username: process.env.BP_TEST_USER || 'admin', password: process.env.BP_TEST_PASSWORD } });
    check(login.ok(), 'Synthetic administrator authentication');
    const auth = await login.json();
    const headers = { Authorization: `Bearer ${auth.access_token}` };
    const action = '/api/_action/stpi-gh-bestprice';
    check((await api.get(`${action}/state`)).status() === 401, 'Unauthenticated configuration denied');
    const state = await api.get(`${action}/state`, { headers });
    if (!state.ok()) { process.stderr.write('State status '+state.status()+' '+(await state.text()).slice(0,1200)+'\n'); }
    check(state.ok(), 'Real administration state route');
    for (const endpoint of ['/pricing/preview', '/analytics/profitability']) {
        check((await api.post(action + endpoint, { headers, data: {} })).status() === 403, `Basic cannot bypass premium ${endpoint}`);
    }
    const fixture = JSON.parse(fs.readFileSync(`${root}/artifacts/fixture.json`, 'utf8'));
    const existing = await (await api.get(`${action}/feeds`, { headers })).json();
    let id = existing.items[0]?.id;
    if (!id) {
        const saved = await api.post(`${action}/feeds`, { headers, data: fixture.config });
        check(saved.ok(), 'Explicit product feed saved through guarded API');
        id = (await saved.json()).id;
    }
    const detail = await (await api.get(`${action}/feeds/${id}`, { headers })).json();
    const expectedFeedName = detail.name;
    check(detail.productIds.includes(fixture.productId), 'Selected product persists');
    check((await api.post(`${action}/feeds`, { headers, data: fixture.config })).status() === 403, 'Second feed denied in Basic');
    check((await api.patch(`/api/stpi-bp-feed/${id}`, { headers, data: { settings: { trackingEnabled: true } } })).status() >= 400, 'Generic DAL cannot bypass guarded configuration');
    if (process.env.BP_QUEUE_BUILD === '1') {
        const queued = await api.post(`${action}/feeds/${id}/build`, { headers });
        check(queued.status() === 202, 'Real feed build queued');
        fs.writeFileSync(`${root}/artifacts/e2e-feed.json`, JSON.stringify({ id, token: detail.token }));
        fs.writeFileSync(`${root}/artifacts/e2e-api.json`, JSON.stringify({ checks }, null, 2));
        await api.dispose(); return;
    }
    const stateData = await state.json();
    const feedHeaders = { Host: new URL(stateData.domains.find(d => d.id === detail.domainId).url).host };
    const feedPath = `/bestprice-feed/${id}/${detail.token}`;
    const xml = await api.get(`${feedPath}.xml`, { headers: feedHeaders });
    if (!xml.ok()) process.stderr.write('XML HTTP '+xml.status()+' '+(await xml.text()).slice(0,800)+'\n');
    check(xml.ok(), 'Published XML retrieved');
    check((await xml.text()).includes(fixture.productId), 'XML contains selected synthetic product');
    const csv = await api.get(`${feedPath}.csv`, { headers: feedHeaders });
    check(csv.ok(), 'Published CSV retrieved');
    check((await api.get(`${feedPath}.xml`, { headers: { ...feedHeaders, 'If-None-Match': xml.headers().etag } })).status() === 304, 'ETag avoids repeated download');
    check((await api.get(`${feedPath.replace(detail.token, '0'.repeat(64))}.xml`, { headers: feedHeaders })).status() === 404, 'Invalid public capability denied');
    const browser = await chromium.launch({ headless: process.env.BP_BROWSER_HEADLESS === '1', executablePath:process.env.BP_CHROMIUM||undefined, args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'] });
    const context = await browser.newContext({ viewport: { width: 1600, height: 1080 } });
    const page = await context.newPage(); const errors = []; const tracking = []; const diagnostics = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (message.type() === 'error' || message.type() === 'warning') diagnostics.push(message.text()); });
    page.on('response', response => { if (response.status() >= 400) diagnostics.push(`${response.status()} ${response.url().split('?')[0]}`); });
    page.on('requestfailed', req => diagnostics.push(`${req.failure()?.errorText} ${req.url().split('?')[0]}`));
    page.on('request', req => { if (req.url().includes('st-pi-gh-best-price-integration')) diagnostics.push(req.url()); if (/360\.bestprice\.gr|bestprice\.gr\/api\/order/.test(req.url())) tracking.push(req.url()); });
    await page.goto(`${base}/admin`, { timeout: 120000, waitUntil: 'domcontentloaded' });
    await page.locator('input[name="username"], #sw-field--username').first().fill(process.env.BP_TEST_USER || 'admin', { timeout: 120000 });
    await page.locator('input[type="password"]').first().fill(process.env.BP_TEST_PASSWORD);
    await page.locator('button[type="submit"]').first().click();
    await page.waitForURL(/#\/(?!login)/, { timeout: 120000 });
    await page.locator('.sw-admin-menu').waitFor({timeout:120000});
    await page.goto(`${base}/admin#/stpi/bestprice/index`, { timeout: 120000 });
    try { await page.locator('.stpi-gh-bestprice__intro').waitFor({ timeout: 60000 }); } catch(e) { fs.writeFileSync(`${root}/artifacts/browser-diagnostic.json`,JSON.stringify({url:page.url(),errors,diagnostics,body:(await page.locator('body').innerText()).slice(-5000),html:(await page.locator('#app').innerHTML()).slice(-6000)},null,2)); await page.screenshot({path:`${root}/artifacts/browser-failure.png`,fullPage:true}); throw e; }
    check(await page.locator('.stpi-gh-bestprice__intro').isVisible(), 'Native BestPrice administration renders');
    await page.locator('.stpi-gh-bestprice__steps').waitFor({timeout:120000});
    check(await page.locator('.stpi-gh-bestprice__steps li').count() === 5, 'Administration setup loads after authenticated API requests');
    fs.writeFileSync(`${root}/artifacts/browser-overview.json`,JSON.stringify({body:await page.locator('body').innerText(),tabs:await page.locator('.mt-tabs').evaluateAll(nodes=>nodes.map(n=>n.outerHTML)),scripts:await page.locator('script[src]').evaluateAll(nodes=>nodes.map(n=>n.src)),diagnostics,errors},null,2));
    const locale = (await page.locator('.mt-tabs__item[data-item-name="feeds"]').innerText()).includes('Produktfeeds') ? 'de-DE' : 'en-GB';
    await page.screenshot({ path: `${root}/artifacts/screenshots/dashboard.${locale}.png`, fullPage: true });
    await page.locator('.mt-tabs__item').nth(1).click();
    await page.locator('.sw-data-grid__body').waitFor({timeout:30000});
    await page.locator('.sw-data-grid__body').getByText(expectedFeedName, {exact:true}).waitFor({timeout:30000});
    check((await page.locator('.sw-data-grid__body').innerText()).includes(expectedFeedName), 'Saved feed appears in native administration grid');
    await page.screenshot({ path: `${root}/artifacts/screenshots/feeds.${locale}.png`, fullPage: true });
    check(errors.length === 0, `No administration JavaScript errors: ${errors.join('; ')}`);
    check(tracking.length === 0, 'Basic administration sends no BestPrice tracking requests');
    await browser.close(); await api.dispose();
    fs.writeFileSync(`${root}/artifacts/e2e-results.json`, JSON.stringify({ checks, javascriptErrors: errors, trackingRequests: tracking.length }, null, 2));
    process.stdout.write(JSON.stringify({ passed: checks.length, checks }, null, 2) + '\n');
})().catch(error => { fs.writeFileSync(`${root}/artifacts/e2e-error.txt`, error.stack); console.error(error.message); process.exit(1); });
