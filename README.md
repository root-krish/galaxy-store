# Sports Galaxy

A PHP sports shop and inventory management project with separate customer, administrator, and delivery workspaces. The runnable demo includes a seeded catalogue, accounts, orders, and expenses.

## Run locally

Requires PHP **8.1 or newer** with JSON and sessions enabled. The local JSON demo needs no database. SMTP delivery requires Composer dependencies; production MySQL requires PDO MySQL and online payments require cURL (see configuration below). PHP's optional mbstring extension improves Unicode text handling.

From the project directory:

```sh
php -S 127.0.0.1:8000 router.php
```

Open [Sports Galaxy](http://localhost:8000). Use the supplied router: it serves the application and public assets while blocking access to account data, application source, and SQL files. PHP's development server does not read Apache's access rules.

The application creates its local data file and lock on first use. The PHP process needs write access to the storage directory. To keep data elsewhere, set `SG_DATA_PATH` to an absolute JSON file path before starting the server. Back up that file while the server is stopped. To reset **demo data**, stop the server and remove that file; the next request recreates the seed catalogue and accounts.

## Demo accounts

| Workspace | Email | Password |
| --- | --- | --- |
| Customer | customer@sportsgalaxy.local | Customer@123 |
| Admin | admin@sportsgalaxy.local | Admin@123 |
| Delivery | delivery@sportsgalaxy.local | Delivery@123 |

The sign-in screen has buttons to fill these credentials. New registrations always create customer accounts.

## Customer journey

1. Browse the catalogue, filter by sport, and search names, brands, descriptions, or SKUs. Stock and restock information come from current inventory.
2. Add equipment to the cart and update quantities. Sign in or register before checkout.
3. Enter a delivery address and six-digit PIN. Optionally use `GALAXY10` for 10% off. The summary updates as the offer is entered.
4. Place an order to receive a printable receipt and delivery timeline. GST is 5% of the discounted subtotal. Delivery costs ₹79, with free delivery when the discounted subtotal is at least ₹999.
5. Open the order from your account to see its six-digit delivery verification code. Share the code with the partner when the equipment arrives.

The default demo simulates UPI/card payments and refunds. COD is collected on delivery. Configure Razorpay below to accept real online payments; real refunds require provider reconciliation.

## Admin and delivery journey

Administrators can create and edit categories with icons or photos, add or edit products, record stock adjustments, set individual reorder thresholds and expected restock dates, assign orders, pack orders, cancel open orders, and record operating expenses. Inventory changes are logged. Cancelling an order requires a non-empty reason (up to 500 characters), saved in its timeline for the buyer to see. It restores stock exactly once and marks a simulated prepaid payment refunded.

The dashboard shows actual daily sales, seven days of sales, popular products, low-stock alerts, and monthly profit. Sales include open and completed orders but exclude cancellations. Net profit uses item sales after discounts, plus external income, less historical item costs and operating expenses; GST and delivery charges are excluded. These are order-based project metrics, not payment settlement or accounting reports.

Delivery partners see assigned jobs in their delivery hub and can also shop using the same account. Their personal purchases appear under My orders. A partner cannot be assigned to deliver their own purchase. Start delivery, then enter the customer's verification code to mark the order delivered. COD becomes paid only after successful delivery. Closed orders cannot be reopened or reassigned.

Try this end to end: place a customer order, sign in as admin and assign Rohan, then sign in as delivery to start the trip. Obtain the code from the customer's order view and complete the handover.

## Storage and hosting

The running application uses a lock-protected **JSON repository**. Writes replace the data file atomically, and checkout validates stock while holding the transaction lock. This keeps the classroom demo portable and prevents simultaneous purchases from overselling.

The original `schema.sql` and `seed.sql` are reference files. The PDO adapter uses `database/runtime.sql` and the migration command documented below; merely importing the reference files does not switch storage.

For Apache, enable `mod_rewrite`, allow the supplied access rules, and use PHP 8.1+. The rules deny requests to private directories and data files. Keep the application on a trusted local/demo environment: it includes published demo credentials and simulated payments. For public deployment, create a personal administrator, disable demo access, and configure the database, payment, email and HTTPS operations documented below.

## Accounts, photos, and external income

- Admin → Categories & gear: create or edit category names, icons, tile colours, and photos. Add gear opens the product editor with the category selected. Products accept a custom icon or emoji, or an uploaded photo. Categories appear immediately on the homepage and in catalogue filters.
- Admin → Administrators: an existing admin can create another admin with full portal access. Public registration cannot create admins. Each admin can update their own name, email, and phone in Profile; changing a password requires the current password.
- Admin → Orders: use status filters and search by order, customer, phone, city, or item. Each order card separates delivery assignment from the next order action and includes expandable item details and tracking.
- Admin → Delivery team: create a delivery partner with a name, email, phone, and initial password. Use Edit details on an existing partner to change their name, email, phone, or optionally reset their password. Only admins can change these partner details. Manage account access can block, restore, or delete a partner with a required reason of 5–500 characters. Each action records its reason, time, and administrator. Blocking or deleting immediately revokes existing sessions and returns active deliveries to packed for reassignment, without changing stock or payment. Deleted accounts cannot sign in or be restored; their records remain for order and audit history. Restoring a blocked account requires a fresh sign-in. Public registration always creates customers, even if a staff role is submitted.
- Admin → Finance: use Add external income for sponsorship, other earnings, or external profit. Income has its own dated ledger and increases profit for that month, without inflating product sales.
- Admin → Products: upload or replace a product photo, keep the existing icon by leaving the file empty, or select the default option to remove a custom photo. Inventory also links to the item photo editor.
- Profile in the navigation: customers and administrators can update their own name, email, and phone. The updated email becomes their sign-in address. Customers, administrators, and delivery partners can upload, replace, or reset their own photo. Initials remain the default.
- Admin → Store settings: change the shop name, upload or reset its logo, and choose accent, page background, and text colours. Settings apply throughout the site and receipts. Choose a light background and dark text; unreadable combinations are rejected. Additional options include the announcement, homepage heading and description, shop button text, footer description, support contact links, secondary colour, heading font, product card corners, desktop product columns, and homepage section visibility. Restore defaults resets all website options without changing finance settings. Existing stores receive default branding automatically.
- Photos accept JPG, PNG, and WebP, up to 2 MB and 4096 × 4096 pixels. Files are checked on the server, assigned random names, and stored privately. They are served as images through the application. SVG and executable files are not accepted.
- Set SG_UPLOAD_PATH to an absolute directory to store photos outside the project. Back up the upload directory alongside the JSON file. Historical product photos remain available for existing receipts when an image is replaced.

## Verification

Run the regression checks from the project directory:

```sh
php tests/store.php
php tests/features.php
php tests/admin.php
python3 tests/test_http.py
```

Optional browser checks require Node 22+ and installed Google Chrome: run node tests/browser.mjs. Set CHROME_BINARY if Chrome uses another executable name. These checks use temporary data and uploads.

The administrator suite checks account provisioning, profile edits, partner access and audit history, reassignment, category management, and website settings. The store suite checks totals, stock rollback, cancellation, verification codes, permissions, validation, and reporting. The HTTP suite starts its own PHP server on an available port with temporary data and tests registration, CSRF, private-file protection, role access, checkout, all admin tabs, and verified delivery. Python 3 is needed only for that test suite. Neither suite modifies the running demo's data.

## Team and licence

Developed as a B.Sc. Computer Science (Major) academic project at Barrackpore Rastraguru Surendranath College by Krishnendu Ghosh ([@root-krish](https://github.com/root-krish)), Souvhagya Halder, and Ritam Sen.

Licensed under GPL-3.0; see LICENSE.

## Secure storefront upgrades

The header now offers live suggestions, product pages have share metadata, and signed-in users have persistent wishlists. Add-to-cart opens an accessible drawer with Escape/focus handling supplied by native HTML dialog; browsers without dialog support retain normal form navigation. Current Chrome, Firefox, Safari and Edge are targeted; Chromium is verified in this task. JavaScript-disabled browsing, cart and checkout remain available. Serve production through HTTPS; no browser security settings need to be disabled.

Admin → Store settings controls demo access, service benefits, story, quote, categories and featured products. **First create a personal administrator in Admin → Administrators**, then disable demo access. This blocks all three seeded accounts, including existing sessions; changing their email does not bypass the restriction. Resetting branding preserves this security setting. Demo UPI/card methods are rejected when demo access is off. Existing charts and low-stock notifications remain available; Orders now exports Excel-compatible UTF-8 CSV with spreadsheet formula protection.

Security adds strict session IDs, HTTP-only SameSite cookies (Secure on HTTPS), login throttling, CSP, anti-framing and MIME protection. Existing CSRF validation applies to every POST, including AJAX, export and payments. Configure TLS termination so PHP receives the correct HTTPS indicator; forwarded headers are not blindly trusted.

### MySQL migration

PHP PDO MySQL is required. Keep JSON for local demo compatibility, or configure MySQL for deployment:

1. Stop application writes and back up the JSON file and uploads.
2. Create an empty UTF-8 MySQL 8 or MariaDB database and a dedicated database user.
3. Set `SG_DB_DSN` (for example `mysql:host=127.0.0.1;dbname=sports_galaxy;charset=utf8mb4`), `SG_DB_USER`, and `SG_DB_PASSWORD` in the process environment.
4. Run `php bin/migrate.php /absolute/path/to/data.json`. It installs `database/runtime.sql`, imports all existing accounts/orders/catalogue/settings and refuses to overwrite populated tables. The original file is preserved. The older `schema.sql` and `seed.sql` are reference-only and are not the runtime schema.
5. Start the app and workers with the same database environment. Never unset the DSN on a live deployment: that would select the old JSON store. Back up MySQL and uploads thereafter.

The PDO adapter uses prepared statements, InnoDB transactions, indexed entity relationships and relational order lines. Entity extension fields and immutable snapshots remain JSON columns. Only changed records are written. Business writes currently use a database mutex to preserve the original stock/ID guarantees; reads load the catalogue/state in memory. This is a safe migration foundation, **not a claim of unlimited throughput**; large catalogues should next move to paginated SQL queries and finer-grained inventory locks. Runtime DB users need SELECT/INSERT/UPDATE/DELETE; use separate schema privileges for migration.

### Real payments (Razorpay Payment Links)

Set `SG_RAZORPAY_KEY_ID` and `SG_RAZORPAY_KEY_SECRET` from the same Razorpay account. Use test keys for acceptance testing before live keys. Enable PHP cURL. Online checkout saves an unpaid order and reserves stock; its receipt offers a hosted Razorpay payment link supporting the payment methods enabled on your merchant account. No card information passes through this application.

Payment status is checked server-to-server against the stored link ID, order reference, INR amount and paid status. Browser-submitted payment claims are never accepted. Schedule `php bin/reconcile-payments.php` every minute, and let buyers use “Check payment status” on receipts. Unverified online orders cannot be packed or assigned. Payment links expire after 24 hours; admins must cancel abandoned orders to release stock. A timed-out link creation may need operator lookup by order reference before retrying; do not create replacement links without reconciling the original.

Cancellation of a pending linked order first cancels its provider link. Paid online cancellations are marked **refund_pending**; issue and reconcile refunds through the Razorpay dashboard. They are never falsely labelled refunded. Demo payment/refund semantics remain unchanged. Live gateway acceptance testing requires merchant credentials and has not been performed here.

API reference: [Razorpay Payment Links](https://razorpay.com/docs/api/payments/payment-links/create-standard/).

### Automated SMTP email

Run `composer install --no-dev` (PHPMailer is locked). Configure `SG_SMTP_HOST`, `SG_SMTP_PORT` (default 587), `SG_SMTP_USER`, `SG_SMTP_PASSWORD`, `SG_MAIL_FROM`, and `SG_ADMIN_EMAIL`. SMTP uses certificate-verified STARTTLS. Set `SG_PUBLIC_URL` to the canonical HTTPS origin for absolute social preview images.

Schedule `php bin/send-mail.php` every minute using the same storage/database environment as the web process. New order receipts, shipping notifications and low-stock/admin notifications are queued transactionally. SMTP failures stay queued with a retry delay; workers lease messages to avoid simultaneous sends. Delivery is at least once: a process crash after SMTP accepts a message can cause a retry. Historical seeded notifications are not mailed. Verify domain authentication and real mailbox delivery before launch. Credentials belong in the service environment, never source control.

Additional checks: `php tests/upgrades.php`; use a disposable imported database for `php tests/mysql.php`. Production activation requires database, HTTPS, personal admin, payment credentials, SMTP and the two scheduled workers above.

### Historical finance graphs

Admin → Finance provides Day, Month and Year views. Choose a date and select **View financial details**, or use Previous/Next. A day shows hourly activity, a month shows daily activity, and a year shows monthly activity. Open **View exact amounts and explore dates** to drill from a year to a month and then a specific day. The totals, order list, expense ledger and external income ledger all use the selected period. Income and expenses have dates rather than times, so the day graph places them at 00:00. Empty periods show zero values. These reports use order dates and the existing order-based profit rules; they are not payment settlement reports.

Admin → Store settings now has a separate **Demo access** On/Off panel and Save button. Create a personal administrator before disabling seeded accounts. The existing session revocation and lockout protections still apply.

Finance regression checks: `php tests/finance.php`.
