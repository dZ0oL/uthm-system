<?php
// api/signal_get_sender_key.php — Return the encrypted sender key distribution for the current user.
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once '../config/database.php';
ob_clean();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorised']);
    exit;
}

$group_id  = intval($_GET['group_id']  ?? 0);
$sender_id = intval($_GET['sender_id'] ?? 0);
$member_id = $_SESSION['user_id'];

if (!$group_id || !$sender_id) {
    http_response_code(400);
    echo json_encode(['error' => 'group_id and sender_id required']);
    exit;
}

try {
    // Fetch the sender key distribution encrypted specifically for this member
    // Each member gets their own row — the sender key chain is wrapped per-member using ECDH
    $stmt = $pdo->prepare("
        SELECT encrypted_dist, dist_iv, dist_auth_tag, iteration
        FROM signal_sender_keys
        WHERE group_id = ? AND sender_id = ? AND member_id = ?
    ");
    $stmt->execute([$group_id, $sender_id, $member_id]);
    $row = $stmt->fetch();

    if (!$row) {
        // No distribution yet — browser will request one from the sender
        echo json_encode(['success' => true, 'distribution' => null]);
        exit;
    }

    echo json_encode([
        'success'      => true,
        'distribution' => [
            'ciphertext' => $row['encrypted_dist'],
            'iv'         => $row['dist_iv'],
            'auth_tag'   => $row['dist_auth_tag'],
            'iteration'  => $row['iteration']
        ]
    ]);

} catch (PDOException $e) {
    error_log('signal_get_sender_key error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch sender key']);
}
