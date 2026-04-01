<?php
// ============================================
// KADILI NET - Auth Helper
// ============================================

function isAdminLoggedIn(): bool {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function requireAdmin(): void {
    if (!isAdminLoggedIn()) {
        header('Location: ' . SITE_URL . '/admin/login.php');
        exit;
    }
}

function isResellerLoggedIn(): bool {
    return isset($_SESSION['reseller_id']) && !empty($_SESSION['reseller_id']);
}

function requireReseller(): void {
    if (!isResellerLoggedIn()) {
        header('Location: ' . SITE_URL . '/reseller/login.php');
        exit;
    }
    // Check subscription
    $reseller = DB::fetch("SELECT subscription_expires, status FROM resellers WHERE id=?", [$_SESSION['reseller_id']]);
    if (!$reseller || $reseller['status'] !== 'active') {
        session_destroy();
        header('Location: ' . SITE_URL . '/reseller/login.php?error=suspended');
        exit;
    }
    if ($reseller['subscription_expires'] && strtotime($reseller['subscription_expires']) < time()) {
        $_SESSION['sub_expired'] = true;
    }
}

function loginAdmin(array $admin): void {
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_name'] = $admin['name'];
    $_SESSION['admin_email'] = $admin['email'];
}

function loginReseller(array $reseller): void {
    $_SESSION['reseller_id'] = $reseller['id'];
    $_SESSION['reseller_name'] = $reseller['name'];
    $_SESSION['reseller_email'] = $reseller['email'];
    $_SESSION['reseller_phone'] = $reseller['phone'];
    $_SESSION['business_name'] = $reseller['business_name'];
}

function generateVoucherCode(int $length = 8): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

function formatTZS(float $amount): string {
    return 'TZS ' . number_format($amount, 0, '.', ',');
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function durationToSeconds(int $value, string $unit): int {
    return match($unit) {
        'minutes' => $value * 60,
        'hours'   => $value * 3600,
        'days'    => $value * 86400,
        'weeks'   => $value * 604800,
        'months'  => $value * 2592000,
        default   => $value * 3600,
    };
}

function durationLabel(int $value, string $unit): string {
    return "$value " . rtrim($unit, 's') . ($value > 1 ? 's' : '');
}
