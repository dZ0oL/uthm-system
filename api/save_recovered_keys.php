<?php
// ============================================================
// api/save_recovered_keys.php
// Saves new ECDH keys and new SSS shares after recovery.
// Called by browser JS after reconstructing the master key.
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

$data = json_decode(file_get_contents('php://input'), true);

$required = [
    'target_uid', 'request_id', 'public_key',
    'encrypted_private_key', 'key_iv', 'key_auth_tag',
    'key_hash', 'share3', 'share4', 'share5',
    'share1_encrypted', 'share1_iv', 'share1_auth_tag', 'share1_eph_pub'
];

foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing: $field"]);
        exit;
    }
}

define('SHARE_ENCRYPTION_KEY', hash('sha256', 'UTHM_BURSARY_SHARE_KEY_FIXED_2025'));

function encryptShare($shareData) {
    $key    = hex2bin(SHARE_ENCRYPTION_KEY);
    $iv     = random_bytes(12);
    $tag    = '';
    $cipher = openssl_encrypt(
        $shareData, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag
    );
    return base64_encode($iv . $tag . $cipher);
}

$target_uid = intval($data['target_uid']);
$request_id = intval($data['request_id']);

// Compute temp password server-side: staffId + name (no spaces, case-sensitive)
$staff_stmt = $pdo->prepare("SELECT staff_id, name FROM users WHERE user_id = ?");
$staff_stmt->execute([$target_uid]);
$staff_row = $staff_stmt->fetch(PDO::FETCH_ASSOC);
if (!$staff_row) {
    http_response_code(400);
    echo json_encode(['error' => 'Target user not found']);
    exit;
}
$temp_password = $staff_row['staff_id'] . preg_replace('/\s+/', '', $staff_row['name']);

// Ensure sss_device_shares table exists
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

try {
    $pdo->beginTransaction();

    // Hash the auto-generated temp password for PHP login
    $new_password_hash = password_hash($temp_password, PASSWORD_DEFAULT);

    // Signal IK columns are nulled so session.js regenerates them fresh
    // with the temp password on next login (they were encrypted with the old password).
    $pdo->prepare("
        UPDATE users SET
            ecdh_public_key          = ?,
            encrypted_private_key    = ?,
            key_iv                   = ?,
            key_auth_tag             = ?,
            key_hash                 = ?,
            password                 = ?,
            password_change_required = 1,
            ik_dh_public             = NULL,
            ik_sign_public           = NULL,
            spk_id                   = NULL,
            spk_public               = NULL,
            spk_signature            = NULL,
            encrypted_ik_dh          = NULL,
            ik_dh_iv                 = NULL,
            ik_dh_auth_tag           = NULL,
            encrypted_ik_sign        = NULL,
            ik_sign_iv               = NULL,
            ik_sign_auth_tag         = NULL
        WHERE user_id = ?
    ")->execute([
        $data['public_key'],
        $data['encrypted_private_key'],
        $data['key_iv'],
        $data['key_auth_tag'],
        $data['key_hash'],
        $new_password_hash,
        $target_uid
    ]);

