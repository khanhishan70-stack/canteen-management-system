-- =====================================================================
--  Canteen Pre-Order and Kitchen Management System
--  Database file: canteen_db.sql
--
--  HOW TO USE (with XAMPP):
--    1. Start "Apache" and "MySQL" from the XAMPP Control Panel.
--    2. Open http://localhost/phpmyadmin
--    3. Click "Import" -> Choose File -> select this file -> Go.
--    (Or from a terminal:  mysql -u root < canteen_db.sql )
--
--  This file is safe to run again. It deletes and recreates the tables,
--  then fills them with sample categories, menu items and demo accounts.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS canteen_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE canteen_db;

-- Drop tables in reverse order of the relationships (children first),
-- otherwise the foreign keys would block the DROP commands.
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS order_status_history;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS menu_items;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

-- =====================================================================
-- TABLE 1: users  (students, manager and chef all live here)
--   The "role" column decides which pages a person is allowed to open.
--   Passwords are stored as bcrypt HASHES made by PHP password_hash().
--   The plain password is never saved in the database.
-- =====================================================================
CREATE TABLE users (
    user_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name      VARCHAR(100) NOT NULL,
    email          VARCHAR(150) NOT NULL,
    phone          VARCHAR(20)  DEFAULT NULL,
    password_hash  VARCHAR(255) NOT NULL,
    role           ENUM('student','manager','chef') NOT NULL DEFAULT 'student',
    balance        DECIMAL(10,2) NOT NULL DEFAULT 0,
    is_active      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE=InnoDB;

-- =====================================================================
-- TABLE 2: categories  (Breakfast, Drinks, ...)
-- =====================================================================
CREATE TABLE categories (
    category_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(60)  NOT NULL,
    description  VARCHAR(255) DEFAULT NULL,
    is_active    TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (category_id),
    UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB;

-- =====================================================================
-- TABLE 3: menu_items
--   "price" is the CURRENT price. Old orders keep their own copy of the
--   price inside the order_items table, so changing a price here never
--   changes the history of old orders.
-- =====================================================================
CREATE TABLE menu_items (
    item_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id   INT UNSIGNED NOT NULL,
    name          VARCHAR(100) NOT NULL,
    description   VARCHAR(255) DEFAULT NULL,
    price         DECIMAL(10,2) NOT NULL,
    is_available  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (item_id),
    KEY idx_menu_items_category (category_id),
    KEY idx_menu_items_available (is_available),
    CONSTRAINT fk_menu_items_category
        FOREIGN KEY (category_id) REFERENCES categories (category_id)
        ON DELETE RESTRICT,
    CONSTRAINT chk_menu_items_price CHECK (price >= 0)
) ENGINE=InnoDB;

-- =====================================================================
-- TABLE 4: orders  (one row = one student order)
--   pickup_code is a long random code (64 bits of randomness).
--   Only this code is turned into the QR picture - no personal or
--   payment information is hidden inside the QR code.
--   status uses the exact status list required by the project.
-- =====================================================================
CREATE TABLE orders (
    order_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id        INT UNSIGNED NOT NULL,
    pickup_code    VARCHAR(24)  NOT NULL,
    pickup_time    TIME         NOT NULL,
    payment_method ENUM('online','cash') NOT NULL,
    status         ENUM('Pending Payment',
                        'Paid',
                        'Sent to Chef',
                        'Accepted',
                        'Preparing',
                        'Ready',
                        'Collected',
                        'Cancelled',
                        'Payment Failed') NOT NULL DEFAULT 'Pending Payment',
    total_amount   DECIMAL(10,2) NOT NULL DEFAULT 0,
    notes          VARCHAR(255)  DEFAULT NULL,
    created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,
    collected_at   DATETIME      DEFAULT NULL,
    PRIMARY KEY (order_id),
    UNIQUE KEY uq_orders_pickup_code (pickup_code),
    KEY idx_orders_user (user_id),
    KEY idx_orders_status (status),
    KEY idx_orders_created_at (created_at),
    CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON DELETE CASCADE,
    CONSTRAINT chk_orders_total CHECK (total_amount >= 0)
) ENGINE=InnoDB;

-- =====================================================================
-- TABLE 5: order_items
--   item_name and unit_price are COPIES taken at the moment of ordering.
--   item_id keeps a link to the menu, but is allowed to become NULL if
--   the menu item is deleted later (ON DELETE SET NULL).
--   The order total is always rebuilt from unit_price * quantity.
-- =====================================================================
CREATE TABLE order_items (
    order_item_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id      INT UNSIGNED NOT NULL,
    item_id       INT UNSIGNED DEFAULT NULL,
    item_name     VARCHAR(100) NOT NULL,
    unit_price    DECIMAL(10,2) NOT NULL,
    quantity      INT UNSIGNED NOT NULL,
    line_total    DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (order_item_id),
    KEY idx_order_items_order (order_id),
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders (order_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_order_items_item
        FOREIGN KEY (item_id) REFERENCES menu_items (item_id)
        ON DELETE SET NULL,
    CONSTRAINT chk_order_items_quantity CHECK (quantity BETWEEN 1 AND 50),
    CONSTRAINT chk_order_items_price    CHECK (unit_price >= 0),
    CONSTRAINT chk_order_items_total    CHECK (line_total >= 0)
) ENGINE=InnoDB;

-- =====================================================================
-- TABLE 6: payments
--   One payment record per order.
--   is_simulation = 1 for every row in this project, because the online
--   payment is only a DEMO. No real money and no real gateway is used.
--   payment_status:
--     pending        -> online order waiting for the student to confirm
--     paid           -> money received (demo card payment or cash at counter)
--     failed         -> the simulated card payment failed
--     due_at_pickup  -> cash order, to be paid when the food is collected
--   payment_token:
--     A long random code that is hidden INSIDE the payment QR code.
--     The student scans the QR with a phone, the phone opens
--     pay.php?token=... and the demo payment is confirmed there.
--     The token cannot be guessed, and it contains no personal details.
-- =====================================================================
CREATE TABLE payments (
    payment_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id         INT UNSIGNED NOT NULL,
    method           ENUM('online','cash') NOT NULL,
    amount           DECIMAL(10,2) NOT NULL,
    payment_status   ENUM('pending','paid','failed','due_at_pickup','refunded')
                     NOT NULL DEFAULT 'pending',
    is_simulation    TINYINT(1) NOT NULL DEFAULT 1,
    payment_token    VARCHAR(64) DEFAULT NULL,
    transaction_ref  VARCHAR(40) DEFAULT NULL,
    paid_at          DATETIME    DEFAULT NULL,
    created_at       TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (payment_id),
    UNIQUE KEY uq_payments_token (payment_token),
    KEY idx_payments_order (order_id),
    KEY idx_payments_status (payment_status),
    CONSTRAINT fk_payments_order
        FOREIGN KEY (order_id) REFERENCES orders (order_id)
        ON DELETE CASCADE,
    CONSTRAINT chk_payments_amount CHECK (amount >= 0)
) ENGINE=InnoDB;

-- =====================================================================
-- TABLE 7: order_status_history  (audit trail / timeline)
--   Every important step of an order writes one row here, so we always
--   know WHO changed WHAT and WHEN.
-- =====================================================================
CREATE TABLE order_status_history (
    history_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id     INT UNSIGNED NOT NULL,
    from_status  VARCHAR(20)  DEFAULT NULL,
    to_status    VARCHAR(20)  NOT NULL,
    changed_by   INT UNSIGNED DEFAULT NULL,
    note         VARCHAR(255) DEFAULT NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (history_id),
    KEY idx_history_order (order_id),
    KEY idx_history_created_at (created_at),
    CONSTRAINT fk_history_order
        FOREIGN KEY (order_id) REFERENCES orders (order_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_history_user
        FOREIGN KEY (changed_by) REFERENCES users (user_id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- TABLE 8: notifications
--   Pop-up messages for the STAFF phone and the CHEF phone.
--   Each notification is addressed to a ROLE (manager or chef), so
--   every device logged in as that role polls this table and shows
--   the unread rows as a pop-up. Once shown, is_read becomes 1.
-- =====================================================================
CREATE TABLE notifications (
    notification_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    role            ENUM('manager','chef') NOT NULL,
    order_id        INT UNSIGNED DEFAULT NULL,
    title           VARCHAR(100) NOT NULL,
    message         VARCHAR(255) NOT NULL,
    is_read         TINYINT(1)   NOT NULL DEFAULT 0,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id),
    KEY idx_notifications_role_read (role, is_read),
    KEY idx_notifications_created_at (created_at),
    CONSTRAINT fk_notifications_order
        FOREIGN KEY (order_id) REFERENCES orders (order_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- SAMPLE DATA: demo accounts
--   All four passwords below are bcrypt HASHES (made with password_hash).
--   Demo logins (see README.md):
--     student  : ali@student.local   /  Student@123
--     manager  : manager@canteen.local / Manager@123
--     chef     : chef@canteen.local  /  Chef@123
--     student  : sara@student.local  /  Student@123
-- =====================================================================
INSERT INTO users (full_name, email, phone, password_hash, role, balance) VALUES
('Ali Khan',      'ali@student.local',    '03001234567', '$2y$10$Q.WOKdCBBrdd3ZdQfIj1KOzt0X05IwdyKkorjMVrlyfklGCR8kEkW', 'student', 500.00),
('Sara Ahmed',    'sara@student.local',   '03119876543', '$2y$10$Q.WOKdCBBrdd3ZdQfIj1KOzt0X05IwdyKkorjMVrlyfklGCR8kEkW', 'student', 500.00),
('Canteen Manager','manager@canteen.local','03215551234', '$2y$10$fEmVlN8pmL4TfOMEfYt9t.UtK3NrUaq7XHWx25Ho2lU3UdVydptma', 'manager', 0.00),
('Chef Rahim',   'chef@canteen.local',   '03334445555', '$2y$10$rEqvFAHQ9iVd2aCMD8crM.q2jbA3kJA8WQj8uVtuG6nnLcP3rGOWW', 'chef',    0.00);

-- =====================================================================
-- SAMPLE DATA: categories
-- =====================================================================
INSERT INTO categories (category_id, name, description) VALUES
(1, 'Breakfast',            'Served from 8:00 AM to 11:00 AM'),
(2, 'Rice & Meals',         'Full plates for a hungry student'),
(3, 'Snacks',               'Quick bites between classes'),
(4, 'Burgers & Sandwiches', 'Handy food you can eat on the go'),
(5, 'Drinks',               'Hot and cold drinks'),
(6, 'Desserts',             'Something sweet after the meal');

-- =====================================================================
-- SAMPLE DATA: menu items
-- =====================================================================
INSERT INTO menu_items (item_id, category_id, name, description, price) VALUES
(1,  1, 'Egg Paratha Roll',      'Paratha wrapped around egg and chutney',  120.00),
(2,  1, 'Aloo Bhujia + Paratha', 'Potato bhujia with one fresh paratha',   100.00),
(3,  1, 'Omelette Sandwich',     'Bread omelette with tea sauce',           90.00),
(4,  2, 'Chicken Biryani',       'Fragrant rice with chicken piece',       250.00),
(5,  2, 'Vegetable Pulao',       'Rice cooked with mixed vegetables',      180.00),
(6,  2, 'Daal Chawal',           'Lentils with steamed rice',              150.00),
(7,  2, 'Chicken Karahi Rice',   'Spicy chicken karahi served with rice',  280.00),
(8,  3, 'Samosa (2 pcs)',        'Crispy vegetable samosas',                60.00),
(9,  3, 'French Fries',          'Salted potato fries',                    120.00),
(10, 3, 'Chicken Nuggets',       '6 pieces with chilli sauce',             150.00),
(11, 3, 'Pakora Plate',          'Mixed pakora with green chutney',         90.00),
(12, 4, 'Chicken Zinger Burger', 'Crispy chicken fillet burger',           220.00),
(13, 4, 'Club Sandwich',         'Triple layer chicken sandwich',          180.00),
(14, 4, 'Vegetable Sandwich',    'Cucumber, potato and salad sandwich',    120.00),
(15, 4, 'Beef Burger',           'Grilled beef patty burger',              250.00),
(16, 5, 'Water Bottle',          '500 ml mineral water',                    40.00),
(17, 5, 'Soft Drink',            '345 ml cold drink can',                   60.00),
(18, 5, 'Fresh Lime Juice',      'Fresh lime with mint',                    80.00),
(19, 5, 'Milk Tea',              'Desi chai in a cup',                      40.00),
(20, 5, 'Coffee',                'Instant coffee, hot',                     70.00),
(21, 6, 'Gulab Jamun (2 pcs)',   'Hot gulab jamun in sugar syrup',          60.00),
(22, 6, 'Chocolate Brownie',     'Soft brownie piece',                     120.00),
(23, 6, 'Vanilla Ice Cream',     'One scoop of vanilla ice cream',          80.00);

-- =====================================================================
-- SAMPLE ORDERS (so the manager screen and reports are not empty
-- when you first open the project).
--   Order 1001 : paid online, waiting for the manager to send to chef
--   Order 1002 : already Ready - open its page to see a QR code
--   Order 1003 : already collected (paid cash at the counter)
-- =====================================================================
INSERT INTO orders (order_id, user_id, pickup_code, pickup_time, payment_method, status, total_amount, created_at, collected_at) VALUES
(1001, 1, '77D1-5E90-A4B6-3C18', '12:45:00', 'online', 'Paid',           300.00, NOW() - INTERVAL 10 MINUTE, NULL),
(1002, 1, '9F3A-71C4-B0D8-2E55', '12:30:00', 'online', 'Ready',          370.00, NOW() - INTERVAL 40 MINUTE, NULL),
(1003, 2, '4B8E-2D19-C7A3-6F02', '11:30:00', 'cash',   'Collected',      160.00, NOW() - INTERVAL 3 HOUR,     NOW() - INTERVAL 2 HOUR);

-- The order_id values are auto-increment, so continue counting after 1003.
ALTER TABLE orders AUTO_INCREMENT = 1004;

INSERT INTO order_items (order_id, item_id, item_name, unit_price, quantity, line_total) VALUES
(1001, 12, 'Chicken Zinger Burger', 220.00, 1, 220.00),
(1001, 18, 'Fresh Lime Juice',      80.00, 1,  80.00),
(1002,  4, 'Chicken Biryani',       250.00, 1, 250.00),
(1002, 17, 'Soft Drink',             60.00, 2, 120.00),
(1003,  8, 'Samosa (2 pcs)',         60.00, 2, 120.00),
(1003, 19, 'Milk Tea',               40.00, 1,  40.00);

INSERT INTO payments (order_id, method, amount, payment_status, transaction_ref, paid_at) VALUES
(1001, 'online', 300.00, 'paid', 'DEMO-TXN-8C41K7', NOW() - INTERVAL 9 MINUTE),
(1002, 'online', 370.00, 'paid', 'DEMO-TXN-2P91M3', NOW() - INTERVAL 39 MINUTE),
(1003, 'cash',   160.00, 'paid', NULL,              NOW() - INTERVAL 2 HOUR);

INSERT INTO order_status_history (order_id, from_status, to_status, changed_by, note, created_at) VALUES
(1001, NULL, 'Pending Payment', 1, 'Order placed by student', NOW() - INTERVAL 10 MINUTE),
(1001, 'Pending Payment', 'Paid', 1, 'Demo online payment confirmed', NOW() - INTERVAL 9 MINUTE),
(1002, NULL, 'Pending Payment', 1, 'Order placed by student', NOW() - INTERVAL 40 MINUTE),
(1002, 'Pending Payment', 'Paid', 1, 'Demo online payment confirmed', NOW() - INTERVAL 39 MINUTE),
(1002, 'Paid', 'Sent to Chef', 3, 'Sent to kitchen by manager', NOW() - INTERVAL 35 MINUTE),
(1002, 'Sent to Chef', 'Accepted', 4, 'Chef accepted the order', NOW() - INTERVAL 30 MINUTE),
(1002, 'Accepted', 'Preparing', 4, 'Cooking started', NOW() - INTERVAL 25 MINUTE),
(1002, 'Preparing', 'Ready', 4, 'Food is ready for pickup', NOW() - INTERVAL 12 MINUTE),
(1003, NULL, 'Pending Payment', 2, 'Order placed by student', NOW() - INTERVAL 3 HOUR),
(1003, 'Pending Payment', 'Sent to Chef', 3, 'Cash at counter - sent to kitchen by manager', NOW() - INTERVAL 170 MINUTE),
(1003, 'Sent to Chef', 'Accepted', 4, 'Chef accepted the order', NOW() - INTERVAL 160 MINUTE),
(1003, 'Accepted', 'Preparing', 4, 'Cooking started', NOW() - INTERVAL 150 MINUTE),
(1003, 'Preparing', 'Ready', 4, 'Food is ready for pickup', NOW() - INTERVAL 130 MINUTE),
(1003, 'Ready', 'Collected', 3, 'QR code verified at the counter', NOW() - INTERVAL 120 MINUTE);
