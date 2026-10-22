# Campus Canteen — Pre-Order and Kitchen Management System

A complete canteen pre-order and kitchen management website, built as a
**second-year diploma DBMS project**. Students order food from a laptop or phone,
the manager sends the order to the chef, and the student collects it with a QR code.

> **Important:** the payment in this project is a **simulation only**.
> No bank, no card company and no real money is involved. Students pay either by
> **scanning a QR code** (taken from a fake **Rs. 500 demo wallet**) or by
> **Cash on Delivery** at the counter.

---

## 1. What the project does

The website is split into **three separate systems**, each with its own login page.
A password only works on its own system — for example the chef's password is refused
on the staff login, with a friendly message pointing to the right system.

| System | Login page | What they can do |
|--------|------------|------------------|
| **Student System** &#127860; | `student/menu.php` | **No login needed.** Open the menu straight away, browse by category, add items to a cart and change quantities — a plain guest starts with **Rs. 500.00 demo money**. At checkout a demo account is created *silently* (no form, no password) just so the order has an owner, then they choose a pickup time and pay by **scanning a QR code with a phone** (money leaves the demo wallet) or **Cash on Delivery**, top up the demo wallet, follow the order status, see the order history, and view the QR pickup code when the order is Ready. Runs on the **laptop**. |
| **Staff System** &#128188; | `staff/login.php` | Log in on a **separate phone** to a dashboard showing **every order students place**, with a **pop-up notification** for each new order and each confirmed QR payment. Add / edit / disable menu items and categories, send orders to the chef (which rings the chef's phone), open full order details, verify a pickup QR code and mark an order Collected, and read daily reports (orders, sales, best sellers). |
| **Chef System** &#127859; | `chef/login.php` | Log in on a **separate phone** to a phone-friendly kitchen screen, with a **pop-up notification** the moment the staff sends an order. See the orders with item names and quantities, Accept an order, mark it Preparing, then mark it Ready. The chef never sees passwords or payment details. |

There is also a small fourth screen that needs **no login** — `pay.php`, the
**phone payment page**. It only opens after scanning the payment QR code, like
a real payment gateway.

### Separate link for every device

Each system has its own page and its own login — one link per device:

| Link | Device | What it is |
|------|--------|------------|
| `.../student/menu.php` | 💻 **Laptop** | **Start here to order — no login needed.** Menu, cart, checkout, payment QR and order tracking |
| `.../staff/login.php` | 📱 **Staff phone** | Staff login → live order board with **pop-up notifications** |
| `.../chef/login.php` | 📱 **Chef phone** | Chef login → kitchen screen with **pop-up notifications** |
| `.../pay.php?token=...` | 📱 Any phone | The payment page (opened only by scanning the payment QR) |
| `.../devices.php` | 💻 Laptop | Shows a QR code for each device, so the phones can scan straight to their own login |

Example with XAMPP: `http://localhost/canteen/student/menu.php`

The home page (`index.php`) shows the three systems as cards — click the Student card
and the menu opens straight away. Each system has its own coloured theme (orange =
student, blue = staff, green = chef) so it is always clear which system you are in.

> **Ordering never asks for a password.** A guest account is created quietly at
> checkout (visible in the `users` table as *Guest Student*) purely so the order has an
> owner. Logging in / registering stays **optional** — it only matters if you want your
> own named account. Staff and chef **do** keep their own logins, because those systems
> can cancel orders and change the menu.

### Order flow

