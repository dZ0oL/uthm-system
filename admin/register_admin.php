<?php
// ============================================================
// admin/register_admin.php
// Register a new admin account, gated by master password.
// Admins have no ECDH/Signal keys — no browser crypto needed.
// ============================================================
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

if (empty($_SESSION['is_head_admin'])) {
    header('Location: dashboard.php');
    exit;
}

define('MASTER_PASSWORD', 'uthm@2026');

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name            = trim($_POST['name']       ?? '');
    $staff_id        = trim($_POST['staff_id']   ?? '');
    $email           = trim($_POST['email']      ?? '');
    $department      = 'Administrative';
    $master_password = $_POST['master_password'] ?? '';

    $errors = [];

    if ($master_password !== MASTER_PASSWORD) {
        $errors[] = 'Incorrect master password.';
    }

    if (empty($name)) {
        $errors[] = 'Full name is required.';
    } elseif (strlen($name) > 100) {
        $errors[] = 'Full name must not exceed 100 characters.';
    }

    if (empty($staff_id)) {
        $errors[] = 'Staff ID is required.';
    } elseif (strlen($staff_id) > 50) {
        $errors[] = 'Staff ID must not exceed 50 characters.';
    }

    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    } elseif (strlen($email) > 120) {
        $errors[] = 'Email must not exceed 120 characters.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) $errors[] = 'Email already registered.';

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE staff_id = ?");
        $stmt->execute([$staff_id]);
        if ($stmt->fetchColumn() > 0) $errors[] = 'Staff ID already exists.';
    }

    if (empty($errors)) {
        $temp_password   = $staff_id . preg_replace('/\s+/', '', $name);
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

        try {
            $pdo->beginTransaction();

            $pdo->prepare("
                INSERT INTO users (name, email, password, role, staff_id, department, password_change_required)
                VALUES (?, ?, ?, 'admin', ?, ?, 1)
            ")->execute([$name, $email, $hashed_password, $staff_id, $department]);

            $new_user_id = $pdo->lastInsertId();

            $pdo->prepare("
                INSERT INTO audit_logs (user_id, action, details, ip_address)
                VALUES (?, 'Register Admin', ?, ?)
            ")->execute([
                $_SESSION['user_id'],
                "Registered new admin: $name ($email), assigned user_id: $new_user_id",
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);

            $pdo->commit();

            // Send welcome email
            try {
                require_once '../includes/mailer.php';

                $html_body = "
                <div style='font-family:Arial,sans-serif;max-width:520px;margin:0 auto;'>
                    <div style='background:#534AB7;padding:24px;text-align:center;border-radius:8px 8px 0 0;'>
                        <h2 style='color:#fff;margin:0;font-size:20px;'>UTHM Bursary Messaging</h2>
                        <p style='color:#ccc;margin:6px 0 0;font-size:13px;'>Administrator Account Created</p>
                    </div>
                    <div style='background:#f9f9f9;padding:28px;border-radius:0 0 8px 8px;border:1px solid #eee;'>
                        <p style='font-size:15px;color:#333;margin-top:0;'>
                            Dear <strong>" . htmlspecialchars($name) . "</strong>,
                        </p>
                        <p style='color:#555;font-size:14px;'>
                            An administrator account has been created for you on the
                            UTHM Bursary Secure Messaging System.
                        </p>
                        <div style='background:#EEEDFE;border-radius:10px;padding:20px;margin:20px 0;'>
                            <p style='margin:0 0 8px;font-size:13px;color:#534AB7;font-weight:bold;'>Account Details</p>
                            <table style='font-size:14px;color:#333;width:100%;'>
                                <tr><td style='padding:3px 0;color:#666;'>Email</td><td><strong>" . htmlspecialchars($email) . "</strong></td></tr>
                                <tr><td style='padding:3px 0;color:#666;'>Staff ID</td><td><strong>" . htmlspecialchars($staff_id) . "</strong></td></tr>
                                <tr><td style='padding:3px 0;color:#666;'>Role</td><td><strong>Administrator</strong></td></tr>
                                <tr><td style='padding:3px 0;color:#666;'>Department</td><td><strong>Administrative</strong></td></tr>
                            </table>
                        </div>
                        <div style='background:#fff3cd;border-radius:8px;padding:14px;margin:16px 0;border-left:4px solid #f0ad4e;'>
                            <p style='margin:0 0 6px;font-size:13px;color:#856404;font-weight:bold;'>
                                Temporary Password
                            </p>
                            <p style='margin:0;font-size:13px;color:#856404;'>
                                Your temporary password is your <strong>Staff ID</strong> followed by your
                                <strong>Full Name</strong> (no spaces, case-sensitive).
                                You will be required to change it on first login.
                            </p>
                        </div>
                        <p style='color:#555;font-size:14px;'>
                            Log in at:
                            <a href='" . $appUrl . "/' style='color:#534AB7;'>
                                UTHM Bursary Messaging System
                            </a>
                        </p>
                        <hr style='border:none;border-top:1px solid #eee;margin:20px 0;'>
                        <p style='font-size:12px;color:#aaa;margin:0;text-align:center;'>
                            UTHM Bursary Office &bull; Secure Internal Messaging System
                        </p>
                    </div>
                </div>";

                $plain_body = "Dear $name,\n\n"
                    . "An administrator account has been created for you.\n\n"
                    . "Email: $email\nStaff ID: $staff_id\nRole: Administrator\nDepartment: Administrative\n\n"
                    . "Your temporary password is your Staff ID followed by your Full Name (no spaces, case-sensitive).\n\n"
                    . "You will be required to change this password on your first login.\n\n"
                    . "UTHM Bursary Office - Secure Internal Messaging System";

                sendEmail($email, $name, 'UTHM Bursary - Admin Account Created', $html_body, $plain_body);
            } catch (Exception $e) {
                error_log('Admin welcome email failed: ' . $e->getMessage());
            }

            $message = "Admin account for '" . htmlspecialchars($name) . "' created successfully!";
            $success = true;
            $_POST   = [];

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('register_admin error: ' . $e->getMessage());
            $errors[] = 'Database error. Please try again.';
        }
    }

    if (!empty($errors)) {
        $message = implode('<br>', $errors);
    }
}

