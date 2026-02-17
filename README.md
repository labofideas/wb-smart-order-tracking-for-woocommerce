# WB Smart Order Tracking for WooCommerce

WordPress plugin to add and display shipment tracking details for WooCommerce orders.

## Features

- Add one or multiple tracking numbers per order
- Built-in carrier presets with auto URL generation
- Tracking details in My Account and customer emails
- Public tracking form via `[wb_order_tracking]`
- CSV import with dry-run and strict mode
- Public lookup rate limiting and security event logging

## Requirements

- WordPress 6.5+
- WooCommerce 8.0+
- PHP 8.0+

## Development

### Plugin checks

```bash
wp plugin check wb-smart-order-tracking-for-woocommerce --format=table
```

### E2E regression

```bash
cd tests/e2e
npm install
npm run install:browsers
E2E_BASE_URL=http://wbrbpw.local E2E_USER=admin E2E_PASS=secret npm test
```
