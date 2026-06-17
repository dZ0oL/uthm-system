<?php
// ============================================================
// api/check_session.php
// Polled by session.js on every staff/admin page to detect
// whether the session is still valid. Returns JSON { valid }.
// Handles two kick reasons: account deactivated and session
// displaced by a login from another device.
// ============================================================
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once '../config/database.php';
ob_clean();

header('Content-Type: application/json');

// No PHP session means the cookie expired or was never set
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'admin'])) {
    echo json_encode(['valid' => false, 'reason' => 'no_session']);
    exit;
}

// Fetch current DB state — token and status can change at any time
$stmt = $pdo->prepare("
    SELECT session_token, status FROM users WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['valid' => false, 'reason' => 'user_not_found']);
    exit;
}

// Admin deactivated the account since this session started
if ($user['status'] === 'inactive') {
    session_destroy();
    echo json_encode([
        'valid'   => false,
        'reason'  => 'account_deactivated',
        'message' => 'Your account has been deactivated. Please contact your administrator.'
    ]);
    exit;
}

// Token mismatch means another device logged in and overwrote the DB token (single-device enforcement)
if ($user['session_token'] !== ($_SESSION['session_token'] ?? '')) {
    session_destroy();
    echo json_encode([
        'valid'   => false,
        'reason'  => 'session_terminated',
        'message' => 'Your session was terminated because you logged in from another device.'
    ]);
    exit;
}

echo json_encode(['valid' => true]);
