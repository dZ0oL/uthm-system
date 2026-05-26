<?php
// ============================================================
// api/get_device_share.php
// One-time pickup of SSS share 1 for the logged-in staff member.
//
// Share 1 was encrypted at registration time by the admin's browser
// using an ephemeral ECDH keypair against the staff's ECDH public key.
// Only the staff's private key can decrypt it — the server stores it
// blindly. After delivery the row is deleted; it lives on-device only.
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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT encrypted_share, share_iv, share_auth_tag, eph_public_key
        FROM sss_device_shares
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        // Not an error — expected on re-login once the share is already on the device
        echo json_encode(['success' => false, 'reason' => 'not_found']);
        exit;
    }

    // Delete immediately — one-time pickup; share lives on device only after this
    $pdo->prepare("DELETE FROM sss_device_shares WHERE user_id = ?")
        ->execute([$user_id]);

    // Audit log
    $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, details, ip_address)
        VALUES (?, 'Device Share Pickup', 'SSS share 1 delivered and removed from server', ?)
    ")->execute([$user_id, $_SERVER['REMOTE_ADDR'] ?? null]);

    echo json_encode([
        'success'         => true,
        'share_encrypted' => $row['encrypted_share'],
        'share_iv'        => $row['share_iv'],
        'share_auth_tag'  => $row['share_auth_tag'],
        'eph_pub'         => $row['eph_public_key']
    ]);

} catch (PDOException $e) {
    error_log('get_device_share error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to retrieve device share']);
}
