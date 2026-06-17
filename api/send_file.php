<?php
// ============================================================
// api/send_file.php
// Receives an already-encrypted file blob from the browser and saves it.
// The file is encrypted client-side before upload — the server stores
// opaque binary and never sees the original file content.
// ============================================================
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once '../config/database.php';
ob_clean();

// Only logged-in staff may upload files
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
$iv                 = $_POST['iv']                  ?? '';  // file AES-GCM nonce (not used server-side)
$auth_tag           = $_POST['auth_tag']            ?? '';  // file AES-GCM auth tag (not used server-side)
$encrypted_aes_key  = $_POST['encrypted_aes_key']  ?? null; // legacy fallback file key
// message_content holds the Signal-wrapped file key (fk/fi/fa), not the file itself
$message_content    = $_POST['message_content']     ?? '';
$signal_header      = $_POST['signal_header']       ?? null; // Double Ratchet header for the key wrapper
$signal_prekey_data = $_POST['signal_prekey_data']  ?? null; // X3DH params if this is the first message

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
$original_name = basename($file['name']); // original filename for display (not a security boundary)
$file_size     = $file['size'];
$file_type     = $_POST['file_type'] ?? 'application/octet-stream'; // MIME type declared by client

// Max 5MB — encrypted file is slightly larger than original due to AES-GCM overhead
$max_size = 5 * 1024 * 1024 + 1024; // 5MB + 1KB buffer
if ($file_size > $max_size) {
    http_response_code(400);
    echo json_encode(['error' => 'File exceeds maximum size of 5MB']);
    exit;
}

// Allowlist MIME types — rejects executable files and other dangerous types
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

// Generate a random filename with no extension — the content is opaque ciphertext
// Using random hex means the filename reveals nothing about the original file
$stored_name = bin2hex(random_bytes(16));
$file_path   = 'uploads/encrypted/' . $stored_name;
$full_path   = __DIR__ . '/../' . $file_path;

if (!move_uploaded_file($file['tmp_name'], $full_path)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file']);
    exit;
}

try {
    // Save the message record — message_content is the encrypted file key wrapper, not the file itself
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
        $original_name, // stored only for display — download uses the random $stored_name path
        $file_size,
        $file_type,
        $file_path      // path to the encrypted blob on disk
    ]);

    $message_id = $pdo->lastInsertId();

    // Mirror to secure backup DB — backup also stores only ciphertext
    try {
        $pdo_secure = new PDO(
            "mysql:host=$host;dbname=uthm_messaging_secure;charset=utf8mb4",
            $username, $password
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

    // Audit log — record file name and type but not content
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
    // Clean up the uploaded file if the DB insert fails
    if (file_exists($full_path)) unlink($full_path);
    error_log('send_file error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file message']);
}
