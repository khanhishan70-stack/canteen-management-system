-- =====================================================================
--  UPGRADE script: student wallet + cross-device notifications
--
--  Run this ONLY if you already imported the old canteen_db.sql and
--  do not want to delete your existing data.
--  New installation? Just import canteen_db.sql instead (it already
--  contains everything below).
--
--  HOW TO USE (with XAMPP):
--    1. Start "Apache" and "MySQL" in the XAMPP Control Panel.
--    2. Open http://localhost/phpmyadmin
--    3. Choose the canteen_db database -> Import -> this file -> Go.
-- =====================================================================

USE canteen_db;

-- 1) Give every user a demo wallet balance (students start with Rs. 500).
ALTER TABLE users
    ADD COLUMN balance DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER role;

UPDATE users SET balance = 500.00 WHERE role = 'student';

-- 2) New table: pop-up notifications for the staff and chef phones.
CREATE TABLE IF NOT EXISTS notifications (
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