// Count existing admins
$admin_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

$page_title = 'Register Admin';
include '../includes/header.php';
?>


<div class="page-header">
    <div>
        <h1>Register New Admin</h1>
        <div class="page-subtitle">Create a new administrator account. Requires master password confirmation.</div>
    </div>
</div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?> alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header">
                        <i data-lucide="user-shield"></i> Admin Account Details
                    </div>
                    <div class="card-body">
                        <form method="POST" autocomplete="off">
                            <?php echo csrf_field(); ?>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" name="name" class="form-control"
                                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                                           required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Staff ID *</label>
                                    <input type="text" name="staff_id" class="form-control"
                                           value="<?php echo isset($_POST['staff_id']) ? htmlspecialchars($_POST['staff_id']) : ''; ?>"
                                           placeholder="e.g., ADM002" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email Address *</label>
                                <input type="email" name="email" class="form-control"
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                       placeholder="name@uthm.edu.my" required>
                            </div>

                            <hr>

                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-lock text-warning"></i> Master Password *
                                </label>
                                <input type="password" name="master_password" class="form-control"
                                       placeholder="Enter system master password to authorise"
                                       autocomplete="new-password" required>
                                <div class="form-text text-muted">
                                    Required to create admin accounts. Contact your head administrator if you don't have it.
                                </div>
                            </div>

                            <div class="d-flex justify-content-between mt-2">
                                <a href="manage_users.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-gradient">
                                    <i class="fas fa-user-shield"></i> Create Admin Account
                                </button>
                            </div>

                        </form>
                    </div>
                </div>
            </div>

            <!-- Info panel -->
            <div class="col-md-5">
                <div class="card mb-3">
                    <div class="card-header">
                        <i data-lucide="info"></i> Registration Notes
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning py-2 mb-0">
                            <small>
                                <div class="fw-semibold mb-1">Important</div>
                                <ul class="mb-0">
                                    <li>Master password required to prevent unauthorised admin creation</li>
                                    <li>Admin must change password on first login</li>
                                    <li>All actions logged in audit trail</li>
                                </ul>
                            </small>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><i data-lucide="users"></i> Current Admins</div>
                    <div class="card-body">
                        <?php
                        $admins = $pdo->query("
                            SELECT name, staff_id, email, status FROM users
                            WHERE role = 'admin' ORDER BY user_id ASC
                        ")->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($admins as $a): ?>
                        <div class="d-flex align-items-center mb-2">
                            <div style="width:36px;height:36px;background:#EEEDFE;border-radius:50%;
                                        display:flex;align-items:center;justify-content:center;margin-right:10px;">
                                <i class="fas fa-user-shield" style="color:#534AB7;font-size:14px;"></i>
                            </div>
                            <div>
                                <div class="fw-semibold" style="font-size:14px;">
                                    <?php echo htmlspecialchars($a['name']); ?>
                                </div>
                                <div class="text-muted" style="font-size:12px;">
                                    <?php echo htmlspecialchars($a['staff_id']); ?>
                                    &bull;
                                    <span class="badge bg-<?php echo $a['status'] === 'active' ? 'success' : 'secondary'; ?> py-0">
                                        <?php echo ucfirst($a['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <hr class="my-2">
                        <small class="text-muted">Total: <?php echo count($admins); ?> admin(s)</small>
                    </div>
                </div>
            </div>
        </div>

<?php include '../includes/footer.php'; ?>
