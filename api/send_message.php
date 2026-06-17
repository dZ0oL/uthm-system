<?php
// ============================================================
// api/send_message.php
// Receives an already-encrypted message from the browser and saves it.
// The server never sees plaintext — it only stores and routes ciphertext.
// ============================================================
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once '../config/database.php';
ob_clean(); // discard any PHP warnings that would corrupt the JSON response

// Only logged-in staff may send messages
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

// Verify required fields are present before touching the database
$required = ['message_content', 'iv', 'auth_tag', 'message_type'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing field: $field"]);
        exit;
    }
}

$sender_id      = $_SESSION['user_id'];
$message_content   = $data['message_content'];   // AES-GCM ciphertext (the server cannot read this)
$iv                = $data['iv'];                 // AES-GCM nonce
$auth_tag          = $data['auth_tag'];           // AES-GCM authentication tag
$message_type      = $data['message_type'];       // 'personal' or 'group'
$receiver_id       = $data['receiver_id'] ?? null;
$group_id          = $data['group_id'] ?? null;
$encrypted_keys    = isset($data['encrypted_keys']) ? json_encode($data['encrypted_keys']) : null;
$signal_header     = $data['signal_header']     ?? null; // Double Ratchet header (DH ratchet key + counter)
$signal_prekey_data= $data['signal_prekey_data'] ?? null; // X3DH params (only on first message of a session)
// ECDH fallback — allows session-loss recovery without IDB
$ecdh_content  = $data['ecdh_content']  ?? null; // copy of message encrypted with static IK keys
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

    // Store only ciphertext — the server has no way to read the message content
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

    // Audit log — record who sent a message to whom (but not the content)
    $detail = $message_type === 'group'
        ? "Sent encrypted group message in group_id: $group_id"
        : "Sent encrypted personal message to user_id: $receiver_id";

    $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, details, ip_address)
        VALUES (?, 'Send Message', ?, ?)
    ")->execute([$sender_id, $detail, $_SERVER['REMOTE_ADDR'] ?? null]);

    $pdo->commit();

    // Mirror to backup DB — non-fatal if this fails
    try {
        $pdo_secure = new PDO(
            "mysql:host=$host;dbname=uthm_messaging_secure;charset=utf8mb4",
            $username, $password
        );
        $pdo_secure->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Store a copy of the ciphertext in the backup DB for disaster recovery
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
