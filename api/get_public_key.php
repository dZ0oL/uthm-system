<?php
// ============================================================
// api/get_public_key.php
// Returns a specific user's ECDH public key.
// Needed by sender to encrypt message for recipient.
// ============================================================
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once '../config/database.php';
ob_clean();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorised']);
    exit;
}

$target_id = intval($_GET['user_id'] ?? 0);
if (!$target_id) {
    http_response_code(400);
    echo json_encode(['error' => 'user_id required']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT user_id, name, ecdh_public_key, ik_dh_public
    FROM users WHERE user_id = ? AND status = 'active'
");
$stmt->execute([$target_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || (empty($user['ecdh_public_key']) && empty($user['ik_dh_public']))) {
    echo json_encode(['error' => 'User not found or has no public key']);
    exit;
}

echo json_encode([
    'success'       => true,
    'user_id'       => $user['user_id'],
    'name'          => $user['name'],
    'public_key'    => $user['ecdh_public_key'],
    'ik_dh_public'  => $user['ik_dh_public']
]);