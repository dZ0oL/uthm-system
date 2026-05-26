<?php
// ============================================================
// api/get_user_keys.php
// Returns the logged-in user's encrypted key data
// Called by JS after login to unlock the private key
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

$stmt = $pdo->prepare("
    SELECT ecdh_public_key, encrypted_private_key,
           key_iv, key_auth_tag, key_hash,
           ik_dh_public, ik_sign_public,
           spk_id, spk_public, spk_signature,
           encrypted_ik_dh,   ik_dh_iv,   ik_dh_auth_tag,
           encrypted_ik_sign, ik_sign_iv, ik_sign_auth_tag
    FROM users
    WHERE user_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || empty($user['encrypted_private_key'])) {
    echo json_encode(['error' => 'No keys found for this user']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT encrypted_share FROM sss_shares
    WHERE user_id = ? AND share_index = 5
");
$stmt->execute([$user_id]);
$share5Row = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'success'               => true,
    'user_id'               => $user_id,
    // Legacy ECDH keys (kept for backward-compat decryption of old messages)
    'public_key'            => $user['ecdh_public_key'],
    'encrypted_private_key' => $user['encrypted_private_key'],
    'key_iv'                => $user['key_iv'],
    'key_auth_tag'          => $user['key_auth_tag'],
    'key_hash'              => $user['key_hash'],
    'has_share5'            => !empty($share5Row),
    // Signal Protocol identity keys
    'ik_dh_public'          => $user['ik_dh_public'],
    'ik_sign_public'        => $user['ik_sign_public'],
    'spk_id'                => $user['spk_id'],
    'spk_public'            => $user['spk_public'],
    'spk_signature'         => $user['spk_signature'],
    'encrypted_ik_dh'       => $user['encrypted_ik_dh'],
    'ik_dh_iv'              => $user['ik_dh_iv'],
    'ik_dh_auth_tag'        => $user['ik_dh_auth_tag'],
    'encrypted_ik_sign'     => $user['encrypted_ik_sign'],
    'ik_sign_iv'            => $user['ik_sign_iv'],
    'ik_sign_auth_tag'      => $user['ik_sign_auth_tag'],
]);