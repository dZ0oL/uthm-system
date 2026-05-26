<?php
// =========================
// admin/change_password.php
// =========================
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new_pw  = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!password_verify($current, $row['password'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new_pw) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new_pw !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif ($new_pw === $current) {
        $error = 'New password must be different from your current password.';
    } else {
        $pdo->prepare("UPDATE users SET password = ?, password_change_required = 0 WHERE user_id = ?")
            ->execute([password_hash($new_pw, PASSWORD_DEFAULT), $_SESSION['user_id']]);
        $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, 'Change Password', 'Admin changed password', ?)")
            ->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR'] ?? null]);
        $_SESSION['password_change_required'] = false;
        $success = true;
    }
}

$page_title = 'Change Password';
include '../includes/header.php';
?>

<?php if ($success): ?>

<div class="page-header"><div><h1>Change Password</h1></div></div>
<div class="alert alert-success">
    <i class="fas fa-check-circle me-1"></i> Password changed successfully.
    <a href="dashboard.php" class="btn btn-sm btn-success ms-3">Go to Dashboard</a>
</div>

<?php else: ?>

<!-- ── Normal (non-forced) change password layout ── -->
<div class="page-header">
    <div>
        <h1>Change Password</h1>
        <div class="page-subtitle">Update your administrator account password</div>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><i class="fas fa-times-circle me-1"></i><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-5">
        <div class="card">
            <div class="card-body">
                <form method="POST" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control"
                               autocomplete="current-password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control"
                               minlength="8" autocomplete="new-password" required>
                        <div class="form-text">Minimum 8 characters.</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control"
                               autocomplete="new-password" required>
                    </div>
                    <button type="submit" class="btn btn-gradient w-100">
                        <i class="fas fa-save me-1"></i>Change Password
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary w-100 mt-2">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php include '../includes/footer.php'; ?>
