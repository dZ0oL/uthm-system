<?php
// =================
// Admin/logout.php
// =================
require_once '../config/database.php';

if (isset($_SESSION['user_id'])) {
    // Write audit record before destroying the session
    $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'Logout', ?)");
    $log_stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);

    session_destroy(); // clears all session variables including session_token
}

header('Location: ../index.php');
exit;
?>