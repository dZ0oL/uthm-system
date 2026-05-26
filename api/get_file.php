<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once '../config/database.php';
ob_clean();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    http_response_code(401);
    exit;
}

$message_id = intval($_GET['message_id'] ?? 0);
$user_id    = $_SESSION['user_id'];

if (!$message_id) {
    http_response_code(400);
    exit;
}

// Verify user has access to this file message
$stmt = $pdo->prepare("
    SELECT m.*, u.ecdh_public_key as sender_public_key
    FROM messages m
    JOIN users u ON m.sender_id = u.user_id
    WHERE m.message_id = ?
    AND m.message_type IN ('personal_file', 'group_file')
    AND (
        m.sender_id   = ? OR
        m.receiver_id = ? OR
        m.group_id IN (
            SELECT group_id FROM group_members WHERE user_id = ?
        )
    )
");
$stmt->execute([$message_id, $user_id, $user_id, $user_id]);
$message = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$message) {
    http_response_code(403);
    exit;
}

$full_path = __DIR__ . '/../' . $message['file_path'];

if (!file_exists($full_path)) {
    http_response_code(404);
    exit;
}

// Serve encrypted file bytes — browser will decrypt
header('Content-Type: application/octet-stream');
header('Content-Length: ' . filesize($full_path));
header('X-File-Name: ' . rawurlencode($message['file_name']));
header('X-File-Type: ' . $message['file_type']);
header('X-Message-IV: ' . $message['iv']);
header('X-Message-AuthTag: ' . $message['auth_tag']);
header('X-Encrypted-AES-Key: ' . ($message['encrypted_aes_key'] ?? ''));
header('Cache-Control: no-store');

readfile($full_path);
exit;