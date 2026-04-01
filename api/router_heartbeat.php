<?php
// ============================================
// KADILI NET - Router Heartbeat API
// MikroTik calls this URL to signal it's alive
// Dashboard polls this to check connection
// ============================================

require_once __DIR__ . '/../includes/init.php';

header('Content-Type: application/json');

$token    = sanitize($_GET['token'] ?? '');
$routerId = (int)($_GET['router_id'] ?? 0);
$check    = isset($_GET['check']); // Dashboard polling

if ($check && $routerId) {
    // Dashboard is polling: check if router is connected
    $router = DB::fetch("SELECT * FROM routers WHERE id=?", [$routerId]);
    if (!$router) {
        echo json_encode(['connected' => false]);
        exit;
    }

    // Check heartbeat recency (within last 30 seconds)
    $recentBeat = $router['last_heartbeat'] && strtotime($router['last_heartbeat']) > (time() - 30);

    // Also try direct API test
    $apiAlive = false;
    if ($router['host'] && $router['host'] !== '0.0.0.0') {
        $apiAlive = MikroTik::testConnection($router);
        if ($apiAlive) {
            DB::query(
                "UPDATE routers SET status='online', last_heartbeat=NOW(), setup_step='step2' WHERE id=?",
                [$router['id']]
            );
        }
    }

    echo json_encode([
        'connected'   => $recentBeat || $apiAlive,
        'heartbeat'   => $router['last_heartbeat'],
        'api_alive'   => $apiAlive,
    ]);
    exit;
}

// MikroTik is calling with its token
if ($token && $routerId) {
    $router = DB::fetch("SELECT * FROM routers WHERE id=? AND vpn_token=?", [$routerId, $token]);
    if ($router) {
        $callerIp = $_SERVER['REMOTE_ADDR'] ?? '';

        // Update router: it's alive, save its IP
        DB::query(
            "UPDATE routers SET status='online', last_heartbeat=NOW(), vpn_assigned_ip=?,
             host=CASE WHEN host='0.0.0.0' THEN ? ELSE host END
             WHERE id=?",
            [$callerIp, $callerIp, $routerId]
        );

        echo json_encode(['ok' => true, 'message' => 'Router registered. Proceed to Step 2 in your dashboard.']);
        exit;
    }
}

// ─── Also handle router_id + set IP via dashboard POST ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    require_once __DIR__ . '/../includes/auth.php';

    $routerId = (int)($_POST['router_id'] ?? 0);
    $ip       = sanitize($_POST['router_ip'] ?? '');
    $rid      = (int)($_SESSION['reseller_id'] ?? 0);

    if ($routerId && $ip && $rid) {
        DB::query(
            "UPDATE routers SET host=? WHERE id=? AND reseller_id=?",
            [$ip, $routerId, $rid]
        );
        echo json_encode(['ok' => true]);
        exit;
    }
}

echo json_encode(['error' => 'Invalid request']);
