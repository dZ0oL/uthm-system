<?php
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

$user_id            = $_SESSION['user_id'];
$receiver_id        = intval($_POST['receiver_id']  ?? 0);
$group_id           = intval($_POST['group_id']     ?? 0);
$message_type       = $_POST['message_type']        ?? '';
$iv                 = $_POST['iv']                  ?? '';
$auth_tag           = $_POST['auth_tag']            ?? '';
$encrypted_aes_key  = $_POST['encrypted_aes_key']  ?? null;
$message_content    = $_POST['message_content']     ?? '';   // Signal: encrypted file key wrapper
$signal_header      = $_POST['signal_header']       ?? null;
$signal_prekey_data = $_POST['signal_prekey_data']  ?? null;

// Validate message type
$valid_types = ['personal_file', 'group_file'];
if (!in_array($message_type, $valid_types)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid message type']);
    exit;
}

// Validate recipient
if ($message_type === 'personal_file' && !$receiver_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Receiver required for personal file']);
    exit;
}
if ($message_type === 'group_file' && !$group_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Group required for group file']);
    exit;
}

// Validate file upload
if (!isset($_FILES['encrypted_file']) || $_FILES['encrypted_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or upload error']);
    exit;
}

$file          = $_FILES['encrypted_file'];
$original_name = basename($file['name']);
$file_size     = $file['size'];
$file_type     = $_POST['file_type'] ?? 'application/octet-stream';

// Max 5MB — encrypted file is slightly larger than original
$max_size = 5 * 1024 * 1024 + 1024; // 5MB + 1KB buffer
if ($file_size > $max_size) {
    http_response_code(400);
    echo json_encode(['error' => 'File exceeds maximum size of 5MB']);
    exit;
}

// Allowed MIME types
$allowed_types = [
    'application/pdf',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'image/jpeg',
    'image/png'
];
if (!in_array($file_type, $allowed_types)) {
    http_response_code(400);
    echo json_encode(['error' => 'File type not allowed. Allowed: PDF, DOCX, XLSX, JPG, PNG']);
    exit;
}

// Save encrypted file blob to server
$upload_dir = __DIR__ . '/../uploads/encrypted/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename — no extension since it's encrypted binary
$stored_name = bin2hex(random_bytes(16));
$file_path   = 'uploads/encrypted/' . $stored_name;
$full_path   = __DIR__ . '/../' . $file_path;

if (!move_uploaded_file($file['tmp_name'], $full_path)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file']);
    exit;
}

try {
    // Save message record
    $stmt = $pdo->prepare("
        INSERT INTO messages
            (sender_id, receiver_id, group_id, message_type,
             message_content, iv, auth_tag, encrypted_aes_key,
             signal_header, signal_prekey_data,
             file_name, file_size, file_type, file_path)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user_id,
        $receiver_id ?: null,
        $group_id    ?: null,
        $message_type,
        $message_content ?: '',
        $iv,
        $auth_tag,
        $encrypted_aes_key ?: null,
        $signal_header      ?: null,
        $signal_prekey_data ?: null,
        $original_name,
        $file_size,
        $file_type,
        $file_path
    ]);

    $message_id = $pdo->lastInsertId();

    // Mirror to secure backup DB
    try {
        $pdo_secure = new PDO(
            'mysql:host=localhost;dbname=uthm_messaging_secure;charset=utf8mb4',
            'root', ''
        );
        $pdo_secure->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo_secure->prepare("
            INSERT INTO messages_backup
                (original_message_id, sender_id, receiver_id, group_id,
                 message_type, message_content, iv, auth_tag,
                 encrypted_aes_key, signal_header, signal_prekey_data,
                 file_name, file_size, file_type,
                 file_path, original_timestamp)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            $message_id,
            $user_id,
            $receiver_id ?: null,
            $group_id    ?: null,
            $message_type,
            $message_content ?: '',
            $iv,
            $auth_tag,
            $encrypted_aes_key ?: null,
            $signal_header      ?: null,
            $signal_prekey_data ?: null,
            $original_name,
            $file_size,
            $file_type,
            $file_path
        ]);
    } catch (PDOException $e) {
        error_log('Backup DB file mirror failed: ' . $e->getMessage());
    }

    // Audit log
    $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, details, ip_address)
        VALUES (?, 'Send File', ?, ?)
    ")->execute([
        $user_id,
        "File sent: $original_name ($file_type)",
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);

    echo json_encode([
        'success'    => true,
        'message_id' => $message_id,
        'file_path'  => $file_path
    ]);

} catch (PDOException $e) {
    // Clean up uploaded file if DB insert fails
    if (file_exists($full_path)) unlink($full_path);
    error_log('send_file error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file message']);
}