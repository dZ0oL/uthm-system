<?php
// api/signal_get_key_bundle.php — Return X3DH key bundle for a user.
// Atomically marks one OPK as used and returns it.
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once '../config/database.php';
ob_clean();

// Never cache — bundle contains a one-time OPK; a stale cached response
// would return a used OPK and hide key rotation (e.g. after account recovery).
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorised']);
    exit;
}

$target_id = intval($_GET['user_id'] ?? 0);
if (!$target_id) {
    http_response_code(400);
    echo json_encode(['error' => 'user_id required']);
    exit;
}

try {
    // Fetch identity keys + SPK from users table
    $stmt = $pdo->prepare("
        SELECT ik_dh_public, ik_sign_public, spk_id, spk_public, spk_signature
        FROM users WHERE user_id = ?
    ");
    $stmt->execute([$target_id]);
    $user = $stmt->fetch();

    if (!$user || empty($user['ik_dh_public'])) {
        echo json_encode(['error' => 'User has no Signal keys — they must log in first', 'success' => false]);
        exit;
    }

    // Fetch and mark one unused OPK
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT prekey_id, public_key FROM signal_prekeys
        WHERE user_id = ? AND used = 0
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$target_id]);
    $opk = $stmt->fetch();

    if ($opk) {
        $pdo->prepare("UPDATE signal_prekeys SET used = 1 WHERE prekey_id = ? AND user_id = ?")
            ->execute([$opk['prekey_id'], $target_id]);
    }

    $pdo->commit();

    echo json_encode([
        'success'       => true,
        'ik_dh_public'  => $user['ik_dh_public'],
        'ik_sign_public'=> $user['ik_sign_public'],
        'spk_id'        => $user['spk_id'],
        'spk_public'    => $user['spk_public'],
        'spk_signature' => $user['spk_signature'],
        'opk_id'        => $opk ? $opk['prekey_id'] : null,
        'opk_public'    => $opk ? $opk['public_key']  : null,
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('signal_get_key_bundle error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch key bundle']);
}
