-- Mitra Ticketing System - Database Schema
-- Run this SQL to set up the database

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','agent','client') NOT NULL DEFAULT 'client',
  `company` VARCHAR(150) DEFAULT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `last_login` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `departments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `manager_id` INT DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tickets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_number` VARCHAR(20) NOT NULL UNIQUE,
  `subject` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `status` ENUM('open','pending','resolved','closed') DEFAULT 'open',
  `priority` ENUM('low','medium','high','critical') DEFAULT 'medium',
  `category` VARCHAR(100) DEFAULT NULL,
  `user_id` INT NOT NULL,
  `agent_id` INT DEFAULT NULL,
  `department_id` INT DEFAULT NULL,
  `due_date` DATE DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `closed_at` DATETIME DEFAULT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`agent_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ticket_replies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `message` TEXT NOT NULL,
  `is_internal` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ticket_attachments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT NOT NULL,
  `reply_id` INT DEFAULT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `file_size` INT DEFAULT 0,
  `mime_type` VARCHAR(100) DEFAULT NULL,
  `uploaded_by` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `action` VARCHAR(255) NOT NULL,
  `entity_type` VARCHAR(50) DEFAULT NULL,
  `entity_id` INT DEFAULT NULL,
  `details` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin user (password: Admin@1234)
INSERT INTO `users` (`name`, `email`, `password`, `role`) VALUES
('System Admin', 'admin@mitra.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Default departments
INSERT INTO `departments` (`name`, `email`) VALUES
('Technical Support', 'support@mitra.local'),
('Network & Infrastructure', 'network@mitra.local'),
('Software & Applications', 'software@mitra.local'),
('Billing & Accounts', 'billing@mitra.local');

-- Default categories
INSERT INTO `categories` (`name`, `description`) VALUES
('Hardware Issue', 'Physical hardware problems'),
('Software Issue', 'Application or OS problems'),
('Network / Connectivity', 'Internet and network issues'),
('Account & Access', 'Login, password, and permissions'),
('Email', 'Email configuration or delivery'),
('Security', 'Virus, malware, or security concerns'),
('New Request', 'New equipment or service requests'),
('Other', 'General inquiries');

-- Default settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('company_name', 'Mitra'),
('company_email', 'support@mitra.local'),
('company_phone', '(780) 555-0100'),
('company_address', 'Edmonton, AB, Canada'),
('tickets_per_page', '15'),
('allow_registration', '1'),
('email_notifications', '0'),
('ticket_prefix', 'MIT');
-- ============================================================
-- Mitra — POS & Invoicing Module Schema
-- Run this SQL AFTER install.sql (it adds to the same DB)
-- ============================================================

-- Products / Services catalog
CREATE TABLE IF NOT EXISTS `pos_products` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `sku`         VARCHAR(80)   DEFAULT NULL,
  `name`        VARCHAR(200)  NOT NULL,
  `description` TEXT          DEFAULT NULL,
  `category_id` INT           DEFAULT NULL,
  `type`        ENUM('product','service','labour') DEFAULT 'product',
  `price`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `cost`        DECIMAL(10,2) DEFAULT 0.00,
  `tax_rate`    DECIMAL(5,2)  DEFAULT 0.00,
  `stock_qty`   INT           DEFAULT NULL COMMENT 'NULL = unlimited/service',
  `unit`        VARCHAR(30)   DEFAULT 'ea',
  `is_active`   TINYINT(1)    DEFAULT 1,
  `created_at`  DATETIME      DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Product categories
CREATE TABLE IF NOT EXISTS `pos_product_categories` (
  `id`   INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `color` VARCHAR(20)  DEFAULT '#2f81f7'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Invoices
CREATE TABLE IF NOT EXISTS `pos_invoices` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_number` VARCHAR(30)   NOT NULL UNIQUE,
  `client_id`      INT           NOT NULL,
  `ticket_id`      INT           DEFAULT NULL COMMENT 'Link to support ticket',
  `status`         ENUM('draft','sent','paid','partial','overdue','void') DEFAULT 'draft',
  `issue_date`     DATE          NOT NULL,
  `due_date`       DATE          DEFAULT NULL,
  `subtotal`       DECIMAL(10,2) DEFAULT 0.00,
  `tax_total`      DECIMAL(10,2) DEFAULT 0.00,
  `discount_type`  ENUM('percent','fixed') DEFAULT 'fixed',
  `discount_value` DECIMAL(10,2) DEFAULT 0.00,
  `discount_total` DECIMAL(10,2) DEFAULT 0.00,
  `total`          DECIMAL(10,2) DEFAULT 0.00,
  `amount_paid`    DECIMAL(10,2) DEFAULT 0.00,
  `balance`        DECIMAL(10,2) DEFAULT 0.00,
  `notes`          TEXT          DEFAULT NULL,
  `terms`          TEXT          DEFAULT NULL,
  `currency`       VARCHAR(3)    DEFAULT 'CAD',
  `created_by`     INT           DEFAULT NULL,
  `created_at`     DATETIME      DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`client_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`ticket_id`)  REFERENCES `tickets`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Invoice line items
CREATE TABLE IF NOT EXISTS `pos_invoice_items` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_id`  INT           NOT NULL,
  `product_id`  INT           DEFAULT NULL,
  `description` VARCHAR(255)  NOT NULL,
  `quantity`    DECIMAL(10,3) DEFAULT 1.000,
  `unit_price`  DECIMAL(10,2) NOT NULL,
  `tax_rate`    DECIMAL(5,2)  DEFAULT 0.00,
  `tax_amount`  DECIMAL(10,2) DEFAULT 0.00,
  `subtotal`    DECIMAL(10,2) DEFAULT 0.00,
  `sort_order`  INT           DEFAULT 0,
  FOREIGN KEY (`invoice_id`)  REFERENCES `pos_invoices`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`)  REFERENCES `pos_products`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payments received
CREATE TABLE IF NOT EXISTS `pos_payments` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_id`     INT           NOT NULL,
  `amount`         DECIMAL(10,2) NOT NULL,
  `method`         ENUM('cash','credit_card','debit','cheque','etransfer','other') DEFAULT 'cash',
  `reference`      VARCHAR(100)  DEFAULT NULL COMMENT 'Cheque #, transaction ID, etc.',
  `note`           VARCHAR(255)  DEFAULT NULL,
  `payment_date`   DATE          NOT NULL,
  `recorded_by`    INT           DEFAULT NULL,
  `created_at`     DATETIME      DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`invoice_id`)  REFERENCES `pos_invoices`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- POS Sale sessions (quick counter sales, no invoice needed)
