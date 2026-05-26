<?php
// ===================
// Admin/dashboard.php
// ===================
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit;
}

// Forced password change — handle POST before any other logic
$pw_forced = !empty($_SESSION['password_change_required']);
$pw_error  = '';

if ($pw_forced && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_change_pw'])) {
    $current = $_POST['current_password'] ?? '';
    $new_pw  = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!password_verify($current, $row['password'])) {
        $pw_error = 'Current password is incorrect.';
    } elseif (strlen($new_pw) < 8) {
        $pw_error = 'New password must be at least 8 characters.';
    } elseif ($new_pw !== $confirm) {
        $pw_error = 'Passwords do not match.';
    } elseif ($new_pw === $current) {
        $pw_error = 'New password must be different from your temporary password.';
    } else {
        $pdo->prepare("UPDATE users SET password = ?, password_change_required = 0 WHERE user_id = ?")
            ->execute([password_hash($new_pw, PASSWORD_DEFAULT), $_SESSION['user_id']]);
        $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, 'Change Password', 'Admin changed password', ?)")
            ->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR'] ?? null]);
        $_SESSION['password_change_required'] = false;
        header('Location: dashboard.php');
        exit;
    }
}

// HOA: Handle admin password reset approve/reject
if (!empty($_SESSION['is_head_admin'])) {
    if (isset($_GET['approve_reset'])) {
        $req_id = intval($_GET['approve_reset']);
        $stmt = $pdo->prepare("
            SELECT arr.user_id, u.name, u.email, u.staff_id
            FROM admin_reset_requests arr
            JOIN users u ON arr.user_id = u.user_id
            WHERE arr.request_id = ? AND arr.status = 'pending'
        ");
        $stmt->execute([$req_id]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($req) {
            $temp_pw = $req['staff_id'] . preg_replace('/\s+/', '', $req['name']);
            $hashed  = password_hash($temp_pw, PASSWORD_DEFAULT);

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE users SET password=?, password_change_required=1 WHERE user_id=?")
                ->execute([$hashed, $req['user_id']]);
            $pdo->prepare("
                UPDATE admin_reset_requests SET status='completed', approved_by=?, approved_at=NOW()
                WHERE request_id=?
            ")->execute([$_SESSION['user_id'], $req_id]);
            $pdo->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, 'Approve Admin Reset', ?)")
                ->execute([$_SESSION['user_id'], "Approved and reset password for: {$req['name']} ({$req['email']})"]);
            $pdo->commit();

            try {
                require_once '../includes/mailer.php';
                $html_body = "
                <div style='font-family:Arial,sans-serif;max-width:500px;margin:0 auto;'>
                    <div style='background:#534AB7;padding:24px;text-align:center;border-radius:8px 8px 0 0;'>
                        <h2 style='color:#fff;margin:0;'>UTHM Bursary Messaging</h2>
                        <p style='color:#ccc;margin:6px 0 0;font-size:13px;'>Password Reset Approved</p>
                    </div>
                    <div style='background:#f9f9f9;padding:28px;border-radius:0 0 8px 8px;border:1px solid #eee;'>
                        <p style='color:#333;'>Dear <strong>" . htmlspecialchars($req['name']) . "</strong>,</p>
                        <p style='color:#555;font-size:14px;'>
                            Your password reset request has been approved. Your account password has been reset to a temporary password.
                        </p>
                        <div style='background:#fff3cd;border-radius:8px;padding:16px;margin:20px 0;border-left:4px solid #f0ad4e;'>
                            <p style='margin:0 0 6px;font-size:13px;color:#856404;font-weight:bold;'>Temporary Password</p>
                            <p style='margin:0;font-size:13px;color:#856404;'>
                                Your temporary password is your <strong>Staff ID</strong> followed by your
                                <strong>Full Name</strong> (no spaces, case-sensitive).
                                You will be required to change it on first login.
                            </p>
                        </div>
                        <p style='color:#555;font-size:14px;'>
                            Log in at: <a href='http://localhost/uthm-system/' style='color:#534AB7;'>UTHM Bursary Messaging System</a>
                        </p>
                        <hr style='border:none;border-top:1px solid #eee;margin:20px 0;'>
                        <p style='font-size:12px;color:#aaa;text-align:center;margin:0;'>UTHM Bursary Office &bull; Secure Internal Messaging System</p>
                    </div>
                </div>";
                $plain = "Dear {$req['name']},\n\n"
                       . "Your password reset has been approved.\n\n"
                       . "Temporary password: your Staff ID + Full Name (no spaces, case-sensitive).\n"
                       . "You must change it on first login.\n\n"
                       . "Login: http://localhost/uthm-system/\n\nUTHM Bursary Office";
                sendEmail($req['email'], $req['name'], 'UTHM Bursary — Password Reset Approved', $html_body, $plain);
            } catch (Exception $e) {
                error_log('Admin reset approval email failed: ' . $e->getMessage());
            }
        }
        header('Location: dashboard.php');
        exit;

    } elseif (isset($_GET['reject_reset'])) {
        $req_id = intval($_GET['reject_reset']);
        $stmt = $pdo->prepare("SELECT user_id FROM admin_reset_requests WHERE request_id = ? AND status = 'pending'");
        $stmt->execute([$req_id]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($req) {
            $pdo->prepare("
                UPDATE admin_reset_requests SET status='rejected', approved_by=?, approved_at=NOW() WHERE request_id=?
            ")->execute([$_SESSION['user_id'], $req_id]);
            $pdo->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, 'Reject Admin Reset', ?)")
                ->execute([$_SESSION['user_id'], "Rejected admin reset request ID: $req_id"]);
        }
        header('Location: dashboard.php');
        exit;
    }
}

$user_id = $_SESSION['user_id'];

// Get dashboard statistics
$stats = [];

// Total users
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
$stats['total_users'] = $stmt->fetch()['count'];

// Active users
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
$stats['active_users'] = $stmt->fetch()['count'];

// Total groups
$stmt = $pdo->query("SELECT COUNT(*) as count FROM `groups`");
$stats['total_groups'] = $stmt->fetch()['count'];

// Total messages
$stmt = $pdo->query("SELECT COUNT(*) as count FROM messages");
$stats['total_messages'] = $stmt->fetch()['count'];

// Pending recovery requests
$stmt = $pdo->query("SELECT COUNT(*) as count FROM recovery_requests WHERE status = 'pending'");
$stats['pending_recovery'] = $stmt->fetch()['count'];

// Recent activities
$stmt = $pdo->query("
    SELECT a.*, u.name as user_name 
    FROM audit_logs a 
    JOIN users u ON a.user_id = u.user_id 
    ORDER BY a.timestamp DESC 
    LIMIT 5
");
$recent_activities = $stmt->fetchAll();

// Recent recovery requests
$stmt = $pdo->query("
    SELECT r.*, u.name as user_name 
    FROM recovery_requests r 
    JOIN users u ON r.user_id = u.user_id 
    WHERE r.status = 'pending' 
    ORDER BY r.request_date DESC 
    LIMIT 5
");
$recent_recovery = $stmt->fetchAll();

// Pending admin reset requests (HOA only)
$pending_admin_resets = [];
if (!empty($_SESSION['is_head_admin'])) {
    $stmt = $pdo->query("
        SELECT arr.request_id, arr.requested_at, u.name as user_name, u.email as user_email, u.staff_id
        FROM admin_reset_requests arr
        JOIN users u ON arr.user_id = u.user_id
        WHERE arr.status = 'pending'
        ORDER BY arr.requested_at DESC
    ");
    $pending_admin_resets = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$page_title = 'Admin Dashboard';
include '../includes/header.php';
?>


<div class="page-header">
    <div>
        <h1>Admin Dashboard</h1>
        <div class="page-subtitle">System overview and statistics</div>
    </div>
</div>

<!-- Stat cards -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon stat-icon--blue"><i data-lucide="users"></i></div>
        <div>
            <div class="stat-value"><?php echo $stats['total_users']; ?></div>
            <div class="stat-label">Total Users</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon--green"><i data-lucide="user-check"></i></div>
        <div>
            <div class="stat-value"><?php echo $stats['active_users']; ?></div>
            <div class="stat-label">Active Users</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon--indigo"><i data-lucide="layers"></i></div>
        <div>
            <div class="stat-value"><?php echo $stats['total_groups']; ?></div>
            <div class="stat-label">Active Groups</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon--amber"><i data-lucide="key"></i></div>
        <div>
            <div class="stat-value"><?php echo $stats['pending_recovery']; ?></div>
            <div class="stat-label">Pending Recovery</div>
        </div>
    </div>
</div>
            
<div class="row g-3 mt-2">
    <!-- Recent Activities -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <i data-lucide="activity"></i> Recent Activities
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_activities as $activity): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($activity['user_name']); ?></td>
                                    <td>
                                        <span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;"><?php echo htmlspecialchars($activity['action']); ?></span>
                                        <?php if ($activity['details']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($activity['details']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="white-space:nowrap;font-size:12px;"><?php echo date('h:i A', strtotime($activity['timestamp'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-body" style="padding-top:12px;padding-bottom:12px;border-top:1px solid var(--border);">
                <a href="audit_logs.php" class="btn btn-sm btn-outline-primary w-100">View All Activities</a>
            </div>
        </div>
    </div>

    <!-- Right column: stacked cards -->
    <div class="col-lg-6 d-flex flex-column gap-3">

        <!-- Pending Recovery Requests -->
        <div class="card">
            <div class="card-header">
                <i data-lucide="key"></i> Pending Recovery Requests
                <?php if (!empty($recent_recovery)): ?>
                    <span class="badge bg-danger ms-auto"><?php echo count($recent_recovery); ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($recent_recovery)): ?>
                    <div class="text-center py-3">
                        <i data-lucide="check-circle" style="width:36px;height:36px;color:var(--success);margin-bottom:8px;"></i>
                        <p class="text-muted mb-0" style="font-size:13px;">No pending recovery requests</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_recovery as $request): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($request['user_name']); ?></td>
                                        <td style="font-size:12px;white-space:nowrap;"><?php echo date('d/m/Y', strtotime($request['request_date'])); ?></td>
                                        <td style="white-space:nowrap;">
                                            <a href="recovery_requests.php?action=approve&id=<?php echo $request['request_id']; ?>"
                                               class="btn btn-sm me-1"
                                               style="background:#dcfce7;color:#166534;border:1px solid #bbf7d0;border-radius:6px;font-size:11px;font-weight:500;"
                                               onclick="event.preventDefault(); appConfirm('Approve Request','Allow recovery request for <?php echo htmlspecialchars($request['user_name'], ENT_QUOTES); ?> to proceed?','success','Approve',()=>location.href=this.href);">
                                                <i class="fas fa-check me-1"></i>Approve
                                            </a>
                                            <a href="recovery_requests.php?action=reject&id=<?php echo $request['request_id']; ?>"
                                               class="btn btn-sm"
                                               style="background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;border-radius:6px;font-size:11px;font-weight:500;"
                                               onclick="event.preventDefault(); appConfirm('Reject Request','Reject recovery request for <?php echo htmlspecialchars($request['user_name'], ENT_QUOTES); ?>?','danger','Reject',()=>location.href=this.href);">
                                                <i class="fas fa-times me-1"></i>Reject
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <a href="recovery_requests.php" class="btn btn-sm btn-outline-primary w-100 mt-3">Manage All Requests</a>
            </div>
        </div>

        <!-- Admin Password Reset Requests (HOA only) -->
        <?php if (!empty($_SESSION['is_head_admin'])): ?>
        <div class="card">
            <div class="card-header">
                <i data-lucide="shield-alert"></i> Admin Password Reset Requests
                <?php if (!empty($pending_admin_resets)): ?>
                    <span class="badge bg-danger ms-auto"><?php echo count($pending_admin_resets); ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($pending_admin_resets)): ?>
                    <div class="text-center py-3">
                        <i data-lucide="check-circle" style="width:36px;height:36px;color:var(--success);margin-bottom:8px;"></i>
                        <p class="text-muted mb-0" style="font-size:13px;">No pending admin reset requests</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_admin_resets as $req): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($req['user_name']); ?></td>
                                    <td style="font-size:12px;white-space:nowrap;"><?php echo date('d/m/Y', strtotime($req['requested_at'])); ?></td>
                                    <td style="white-space:nowrap;">
                                        <a href="?approve_reset=<?php echo $req['request_id']; ?>"
                                           class="btn btn-sm me-1"
                                           style="background:#dcfce7;color:#166534;border:1px solid #bbf7d0;border-radius:6px;font-size:11px;font-weight:500;"
                                           onclick="event.preventDefault(); appConfirm('Reset Password','Approve and reset password for <?php echo htmlspecialchars($req['user_name'], ENT_QUOTES); ?>?','success','Approve',()=>location.href=this.href);">
                                            <i class="fas fa-check me-1"></i>Approve
                                        </a>
                                        <button type="button" class="btn btn-sm"
                                                style="background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;border-radius:6px;font-size:11px;font-weight:500;"
                                                onclick="openRejectAdminModal(<?php echo $req['request_id']; ?>)">
                                            <i class="fas fa-times me-1"></i>Reject
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /.col-lg-6 right -->
</div><!-- /.row -->

<!-- System Status -->
<div class="card mt-3">
    <div class="card-header">
        <i data-lucide="server"></i> System Status
    <div class="card-body">
        <div class="d-flex flex-wrap gap-4" style="font-size:13px;color:#475569;">
            <div class="d-flex align-items-center gap-2">
                <span style="display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:#dcfce7;color:#166534;border:1px solid #bbf7d0;">Online</span>
                Database
            </div>
            <div class="d-flex align-items-center gap-2">
                <span style="display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:#dcfce7;color:#166534;border:1px solid #bbf7d0;">Online</span>
                Authentication Service
            </div>
            <div class="d-flex align-items-center gap-2">
                <span style="display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;">Active</span>
                User Sessions: <?php echo $stats['active_users']; ?>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span style="display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:#ede9fe;color:#5b21b6;border:1px solid #ddd6fe;">Running</span>
                Messages Today: <?php echo $stats['total_messages']; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($_SESSION['is_head_admin'])): ?>
<!-- Reject Admin Reset modal (dashboard) -->
<div class="modal fade" id="rejectAdminResetModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:360px;">
        <div class="modal-content" style="border-radius:20px;overflow:hidden;border:none;box-shadow:0 12px 40px rgba(0,0,0,.15);">
            <div class="modal-header border-0" style="padding:20px 24px 8px;">
                <h5 class="modal-title" style="font-size:15px;font-weight:700;color:#0f172a;">Reject Password Reset Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="recovery_requests.php">
                <input type="hidden" name="action" value="reject_admin_reset">
                <input type="hidden" name="request_id" id="rejectAdminResetId">
                <div class="modal-body" style="padding:8px 24px;">
                    <p style="font-size:13px;color:#64748b;margin-bottom:12px;">Provide a reason — it will be emailed to the admin.</p>
                    <textarea name="rejection_reason" id="rejectAdminResetReason" class="form-control"
                              rows="4" style="font-size:13px;resize:none;border-radius:10px;"
                              placeholder="e.g. Cannot verify identity. Please contact the Head of Admin directly." required></textarea>
                </div>
                <div class="modal-footer border-0" style="padding:12px 24px 20px;justify-content:flex-end;gap:4px;">
                    <button type="button" data-bs-dismiss="modal"
                            style="background:none;border:none;padding:9px 16px;font-size:13px;font-weight:500;color:#94a3b8;cursor:pointer;border-radius:10px;"
                            onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'">Cancel</button>
                    <button type="submit"
                            style="background:none;border:none;padding:9px 16px;font-size:13px;font-weight:600;color:#b91c1c;cursor:pointer;border-radius:10px;"
                            onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='none'">Reject &amp; Notify</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function openRejectAdminModal(requestId) {
    document.getElementById('rejectAdminResetId').value = requestId;
    document.getElementById('rejectAdminResetReason').value = '';
    new bootstrap.Modal(document.getElementById('rejectAdminResetModal')).show();
}
</script>
<?php endif; ?>

<?php if ($pw_forced): ?>
<!-- Forced password change — real dashboard is the blurred background -->
<div style="
    position:fixed;inset:0;z-index:1050;
    background:rgba(15,23,42,.4);
    backdrop-filter:blur(8px);
    -webkit-backdrop-filter:blur(8px);
    display:flex;align-items:center;justify-content:center;
    padding:16px;">

    <div class="card shadow-lg" style="width:460px;max-width:100%;">
        <div class="card-body p-5">

            <div class="text-center mb-4">
                <div style="width:64px;height:64px;background:#EEEDFE;border-radius:50%;
                            display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                    <i class="fas fa-key fa-2x" style="color:#534AB7;"></i>
                </div>
                <h4 class="mb-1">Password Change Required</h4>
                <p class="text-muted small">
                    A temporary password was set for your account. You must choose a new password before continuing.
                </p>
            </div>

            <?php if ($pw_error): ?>
            <div class="alert alert-danger py-2 mb-3">
                <i class="fas fa-times-circle me-1"></i><?php echo htmlspecialchars($pw_error); ?>
            </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <input type="hidden" name="_change_pw" value="1">
                <div class="mb-3">
                    <label class="form-label">Current Temporary Password</label>
                    <input type="password" name="current_password" class="form-control"
                           autocomplete="current-password" placeholder="Enter your temporary password" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" id="forced_new_pw" class="form-control"
                           minlength="8" autocomplete="new-password" placeholder="Minimum 8 characters" required>
                    <div id="forced-pw-strength" class="mt-1"></div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control"
                           autocomplete="new-password" placeholder="Repeat new password" required>
                </div>
                <button type="submit" class="btn btn-gradient w-100">
                    <i class="fas fa-lock me-1"></i>Set New Password &amp; Continue
                </button>
            </form>

        </div>
    </div>
</div>
<script>
document.getElementById('forced_new_pw').addEventListener('input', function () {
    var pw  = this.value;
    var str = document.getElementById('forced-pw-strength');
    if (!pw) { str.innerHTML = ''; return; }
    var score = 0;
    if (pw.length >= 8)           score++;
    if (pw.length >= 12)          score++;
    if (/[A-Z]/.test(pw))        score++;
    if (/[0-9]/.test(pw))        score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    var levels = [
        {label:'Very weak',color:'danger'},
        {label:'Weak',color:'danger'},
        {label:'Fair',color:'warning'},
        {label:'Good',color:'info'},
        {label:'Strong',color:'success'},
        {label:'Very strong',color:'success'}
    ];
    var l = levels[Math.min(score, 5)];
    str.innerHTML = '<small class="text-' + l.color + '"><i class="fas fa-circle"></i> ' + l.label + '</small>';
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>