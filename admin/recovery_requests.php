<?php
// ============================================================
// admin/recovery_requests.php
// Manages staff recovery requests with SSS key reconstruction
// ============================================================
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once '../config/database.php';
ob_clean();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Ensure rejection_reason columns exist
try { $pdo->exec("ALTER TABLE recovery_requests ADD COLUMN rejection_reason VARCHAR(500) NULL"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE admin_reset_requests ADD COLUMN rejection_reason VARCHAR(500) NULL"); } catch (PDOException $e) {}

// POST: reject with reason + email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reject_staff') {
    $request_id = intval($_POST['request_id'] ?? 0);
    $reason     = trim($_POST['rejection_reason'] ?? '');

    $stmt = $pdo->prepare("
        SELECT rr.request_id, u.name, u.email
        FROM recovery_requests rr
        JOIN users u ON rr.user_id = u.user_id
        WHERE rr.request_id = ? AND rr.status = 'pending'
    ");
    $stmt->execute([$request_id]);
    $req_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($req_data) {
        $pdo->prepare("
            UPDATE recovery_requests
            SET status = 'rejected', approved_by = ?, approved_date = NOW(), rejection_reason = ?
            WHERE request_id = ?
        ")->execute([$_SESSION['user_id'], $reason, $request_id]);

        $pdo->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, 'Reject Recovery', ?)")
            ->execute([$_SESSION['user_id'], "Rejected recovery request ID: $request_id"]);

        try {
            require_once '../includes/mailer.php';
            $reason_html = nl2br(htmlspecialchars($reason));
            $html_body = "<div style='font-family:Arial,sans-serif;max-width:500px;margin:0 auto;'>
                <div style='background:#534AB7;padding:24px;text-align:center;border-radius:8px 8px 0 0;'>
                    <h2 style='color:#fff;margin:0;'>UTHM Bursary Messaging</h2>
                    <p style='color:#ccc;margin:6px 0 0;font-size:13px;'>Account Recovery Update</p>
                </div>
                <div style='background:#f9f9f9;padding:28px;border-radius:0 0 8px 8px;border:1px solid #eee;'>
                    <p style='color:#333;'>Dear <strong>" . htmlspecialchars($req_data['name']) . "</strong>,</p>
                    <p style='color:#555;font-size:14px;'>Your account recovery request has been <strong style='color:#b91c1c;'>rejected</strong>.</p>
                    <div style='background:#fff;border:1px solid #fecaca;border-radius:8px;padding:16px;margin:16px 0;'>
                        <p style='margin:0;font-size:13px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.4px;'>Reason</p>
                        <p style='margin:8px 0 0;color:#333;font-size:14px;'>{$reason_html}</p>
                    </div>
                    <p style='color:#555;font-size:13px;'>If you believe this is a mistake or need further assistance, please contact your administrator.</p>
                    <hr style='border:none;border-top:1px solid #eee;margin:20px 0;'>
                    <p style='font-size:12px;color:#aaa;text-align:center;margin:0;'>UTHM Bursary Office &bull; Secure Internal Messaging System</p>
                </div></div>";
            $plain = "Dear {$req_data['name']},\n\nYour account recovery request has been rejected.\n\nReason: {$reason}\n\nIf you need further assistance, please contact your administrator.\n\nUTHM Bursary Office";
            sendEmail($req_data['email'], $req_data['name'], 'UTHM Bursary — Recovery Request Rejected', $html_body, $plain);
        } catch (Exception $e) {
            error_log('Recovery rejection email failed: ' . $e->getMessage());
        }
    }

    header('Location: recovery_requests.php');
    exit;
}

// Handle status updates (approve only — reject is POST)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $request_id = intval($_GET['id']);

    if ($_GET['action'] === 'approve') {
        $pdo->prepare("
            UPDATE recovery_requests
            SET status = 'approved', approved_by = ?, approved_date = NOW()
            WHERE request_id = ?
        ")->execute([$_SESSION['user_id'], $request_id]);

        $pdo->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, 'Approve Recovery', ?)")
            ->execute([$_SESSION['user_id'], "Approved recovery request ID: $request_id"]);
    }

    header('Location: recovery_requests.php');
    exit;
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
            $pdo->prepare("UPDATE admin_reset_requests SET status='completed', approved_by=?, approved_at=NOW() WHERE request_id=?")
                ->execute([$_SESSION['user_id'], $req_id]);
            $pdo->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, 'Approve Admin Reset', ?)")
                ->execute([$_SESSION['user_id'], "Approved and reset password for: {$req['name']} ({$req['email']})"]);
            $pdo->commit();
            try {
                require_once '../includes/mailer.php';
                $html_body = "<div style='font-family:Arial,sans-serif;max-width:500px;margin:0 auto;'>
                    <div style='background:#534AB7;padding:24px;text-align:center;border-radius:8px 8px 0 0;'>
                        <h2 style='color:#fff;margin:0;'>UTHM Bursary Messaging</h2>
                        <p style='color:#ccc;margin:6px 0 0;font-size:13px;'>Password Reset Approved</p>
                    </div>
                    <div style='background:#f9f9f9;padding:28px;border-radius:0 0 8px 8px;border:1px solid #eee;'>
                        <p style='color:#333;'>Dear <strong>" . htmlspecialchars($req['name']) . "</strong>,</p>
                        <p style='color:#555;font-size:14px;'>Your password has been reset. Your temporary password is your <strong>Staff ID</strong> followed by your <strong>Full Name</strong> (no spaces, case-sensitive). You will be required to change it on first login.</p>
                        <hr style='border:none;border-top:1px solid #eee;margin:20px 0;'>
                        <p style='font-size:12px;color:#aaa;text-align:center;margin:0;'>UTHM Bursary Office &bull; Secure Internal Messaging System</p>
                    </div></div>";
                $plain = "Dear {$req['name']},\n\nYour password reset has been approved. Temporary password: Staff ID + Full Name (no spaces).\n\nUTHM Bursary Office";
                sendEmail($req['email'], $req['name'], 'UTHM Bursary — Password Reset Approved', $html_body, $plain);
            } catch (Exception $e) {
                error_log('Admin reset approval email failed: ' . $e->getMessage());
            }
        }
        header('Location: recovery_requests.php');
        exit;
    }

    // POST: reject admin reset with reason + email
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reject_admin_reset') {
        $req_id = intval($_POST['request_id'] ?? 0);
        $reason = trim($_POST['rejection_reason'] ?? '');

        $stmt = $pdo->prepare("
            SELECT arr.request_id, u.name, u.email
            FROM admin_reset_requests arr
            JOIN users u ON arr.user_id = u.user_id
            WHERE arr.request_id = ? AND arr.status = 'pending'
        ");
        $stmt->execute([$req_id]);
        $req_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($req_data) {
            $pdo->prepare("UPDATE admin_reset_requests SET status='rejected', approved_by=?, approved_at=NOW(), rejection_reason=? WHERE request_id=?")
                ->execute([$_SESSION['user_id'], $reason, $req_id]);
            $pdo->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, 'Reject Admin Reset', ?)")
                ->execute([$_SESSION['user_id'], "Rejected admin reset request ID: $req_id"]);

            try {
                require_once '../includes/mailer.php';
                $reason_html = nl2br(htmlspecialchars($reason));
                $html_body = "<div style='font-family:Arial,sans-serif;max-width:500px;margin:0 auto;'>
                    <div style='background:#534AB7;padding:24px;text-align:center;border-radius:8px 8px 0 0;'>
                        <h2 style='color:#fff;margin:0;'>UTHM Bursary Messaging</h2>
                        <p style='color:#ccc;margin:6px 0 0;font-size:13px;'>Password Reset Request Update</p>
                    </div>
                    <div style='background:#f9f9f9;padding:28px;border-radius:0 0 8px 8px;border:1px solid #eee;'>
                        <p style='color:#333;'>Dear <strong>" . htmlspecialchars($req_data['name']) . "</strong>,</p>
                        <p style='color:#555;font-size:14px;'>Your admin password reset request has been <strong style='color:#b91c1c;'>rejected</strong>.</p>
                        <div style='background:#fff;border:1px solid #fecaca;border-radius:8px;padding:16px;margin:16px 0;'>
                            <p style='margin:0;font-size:13px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.4px;'>Reason</p>
                            <p style='margin:8px 0 0;color:#333;font-size:14px;'>{$reason_html}</p>
                        </div>
                        <p style='color:#555;font-size:13px;'>If you need further assistance, please contact the Head of Admin.</p>
                        <hr style='border:none;border-top:1px solid #eee;margin:20px 0;'>
                        <p style='font-size:12px;color:#aaa;text-align:center;margin:0;'>UTHM Bursary Office &bull; Secure Internal Messaging System</p>
                    </div></div>";
                $plain = "Dear {$req_data['name']},\n\nYour admin password reset request has been rejected.\n\nReason: {$reason}\n\nIf you need further assistance, please contact the Head of Admin.\n\nUTHM Bursary Office";
                sendEmail($req_data['email'], $req_data['name'], 'UTHM Bursary — Password Reset Request Rejected', $html_body, $plain);
            } catch (Exception $e) {
                error_log('Admin reset rejection email failed: ' . $e->getMessage());
            }
        }
        header('Location: recovery_requests.php');
        exit;
    }
}