CREATE TABLE IF NOT EXISTS `pos_sales` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `sale_number` VARCHAR(30)   NOT NULL UNIQUE,
  `client_id`   INT           DEFAULT NULL,
  `ticket_id`   INT           DEFAULT NULL,
  `subtotal`    DECIMAL(10,2) DEFAULT 0.00,
  `tax_total`   DECIMAL(10,2) DEFAULT 0.00,
  `discount`    DECIMAL(10,2) DEFAULT 0.00,
  `total`       DECIMAL(10,2) DEFAULT 0.00,
  `tendered`    DECIMAL(10,2) DEFAULT 0.00,
  `change_due`  DECIMAL(10,2) DEFAULT 0.00,
  `payment_method` ENUM('cash','credit_card','debit','cheque','etransfer','other') DEFAULT 'cash',
  `note`        VARCHAR(255)  DEFAULT NULL,
  `served_by`   INT           DEFAULT NULL,
  `created_at`  DATETIME      DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`client_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`served_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- POS Sale line items
CREATE TABLE IF NOT EXISTS `pos_sale_items` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `sale_id`     INT           NOT NULL,
  `product_id`  INT           DEFAULT NULL,
  `description` VARCHAR(255)  NOT NULL,
  `quantity`    DECIMAL(10,3) DEFAULT 1.000,
  `unit_price`  DECIMAL(10,2) NOT NULL,
  `tax_rate`    DECIMAL(5,2)  DEFAULT 0.00,
  `tax_amount`  DECIMAL(10,2) DEFAULT 0.00,
  `subtotal`    DECIMAL(10,2) DEFAULT 0.00,
  FOREIGN KEY (`sale_id`)    REFERENCES `pos_sales`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `pos_products`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Expense tracking
