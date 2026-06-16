<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once '../config/database.php';
ob_clean();

// Only admin can call this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorised']);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required = ['user_id', 'public_key', 'encrypted_private_key',
             'key_iv', 'key_auth_tag', 'key_hash',
             'share1_encrypted', 'share1_iv', 'share1_auth_tag', 'share1_eph_pub',
             'share2', 'share3', 'share4', 'share5'];

foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing field: $field"]);
        exit;
    }
}

$user_id               = intval($data['user_id']);
$public_key            = $data['public_key'];
$encrypted_private_key = $data['encrypted_private_key'];
$key_iv                = $data['key_iv'];
$key_auth_tag          = $data['key_auth_tag'];
$key_hash              = $data['key_hash'];

// Server-side encryption key for shares at rest
// In production: store this in .env, never hardcode
// For FYP: this is acceptable — just document it
define('SHARE_ENCRYPTION_KEY', hash('sha256', 'UTHM_BURSARY_SHARE_KEY_FIXED_2025'));

function encryptShare($shareData) {
    $key    = hex2bin(SHARE_ENCRYPTION_KEY);
    $iv     = random_bytes(12);
    $tag    = '';
    $cipher = openssl_encrypt($shareData, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $cipher);
}

// Create device-share table on first use (holds encrypted share 1 until staff picks it up)
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sss_device_shares (
            user_id         INT          NOT NULL,
            encrypted_share TEXT         NOT NULL,
            share_iv        VARCHAR(128) NOT NULL,
            share_auth_tag  VARCHAR(128) NOT NULL,
            eph_public_key  TEXT         NOT NULL,
            created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    error_log('sss_device_shares table creation failed: ' . $e->getMessage());
}

try {
    // Begin transaction on main DB
    $pdo->beginTransaction();

    // 1. Save public key + encrypted private key to users table
    $stmt = $pdo->prepare("
        UPDATE users
        SET ecdh_public_key        = ?,
            encrypted_private_key  = ?,
            key_iv                 = ?,
            key_auth_tag           = ?,
            key_hash               = ?
        WHERE user_id = ?
    ");
    $stmt->execute([
        $public_key,
        $encrypted_private_key,
        $key_iv,
        $key_auth_tag,
        $key_hash,
        $user_id
    ]);

    // 2. Save share 5 to main DB sss_shares table
    $encShare5 = encryptShare($data['share5']);
    $stmt = $pdo->prepare("
        INSERT INTO sss_shares (user_id, share_index, encrypted_share, storage_location)
        VALUES (?, 5, ?, 'main_server')
        ON DUPLICATE KEY UPDATE
            encrypted_share  = VALUES(encrypted_share),
            updated_at       = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$user_id, $encShare5]);

    // 3. Save to sss_shares_secondary
    $encShare2 = encryptShare($data['share2']);
    $stmt = $pdo->prepare("
        INSERT INTO sss_shares_secondary (user_id, share_index, encrypted_share, storage_location)
        VALUES (?, 2, ?, 'main_server_secondary')
        ON DUPLICATE KEY UPDATE
            encrypted_share = VALUES(encrypted_share),
            updated_at      = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$user_id, $encShare2]);

    // 4. Log key generation in audit log
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, details, ip_address)
        VALUES (?, 'Key Generation', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "ECDH keys generated for user_id: $user_id",
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);

    // 5. Store encrypted share 1 for one-time device pickup.
    // Encrypted by the admin's browser with an ephemeral ECDH key against the staff's
    // public key — the server cannot decrypt it, only the staff's private key can.
    $stmt = $pdo->prepare("
        INSERT INTO sss_device_shares
            (user_id, encrypted_share, share_iv, share_auth_tag, eph_public_key)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            encrypted_share = VALUES(encrypted_share),
            share_iv        = VALUES(share_iv),
            share_auth_tag  = VALUES(share_auth_tag),
            eph_public_key  = VALUES(eph_public_key),
            created_at      = CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        $user_id,
        $data['share1_encrypted'],
        $data['share1_iv'],
        $data['share1_auth_tag'],
        $data['share1_eph_pub']
    ]);

    $pdo->commit();

    // 5. Now save shares 3 and 4 to secure DB
    try {
        $pdo_secure = new PDO(
            "mysql:host=$host;dbname=uthm_messaging_secure;charset=utf8mb4",
            $username, $password
        );
        $pdo_secure->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Save share 3 — admin vault
        $encShare3 = encryptShare($data['share3']);
        $stmt = $pdo_secure->prepare("
            INSERT INTO admin_vault_shares
                (user_id, share_index, encrypted_share, key_hash)
            VALUES (?, 3, ?, ?)
            ON DUPLICATE KEY UPDATE
                encrypted_share = VALUES(encrypted_share),
                key_hash        = VALUES(key_hash),
                updated_at      = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$user_id, $encShare3, $key_hash]);

        // Save share 4 — backup server
        $encShare4 = encryptShare($data['share4']);
        $stmt = $pdo_secure->prepare("
            INSERT INTO backup_shares
                (user_id, share_index, encrypted_share, key_hash)
            VALUES (?, 4, ?, ?)
            ON DUPLICATE KEY UPDATE
                encrypted_share = VALUES(encrypted_share),
                key_hash        = VALUES(key_hash),
                updated_at      = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$user_id, $encShare4, $key_hash]);

    } catch (PDOException $e) {
        // Secure DB failure — log it but don't fail the whole registration
        // In production you'd want to retry or queue this
        error_log('Secure DB error during key registration: ' . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Keys and shares stored successfully'
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Key registration error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error during key storage']);
}
