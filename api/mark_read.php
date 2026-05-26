<?php
// ============================================================
// api/mark_read.php
// Records when a staff member last viewed a conversation.
// Called by the chat page whenever messages are loaded.
// ============================================================
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data      = json_decode(file_get_contents('php://input'), true);
$chat_type = $data['chat_type'] ?? '';
$chat_id   = intval($data['chat_id'] ?? 0);
$user_id   = $_SESSION['user_id'];

if (!in_array($chat_type, ['personal', 'group'], true) || !$chat_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

try {
    $pdo->prepare("
        INSERT INTO conversation_reads (user_id, chat_type, chat_id, last_read_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE last_read_at = NOW()
    ")->execute([$user_id, $chat_type, $chat_id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log('mark_read error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed']);
}
