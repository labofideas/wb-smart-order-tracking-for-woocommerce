#!/usr/bin/env node
/**
 * WB Smart Order Tracking regression checks.
 *
 * Usage:
 * E2E_BASE_URL=http://wbrbpw.local \
 * E2E_USER=admin \
 * E2E_PASS=secret \
 * node wp-content/plugins/wb-smart-order-tracking-for-woocommerce/tests/e2e/wbsot-regression.js
 */
const { chromium } = require('playwright');
const path = require('path');

const baseUrl = process.env.E2E_BASE_URL || 'http://wbrbpw.local';
const user = process.env.E2E_USER;
const pass = process.env.E2E_PASS;

if (!user || !pass) {
  console.error('Missing E2E_USER or E2E_PASS.');
  process.exit(1);
}

async function run() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  const fixturePath = path.join(
    process.cwd(),
    'wp-content/uploads/wbsot-test-fixtures/wbsot-valid.csv'
  );

  try {
    await page.goto(`${baseUrl}/wp-login.php`, { waitUntil: 'domcontentloaded' });
    await page.fill('#user_login', user);
    await page.fill('#user_pass', pass);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      page.click('#wp-submit'),
    ]);

    await page.goto(`${baseUrl}/wp-admin/admin.php?page=wc-settings&tab=wb_order_tracking&section=customer`, { waitUntil: 'domcontentloaded' });
    await page.locator('input[name="wbsot_public_tracking_rate_limit"]').waitFor({ timeout: 10000 });

    await page.goto(`${baseUrl}/wp-admin/admin.php?page=wbsot-import`, { waitUntil: 'domcontentloaded' });
    await page.locator('input[name="wbsot_csv"]').setInputFiles(fixturePath);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      page.$eval('form[action*="admin-post.php"]', (form) => HTMLFormElement.prototype.submit.call(form)),
    ]);
    const importText = await page.locator('body').innerText();
    if (!importText.includes('Imported:')) {
      throw new Error('CSV import result notice missing');
    }

    await page.goto(`${baseUrl}/order-tracking/`, { waitUntil: 'domcontentloaded' });
    await page.fill('#wbsot_order_id', '999999');
    await page.fill('#wbsot_billing_email', 'qa@example.com');
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      page.click('button[name="wbsot_track_order"]'),
    ]);
    const trackingText = await page.locator('main').innerText();
    if (!trackingText.includes('Order not found.') && !trackingText.includes('Too many tracking requests')) {
      throw new Error('Shortcode result message missing');
    }

    await page.goto(`${baseUrl}/wp-admin/admin.php?page=wbsot-tools`, { waitUntil: 'domcontentloaded' });
    await page.locator('h1:has-text("WB Tracking Tools")').waitFor({ timeout: 10000 });

    console.log('WBSOT regression checks passed.');
  } finally {
    await browser.close();
  }
}

run().catch((error) => {
  console.error(error.message || error);
  process.exit(1);
});
