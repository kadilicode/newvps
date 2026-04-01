-- ============================================
-- KADILI NET v4 - Database Update Script
-- Run this ONCE on existing installation
-- Compatible: MySQL 5.7+ and 8.x
-- ============================================

USE kadili_net;

-- ─── Routers: new wizard columns ──────────────────────────────
ALTER TABLE `routers`
  ADD COLUMN IF NOT EXISTS `setup_step` ENUM('none','step1','step2','step3','complete') DEFAULT 'none',
  ADD COLUMN IF NOT EXISTS `vpn_token` VARCHAR(64) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `vpn_assigned_ip` VARCHAR(45) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `wan_interface` VARCHAR(50) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `bridge_name` VARCHAR(50) DEFAULT 'KADILI-BRIDGE',
  ADD COLUMN IF NOT EXISTS `gateway_ip` VARCHAR(20) DEFAULT '192.168.88.1',
  ADD COLUMN IF NOT EXISTS `subnet_size` VARCHAR(10) DEFAULT '/24',
  ADD COLUMN IF NOT EXISTS `hotspot_dns` VARCHAR(100) DEFAULT 'wifi.com',
  ADD COLUMN IF NOT EXISTS `deploy_log` TEXT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `last_heartbeat` DATETIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `last_checked` DATETIME DEFAULT NULL;

-- Enforce one router per reseller
ALTER TABLE `routers`
  ADD UNIQUE INDEX `unique_reseller_router` (`reseller_id`);

-- ─── Resellers: captive portal columns ───────────────────────
ALTER TABLE `resellers`
  ADD COLUMN IF NOT EXISTS `portal_theme` ENUM('modern','classic','minimal') DEFAULT 'modern',
  ADD COLUMN IF NOT EXISTS `portal_color` VARCHAR(20) DEFAULT '#16a34a',
  ADD COLUMN IF NOT EXISTS `portal_bg_color` VARCHAR(20) DEFAULT '#f0fdf4',
  ADD COLUMN IF NOT EXISTS `portal_wifi_name` VARCHAR(100) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `portal_footer_text` VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `portal_show_voucher` TINYINT(1) DEFAULT 1,
  ADD COLUMN IF NOT EXISTS `portal_show_packages` TINYINT(1) DEFAULT 1;

-- ─── New system settings ──────────────────────────────────────
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('vpn_server_host',     'kadilihotspot.online'),
('vpn_psk',            'kadili2024vpn'),
('sms_payment_alert',  '1'),
('sms_daily_report',   '0'),
('voucher_print_logo', ''),
('voucher_print_footer','Thank you for using our WiFi!'),
('captive_default_theme','modern');

-- ─── Fresh install: full schema (skip if tables exist) ────────
-- Run database.sql first for a fresh install, then this file.
