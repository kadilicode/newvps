<?php
// ============================================
// KADILI NET - Cron Job Script
// Run every 5 minutes:
// */5 * * * * php /var/www/html/cron/check_expiry.php >> /var/log/kadilinet_cron.log 2>&1
// ============================================

define('CRON_MODE', true);
require_once __DIR__ . '/../includes/init.php';

$log = function(string $msg) {
    echo "[" . date('Y-m-d H:i:s') . "] $msg\n";
};

$log("=== KADILI NET Cron Started ===");

// ============================================================
// 1. SEND REMINDER SMS - 30 min before WiFi expires
// ============================================================
$expiringSoon = DB::fetchAll("
    SELECT hu.*, r.name as reseller_name, r.business_name
    FROM hotspot_users hu
    JOIN resellers r ON r.id = hu.reseller_id
    WHERE hu.status = 'active'
      AND hu.reminder_sent = 0
      AND hu.expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 MINUTE)
      AND hu.phone IS NOT NULL
");

foreach ($expiringSoon as $user) {
    $bizName = $user['business_name'] ?: 'KADILI NET';
    $msg = "Habari! WiFi yako ya {$bizName} itamalizika dakika 30. Nunua tena kuendelea kutumia mtandao. - KADILI NET";
    $result = BeemSMS::send($user['phone'], $msg);
    DB::query("UPDATE hotspot_users SET reminder_sent=1 WHERE id=?", [$user['id']]);
    $log("Reminder sent to {$user['phone']} (User: {$user['username']}) - " . ($result['success']?'OK':'FAIL'));
}

$log("Reminders sent: " . count($expiringSoon));

// ============================================================
// 2. EXPIRE HOTSPOT USERS
// ============================================================
$expired = DB::fetchAll("
    SELECT hu.*, r.host, r.port, r.username as r_user, r.password as r_pass
    FROM hotspot_users hu
    JOIN routers r ON r.id = hu.router_id
    WHERE hu.status = 'active'
      AND hu.expires_at < NOW()
");

foreach ($expired as $user) {
    // Remove from MikroTik
    $router = [
        'host'     => $user['host'],
        'port'     => $user['port'],
        'username' => $user['r_user'],
        'password' => $user['r_pass'],
    ];
    $removed = MikroTik::removeHotspotUser($router, $user['username']);
    DB::query("UPDATE hotspot_users SET status='expired' WHERE id=?", [$user['id']]);
    DB::query("UPDATE vouchers SET status='expired' WHERE code=?", [$user['username']]);
    $log("Expired user: {$user['username']} - MikroTik remove: " . ($removed?'OK':'FAIL'));
}

$log("Expired users processed: " . count($expired));

// ============================================================
// 3. SUSPEND RESELLERS WITH EXPIRED SUBSCRIPTIONS
// ============================================================
$expiredResellers = DB::fetchAll("
    SELECT id, name, phone, email, subscription_expires
    FROM resellers
    WHERE status = 'active'
      AND subscription_expires IS NOT NULL
      AND subscription_expires < DATE_SUB(NOW(), INTERVAL 3 DAY)
");

foreach ($expiredResellers as $r) {
    DB::query("UPDATE resellers SET status='suspended' WHERE id=?", [$r['id']]);
    BeemSMS::send($r['phone'], "Habari {$r['name']}, usajili wako wa KADILI NET umekwisha na API calls zimezimwa. Lipa TZS " . MONTHLY_FEE . " kuruhusu. kadilihotspot.online");
    $log("Suspended reseller: {$r['name']} (ID:{$r['id']}) - expired {$r['subscription_expires']}");
}

$log("Resellers suspended: " . count($expiredResellers));

// ============================================================
// 4. CHECK ROUTER STATUS
// ============================================================
$routers = DB::fetchAll("SELECT * FROM routers WHERE last_checked < DATE_SUB(NOW(), INTERVAL 10 MINUTE) OR last_checked IS NULL LIMIT 20");

foreach ($routers as $r) {
    $online = MikroTik::testConnection($r);
    DB::query("UPDATE routers SET status=?, last_checked=NOW() WHERE id=?", [$online?'online':'offline', $r['id']]);
}

$log("Routers checked: " . count($routers));

// ============================================================
// 5. CLEAN OLD OTP CODES (older than 1 hour)
// ============================================================
DB::query("DELETE FROM otp_codes WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");

$log("=== Cron Finished ===");
echo "\n";