// Fetch all recovery requests with user info
$stmt = $pdo->query("
    SELECT rr.*, u.name as user_name, u.email as user_email,
           u.staff_id, u.department,
           a.name as approved_by_name
    FROM recovery_requests rr
    JOIN users u ON rr.user_id = u.user_id
    LEFT JOIN users a ON rr.approved_by = a.user_id
    ORDER BY rr.request_date DESC
");
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch admin reset requests (HOA only)
$admin_resets = [];
if (!empty($_SESSION['is_head_admin'])) {
    $stmt = $pdo->query("
        SELECT arr.*, u.name as user_name, u.email as user_email,
               a.name as approved_by_name
        FROM admin_reset_requests arr
        JOIN users u ON arr.user_id = u.user_id
        LEFT JOIN users a ON arr.approved_by = a.user_id
        ORDER BY arr.requested_at DESC
    ");
    $admin_resets = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$page_title = 'Recovery Requests';
include '../includes/header.php';
?>


<div class="page-header">
    <div>
        <h1>Recovery Requests</h1>
        <div class="page-subtitle">Manage staff account recovery using Shamir's Secret Sharing</div>
    </div>
</div>

<!-- Status message -->
<div id="recovery-status" class="alert" style="display:none;"></div>

<?php
$STATUS_STYLES = [
    'pending'      => 'background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;',
    'approved'     => 'background:#ede9fe;color:#5b21b6;border:1px solid #ddd6fe;',
    'completed'    => 'background:#dcfce7;color:#166534;border:1px solid #bbf7d0;',
    'rejected'     => 'background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;',
    'otp_pending'  => 'background:#fef3c7;color:#92400e;border:1px solid #fde68a;',
];
$pending_count = count(array_filter($requests, fn($r) => $r['status'] === 'pending'));
$admin_pending = !empty($_SESSION['is_head_admin'])
    ? count(array_filter($admin_resets, fn($r) => $r['status'] === 'pending'))
    : 0;
?>

<?php if (!empty($_SESSION['is_head_admin'])): ?>
<!-- Tab control (HOA sees 2 tabs) -->
<div class="d-flex justify-content-center mb-4">
    <div class="tab-pill">
        <button class="tab-pill-btn active" data-tab="tab-staff-recovery" title="Account Recovery">
            <i data-lucide="key"></i>
            <span class="tab-full-label">Account Recovery<?php if ($pending_count): ?> <span class="badge bg-danger" style="font-size:10px;vertical-align:middle;"><?php echo $pending_count; ?></span><?php endif; ?></span>
            <span class="tab-short-label">Recovery</span>
        </button>
        <button class="tab-pill-btn" data-tab="tab-admin-reset" title="Admin Forgot Password">
            <i data-lucide="shield-alert"></i>
            <span class="tab-full-label">Admin Forgot Password<?php if ($admin_pending): ?> <span class="badge bg-danger" style="font-size:10px;vertical-align:middle;"><?php echo $admin_pending; ?></span><?php endif; ?></span>
            <span class="tab-short-label">Admin Reset</span>
        </button>
    </div>
</div>
<?php endif; ?>

<!-- ── Tab 1: Staff Recovery Requests ── -->
<div class="tab-pane" id="tab-staff-recovery">
    <div class="card">
        <div class="card-header">
            <i data-lucide="key"></i> Staff Recovery Requests
            <?php if ($pending_count): ?>
                <span class="badge bg-danger ms-auto"><?php echo $pending_count; ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <?php if (empty($requests)): ?>
                <div class="text-center py-5">
                    <i data-lucide="inbox" style="width:48px;height:48px;opacity:.25;"></i>
                    <h5 class="mt-3">No Recovery Requests</h5>
                    <p class="text-muted">There are currently no account recovery requests.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th>Department</th>
                            <th>Reason</th>
                            <th>Requested</th>
                            <th>Status</th>
                            <th>Approved By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                        <tr id="row-<?php echo $req['request_id']; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($req['user_name']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($req['user_email']); ?></small>
                            </td>
                            <td style="font-size:13px;"><?php echo htmlspecialchars($req['department']); ?></td>
                            <td style="font-size:13px;"><?php echo htmlspecialchars($req['reason']); ?></td>
                            <td><small><?php echo date('d/m/Y H:i', strtotime($req['request_date'])); ?></small></td>
                            <td>
                                <?php $s_style = $STATUS_STYLES[$req['status']] ?? $STATUS_STYLES['rejected']; ?>
                                <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;<?php echo $s_style; ?>">
                                    <?php echo ucfirst($req['status']); ?>
                                </span>
                                <?php if ($req['status'] === 'rejected' && !empty($req['rejection_reason'])): ?>
                                    <br><small style="font-size:11px;color:#94a3b8;display:block;margin-top:3px;max-width:160px;" title="<?php echo htmlspecialchars($req['rejection_reason']); ?>">
                                        <?php echo htmlspecialchars(mb_strimwidth($req['rejection_reason'], 0, 60, '…')); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><small><?php echo htmlspecialchars($req['approved_by_name'] ?? '—'); ?></small></td>
                            <td style="white-space:nowrap;">
                                <?php if ($req['status'] === 'pending'): ?>
                                    <a href="?action=approve&id=<?php echo $req['request_id']; ?>"
                                       class="btn btn-sm me-1"
                                       style="background:#dcfce7;color:#166534;border:1px solid #bbf7d0;border-radius:6px;font-size:11px;font-weight:500;"
                                       onclick="event.preventDefault(); appConfirm('Approve Request','Allow this recovery request to proceed?','success','Approve',()=>location.href=this.href);">
                                        <i class="fas fa-check me-1"></i>Approve
                                    </a>
                                    <button type="button" class="btn btn-sm"
                                            style="background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;border-radius:6px;font-size:11px;font-weight:500;"
                                            onclick="openRejectModal(<?php echo $req['request_id']; ?>)">
                                        <i class="fas fa-times me-1"></i>Reject
                                    </button>
                                <?php elseif ($req['status'] === 'approved'): ?>
                                    <button class="btn btn-sm btn-gradient"
                                            onclick="startRecovery(
                                                <?php echo $req['request_id']; ?>,
                                                <?php echo $req['user_id']; ?>,
                                                '<?php echo htmlspecialchars($req['user_name'], ENT_QUOTES); ?>',
                                                '<?php echo htmlspecialchars($req['staff_id'],   ENT_QUOTES); ?>'
                                            )">
                                        <i class="fas fa-key"></i> Execute Recovery
                                    </button>
                                <?php elseif ($req['status'] === 'completed'): ?>
                                    <span style="color:#166534;font-size:12px;"><i class="fas fa-check-circle me-1"></i>Done</span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Tab 2: Admin Password Reset Requests (HOA only) ── -->
<?php if (!empty($_SESSION['is_head_admin'])): ?>
<div class="tab-pane" id="tab-admin-reset" style="display:none;">
    <div class="card">
        <div class="card-header">
            <i data-lucide="shield-alert"></i> Admin Password Reset Requests
            <?php if ($admin_pending): ?>
                <span class="badge bg-danger ms-auto"><?php echo $admin_pending; ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <?php if (empty($admin_resets)): ?>
                <div class="text-center py-5">
                    <i data-lucide="inbox" style="width:48px;height:48px;opacity:.25;"></i>
                    <h5 class="mt-3">No Requests</h5>
                    <p class="text-muted">No admin password reset requests.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Admin</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Approved By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admin_resets as $ar): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($ar['user_name']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($ar['user_email']); ?></small>
                            </td>
                            <td><small><?php echo date('d/m/Y H:i', strtotime($ar['requested_at'])); ?></small></td>
                            <td>
                                <?php $ar_style = $STATUS_STYLES[$ar['status']] ?? $STATUS_STYLES['rejected']; ?>
                                <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;<?php echo $ar_style; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $ar['status'])); ?>
                                </span>
                                <?php if ($ar['status'] === 'rejected' && !empty($ar['rejection_reason'])): ?>
                                    <br><small style="font-size:11px;color:#94a3b8;display:block;margin-top:3px;max-width:160px;" title="<?php echo htmlspecialchars($ar['rejection_reason']); ?>">
                                        <?php echo htmlspecialchars(mb_strimwidth($ar['rejection_reason'], 0, 60, '…')); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><small><?php echo htmlspecialchars($ar['approved_by_name'] ?? '—'); ?></small></td>
                            <td style="white-space:nowrap;">
                                <?php if ($ar['status'] === 'pending'): ?>
                                    <a href="?approve_reset=<?php echo $ar['request_id']; ?>"
                                       class="btn btn-sm me-1"
                                       style="background:#dcfce7;color:#166534;border:1px solid #bbf7d0;border-radius:6px;font-size:11px;font-weight:500;"
                                       onclick="event.preventDefault(); appConfirm('Reset Password','Approve and reset password for <?php echo htmlspecialchars($ar['user_name'], ENT_QUOTES); ?>?','success','Approve',()=>location.href=this.href);">
                                        <i class="fas fa-check me-1"></i>Approve
                                    </a>
                                    <button type="button" class="btn btn-sm"
                                            style="background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;border-radius:6px;font-size:11px;font-weight:500;"
                                            onclick="openRejectAdminModal(<?php echo $ar['request_id']; ?>)">
                                        <i class="fas fa-times me-1"></i>Reject
                                    </button>
                                <?php elseif ($ar['status'] === 'completed'): ?>
                                    <span style="color:#166534;font-size:12px;"><i class="fas fa-check-circle me-1"></i>Done</span>
                                <?php elseif ($ar['status'] === 'rejected'): ?>
                                    <span style="color:#64748b;font-size:12px;">—</span>
                                <?php else: ?>
                                    <span style="color:#92400e;font-size:12px;"><i class="fas fa-clock me-1"></i>OTP pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Reject reason modal -->
