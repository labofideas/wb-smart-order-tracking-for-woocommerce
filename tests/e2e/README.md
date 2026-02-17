# WB Smart Order Tracking E2E

Playwright regression checks for core plugin flows:
- settings screen load
- CSV import flow
- public tracking shortcode response
- tools page load

## Prerequisites

- WordPress site running locally
- Plugin active
- Node.js 18+

## Install

```bash
cd wp-content/plugins/wb-smart-order-tracking-for-woocommerce/tests/e2e
npm install
npm run install:browsers
```

## Run

```bash
cd wp-content/plugins/wb-smart-order-tracking-for-woocommerce/tests/e2e
E2E_BASE_URL=http://wbrbpw.local \
E2E_USER=Steve \
E2E_PASS=Steve \
npm test
```

## Expected Output

```text
WBSOT regression checks passed.
```

## Notes

- If browser binaries are missing, run:
```bash
npm run install:browsers
```
- If login fails, verify `E2E_USER` and `E2E_PASS`.
