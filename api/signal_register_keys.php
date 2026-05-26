<?php
// api/signal_register_keys.php — Save Signal identity keys + SPK + OPKs for a staff user.
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

$data     = json_decode(file_get_contents('php://input'), true);
$user_id  = $_SESSION['user_id'];

if (empty($data['spk_id']) || empty($data['spk_public']) || empty($data['spk_signature'])) {
    http_response_code(400);
    echo json_encode(['error' => 'spk_id, spk_public, spk_signature required']);
    exit;
}

// Determine if this is a full registration (includes IK) or just a prekey refresh
$hasIK = !empty($data['ik_dh_public']) && !empty($data['encrypted_ik_dh']);

try {
    $pdo->beginTransaction();

    if ($hasIK) {
        // Full registration — update IK + SPK
        $pdo->prepare("
            UPDATE users SET
                ik_dh_public      = ?,
                ik_sign_public    = ?,
                spk_id            = ?,
                spk_public        = ?,
                spk_signature     = ?,
                encrypted_ik_dh   = ?,
                ik_dh_iv          = ?,
                ik_dh_auth_tag    = ?,
                encrypted_ik_sign = ?,
                ik_sign_iv        = ?,
                ik_sign_auth_tag  = ?
            WHERE user_id = ?
        ")->execute([
            $data['ik_dh_public'],
            $data['ik_sign_public'],
            $data['spk_id'],
            $data['spk_public'],
            $data['spk_signature'],
            $data['encrypted_ik_dh'],
            $data['ik_dh_iv'],
            $data['ik_dh_auth_tag'],
            $data['encrypted_ik_sign'],
            $data['ik_sign_iv'],
            $data['ik_sign_auth_tag'],
            $user_id
        ]);
    } else {
        // Prekey refresh only — update SPK without touching IK
        $pdo->prepare("
            UPDATE users SET spk_id = ?, spk_public = ?, spk_signature = ?
            WHERE user_id = ?
        ")->execute([$data['spk_id'], $data['spk_public'], $data['spk_signature'], $user_id]);
    }

    // Save one-time prekeys
    if (!empty($data['opk_keys']) && is_array($data['opk_keys'])) {
        // Remove old unused prekeys for this user first
        $pdo->prepare("DELETE FROM signal_prekeys WHERE user_id = ? AND used = 0")->execute([$user_id]);

        $stmt = $pdo->prepare("
            INSERT INTO signal_prekeys (prekey_id, user_id, public_key)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE public_key = VALUES(public_key), used = 0
        ");
        foreach ($data['opk_keys'] as $opk) {
            if (isset($opk['key_id'], $opk['public_key'])) {
                $stmt->execute([$opk['key_id'], $user_id, $opk['public_key']]);
            }
        }
    }

    $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, details, ip_address)
        VALUES (?, 'Signal Key Registration', 'Signal Protocol keys registered', ?)
    ")->execute([$user_id, $_SERVER['REMOTE_ADDR'] ?? null]);

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('signal_register_keys error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save Signal keys']);
}
