<?php
// ============================================================
// api/verify_otp.php
// Verifies OTP and submits the recovery request.
// ============================================================
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once '../config/database.php';
ob_clean();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data   = json_decode(file_get_contents('php://input'), true);
$email  = trim($data['email'] ?? '');
$otp    = trim($data['otp'] ?? '');
error_log("OTP received: '$otp', Email: '$email'");
$reason = trim($data['reason'] ?? 'Account recovery requested by staff');

if (!$email || !$otp) {
    http_response_code(400);
    echo json_encode(['error' => 'Email and OTP are required']);
    exit;
}

// Verify OTP
$stmt = $pdo->prepare("
    SELECT * FROM recovery_otps
    WHERE email    = ?
      AND otp_code = ?
      AND used     = 0
      AND expires_at > NOW()
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->execute([$email, $otp]);
$otpRecord = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$otpRecord) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or expired OTP. Please try again.']);
    exit;
}

// Mark OTP as used
$pdo->prepare("
    UPDATE recovery_otps SET used = 1
    WHERE otp_id = ?
")->execute([$otpRecord['otp_id']]);

// Get user
$stmt = $pdo->prepare("
    SELECT user_id, name FROM users
    WHERE email = ? AND status = 'active'
");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(400);
    echo json_encode(['error' => 'User not found']);
    exit;
}

// Check for existing pending request
$stmt = $pdo->prepare("
    SELECT request_id FROM recovery_requests
    WHERE user_id = ? AND status = 'pending'
");
$stmt->execute([$user['user_id']]);
if ($stmt->fetch()) {
    echo json_encode([
        'success' => true,
        'message' => 'A recovery request is already pending for your account. Please wait for admin approval.'
    ]);
    exit;
}

// Create recovery request
$pdo->prepare("
    INSERT INTO recovery_requests (user_id, reason)
    VALUES (?, ?)
")->execute([$user['user_id'], $reason]);

// Log it
$pdo->prepare("
    INSERT INTO audit_logs (user_id, action, details, ip_address)
    VALUES (?, 'Recovery Request Submitted', ?, ?)
")->execute([
    $user['user_id'],
    "Recovery request submitted via OTP verification for: {$email}",
    $_SERVER['REMOTE_ADDR'] ?? null
]);

echo json_encode([
    'success' => true,
    'message' => 'Recovery request submitted successfully. An administrator will review your request shortly.'
]);