# Sports Galaxy - Online Sports Shop Management

Sports Galaxy is a web-based online sports shop management application that modernizes retail administration by digitizing inventory records, streamlining product sales, and automating revenue calculations. This project was developed as part of the B.Sc. Computer Science (Major) curriculum at Barrackpore Rastraguru Surendranath College by Souvhagya Halder, Krishnendu Ghosh, and Ritam Sen. The system architecture detailed in "PROJECT SRS DOCUMENT LATEST2.pdf" ensures a reliable platform for users to manage operations and purchase sports equipment.

## Key Features

* **Customer Ordering:** Customers can view available sports items, select items and quantities, view total bill amounts inclusive of taxes, and receive printable digital receipts.


* **Inventory Management:** Admins can add, update, archive, and manage sports item records. The system instantly deducts purchased quantities from active inventory upon order placement.


* **Automated Alerts:** Low-stock alerts are automatically displayed on the Admin dashboard when item quantities fall below their minimum threshold.


* **Analytics & Accounting:** The platform records and displays daily sales totals, transaction counts, and gross revenue. It also automatically calculates monthly net profit by subtracting item purchase costs and store operational expenses from gross sales revenue.


* **Delivery Coordination:** Delivery partners receive order details and update delivery milestones in the system. A shared verification code is sent to both the customer and delivery partner to ensure secure delivery validation.



## Technology Stack

* **Frontend:** HTML builds the product catalog fields and interactive checkout billing forms. CSS styles the stock inventory tables, dashboard layouts, and mobile-friendly views. JavaScript powers real-time live search, form validation, and instant math calculations for billing.


* **Backend:** PHP verifies logins, enforces role-based permissions, triggers instant system alerts for low stock, and processes financial calculations.


* **Database:** MySQL is used as a centralized relational database management system to store product records, user information, store operational costs, and order invoices.


* **Development Tools:** Developed using Visual Studio Code and designed for browser compatibility across Google Chrome, Microsoft Edge, and Mozilla Firefox.



## Stakeholder Roles

* **Admin:** Logs into the central dashboard to manage items, users, and payments, view all bookings, update statuses, and monitor real-time inventory and financial reports.


* **Delivery Partner:** Accesses a mobile-friendly view to check order addresses, collects packed products, and successfully delivers them to the customer's location.


* **Customer:** Registers for an account, searches for items using real-time filtering, views expected restock dates for out-of-stock items, and places orders.
