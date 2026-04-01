<?php
// /api/send_otp.php - AJAX OTP sender
require_once __DIR__ . '/../includes/init.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST required']);
    exit;
}

$phone   = sanitize($_POST['phone'] ?? '');
$purpose = sanitize($_POST['purpose'] ?? 'registration');

if (!$phone) {
    echo json_encode(['success' => false, 'message' => 'Phone required']);
    exit;
}

// Rate limit: max 3 OTPs per phone per 10 minutes
$recent = DB::fetch(
    "SELECT COUNT(*) as c FROM otp_codes WHERE phone=? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)",
    [BeemSMS::formatPhone($phone)]
);
if ((int)$recent['c'] >= 3) {
    echo json_encode(['success' => false, 'message' => 'Subiri dakika 10 kisha jaribu tena.']);
    exit;
}

$result = BeemSMS::generateLocalOTP(BeemSMS::formatPhone($phone), $purpose);
echo json_encode(['success' => $result['success'], 'message' => $result['success'] ? 'OTP imetumwa' : 'Imeshindwa kutuma OTP']);
