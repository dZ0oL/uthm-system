<?php
// =================
// Admin/logout.php
// =================
require_once '../config/database.php';

if (isset($_SESSION['user_id'])) {
    // Log logout activity
    $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'Logout', ?)");
    $log_stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
    
    session_destroy();
}

header('Location: ../index.php');
exit;
?>