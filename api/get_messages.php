<?php
// ============================================================
// api/get_messages.php
// Returns raw ciphertext messages to the browser for
// client-side decryption. Never decrypts server-side.
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

$user_id      = $_SESSION['user_id'];
$message_type = $_GET['type'] ?? 'group';
$chat_id      = intval($_GET['id'] ?? 0);

if (!$chat_id) {
    echo json_encode(['messages' => [], 'members' => []]);
    exit;
}

$messages = [];
$members  = [];

if ($message_type === 'group') {
    // Fetch group messages
    $stmt = $pdo->prepare("
        SELECT m.message_id, m.sender_id, m.message_content,
                m.iv, m.auth_tag, m.encrypted_aes_key,
                m.message_type, m.timestamp,
                m.file_name, m.file_size, m.file_type, m.file_path,
                m.signal_header, m.signal_prekey_data,
                u.name as sender_name,
                u.ecdh_public_key as sender_public_key,
                u.ik_dh_public as sender_ik_dh_public
        FROM messages m
        JOIN users u ON m.sender_id = u.user_id
        WHERE m.group_id = ?
        ORDER BY m.timestamp ASC
    ");
    $stmt->execute([$chat_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all group members with their public keys
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.name, u.ecdh_public_key, u.ik_dh_public
        FROM users u
        JOIN group_members gm ON u.user_id = gm.user_id
        WHERE gm.group_id = ?
    ");
    $stmt->execute([$chat_id]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

} else {
    // Fetch personal messages between two users
    $stmt = $pdo->prepare("
        SELECT m.message_id, m.sender_id, m.receiver_id,
               m.message_content, m.iv, m.auth_tag,
               m.encrypted_aes_key, m.message_type, m.timestamp,
               m.file_name, m.file_size, m.file_type, m.file_path,
               m.signal_header, m.signal_prekey_data,
               m.ecdh_content, m.ecdh_iv, m.ecdh_auth_tag,
               u.name as sender_name,
               u.ecdh_public_key as sender_public_key,
               u.ik_dh_public as sender_ik_dh_public
        FROM messages m
        JOIN users u ON m.sender_id = u.user_id
        WHERE m.message_type IN ('personal', 'personal_file')
          AND (
              (m.sender_id = ? AND m.receiver_id = ?)
           OR (m.sender_id = ? AND m.receiver_id = ?)
          )
        ORDER BY m.timestamp ASC
    ");
    $stmt->execute([$user_id, $chat_id, $chat_id, $user_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch the other user's public key (including Signal keys)
    $stmt = $pdo->prepare("
        SELECT user_id, name, ecdh_public_key, ik_dh_public
        FROM users WHERE user_id = ?
    ");
    $stmt->execute([$chat_id]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Also return current user's public key
$stmt = $pdo->prepare("
    SELECT ecdh_public_key FROM users WHERE user_id = ?
");
$stmt->execute([$user_id]);
$myKey = $stmt->fetchColumn();

echo json_encode([
    'success'        => true,
    'messages'       => $messages,
    'members'        => $members,
    'my_public_key'  => $myKey,
    'my_user_id'     => $user_id
]);