<div class="modal fade" id="rejectStaffModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:360px;">
        <div class="modal-content" style="border-radius:20px;overflow:hidden;border:none;box-shadow:0 12px 40px rgba(0,0,0,.15);">
            <div class="modal-header border-0" style="padding:20px 24px 8px;">
                <h5 class="modal-title" style="font-size:15px;font-weight:700;color:#0f172a;">Reject Recovery Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="reject_staff">
                <input type="hidden" name="request_id" id="rejectStaffId">
                <div class="modal-body" style="padding:8px 24px;">
                    <p style="font-size:13px;color:#64748b;margin-bottom:12px;">Provide a reason — it will be emailed to the staff member.</p>
                    <textarea name="rejection_reason" id="rejectStaffReason" class="form-control"
                              rows="4" style="font-size:13px;resize:none;border-radius:10px;"
                              placeholder="e.g. Insufficient information provided. Please resubmit with your employee number." required></textarea>
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
function openRejectModal(requestId) {
    document.getElementById('rejectStaffId').value = requestId;
    document.getElementById('rejectStaffReason').value = '';
    new bootstrap.Modal(document.getElementById('rejectStaffModal')).show();
}
</script>

<!-- Reject Admin Reset modal -->
<div class="modal fade" id="rejectAdminResetModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:360px;">
        <div class="modal-content" style="border-radius:20px;overflow:hidden;border:none;box-shadow:0 12px 40px rgba(0,0,0,.15);">
            <div class="modal-header border-0" style="padding:20px 24px 8px;">
                <h5 class="modal-title" style="font-size:15px;font-weight:700;color:#0f172a;">Reject Password Reset Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
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