```
GUEST OPENS THE MENU ──► Rs. 500.00 demo money already in the wallet
        │                   (NO LOGIN at any point)
        ▼
Student System (LAPTOP): fills the cart, chooses how to pay
        │
        │  at checkout a demo account is created silently, so the
        │  order has an owner and can be followed afterwards
        │
        ├─► QR code scan
        │       └─► LAPTOP shows a payment QR code
        │               │
        │               │  the student SCANS it with a PHONE
        │               ▼
        │       PHONE opens pay.php (no login, like a real gateway)
        │               │
        │               ├─► [Confirm payment] ──► Paid (Rs. leaves the wallet)
        │               │        (the LAPTOP screen flips to "Paid" by itself)
        │               └─► [Simulate failure] ──► Payment Failed ──► scan again
        │
        └─► COD (Cash on Delivery) ──────────► pay cash at the counter
        │
STAFF PHONE: 🔔 POP-UP "New order #N"  +  🔔 POP-UP "Payment confirmed"
        │
Staff System: "Send to chef" ──► Sent to Chef
        │
CHEF PHONE: 🔔 POP-UP "New order #N - Cook now"
        │
Chef System: "Accept" ──► Accepted ──► "Start preparing" ──► Preparing ──► "Mark ready" ──► Ready
        │
Student System: sees the QR pickup code
        │
Staff System: verifies the code ──► "Mark as Collected" ──► Collected
        (an order can never be collected twice)
```

**Order statuses used:** Pending Payment, Paid, Sent to Chef, Accepted, Preparing,
Ready, Collected, Cancelled, Payment Failed.
Only valid moves are allowed (for example a chef can never mark an order Collected),
and every important step is written to the `order_status_history` table with a timestamp.

---

## 2. Technology used

| Part | Technology |
|------|------------|
| Server side | PHP 8 (plain PHP, no framework — easy to read and study) |
| Database | MySQL / MariaDB (InnoDB, foreign keys, indexes, constraints) |
| Front end | HTML5, CSS3, a little JavaScript |
| QR codes | `assets/js/qrcode.js` (open source *qrcode-generator*, MIT licence) — drawn inside the browser, works **offline**, no API key and no internet service is used |
| Local server | XAMPP (Apache + MySQL + PHP) |

Nothing in this project needs an internet connection, a paid service or an API key.

---

## 3. How to install and run it with XAMPP

1. **Install XAMPP** from <https://www.apachefriends.org> (any recent version).
2. **Copy this project** into the XAMPP web folder:
   ```
   C:\xampp\htdocs\canteen\
   ```
   (On macOS/Linux it is usually `/opt/lampp/htdocs/`.)
3. Open the **XAMPP Control Panel** and press **Start** for **Apache** and **MySQL**.
4. You are ready — the website is now at:
   ```
   http://localhost/canteen/
   ```

### Testing the QR phone payment with your phone

The payment QR contains a link like `http://YOUR-PC-IP/canteen/pay.php?token=...`.
For your phone to open that link, the phone and the computer must be on the **same
WiFi**, and the computer must be opened with its **IP address** (not `localhost` —
on a phone, `localhost` means the phone itself):

1. Find your computer's IP: press `Win+R`, type `cmd`, run `ipconfig`,
   look for **IPv4 Address** (e.g. `192.168.0.118`).
2. Open the student system with that IP, e.g.
   `http://192.168.0.118/canteen/student/`
3. Place an order with **Online payment** — the payment QR now contains the IP
   link, so scanning it with your phone really opens the payment page.
4. No phone handy? Click **"Pay on this device (testing)"** under the QR — it
   opens the same payment page in a new tab.

### Database connection settings

The settings live at the top of `includes/config.php`:

```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'canteen_db');
define('DB_USER', 'root');
define('DB_PASS', '');      // XAMPP default: empty password
```

If you gave your MySQL user a password, write it in `DB_PASS` and nothing else needs changing.

---

## 4. How to create and seed the MySQL database

The file **`database/canteen_db.sql`** creates the database, all seven tables, the sample
menu and the demo accounts.

**Option A — phpMyAdmin (easiest)**

1. Open <http://localhost/phpmyadmin>
2. Click **Import** (top menu).
3. **Choose File** → select `database/canteen_db.sql` → press **Go**.
4. You should see the message *Import has been successfully finished*.

**Option B — MySQL command line**

```
mysql -u root < database/canteen_db.sql
```

> The file is safe to run again: it deletes and recreates the tables, so you can
> always reset the project back to the sample data.

### Database design (7 tables, third normal form)

