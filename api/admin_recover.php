<?php
// ============================================================
// api/admin_recover.php
// Handles SSS-based account recovery.
// Called by admin panel after approving a recovery request.
// Reconstructs master key from shares 3, 4, 5 and re-issues
// new ECDH keys for the recovered user.
// ============================================================
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once '../config/database.php';
ob_clean();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorised']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data       = json_decode(file_get_contents('php://input'), true);
$request_id = intval($data['request_id'] ?? 0);
$target_uid = intval($data['user_id'] ?? 0);

if (!$request_id || !$target_uid) {
    http_response_code(400);
    echo json_encode(['error' => 'request_id and user_id are required']);
    exit;
}

// Verify recovery request exists and is approved
$stmt = $pdo->prepare("
    SELECT * FROM recovery_requests
    WHERE request_id = ? AND user_id = ? AND status = 'approved'
");
$stmt->execute([$request_id, $target_uid]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    http_response_code(400);
    echo json_encode(['error' => 'Recovery request not found or not approved']);
    exit;
}

// Server-side share decryption key (must match register_keys.php)
define('SHARE_ENCRYPTION_KEY', hash('sha256', 'UTHM_BURSARY_SHARE_KEY_FIXED_2025'));

function decryptShare($encryptedData) {
    $key    = hex2bin(SHARE_ENCRYPTION_KEY);
    $data   = base64_decode($encryptedData);
    $iv     = substr($data, 0, 12);
    $tag    = substr($data, 12, 16);
    $cipher = substr($data, 28);
    $result = openssl_decrypt(
        $cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag
    );
    if ($result === false) {
        throw new Exception('Share decryption failed');
    }
    return $result;
}

function encryptShare($shareData) {
    $key    = hex2bin(SHARE_ENCRYPTION_KEY);
    $iv     = random_bytes(12);
    $tag    = '';
    $cipher = openssl_encrypt(
        $shareData, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag
    );
    return base64_encode($iv . $tag . $cipher);
}

try {
    // ── Step 1: Fetch share 5 from main DB ───────────────────
    $stmt = $pdo->prepare("
        SELECT encrypted_share FROM sss_shares
        WHERE user_id = ? AND share_index = 5
    ");
    $stmt->execute([$target_uid]);
    $row5 = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row5) {
        echo json_encode(['error' => 'Share 5 not found in main DB']);
        exit;
    }

    // ── Step 2: Fetch shares 3 and 4 from secure DB ──────────
    $pdo_secure = new PDO(
        'mysql:host=localhost;dbname=uthm_messaging_secure;charset=utf8mb4',
        'root',
        ''
    );
    $pdo_secure->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo_secure->prepare("
        SELECT encrypted_share, key_hash
        FROM admin_vault_shares WHERE user_id = ?
    ");
    $stmt->execute([$target_uid]);
    $row3 = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo_secure->prepare("
        SELECT encrypted_share FROM backup_shares WHERE user_id = ?
    ");
    $stmt->execute([$target_uid]);
    $row4 = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row3 || !$row4) {
        echo json_encode(['error' => 'Shares 3 or 4 not found in secure DB']);
        exit;
    }

    // ── Step 3: Decrypt all three shares ─────────────────────
    $share3 = decryptShare($row3['encrypted_share']);
    $share4 = decryptShare($row4['encrypted_share']);
    $share5 = decryptShare($row5['encrypted_share']);

    // ── Step 4: Send shares to browser for JS reconstruction ─
    // We send the raw share data to the browser.
    // The browser reconstructs the master key using UTHMSS.reconstruct()
    // then generates new ECDH keys and new shares.
    // This keeps the master key out of PHP memory entirely.

    // ── Step 5: Log recovery initiation ──────────────────────
    $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, details, ip_address)
        VALUES (?, 'Recovery Initiated', ?, ?)
    ")->execute([
        $_SESSION['user_id'],
        "SSS recovery initiated for user_id: $target_uid, request_id: $request_id",
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);

    echo json_encode([
        'success'    => true,
        'shares'     => [
            ['shareIndex' => 3, 'shareData' => $share3],
            ['shareIndex' => 4, 'shareData' => $share4],
            ['shareIndex' => 5, 'shareData' => $share5]
        ],
        'key_hash'   => $row3['key_hash'],
        'target_uid' => $target_uid,
        'request_id' => $request_id
    ]);

} catch (Exception $e) {
    error_log('admin_recover error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Recovery failed: ' . $e->getMessage()]);
}