<?php
// ============================================
// KADILI NET - PalmPesa Callback Handler
// URL: /api/palmpesa_callback.php
// ============================================
require_once __DIR__ . '/../includes/init.php';

// Log all callbacks
$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? $_POST;

error_log("PalmPesa Callback: " . $raw);

$orderId       = $data['order_id'] ?? null;
$paymentStatus = $data['payment_status'] ?? null;
$resellerId    = (int)($_GET['reseller_id'] ?? 0);
$txDbId        = (int)($_GET['tx_id'] ?? 0);
$userMac       = sanitize($_GET['mac'] ?? '');
$userIp        = sanitize($_GET['ip'] ?? '');
$months        = (int)($_GET['months'] ?? 0);
$type          = sanitize($_GET['type'] ?? 'voucher_sale');

if (!$orderId) {
    http_response_code(400);
    echo json_encode(['error' => 'No order_id']);
    exit;
}

// Only process COMPLETED payments
if ($paymentStatus !== 'COMPLETED') {
    echo json_encode(['received' => true, 'status' => $paymentStatus]);
    exit;
}

// Find transaction by order_id or tx_id
$tx = null;
if ($txDbId > 0) {
    $tx = DB::fetch("SELECT * FROM transactions WHERE id=?", [$txDbId]);
} elseif ($orderId) {
    $tx = DB::fetch("SELECT * FROM transactions WHERE palmpesa_order_id=?", [$orderId]);
}

if (!$tx) {
    // Try to find pending transaction for this reseller
    if ($resellerId) {
        $tx = DB::fetch("SELECT * FROM transactions WHERE reseller_id=? AND payment_status='pending' AND type=? ORDER BY id DESC LIMIT 1", [$resellerId, $type]);
    }
}

if (!$tx) {
    echo json_encode(['error' => 'Transaction not found']);
    exit;
}

$reseller = DB::fetch("SELECT * FROM resellers WHERE id=?", [$tx['reseller_id']]);
if (!$reseller) {
    echo json_encode(['error' => 'Reseller not found']);
    exit;
}

// Mark transaction completed
DB::query("UPDATE transactions SET payment_status='completed', palmpesa_order_id=? WHERE id=?", [$orderId, $tx['id']]);

// ========== HANDLE BASED ON TYPE ==========

if ($tx['type'] === 'voucher_sale') {
    // Get package
    $package = $tx['package_id'] ? DB::fetch("SELECT * FROM packages WHERE id=?", [$tx['package_id']]) : null;

    if ($package) {
        $router = DB::fetch("SELECT * FROM routers WHERE id=?", [$package['router_id']]);

        // Generate voucher code
        $code     = generateVoucherCode(8);
        $seconds  = durationToSeconds($package['duration_value'], $package['duration_unit']);
        $expiresAt = date('Y-m-d H:i:s', time() + $seconds);

        // Add to MikroTik
        $mikrotikAdded = false;
        if ($router) {
            $mikrotikAdded = MikroTik::addVoucher($router, $code, $package['profile_name'], $seconds);
        }

        // Save voucher to DB
        $voucherId = DB::insert(
            "INSERT INTO vouchers (reseller_id, router_id, package_id, code, profile_name, status, used_by_phone, used_at, expires_at) VALUES (?,?,?,?,?,'used',?,NOW(),?)",
            [$reseller['id'], $package['router_id'], $package['id'], $code, $package['profile_name'], $tx['customer_phone'], $expiresAt]
        );

        // Save hotspot user
        DB::query(
            "INSERT INTO hotspot_users (reseller_id, router_id, username, password, phone, package_id, session_start, expires_at, status) VALUES (?,?,?,?,?,?,NOW(),?,'active')",
            [$reseller['id'], $package['router_id'], $code, $code, $tx['customer_phone'], $package['id'], $expiresAt]
        );

        // Update transaction with voucher
        DB::query("UPDATE transactions SET voucher_id=? WHERE id=?", [$voucherId, $tx['id']]);

        // Credit reseller if using system gateway
        if (!$reseller['use_own_gateway']) {
            DB::query("UPDATE resellers SET balance = balance + ? WHERE id=?", [$tx['amount'], $reseller['id']]);
        }

        // Send SMS with credentials
        $smsEnabled = DB::setting('sms_enabled');
        if ($smsEnabled) {
            $bizName = $reseller['business_name'] ?: 'KADILI NET';
            $durationLabel = $package['duration_value'] . ' ' . $package['duration_unit'];
            $message = "Habari! WiFi yako ya {$bizName} imewashwa.\nCode: {$code}\nDuration: {$durationLabel}\nPayments: TZS {$tx['amount']}\nFuraha! - KADILI NET";
            $smsResult = BeemSMS::send($tx['customer_phone'], $message);
            if (!empty($smsResult['success'])) {
                DB::query("UPDATE transactions SET sms_sent=1 WHERE id=?", [$tx['id']]);
            }
        }

        // Auto-login on MikroTik if MAC/IP provided
        if ($router && ($userMac || $userIp)) {
            try {
                MikroTik::loginUser($router, $userIp, $code, $code);
            } catch (Exception $e) {
                error_log("Auto-login failed: " . $e->getMessage());
            }
        }
    }

} elseif ($tx['type'] === 'subscription') {
    // Extend reseller subscription
    $rid = $tx['reseller_id'];
    $r   = DB::fetch("SELECT subscription_expires FROM resellers WHERE id=?", [$rid]);
    $base = ($r && $r['subscription_expires'] && strtotime($r['subscription_expires']) > time())
        ? $r['subscription_expires'] : date('Y-m-d');
    $m   = $months ?: 1;
    $newExpiry = date('Y-m-d', strtotime("+$m months", strtotime($base)));
    DB::query("UPDATE resellers SET subscription_expires=?, status='active' WHERE id=?", [$newExpiry, $rid]);

    // Notify reseller
    $r2 = DB::fetch("SELECT phone, name FROM resellers WHERE id=?", [$rid]);
    if ($r2) {
        BeemSMS::send($r2['phone'], "Habari {$r2['name']}! Subscription wako wa KADILI NET umepanuliwa hadi {$newExpiry}. Asante!");
    }

} elseif ($tx['type'] === 'setup_fee') {
    $rid = $tx['reseller_id'];
    DB::query("UPDATE resellers SET status='active', setup_fee_paid=1, subscription_expires=DATE_ADD(NOW(), INTERVAL 1 MONTH) WHERE id=?", [$rid]);
}

echo json_encode(['success' => true, 'message' => 'Payment processed']);