| Table | What it stores |
|-------|----------------|
| `users` | Every account: student, manager, chef. Passwords are **bcrypt hashes** made with `password_hash()` — never plain text. |
| `categories` | Menu categories (Breakfast, Drinks, …). |
| `menu_items` | Food and drinks with the **current** price and an availability switch. |
| `orders` | One row per order: who, pickup time, payment method, status, total, and the unique random `pickup_code` used by the QR picture. |
| `order_items` | What was ordered. It keeps a **copy of the item name and price at the time of ordering**, so changing the menu later never rewrites old orders. |
| `payments` | One payment record per order (pending / paid / failed / due at pickup) plus a demo transaction reference. |
| `order_status_history` | An audit trail: from status, to status, who changed it, a note and a timestamp. |

Relationships: `users 1—N orders 1—N order_items`, `orders 1—1 payments`,
`orders 1—N order_status_history`, `categories 1—N menu_items`,
`menu_items 1—N order_items` (with `ON DELETE SET NULL` so old orders stay valid).

**All totals are calculated on the server** from the database prices. The browser never
sends a price, so a tampered form cannot change an order total.

---

## 5. Demo login accounts

Each system has its own login page — use the right one:

| System | Login page | Email | Password |
|--------|------------|-------|----------|
| Student System | `student/login.php` | `ali@student.local` | `Student@123` |
| Student System | `student/login.php` | `sara@student.local` | `Student@123` |
| Staff System | `staff/login.php` | `manager@canteen.local` | `Manager@123` |
| Chef System | `chef/login.php` | `chef@canteen.local` | `Chef@123` |

If you type a password on the **wrong** system's login page, it is refused with a
message that links to the correct one. (Try it — it makes a nice point in the viva.)

These accounts are also inserted by `database/canteen_db.sql`.
(You can also register a brand-new student from the **Register** page.)

The database also contains three sample orders so that the screens are not empty on the
first run — including order **#1002**, which is already **Ready**, so you can see a QR code
immediately.

---

## 6. How to test the full order workflow

Use three browser windows (or one normal + one private window) so the three roles stay
logged in at the same time.

**Step 1 — Student orders**
1. Open the **Student System** (`student/login.php`) and log in as `ali@student.local`.
2. Open **Menu**, pick a category, choose a quantity and press **Add**.
3. Open **Cart**, change a quantity, press **Update**.
4. Press **Choose pickup time & pay**, pick a time, choose a payment method and
   press **Place order**.

**Step 2 — Payment (scan the QR with a phone)**
- *Online (demo):* you land on the **QR payment screen**. A big QR code is shown
  on the laptop. Scan it with your **phone camera** — the phone opens the demo
  payment page (no login needed). Press **Confirm demo payment** on the phone
  and the laptop screen flips to *Payment received!* by itself.
  Press **Simulate a failed payment** on the phone instead and the order becomes
  **Payment Failed** — the laptop keeps showing the QR, scan again to retry.
  *(Testing on one computer? Press "Pay on this device" under the QR — it opens
  the same phone page in a new tab.)*
- *Cash at the counter:* the order stays **Pending Payment** with "pay at the counter".

**Step 3 — Staff sends it to the chef**
1. Open the **Staff System** (`staff/login.php`) and log in as `manager@canteen.local`.
2. On the **Staff dashboard**, find the order in the *To send* tab and press **Send to chef**.
3. The status becomes **Sent to Chef**.

**Step 4 — Chef works on it**
1. Open the **Chef System** (`chef/login.php`) and log in as `chef@canteen.local`.
2. On the **Kitchen screen** press **Accept order** → **Start preparing** → **Mark as Ready**.
   (The screen refreshes itself every 15 seconds.)

**Step 5 — QR pickup**
1. Log in again on the **Student System**, open **My Orders** → the order is **Ready** and
   shows the QR code plus the printed pickup code.
2. On the **Staff System**, open **Verify Pickup**, type the pickup code and press
   **Hand over food — mark as Collected**.
