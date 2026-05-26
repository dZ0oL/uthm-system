<?php
// api/signal_save_sender_key.php — Save encrypted sender key distributions for group members.
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

$data      = json_decode(file_get_contents('php://input'), true);
$sender_id = $_SESSION['user_id'];
$group_id  = intval($data['group_id'] ?? 0);
$dists     = $data['distributions'] ?? [];

if (!$group_id || !is_array($dists) || count($dists) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'group_id and distributions required']);
    exit;
}

// Verify sender is a member of the group
$stmt = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?");
$stmt->execute([$group_id, $sender_id]);
if (!$stmt->fetchColumn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Not a member of this group']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO signal_sender_keys
            (group_id, sender_id, member_id, encrypted_dist, dist_iv, dist_auth_tag, iteration)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            encrypted_dist = VALUES(encrypted_dist),
            dist_iv        = VALUES(dist_iv),
            dist_auth_tag  = VALUES(dist_auth_tag),
            iteration      = VALUES(iteration)
    ");

    foreach ($dists as $d) {
        $member_id = intval($d['member_id'] ?? 0);
        if (!$member_id || empty($d['ciphertext']) || empty($d['iv']) || empty($d['auth_tag'])) continue;

        $stmt->execute([
            $group_id, $sender_id, $member_id,
            $d['ciphertext'], $d['iv'], $d['auth_tag'],
            intval($d['iteration'] ?? 0)
        ]);
    }

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log('signal_save_sender_key error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save sender key']);
}