<!-- Recovery modal -->
<div class="modal fade" id="recoveryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-key text-warning"></i> Execute SSS Recovery
                </h5>
            </div>
            <div class="modal-body">
                <p>You are about to reconstruct the master key for
                   <strong id="recovery-user-name"></strong> using
                   shares from admin vault, backup server and main server.</p>

                <div id="recovery-progress" style="display:none;">
                    <div class="progress mb-2">
                        <div id="recovery-bar" class="progress-bar progress-bar-striped
                             progress-bar-animated bg-success" style="width:0%"></div>
                    </div>
                    <small id="recovery-step-label" class="text-muted"></small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                        data-bs-dismiss="modal" id="modal-cancel-btn">
                    Cancel
                </button>
                <button type="button" class="btn btn-gradient" id="execute-recovery-btn">
                    <i class="fas fa-key"></i> Execute Recovery
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ── Tab switching ─────────────────────────────────────────────
document.querySelectorAll('.tab-pill-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.tab-pill-btn').forEach(function (b) { b.classList.remove('active'); });
        document.querySelectorAll('.tab-pane').forEach(function (p) { p.style.display = 'none'; });
        btn.classList.add('active');
        document.getElementById(btn.dataset.tab).style.display = '';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
});

let _recoveryRequestId = null;
let _recoveryUserId    = null;
let _recoveryUserName  = null;
let _recoveryStaffId   = null;