3. Try the same code again: the system refuses with
   *"already collected … cannot be collected a second time."*

**Step 6 — Reports**
Open **Reports** on the Staff System to see today's orders, sales, cash/online split,
the last 7 days and the best selling items.

**Also worth showing in the presentation**
- Typing the chef's password on the Staff login is refused and links to the Chef System.
- A student who opens a staff page gets a friendly *"This page is not for you"* page
  with a button that leads back to the Student System.
- A disabled menu item disappears from the student menu but stays inside old orders.
- Cancelling an order is possible only while the kitchen has not started it.

---

## 7. What is simulated, and what could be added later

### Simulated in this project
- **The student wallet.** Every account is created with **Rs. 500.00 of demo money**
  (`users.balance`). The balance is shown in the top bar of every student page and on
  the checkout page, where a **+ Add Rs. 500 demo money** button tops it up again for
  demonstrations. When a QR payment is confirmed, the amount is subtracted inside the
  same database transaction as the payment itself, with the user row locked, so two
  phones can never spend the same rupee twice. If the wallet cannot cover the order the
  payment is rejected and the student is asked to use **Cash on Delivery (COD)** instead.
  **No real money exists anywhere.**
- **Payment by QR scan.** The laptop shows a QR code that hides a long
  random token (32 characters). Scanning it opens `pay.php` on the phone, which
  looks like a small payment gateway. Pressing the confirmation button records
  "paid" in the database, takes the money out of the demo wallet, and the laptop
  screen updates itself. There is no bank, no card form and no real money.
  Every payment row is marked `is_simulation = 1`.
  The QR contains **only** the secret link — no name, card number, phone number
  or password — and the phone page shows no personal details either.
- **Cash on Delivery (COD).** The order stays **Pending Payment** with "pay at the
  counter". Nothing is taken from the wallet; the payment is recorded when the food
  is collected.
- **Cross-device pop-up notifications.** Every important step inserts a row into the
  `notifications` table addressed to a **role** (`manager` or `chef`). The staff phone
  and the chef phone each poll `api/notifications.php` every 5 seconds with
  `assets/js/notifications.js`, which shows a toast, plays a short beep generated by
  the Web Audio API (no sound file needed) and raises a system notification when the
  browser allows it. Unread rows are marked as read when they are handed out, so a
  message pops up once per device and never repeats.
- **QR pickup codes** are pictures of the random pickup code only. They contain no name, phone
  number, price or password. Staff can also type the code in by hand.
- **The cart** is kept in the PHP session (no database table), which is enough for this
  project and keeps the design simple.

### Possible future improvements
- A real payment gateway (e.g. a local bank API) with a proper server-to-server callback.
- Camera QR scanning on the manager phone (a JavaScript QR *reader*), instead of typing.
- Email or SMS notifications when the order becomes Ready.
- Stock quantity per menu item, and automatic disabling when something runs out.
- Tables / delivery instead of pickup only, and pre-orders for a future date.
- Login throttling and a password reset flow.
- Graphs for the reports page, and exporting them to PDF/Excel.

---

## 8. Project file structure

