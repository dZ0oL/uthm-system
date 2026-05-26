<?php
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

$uid = intval($_SESSION['user_id']);

$stmt = $pdo->prepare("
    SELECT m.sender_id AS chat_id, 'personal' AS chat_type, u.name AS chat_name,
           COUNT(*) AS unread_count
    FROM messages m
    JOIN users u ON m.sender_id = u.user_id
    LEFT JOIN conversation_reads cr
           ON cr.user_id = ? AND cr.chat_type = 'personal' AND cr.chat_id = m.sender_id
    WHERE m.receiver_id = ?
      AND m.message_type IN ('personal','personal_file')
      AND (cr.last_read_at IS NULL OR m.timestamp > cr.last_read_at)
    GROUP BY m.sender_id, u.name

    UNION ALL

    SELECT m.group_id AS chat_id, 'group' AS chat_type, g.group_name AS chat_name,
           COUNT(*) AS unread_count
    FROM messages m
    JOIN `groups` g ON m.group_id = g.group_id
    JOIN group_members gm ON gm.group_id = m.group_id AND gm.user_id = ?
    LEFT JOIN conversation_reads cr
           ON cr.user_id = ? AND cr.chat_type = 'group' AND cr.chat_id = m.group_id
    WHERE m.sender_id != ?
      AND m.message_type IN ('group','group_file')
      AND (cr.last_read_at IS NULL OR m.timestamp > cr.last_read_at)
    GROUP BY m.group_id, g.group_name

    ORDER BY unread_count DESC
    LIMIT 15
");
$stmt->execute([$uid, $uid, $uid, $uid, $uid]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode(['items' => $items]);
