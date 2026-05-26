<?php
// ================
// Staff/logout.php
// ================
require_once '../config/database.php';

// Clear session token in DB so no device can use it
if (isset($_SESSION['user_id'])) {
    $pdo->prepare("
        UPDATE users SET session_token = NULL WHERE user_id = ?
    ")->execute([$_SESSION['user_id']]);

    // Log the logout
    $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, details, ip_address)
        VALUES (?, 'Logout', 'User logged out — session token cleared', ?)
    ")->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR'] ?? null]);
}

session_destroy();
header('Location: ../index.php');
exit;