```
canteen/
├── database/
│   ├── canteen_db.sql          ← creates and seeds the whole database
│   └── upgrade_wallet_notifications.sql  ← add wallet + notifications to an OLD database
├── api/
│   └── notifications.php       ← JSON polling endpoint (staff phone / chef phone)
├── includes/
│   ├── config.php              ← database connection + settings
│   ├── functions.php           ← helpers, cart, wallet, notifications, status workflow
│   ├── auth.php                ← sessions, role checks, CSRF protection
│   ├── login_page.php          ← the login screen shared by the three systems
│   ├── header.php              ← top of every page + navigation + wallet badge
│   └── footer.php              ← bottom of every page (+ notifications.js for staff/chef)
├── assets/
│   ├── css/style.css           ← the whole design (responsive) + toast + wallet styles
│   └── js/
│       ├── main.js             ← small helpers + QR drawing + kitchen auto refresh
│       ├── notifications.js    ← polls the API and shows the pop-up toast + beep
│       ├── qrcode.js           ← open source QR generator (MIT), works offline
│       ├── jsqr.js             ← open source QR reader (MIT), used by the scanner
│       └── scanner.js          ← camera scanning logic (getUserMedia + jsQR)
├── student/                    ← 🍔 STUDENT SYSTEM (laptop)
│   ├── index.php               ← student system page (what you can do + login)
│   ├── login.php               ← student login (students only)
│   ├── menu.php                ← browse the menu, add to cart
│   ├── cart.php                ← change quantities
│   ├── checkout.php            ← wallet balance + pickup time + QR/COD + top-up
│   ├── payment.php             ← laptop screen: the payment QR code + wallet
│   ├── orders.php              ← order history
│   ├── order_details.php       ← status, history and the pickup QR code
│   └── scan.php                ← in-browser camera "Scan & pay"
├── staff/                      ← 📋 STAFF SYSTEM
│   ├── index.php               ← staff system page (what staff see + login)
│   └── login.php               ← staff login (staff only)
├── manager/                    ← the staff system's pages
│   ├── dashboard.php           ← sees every order students place, send to chef
│   ├── order_details.php       ← one order in full
│   ├── menu.php                ← add / edit / disable menu items
│   ├── verify.php              ← QR pickup verification
│   └── reports.php             ← daily orders, sales, best sellers
├── chef/                       ← 👨‍🍳 CHEF SYSTEM
│   ├── index.php               ← chef system page (what the chef sees + login)
│   ├── login.php               ← chef login (chefs only)
│   └── kitchen.php             ← phone-friendly kitchen board
├── index.php                   ← home page: pick your system
├── pay.php                     ← PHONE payment page (opens after scanning the QR)
├── login.php                   ← old address, redirects to the home page
├── register.php  logout.php
└── README.md
```

---

## 9. Security notes (good points for the viva)

- **Prepared statements everywhere** — all SQL goes through `db_run()` / `db_all()` /
  `db_row()` in `includes/functions.php`, so user input is never part of the SQL text.
- **Passwords** are stored with `password_hash()` (bcrypt) and checked with `password_verify()`.
- **Output escaping** with `e()` (htmlspecialchars) on every piece of printed text → XSS protection.
- **Role checks** (`require_role('student'|'manager'|'chef')`) guard every page.
- **CSRF token** on every POST form.
- **Server-side totals** — prices and totals come from the database, never from the browser.
- **Wallet spending is atomic** — the demo balance is subtracted inside the *same*
  transaction as the payment, with `SELECT ... FOR UPDATE` on the user row, so two phones
  confirming at the same instant can never spend the same rupee twice, and a rejected
  payment leaves the balance untouched.
- **Workflow rules** — `change_order_status()` is the single place that changes an order
  status, and it checks the allowed moves, uses a transaction and locks the row, so two
  people cannot collect the same order twice.
- **No secrets in the pages** — database credentials stay in `includes/config.php`,
  and no password is shown on any screen.
- Errors are logged, and visitors only see a friendly message.

## 10. Troubleshooting

| Problem | Solution |
|---------|----------|
| *"Database not ready"* on every page | Start **MySQL** in XAMPP and import `database/canteen_db.sql` (step 4). |
| Old database, missing `balance` / `notifications` | Import `database/upgrade_wallet_notifications.sql` instead of re-creating everything — it keeps your existing orders. |
| Staff / chef pop-ups never appear | Allow notifications for the site when the browser asks, keep the page open, and check `api/notifications.php` returns JSON after logging in. |
| Page cannot be found / CSS missing | Make sure the folder inside `htdocs` is named `canteen`, and open `http://localhost/canteen/`. |
| *"Access denied for user 'root'"* | Put your MySQL password into `DB_PASS` in `includes/config.php`. |
| The QR picture does not appear | The code is still printed in large text under it — type that code into the manager's *Verify Pickup* page. |
| Everything looks unstyled | Press **Ctrl+F5** to reload the CSS without cache. |
