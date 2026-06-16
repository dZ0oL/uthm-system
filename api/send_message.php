<?php
// ============================================================
// api/send_message.php
// Receives encrypted message from browser and stores it.
// Also mirrors to backup DB.
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

$data = json_decode(file_get_contents('php://input'), true);

$required = ['message_content', 'iv', 'auth_tag', 'message_type'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing field: $field"]);
        exit;
    }
}

$sender_id      = $_SESSION['user_id'];
$message_content   = $data['message_content'];
$iv                = $data['iv'];
$auth_tag          = $data['auth_tag'];
$message_type      = $data['message_type'];
$receiver_id       = $data['receiver_id'] ?? null;
$group_id          = $data['group_id'] ?? null;
$encrypted_keys    = isset($data['encrypted_keys']) ? json_encode($data['encrypted_keys']) : null;
$signal_header     = $data['signal_header']     ?? null;
$signal_prekey_data= $data['signal_prekey_data'] ?? null;
// ECDH fallback — allows session-loss recovery without IDB
$ecdh_content  = $data['ecdh_content']  ?? null;
$ecdh_iv       = $data['ecdh_iv']       ?? null;
$ecdh_auth_tag = $data['ecdh_auth_tag'] ?? null;

// Validate type-specific fields
if ($message_type === 'personal' && !$receiver_id) {
    http_response_code(400);
    echo json_encode(['error' => 'receiver_id required for personal messages']);
    exit;
}
if ($message_type === 'group' && !$group_id) {
    http_response_code(400);
    echo json_encode(['error' => 'group_id required for group messages']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO messages
            (sender_id, receiver_id, group_id, message_content,
             iv, auth_tag, encrypted_aes_key, message_type,
             signal_header, signal_prekey_data,
             ecdh_content, ecdh_iv, ecdh_auth_tag)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $sender_id,
        $receiver_id,
        $group_id,
        $message_content,
        $iv,
        $auth_tag,
        $encrypted_keys,
        $message_type,
        $signal_header,
        $signal_prekey_data,
        $ecdh_content,
        $ecdh_iv,
        $ecdh_auth_tag
    ]);

    $message_id = $pdo->lastInsertId();

    // Audit log
    $detail = $message_type === 'group'
        ? "Sent encrypted group message in group_id: $group_id"
        : "Sent encrypted personal message to user_id: $receiver_id";

    $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, details, ip_address)
        VALUES (?, 'Send Message', ?, ?)
    ")->execute([$sender_id, $detail, $_SERVER['REMOTE_ADDR'] ?? null]);

    $pdo->commit();

    // Mirror to backup DB
    try {
        $pdo_secure = new PDO(
            "mysql:host=$host;dbname=uthm_messaging_secure;charset=utf8mb4",
            $username, $password
        );
        $pdo_secure->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo_secure->prepare("
            INSERT INTO messages_backup
                (original_message_id, sender_id, receiver_id, group_id,
                 message_content, iv, auth_tag, encrypted_aes_key,
                 message_type, signal_header, signal_prekey_data, original_timestamp)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            $message_id, $sender_id, $receiver_id, $group_id,
            $message_content, $iv, $auth_tag, $encrypted_keys,
            $message_type, $signal_header, $signal_prekey_data
        ]);
    } catch (Exception $e) {
        error_log('Backup DB mirror failed: ' . $e->getMessage());
        // Non-fatal — main DB succeeded
    }

    echo json_encode([
        'success'    => true,
        'message_id' => $message_id
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('send_message error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to store message']);
}