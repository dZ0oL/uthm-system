<?php
// ============================================================
// api/change_password.php
// Updates PHP password hash in DB.
// The ECDH private key re-encryption happens in the browser
// before this endpoint is called.
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

$required = [
    'current_password', 'new_password',
    'encrypted_private_key', 'key_iv', 'key_auth_tag'
];
foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing field: $field"]);
        exit;
    }
}

// Optional Signal IK re-encryption fields
$hasSignalKeys = !empty($data['encrypted_ik_dh']) && !empty($data['ik_dh_iv']);

$user_id          = $_SESSION['user_id'];
$current_password = $data['current_password'];
$new_password     = $data['new_password'];

if (strlen($new_password) < 8) {
    http_response_code(400);
    echo json_encode(['error' => 'New password must be at least 8 characters']);
    exit;
}

// Verify current password
$stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($current_password, $user['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Current password is incorrect']);
    exit;
}

try {
    if ($hasSignalKeys) {
        $pdo->prepare("
            UPDATE users SET
                password              = ?,
                encrypted_private_key = ?,
                key_iv                = ?,
                key_auth_tag          = ?,
                encrypted_ik_dh       = ?,
                ik_dh_iv              = ?,
                ik_dh_auth_tag        = ?,
                encrypted_ik_sign     = ?,
                ik_sign_iv            = ?,
                ik_sign_auth_tag      = ?
            WHERE user_id = ?
        ")->execute([
            password_hash($new_password, PASSWORD_DEFAULT),
            $data['encrypted_private_key'],
            $data['key_iv'],
            $data['key_auth_tag'],
            $data['encrypted_ik_dh'],
            $data['ik_dh_iv'],
            $data['ik_dh_auth_tag'],
            $data['encrypted_ik_sign'],
            $data['ik_sign_iv'],
            $data['ik_sign_auth_tag'],
            $user_id
        ]);
    } else {
        // Update PHP password hash + re-wrapped ECDH private key
        $pdo->prepare("
            UPDATE users SET
                password              = ?,
                encrypted_private_key = ?,
                key_iv                = ?,
                key_auth_tag          = ?
            WHERE user_id = ?
        ")->execute([
            password_hash($new_password, PASSWORD_DEFAULT),
            $data['encrypted_private_key'],
            $data['key_iv'],
            $data['key_auth_tag'],
            $user_id
        ]);
    }

    // Clear forced password change flag if this was a post-recovery change
    if (!empty($data['clear_pw_change_flag'])) {
        $pdo->prepare("
            UPDATE users SET password_change_required = 0
            WHERE user_id = ?
        ")->execute([$user_id]);

        // Clear session flag so redirect stops
        $_SESSION['password_change_required'] = false;
    }

    // Log it
    $action  = !empty($data['clear_pw_change_flag'])
        ? 'Force Password Change'
        : 'Change Password';
    $details = !empty($data['clear_pw_change_flag'])
        ? 'Temporary password changed after account recovery'
        : 'Password and encryption key updated';

    $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, details, ip_address)
        VALUES (?, ?, ?, ?)
    ")->execute([$user_id, $action, $details, $_SERVER['REMOTE_ADDR'] ?? null]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log('change_password error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update password']);
}
