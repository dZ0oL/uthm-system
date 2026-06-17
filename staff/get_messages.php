<?php
// ============================================================
// staff/get_messages.php
// Legacy polling endpoint for incremental message fetch.
// NOTE: The decrypt_message() function below is legacy code
// from an earlier prototype — it uses server-side AES-CBC with
// a hardcoded key. In the current system, all real encryption
// is E2E (browser-side via Web Crypto API). This endpoint is
// kept for backward compatibility but is no longer the primary
// message delivery path — chat.php uses api/get_messages.php instead.
// ============================================================
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    http_response_code(401);
    exit;
}

// LEGACY: server-side decrypt with hardcoded key — only works on old-format messages
// Current messages are E2E encrypted and cannot be decrypted server-side
function decrypt_message($encrypted_message) {
    $key = 'UTHM_SECRET_KEY_2025';
    try {
        $data = base64_decode($encrypted_message);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    } catch (Exception $e) {
        return "[Decryption Error]";
    }
}

$user_id = $_SESSION['user_id'];
$chat_type = $_GET['type'] ?? '';
$chat_id = intval($_GET['id'] ?? 0);
$last_id = intval($_GET['last_id'] ?? 0);

$messages = [];

if ($chat_id > 0) {
    if ($chat_type == 'group') {
        // Get new group messages after last_id
        $stmt = $pdo->prepare("
            SELECT m.*, u.name as sender_name
            FROM messages m
            JOIN users u ON m.sender_id = u.user_id
            WHERE m.group_id = ? 
            AND m.message_type = 'group'
            AND m.message_id > ?
            ORDER BY m.timestamp ASC
        ");
        $stmt->execute([$chat_id, $last_id]);
        
    } else {
        // Get new personal messages after last_id
        $stmt = $pdo->prepare("
            SELECT m.*, u.name as sender_name
            FROM messages m
            JOIN users u ON m.sender_id = u.user_id
            WHERE m.message_type = 'personal'
            AND ((m.sender_id = ? AND m.receiver_id = ?) 
                 OR (m.sender_id = ? AND m.receiver_id = ?))
            AND m.message_id > ?
            ORDER BY m.timestamp ASC
        ");
        $stmt->execute([$user_id, $chat_id, $chat_id, $user_id, $last_id]);
    }
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $msg) {
        $decrypted = decrypt_message($msg['message_content']);
        $messages[] = [
            'message_id' => $msg['message_id'],
            'sender_name' => $msg['sender_name'],
            'sender_initial' => strtoupper(substr($msg['sender_name'], 0, 1)),
            'content' => htmlspecialchars($decrypted),
            'time' => date('H:i', strtotime($msg['timestamp'])),
            'is_sent' => ($msg['sender_id'] == $user_id)
        ];
    }
}

header('Content-Type: application/json');
echo json_encode(['messages' => $messages]);
?>