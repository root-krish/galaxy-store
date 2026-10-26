# Sports Galaxy

A PHP sports shop and inventory management project with separate customer, administrator, and delivery workspaces. The runnable demo includes a seeded catalogue, accounts, orders, and expenses.

## Run locally

Requires PHP **8.1 or newer** with JSON and sessions enabled. No Composer, Node, or database installation is required. PHP's optional mbstring extension improves Unicode text handling.

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

COD, UPI, card payments, and prepaid refunds are **simulated**. No payment gateway is connected and no money moves.

## Admin and delivery journey

Administrators can add or edit products, record stock adjustments, set individual reorder thresholds and expected restock dates, assign orders, pack orders, cancel open orders, and record operating expenses. Inventory changes are logged. Cancelling an order requires a non-empty reason (up to 500 characters), saved in its timeline for the buyer to see. It restores stock exactly once and marks a simulated prepaid payment refunded.

The dashboard shows actual daily sales, seven days of sales, popular products, low-stock alerts, and monthly profit. Sales include open and completed orders but exclude cancellations. Net profit uses item sales after discounts, plus external income, less historical item costs and operating expenses; GST and delivery charges are excluded. These are order-based project metrics, not payment settlement or accounting reports.

Delivery partners see assigned jobs in their delivery hub and can also shop using the same account. Their personal purchases appear under My orders. A partner cannot be assigned to deliver their own purchase. Start delivery, then enter the customer's verification code to mark the order delivered. COD becomes paid only after successful delivery. Closed orders cannot be reopened or reassigned.

Try this end to end: place a customer order, sign in as admin and assign Rohan, then sign in as delivery to start the trip. Obtain the code from the customer's order view and complete the handover.

## Storage and hosting

The running application uses a lock-protected **JSON repository**. Writes replace the data file atomically, and checkout validates stock while holding the transaction lock. This keeps the classroom demo portable and prevents simultaneous purchases from overselling.

The SQL files provide a **reference MySQL 8 schema and seed catalogue** for a future database-backed version. Importing them does not switch the app to MySQL; a relational repository adapter is not included.

For Apache, enable `mod_rewrite`, allow the supplied access rules, and use PHP 8.1+. The rules deny requests to private directories and data files. Keep the application on a trusted local/demo environment: it includes published demo credentials and simulated payments. A public retail deployment would need real account provisioning, a payment integration, and deployment-specific operations.

## Accounts, photos, and external income

- Admin → Delivery team: create a delivery partner with a name, email, phone, and initial password. Use Edit details on an existing partner to change their name, email, phone, or optionally reset their password. Only admins can change these partner details. Public registration always creates customers, even if a staff role is submitted.
- Admin → Finance: use Add external income for sponsorship, other earnings, or external profit. Income has its own dated ledger and increases profit for that month, without inflating product sales.
- Admin → Products: upload or replace a product photo, keep the existing icon by leaving the file empty, or select the default option to remove a custom photo. Inventory also links to the item photo editor.
- Profile in the navigation: customers can update their own name, email, and phone. The updated email becomes their sign-in address. Customers, administrators, and delivery partners can upload, replace, or reset their own photo. Initials remain the default.
- Admin → Store settings: change the shop name, upload or reset its logo, and choose accent, page background, and text colours. Settings apply throughout the site and receipts. Choose a light background and dark text; unreadable combinations are rejected. Restore defaults resets branding without changing finance settings. Existing stores receive default branding automatically.
- Photos accept JPG, PNG, and WebP, up to 2 MB and 4096 × 4096 pixels. Files are checked on the server, assigned random names, and stored privately. They are served as images through the application. SVG and executable files are not accepted.
- Set SG_UPLOAD_PATH to an absolute directory to store photos outside the project. Back up the upload directory alongside the JSON file. Historical product photos remain available for existing receipts when an image is replaced.

## Verification

Run the regression checks from the project directory:

```sh
php tests/store.php
php tests/features.php
python3 tests/test_http.py
```

Optional browser checks require Node 22+ and installed Google Chrome: run node tests/browser.mjs. Set CHROME_BINARY if Chrome uses another executable name. These checks use temporary data and uploads.

The store suite checks totals, stock rollback, cancellation, verification codes, permissions, validation, and reporting. The HTTP suite starts its own PHP server on an available port with temporary data and tests registration, CSRF, private-file protection, role access, checkout, all admin tabs, and verified delivery. Python 3 is needed only for that test suite. Neither suite modifies the running demo's data.

## Team and licence

Developed as a B.Sc. Computer Science (Major) academic project at Barrackpore Rastraguru Surendranath College by Krishnendu Ghosh ([@root-krish](https://github.com/root-krish)), Souvhagya Halder, and Ritam Sen.

Licensed under GPL-3.0; see LICENSE.
