<?php
// ============================================================
// api/get_shares_for_device.php
// Called on first login to help browser reconstruct
// master key and generate share 1 for this device.
// Returns decrypted shares 3, 4, 5 for JS reconstruction.
// Only works for the logged-in user's own shares.
// ============================================================
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once '../config/database.php';
ob_clean();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Server-side decryption key (must match register_keys.php)
define('SHARE_ENCRYPTION_KEY', hash('sha256', 'UTHM_BURSARY_SHARE_KEY_FIXED_2025'));

function decryptShare($encryptedData) {
    $key  = hex2bin(SHARE_ENCRYPTION_KEY);
    $data = base64_decode($encryptedData);
    $iv   = substr($data, 0, 12);
    $tag  = substr($data, 12, 16);
    $cipher = substr($data, 28);
    $result = openssl_decrypt($cipher, 'aes-256-gcm', $key,
                OPENSSL_RAW_DATA, $iv, $tag);
    if ($result === false) {
        throw new Exception('Share decryption failed');
    }
    return $result;
}

try {
    // Get share 5 from main DB
    $stmt = $pdo->prepare("
        SELECT encrypted_share FROM sss_shares
        WHERE user_id = ? AND share_index = 5
    ");
    $stmt->execute([$user_id]);
    $row5 = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row5) {
        echo json_encode(['error' => 'Shares not found — contact admin']);
        exit;
    }

    // Get shares 3 and 4 from secure DB
    $pdo_secure = new PDO(
        "mysql:host=$host;dbname=uthm_messaging_secure;charset=utf8mb4",
        $username, $password
    );
    $pdo_secure->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo_secure->prepare("
        SELECT encrypted_share FROM admin_vault_shares
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $row3 = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo_secure->prepare("
        SELECT encrypted_share FROM backup_shares
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $row4 = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row3 || !$row4) {
        echo json_encode(['error' => 'Secure shares not found — contact admin']);
        exit;
    }

    // Decrypt all three shares server-side before sending to browser
    $share3 = decryptShare($row3['encrypted_share']);
    $share4 = decryptShare($row4['encrypted_share']);
    $share5 = decryptShare($row5['encrypted_share']);

    echo json_encode([
        'success' => true,
        'shares'  => [
            ['shareIndex' => 3, 'shareData' => $share3],
            ['shareIndex' => 4, 'shareData' => $share4],
            ['shareIndex' => 5, 'shareData' => $share5]
        ]
    ]);

} catch (Exception $e) {
    error_log('get_shares_for_device error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to retrieve shares']);
}