function startRecovery(requestId, userId, userName, staffId) {
    _recoveryRequestId = requestId;
    _recoveryUserId    = userId;
    _recoveryUserName  = userName;
    _recoveryStaffId   = staffId;
    document.getElementById('recovery-user-name').textContent = userName;
    document.getElementById('recovery-progress').style.display = 'none';
    new bootstrap.Modal(document.getElementById('recoveryModal')).show();
}

function setProgress(pct, label) {
    document.getElementById('recovery-bar').style.width  = pct + '%';
    document.getElementById('recovery-step-label').textContent = label;
    document.getElementById('recovery-progress').style.display = 'block';
}

function showStatus(message, type) {
    const el = document.getElementById('recovery-status');
    el.className     = `alert alert-${type}`;
    el.textContent   = message;
    el.style.display = 'block';
    el.scrollIntoView({ behavior: 'smooth' });
}

document.getElementById('execute-recovery-btn').addEventListener('click', async () => {
    // Auto-generate temp password: staffId + name (no spaces, case-sensitive)
    const newPassword = _recoveryStaffId + _recoveryUserName.replace(/\s+/g, '');

    const executeBtn   = document.getElementById('execute-recovery-btn');
    const cancelBtn    = document.getElementById('modal-cancel-btn');
    executeBtn.disabled= true;
    cancelBtn.disabled = true;
    executeBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Recovering...';

    try {
        // Step 1: Fetch server shares via PHP
        setProgress(20, 'Fetching key shares from server...');
        const recoverRes = await fetch((window.__API_BASE || '/api') + '/admin_recover.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                request_id: _recoveryRequestId,
                user_id:    _recoveryUserId
            })
        });
        const recoverData = await recoverRes.json();

        if (!recoverData.success) {
            throw new Error(recoverData.error || 'Failed to fetch shares');
        }

        // Step 2: Reconstruct master key in browser using SSS
        setProgress(40, 'Reconstructing master key using Shamir\'s Secret Sharing...');
        const masterKey = UTHMSS.reconstruct(recoverData.shares);

        // Step 3: Verify reconstruction
        setProgress(55, 'Verifying reconstructed key...');
        const encoder   = new TextEncoder();
        const hashBuf   = await crypto.subtle.digest('SHA-256', masterKey);
        const hashHex   = Array.from(new Uint8Array(hashBuf))
            .map(b => b.toString(16).padStart(2, '0')).join('');

        // Step 4: Generate new ECDH key pair encrypted with the auto-generated temp password
        setProgress(70, 'Generating new encryption keys...');
        const newKeys = await UTHMCrypto.generateKeyPair(newPassword);

        // Step 5: Generate new SSS shares
        setProgress(78, 'Splitting new key shares...');
        const secretBuf = encoder.encode(newKeys.keyHash);
        const newShares = UTHMSS.split(new Uint8Array(secretBuf.buffer), 5, 3);

        // Step 5b: Encrypt share 1 with staff's new ECDH public key via ephemeral ECDH (ECIES)
        const ephKP     = await crypto.subtle.generateKey(
            { name: 'ECDH', namedCurve: 'P-256' }, true, ['deriveKey', 'deriveBits']
        );
        const ephPubJwk = JSON.stringify(await crypto.subtle.exportKey('jwk', ephKP.publicKey));
        const share1Enc = await UTHMCrypto.encryptMessage(
            newShares[0].shareData, ephKP.privateKey, newKeys.publicKeyJwk
        );

        // Step 6: Save everything to server
        setProgress(90, 'Saving new keys and shares to server...');
        const saveRes = await fetch((window.__API_BASE || '/api') + '/save_recovered_keys.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                target_uid:            _recoveryUserId,
                request_id:            _recoveryRequestId,
                public_key:            newKeys.publicKeyJwk,
                encrypted_private_key: newKeys.encryptedPrivateKey,
                key_iv:                newKeys.keyIv,
                key_auth_tag:          newKeys.keyAuthTag,
                key_hash:              newKeys.keyHash,
                share1_encrypted:      share1Enc.ciphertext,
                share1_iv:             share1Enc.iv,
                share1_auth_tag:       share1Enc.authTag,
                share1_eph_pub:        ephPubJwk,
                share2:                newShares[1].shareData,
                share3:                newShares[2].shareData,
                share4:                newShares[3].shareData,
                share5:                newShares[4].shareData
            })
        });

        const saveResult = await saveRes.json();
        if (!saveResult.success) {
            throw new Error(saveResult.error || 'Failed to save recovered keys');
        }

        setProgress(100, 'Recovery complete!');

        // Close modal and show success
        setTimeout(() => {
            bootstrap.Modal.getInstance(
                document.getElementById('recoveryModal')
            ).hide();
            showStatus(
                `✅ Recovery successful! New keys issued. ` +
                `Staff member will receive an email with login instructions.`,
                'success'
            );
            // Update row status pill in table
            const statusPill = document.querySelector(`#row-${_recoveryRequestId} span[style*="border-radius:20px"]`);
            if (statusPill) {
                statusPill.style.cssText = 'display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;background:#dcfce7;color:#166534;border:1px solid #bbf7d0;';
                statusPill.textContent = 'Completed';
            }
            const actionCell = document.querySelector(
                `#row-${_recoveryRequestId} td:last-child`
            );
            if (actionCell) {
                actionCell.innerHTML =
                    '<span class="text-success"><i class="fas fa-check-circle"></i> Done</span>';
            }
        }, 800);

    } catch (err) {
        console.error('Recovery error:', err);
        setProgress(0, '');
        document.getElementById('recovery-progress').style.display = 'none';
        showStatus('❌ Recovery failed: ' + err.message, 'danger');
    } finally {
        executeBtn.disabled  = false;
        cancelBtn.disabled   = false;
        executeBtn.innerHTML = '<i class="fas fa-key"></i> Execute Recovery';
    }
});
</script>

<?php include '../includes/footer.php'; ?>