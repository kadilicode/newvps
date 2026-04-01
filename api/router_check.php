<?php
// /api/router_check.php - AJAX router connection test
require_once __DIR__ . '/../includes/init.php';

header('Content-Type: application/json');

if (!isResellerLoggedIn() && !isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$routerId = (int)($_GET['router_id'] ?? 0);
$rid = $_SESSION['reseller_id'] ?? null;

$router = $rid
    ? DB::fetch("SELECT * FROM routers WHERE id=? AND reseller_id=?", [$routerId, $rid])
    : DB::fetch("SELECT * FROM routers WHERE id=?", [$routerId]);

if (!$router) {
    echo json_encode(['error' => 'Router not found']);
    exit;
}

$online = MikroTik::testConnection($router);
DB::query("UPDATE routers SET status=?, last_checked=NOW() WHERE id=?", [$online ? 'online' : 'offline', $router['id']]);

$info = [];
if ($online) {
    $identity = MikroTik::getRouterIdentity($router);
    $resource = MikroTik::getResourceInfo($router);
    $info = [
        'identity'  => $identity,
        'uptime'    => $resource['uptime'] ?? null,
        'cpu_load'  => $resource['cpu-load'] ?? null,
        'free_mem'  => $resource['free-memory'] ?? null,
        'version'   => $resource['version'] ?? null,
    ];
}

echo json_encode([
    'online'    => $online,
    'router_id' => $router['id'],
    'name'      => $router['name'],
    'host'      => $router['host'],
    'info'      => $info,
]);
