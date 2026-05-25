-- ============================================================
--  CheesyBurger — Complete Database Import File
--  Import: phpMyAdmin → Import → Choose File → Go
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- ── CREATE & SELECT DATABASE ─────────────────────────────────
CREATE DATABASE IF NOT EXISTS `cheesyBurger`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `cheesyBurger`;

-- ============================================================
-- TABLE: users
-- ============================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(150) NOT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `name`       VARCHAR(100) NOT NULL,
  `phone`      VARCHAR(20)  NOT NULL DEFAULT '',
  `address`    VARCHAR(255) NOT NULL DEFAULT '',
  `city`       VARCHAR(50)  NOT NULL DEFAULT '',
  `role`       ENUM('user','admin') NOT NULL DEFAULT 'user',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- TABLE: menu
-- ============================================================
DROP TABLE IF EXISTS `menu`;
CREATE TABLE `menu` (
  `id`    INT(11)      NOT NULL AUTO_INCREMENT,
  `emoji` VARCHAR(10)  NOT NULL DEFAULT '🍔',
  `name`  VARCHAR(100) NOT NULL,
  `desc`  VARCHAR(255) NOT NULL DEFAULT '',
  `price` INT(11)      NOT NULL DEFAULT 0,
  `cat`   VARCHAR(30)  NOT NULL DEFAULT 'burger',
  `avail` TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- TABLE: riders
-- ============================================================
DROP TABLE IF EXISTS `riders`;
CREATE TABLE `riders` (
  `id`    INT(11)      NOT NULL AUTO_INCREMENT,
  `name`  VARCHAR(100) NOT NULL,
  `init`  VARCHAR(20)  NOT NULL DEFAULT '',
  `phone` VARCHAR(20)  NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- TABLE: orders
-- ============================================================
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id`            VARCHAR(30)  NOT NULL,
  `customer_id`   INT(11)      DEFAULT NULL,
  `customer_name` VARCHAR(100) NOT NULL DEFAULT '',
  `items`         TEXT         NOT NULL,
  `subtotal`      INT(11)      NOT NULL DEFAULT 0,
  `deliv_fee`     INT(11)      NOT NULL DEFAULT 80,
  `total`         INT(11)      NOT NULL DEFAULT 0,
  `payment`       VARCHAR(30)  NOT NULL DEFAULT 'cod',
  `status`        ENUM('pending','cooking','out','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `address`       VARCHAR(255) NOT NULL DEFAULT '',
  `phone`         VARCHAR(20)  NOT NULL DEFAULT '',
  `note`          TEXT         DEFAULT NULL,
  `lat`           DOUBLE       DEFAULT NULL,
  `lng`           DOUBLE       DEFAULT NULL,
  `rider`         INT(11)      DEFAULT NULL,
  `time`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_rider`    (`rider`),
  KEY `idx_status`   (`status`),
  CONSTRAINT `fk_ord_user`  FOREIGN KEY (`customer_id`) REFERENCES `users`  (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ord_rider` FOREIGN KEY (`rider`)       REFERENCES `riders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- TABLE: user_cart  (persistent cart — survives logout)
-- ============================================================
DROP TABLE IF EXISTS `user_cart`;
CREATE TABLE `user_cart` (
  `user_id` INT(11)      NOT NULL,
  `item_id` INT(11)      NOT NULL,
  `name`    VARCHAR(100) NOT NULL DEFAULT '',
  `emoji`   VARCHAR(10)  NOT NULL DEFAULT '🍔',
  `price`   INT(11)      NOT NULL DEFAULT 0,
  `qty`     INT(11)      NOT NULL DEFAULT 1,
  `updated` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `item_id`),
  CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- DATA: users
-- ╔═══════════════════════╦══════════════╦═════════╗
-- ║ Email                 ║ Password     ║ Role    ║
-- ╠═══════════════════════╬══════════════╬═════════╣
-- ║ admin@cheesy.com      ║ Admin@123    ║ admin   ║
-- ║ ali@example.com       ║ Test@1234    ║ user    ║
-- ║ sana@example.com      ║ Test@1234    ║ user    ║
-- ║ hamza@example.com     ║ Test@1234    ║ user    ║
-- ╚═══════════════════════╩══════════════╩═════════╝
-- NOTE: Hashes generated & verified with bcrypt (PHP compatible)
-- ============================================================
INSERT INTO `users` (`email`, `password`, `name`, `phone`, `address`, `city`, `role`) VALUES

('admin@cheesy.com',
 '$2y$12$hmC0BEtcDPBOjBfKu8.qpOcK4zaB8NZ9q8AHNAVeyOjKSHmhrbS/C',
 'Cheesy Admin',  '03001234567', 'Cheesy HQ, Raja Bazar', 'Rawalpindi', 'admin'),

('ali@example.com',
 '$2y$12$wrRdtpJTcgipsOq2xeKh4OBnNUC3sb1WYVsyIgHzP1zaJsTOpo.2G',
 'Ali Hassan',    '03111234567', 'House 12, Street 5, Satellite Town', 'Rawalpindi', 'user'),

('sana@example.com',
 '$2y$12$wrRdtpJTcgipsOq2xeKh4OBnNUC3sb1WYVsyIgHzP1zaJsTOpo.2G',
 'Sana Khan',     '03219876543', 'Block B, Bahria Town Phase 7',       'Rawalpindi', 'user'),

('hamza@example.com',
 '$2y$12$wrRdtpJTcgipsOq2xeKh4OBnNUC3sb1WYVsyIgHzP1zaJsTOpo.2G',
 'Hamza Malik',   '03451122334', 'G-9/3, Near Faizabad Metro',         'Islamabad',  'user');

-- ============================================================
-- DATA: menu  (17 items)
-- ============================================================
INSERT INTO `menu` (`emoji`, `name`, `desc`, `price`, `cat`, `avail`) VALUES
-- 🍔 Burgers
('🍔','Cheese Overload Burger', 'Triple cheese, beef patty, crispy bacon',             950, 'burger',  1),
('🍔','Classic Smash Burger',   'Double smash patty, American cheese, pickles',         750, 'burger',  1),
('🍔','Spicy Fiery Burger',     'Jalapeño, habanero sauce, crispy chicken fillet',       820, 'burger',  1),
('🍔','Zinger Deluxe Burger',  'Crispy zinger fillet, garlic mayo, coleslaw',           780, 'burger',  1),
-- 🍕 Pizzas
('🍕','4-Cheese Pizza',         'Mozzarella, cheddar, gouda, parmesan on thin crust',  1150, 'pizza',   1),
('🍕','BBQ Chicken Pizza',      'Smoky BBQ sauce, grilled chicken, mozzarella',        1050, 'pizza',   1),
('🍕','Pepperoni Blast',        'Double pepperoni, tomato base, mozzarella',           1100, 'pizza',   1),
-- 🍟 Fries
('🍟','Cheesy Fries Bucket',    'Loaded fries with nacho cheese sauce',                 480, 'fries',   1),
('🍟','Classic Salted Fries',   'Golden crispy fries with sea salt',                    280, 'fries',   1),
('🍟','Onion Rings',            'Crispy battered onion rings with ranch dip',           320, 'fries',   1),
-- 🌯 Wraps
('🌯','Zinger Cheese Wrap',     'Crispy zinger, cheese slice, garlic mayo',             550, 'wrap',    1),
('🌯','Grilled Chicken Wrap',   'Grilled chicken strips, lettuce, salsa sauce',         520, 'wrap',    1),
-- 🍨 Desserts
('🍨','Cheese Lava Brownie',    'Warm brownie, molten cheese drizzle, vanilla ice cream',420,'dessert', 1),
('🍦','Oreo Cheesecake Slice',  'New York style cheesecake with Oreo crust',            380, 'dessert', 1),
-- 🥤 Drinks
('🥛','Cheese Lava Shake',      'Thick milkshake with real cheese swirl',               380, 'drink',   1),
('🥤','Classic Coca-Cola',      'Chilled 350ml can',                                    120, 'drink',   1),
('🥤','Fresh Mint Lemonade',    'Fresh mint, lemon juice, sparkling soda',              180, 'drink',   1);

-- ============================================================
-- DATA: riders
-- ============================================================
INSERT INTO `riders` (`name`, `init`, `phone`) VALUES
('Usman Ghani',   '🛵 USM', '03001111111'),
('Bilal Ahmed',   '🛵 BIL', '03002222222'),
('Tariq Mehmood', '🛵 TRQ', '03003333333');

-- ============================================================
-- DATA: sample orders  (use on track.php to test)
-- IDs: CB-TEST001 (cooking) | CB-TEST002 (out for delivery)
-- ============================================================
INSERT INTO `orders`
  (`id`,`customer_id`,`customer_name`,`items`,
   `subtotal`,`deliv_fee`,`total`,
   `payment`,`status`,`address`,`phone`,`note`,
   `lat`,`lng`,`rider`,`time`)
VALUES
('CB-TEST001', 2, 'Ali Hassan',
 '[{"id":1,"name":"Cheese Overload Burger","e":"\ud83c\udf54","price":950,"qty":2},{"id":8,"name":"Cheesy Fries Bucket","e":"\ud83c\udf5f","price":480,"qty":1}]',
 2380, 80, 2460, 'cod', 'cooking',
 'House 12, Street 5, Satellite Town, Rawalpindi',
 '03111234567', 'Extra cheese on both burgers!',
 33.6007, 73.0679, 1, NOW()),

('CB-TEST002', 3, 'Sana Khan',
 '[{"id":5,"name":"4-Cheese Pizza","e":"\ud83c\udf55","price":1150,"qty":1},{"id":15,"name":"Cheese Lava Shake","e":"\ud83e\udd5b","price":380,"qty":2}]',
 1910, 80, 1990, 'jazzcash', 'out',
 'Block B, Bahria Town Phase 7, Rawalpindi',
 '03219876543', '', 33.5651, 73.0169, 2, NOW());

COMMIT;

-- ============================================================
-- ✅ DONE!  Verify with:
--    SELECT id, email, role FROM users;
--    SELECT COUNT(*) as menu_items FROM menu;
--    SELECT id, status FROM orders;
-- ============================================================
