<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once '../config/database.php';
ob_clean();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'admin'])) {
    echo json_encode(['valid' => false, 'reason' => 'no_session']);
    exit;
}

// Fetch user — check both token AND status separately
$stmt = $pdo->prepare("
    SELECT session_token, status FROM users WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['valid' => false, 'reason' => 'user_not_found']);
    exit;
}

// Check if account was deactivated
if ($user['status'] === 'inactive') {
    session_destroy();
    echo json_encode([
        'valid'   => false,
        'reason'  => 'account_deactivated',
        'message' => 'Your account has been deactivated. Please contact your administrator.'
    ]);
    exit;
}

// Check session token mismatch
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