// Send recovery notification email with password clue
try {
    require_once '../includes/mailer.php';

    // Fetch staff details for the email
    $staff = $pdo->prepare("
        SELECT name, email, staff_id FROM users WHERE user_id = ?
    ");
    $staff->execute([$target_uid]);
    $staff_info = $staff->fetch(PDO::FETCH_ASSOC);

    if ($staff_info) {
        $html_body = "
        <div style='font-family:Arial,sans-serif;max-width:520px;margin:0 auto;'>
            <div style='background:#534AB7;padding:24px;text-align:center;border-radius:8px 8px 0 0;'>
                <h2 style='color:#fff;margin:0;font-size:20px;'>UTHM Bursary Messaging</h2>
                <p style='color:#ccc;margin:6px 0 0;font-size:13px;'>Account Recovery Completed</p>
            </div>
            <div style='background:#f9f9f9;padding:28px;border-radius:0 0 8px 8px;border:1px solid #eee;'>
                <p style='font-size:15px;color:#333;margin-top:0;'>
                    Dear <strong>" . htmlspecialchars($staff_info['name']) . "</strong>,
                </p>
                <p style='color:#555;font-size:14px;'>
                    Your account has been successfully recovered by the administrator.
                    A new temporary password has been set for your account.
                </p>
                <div style='background:#EEEDFE;border-radius:10px;padding:20px;margin:20px 0;'>
                    <p style='margin:0 0 10px;font-size:13px;color:#534AB7;font-weight:bold;'>
                        Password Hint
                    </p>
                    <p style='margin:0;font-size:14px;color:#3C3489;'>
                        Your new temporary password is your
                        <strong>Staff ID</strong> followed by your <strong>Full Name</strong>
                        (no spaces, case sensitive).
                    </p>
                </div>
                <div style='background:#fff3cd;border-radius:8px;padding:14px;margin:16px 0;border-left:4px solid #f0ad4e;'>
                    <p style='margin:0;font-size:13px;color:#856404;'>
                        <strong>Important:</strong> You will be required to change this 
                        temporary password immediately after logging in.
                    </p>
                </div>
                <p style='color:#555;font-size:14px;'>
                    If you did not request this recovery or have any concerns, 
                    please contact your administrator immediately.
                </p>
                <hr style='border:none;border-top:1px solid #eee;margin:20px 0;'>
                <p style='font-size:12px;color:#aaa;margin:0;text-align:center;'>
                    UTHM Bursary Office &bull; Secure Internal Messaging System
                </p>
            </div>
        </div>";

        $plain_body = "Dear {$staff_info['name']},\n\n"
                    . "Your account has been successfully recovered.\n\n"
                    . "Your new temporary password is your Staff ID followed by your Full Name (no spaces, case sensitive).\n\n"
                    . "You will be required to change this password on first login.\n\n"
                    . "UTHM Bursary Office - Secure Internal Messaging System";

        sendEmail(
            $staff_info['email'],
            $staff_info['name'],
            'UTHM Bursary — Account Recovery Completed',
            $html_body,
            $plain_body
        );
    }
} catch (Exception $e) {
    error_log('Recovery email failed: ' . $e->getMessage());
    // Non-fatal — recovery still succeeded even if email fails
}

    // Store encrypted share 1 for one-time device pickup on first login
    $pdo->prepare("
        INSERT INTO sss_device_shares
            (user_id, encrypted_share, share_iv, share_auth_tag, eph_public_key)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            encrypted_share = VALUES(encrypted_share),
            share_iv        = VALUES(share_iv),
            share_auth_tag  = VALUES(share_auth_tag),
            eph_public_key  = VALUES(eph_public_key),
            created_at      = CURRENT_TIMESTAMP
    ")->execute([
        $target_uid,
        $data['share1_encrypted'],
        $data['share1_iv'],
        $data['share1_auth_tag'],
        $data['share1_eph_pub']
    ]);

    // Clear old prekeys — they belong to the previous identity
    $pdo->prepare("DELETE FROM signal_prekeys WHERE user_id = ?")->execute([$target_uid]);

    // ── Update share 5 in main DB ─────────────────────────────
    $pdo->prepare("
        INSERT INTO sss_shares
            (user_id, share_index, encrypted_share, storage_location)
        VALUES (?, 5, ?, 'main_server')
        ON DUPLICATE KEY UPDATE
            encrypted_share = VALUES(encrypted_share),
            updated_at      = CURRENT_TIMESTAMP
    ")->execute([$target_uid, encryptShare($data['share5'])]);

    // ── Update recovery request status ────────────────────────
    // Update status first
    $pdo->prepare("
        UPDATE recovery_requests
        SET status = 'completed',
            approved_date = NOW()
        WHERE request_id = ?
    ")->execute([$request_id]);

    // Try to save key hash separately
    try {
        $pdo->prepare("
            UPDATE recovery_requests
            SET new_key_hash = ?
            WHERE request_id = ?
        ")->execute([$data['key_hash'], $request_id]);
} catch (PDOException $e) {
    // Column may not exist — non-fatal
    error_log('new_key_hash update skipped: ' . $e->getMessage());
}

    // ── Audit log ─────────────────────────────────────────────
    $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, details, ip_address)
        VALUES (?, 'Recovery Completed', ?, ?)
    ")->execute([
        $_SESSION['user_id'],
        "New keys issued for user_id: $target_uid, request_id: $request_id",
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);

    $pdo->commit();

    // ── Update shares 3 and 4 in secure DB ───────────────────
    try {
        $pdo_secure = new PDO(
            'mysql:host=localhost;dbname=uthm_messaging_secure;charset=utf8mb4',
            'root',
            ''
        );
        $pdo_secure->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo_secure->prepare("
            INSERT INTO admin_vault_shares
                (user_id, share_index, encrypted_share, key_hash)
            VALUES (?, 3, ?, ?)
            ON DUPLICATE KEY UPDATE
                encrypted_share = VALUES(encrypted_share),
                key_hash        = VALUES(key_hash),
                updated_at      = CURRENT_TIMESTAMP
        ")->execute([
            $target_uid,
            encryptShare($data['share3']),
            $data['key_hash']
        ]);

        $pdo_secure->prepare("
            INSERT INTO backup_shares
                (user_id, share_index, encrypted_share, key_hash)
            VALUES (?, 4, ?, ?)
            ON DUPLICATE KEY UPDATE
                encrypted_share = VALUES(encrypted_share),
                key_hash        = VALUES(key_hash),
                updated_at      = CURRENT_TIMESTAMP
        ")->execute([
            $target_uid,
            encryptShare($data['share4']),
            $data['key_hash']
        ]);

    } catch (Exception $e) {
        error_log('Secure DB update failed during recovery: ' . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Recovery completed. New keys issued successfully.'
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('save_recovered_keys error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save recovered keys']);
}