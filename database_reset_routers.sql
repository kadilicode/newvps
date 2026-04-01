-- ============================================
-- KADILI NET — Router Table Cleanup / Reset
-- Run this when you want to clear corrupted
-- router data and start fresh.
--
-- USAGE (via SSH or phpMyAdmin):
--   mysql -u root -p kadili_net < database_reset_routers.sql
--
-- WARNING: This deletes ALL routers and
-- dependent data (vouchers, packages,
-- hotspot_users) for ALL resellers.
-- ============================================

USE kadili_net;

-- Disable FK checks so we can truncate in any order
SET FOREIGN_KEY_CHECKS = 0;

-- Clear dependent tables first
TRUNCATE TABLE `hotspot_users`;
TRUNCATE TABLE `vouchers`;
TRUNCATE TABLE `packages`;
TRUNCATE TABLE `routers`;

-- Re-enable FK checks
SET FOREIGN_KEY_CHECKS = 1;

-- Reset AUTO_INCREMENT counters
ALTER TABLE `routers`       AUTO_INCREMENT = 1;
ALTER TABLE `packages`      AUTO_INCREMENT = 1;
ALTER TABLE `vouchers`      AUTO_INCREMENT = 1;
ALTER TABLE `hotspot_users` AUTO_INCREMENT = 1;

-- Reset router status on resellers (optional — removes stuck "offline" badges)
UPDATE `resellers` SET `updated_at` = NOW();

SELECT 'Router tables cleared successfully.' AS result;
