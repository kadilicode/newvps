-- ============================================
-- KADILI NET v4 - Clean Database Schema
-- Domain: kadilihotspot.online
-- Created by KADILI DEV
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. SETTINGS TABLE
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tumia INSERT IGNORE ili isilete kosa ikikuta data ipo
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('setup_fee_enabled', '1'),
('setup_fee_amount', '150000'),
('monthly_fee', '10000'),
('sms_enabled', '1'),
('otp_enabled', '0'),
('palmpesa_api_key', 'lBdPSHSGWkO4qSChKVr40EjVaGQuIPjOmWHj1o7Cs3GaQaLYSzkhZy5jXVm9'),
('beem_sms_api_key', 'f8d1f94c2e0c105e'),
('beem_sms_secret', 'ODJmYTVkMWI5N2U3OTZmZWEzZWE3NjZlOWQ4OTBmOTExYjRiM2E0NGE2ZjA5ZGNkZWRiNjBmNDYxNmQxMmYwNA=='),
('beem_otp_api_key', 'e7290c98483303bf'),
('beem_otp_secret', 'NjFhNjNmMWVkNTFhODc0MTQzMzg5ZjdiY2FmZDY0MWQyMTljN2IyOWYxOTM3YmE1MzBmNDkzYWI2YzQ0ZDk2OQ=='),
('sender_id', 'KADILINET'),
('site_name', 'KADILI NET'),
('site_domain', 'kadilihotspot.online'),
('withdrawal_min', '31500'),
('withdrawal_fee_percent', '5'),
('vpn_server_host', 'kadilihotspot.online'),
('vpn_psk', 'kadili2024vpn');

-- 2. ADMINS
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO `admins` (`name`, `email`, `password`) VALUES
('KADILI Admin', 'kadiliy17@gmail.com', '$2b$12$VzvgRqcNYCh4z314ghZY9.Tq4pdJNBMpD9KgHQVGW240bilv8VdPm');

-- 3. RESELLERS
CREATE TABLE IF NOT EXISTS `resellers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `phone` VARCHAR(20) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `business_name` VARCHAR(200),
  `logo_url` VARCHAR(500),
  `status` ENUM('pending','active','suspended') DEFAULT 'pending',
  `phone_verified` TINYINT(1) DEFAULT 0,
  `setup_fee_paid` TINYINT(1) DEFAULT 0,
  `subscription_expires` DATE DEFAULT NULL,
  `balance` DECIMAL(12,2) DEFAULT 0.00,
  `palmpesa_api_key` VARCHAR(300) DEFAULT NULL,
  `use_own_gateway` TINYINT(1) DEFAULT 0,
  `portal_theme` ENUM('modern','classic','minimal') DEFAULT 'modern',
  `portal_color` VARCHAR(20) DEFAULT '#16a34a',
  `portal_bg_color` VARCHAR(20) DEFAULT '#f0fdf4',
  `portal_wifi_name` VARCHAR(100) DEFAULT NULL,
  `portal_footer_text` VARCHAR(255) DEFAULT NULL,
  `portal_show_voucher` TINYINT(1) DEFAULT 1,
  `portal_show_packages` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 4. ROUTERS (V4 FIXED)
CREATE TABLE IF NOT EXISTS `routers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `reseller_id` INT NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `host` VARCHAR(100) NOT NULL,
  `port` INT DEFAULT 8728,
  `username` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `vpn_type` ENUM('direct','sstp','l2tp') DEFAULT 'direct',
  `status` ENUM('online','offline','unknown') DEFAULT 'unknown',
  `setup_step` ENUM('none','step1','step2','step3','complete') DEFAULT 'none',
  `vpn_token` VARCHAR(64) DEFAULT NULL,
  `vpn_assigned_ip` VARCHAR(45) DEFAULT NULL,
  `wan_interface` VARCHAR(50) DEFAULT NULL,
  `bridge_name` VARCHAR(50) DEFAULT 'KADILI-BRIDGE',
  `gateway_ip` VARCHAR(20) DEFAULT '192.168.88.1',
  `subnet_size` VARCHAR(10) DEFAULT '/24',
  `hotspot_dns` VARCHAR(100) DEFAULT 'wifi.com',
  `deploy_log` TEXT DEFAULT NULL,
  `last_heartbeat` DATETIME DEFAULT NULL,
  `hotspot_name` VARCHAR(100) DEFAULT NULL,
  `last_checked` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_reseller_router` (`reseller_id`),
  FOREIGN KEY (`reseller_id`) REFERENCES `resellers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. PACKAGES
CREATE TABLE IF NOT EXISTS `packages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `reseller_id` INT NOT NULL,
  `router_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `duration_value` INT NOT NULL,
  `duration_unit` ENUM('minutes','hours','days','weeks','months') DEFAULT 'hours',
  `speed_limit` VARCHAR(50) DEFAULT NULL,
  `profile_name` VARCHAR(100) NOT NULL,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`reseller_id`) REFERENCES `resellers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`router_id`) REFERENCES `routers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. VOUCHERS
CREATE TABLE IF NOT EXISTS `vouchers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `reseller_id` INT NOT NULL,
  `router_id` INT NOT NULL,
  `package_id` INT DEFAULT NULL,
  `code` VARCHAR(20) NOT NULL UNIQUE,
  `profile_name` VARCHAR(100),
  `status` ENUM('unused','used','expired') DEFAULT 'unused',
  `used_by_phone` VARCHAR(20) DEFAULT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `batch_id` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`reseller_id`) REFERENCES `resellers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`router_id`) REFERENCES `routers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. TRANSACTIONS
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `reseller_id` INT NOT NULL,
  `voucher_id` INT DEFAULT NULL,
  `package_id` INT DEFAULT NULL,
  `customer_phone` VARCHAR(20) NOT NULL,
  `customer_name` VARCHAR(150),
  `amount` DECIMAL(10,2) NOT NULL,
  `palmpesa_order_id` VARCHAR(100),
  `payment_status` ENUM('pending','completed','failed','cancelled') DEFAULT 'pending',
  `type` ENUM('voucher_sale','subscription','setup_fee','withdrawal') DEFAULT 'voucher_sale',
  `sms_sent` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`reseller_id`) REFERENCES `resellers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 8. HOTSPOT USERS
CREATE TABLE IF NOT EXISTS `hotspot_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `reseller_id` INT NOT NULL,
  `router_id` INT NOT NULL,
  `username` VARCHAR(100) NOT NULL,
  `password` VARCHAR(100),
  `mac_address` VARCHAR(20),
  `phone` VARCHAR(20),
  `package_id` INT DEFAULT NULL,
  `session_start` DATETIME DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `reminder_sent` TINYINT(1) DEFAULT 0,
  `status` ENUM('active','expired') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`reseller_id`) REFERENCES `resellers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 9. OTHER TABLES (Simplified)
CREATE TABLE IF NOT EXISTS `otp_codes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `phone` VARCHAR(20) NOT NULL,
  `code` VARCHAR(10) NOT NULL,
  `used` TINYINT(1) DEFAULT 0,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `withdrawals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `reseller_id` INT NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `status` ENUM('pending','approved','rejected') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`reseller_id`) REFERENCES `resellers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
  