CREATE TABLE IF NOT EXISTS `pos_expenses` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `category`     VARCHAR(100) DEFAULT NULL,
  `description`  VARCHAR(255) NOT NULL,
  `amount`       DECIMAL(10,2) NOT NULL,
  `expense_date` DATE NOT NULL,
  `vendor`       VARCHAR(150) DEFAULT NULL,
  `receipt_ref`  VARCHAR(100) DEFAULT NULL,
  `recorded_by`  INT DEFAULT NULL,
  `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default product categories
INSERT IGNORE INTO `pos_product_categories` (`name`, `color`) VALUES
('Hardware',        '#2f81f7'),
('Software',        '#a371f7'),
('Labour / Service','#3fb950'),
('Cables & Parts',  '#f0883e'),
('Networking',      '#d29922'),
('Accessories',     '#f85149'),
('Other',           '#656d76');

-- Default products / services
INSERT IGNORE INTO `pos_products` (`sku`,`name`,`type`,`price`,`tax_rate`,`unit`,`stock_qty`) VALUES
('LAB-HR',  'Labour — Hourly Rate',         'labour',  125.00, 5.00, 'hr',  NULL),
('LAB-DIAG','Diagnostic Fee',               'service',  75.00, 5.00, 'ea',  NULL),
('LAB-SITE','On-Site Visit',               'service', 150.00, 5.00, 'visit',NULL),
('SFT-AV',  'Antivirus License (1yr)',      'service',  49.99, 5.00, 'seat',NULL),
('SFT-O365','Microsoft 365 Business (1mo)','service',  22.00, 5.00, 'seat',NULL),
('HW-SSD',  'SSD 500GB',                   'product', 89.99, 5.00, 'ea',  10),
('HW-RAM',  'RAM 16GB DDR4',               'product', 59.99, 5.00, 'ea',  8),
('HW-KB',   'Keyboard & Mouse Combo',      'product', 39.99, 5.00, 'ea',  5),
('NET-CAB', 'Cat6 Ethernet Cable 10ft',    'product',  9.99, 5.00, 'ea',  20),
('NET-SW',  'Network Switch 8-Port',       'product', 49.99, 5.00, 'ea',  4);

-- POS settings
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('pos_tax_name',         'GST'),
('pos_tax_rate',         '5.00'),
('pos_currency',         'CAD'),
('pos_invoice_prefix',   'INV'),
('pos_sale_prefix',      'SALE'),
('pos_next_invoice',     '1001'),
('pos_next_sale',        '1001'),
('pos_invoice_terms',    'Payment due within 30 days. Thank you for your business.'),
('pos_invoice_footer',   'Mitra Business Suite — support@mitra.local'),
('pos_enable_stock',     '1');

-- Mitra branding & document design settings
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('branding_logo',            ''),
('branding_logo_width',      '180'),
('branding_primary_color',   '#2f81f7'),
('branding_accent_color',    '#a371f7'),
('branding_font',            'DM Sans'),
('invoice_show_logo',        '1'),
('invoice_show_tagline',     '1'),
('invoice_tagline',          'Professional IT Services'),
('invoice_color_scheme',     'dark'),
('invoice_header_bg',        '#1a1a2e'),
('invoice_header_text',      '#ffffff'),
('invoice_accent',           '#2f81f7'),
('quote_prefix',             'QUO'),
('quote_validity_days',      '30'),
('quote_default_notes',      'This quote is valid for 30 days. Prices subject to change after expiry.');
