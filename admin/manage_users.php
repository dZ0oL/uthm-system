<?php
// ======================
// Admin/manage_users.php
// ======================
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit;
}

// POST: edit user info (name, staff_id, department)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_user') {
    $target_id  = intval($_POST['user_id'] ?? 0);
    $name       = trim($_POST['name'] ?? '');
    $staff_id   = trim($_POST['staff_id'] ?? '');
    $department = trim($_POST['department'] ?? '');

    if ($name && $staff_id && $target_id) {
        // Normal admin can only edit staff — HOA can edit anyone
        $tgt_role_stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
        $tgt_role_stmt->execute([$target_id]);
        $tgt_role = $tgt_role_stmt->fetchColumn();
        if ($tgt_role === 'admin' && empty($_SESSION['is_head_admin'])) {
            header('Location: manage_users.php');
            exit;
        }

        // Check staff_id uniqueness (excluding this user)
        $dup = $pdo->prepare("SELECT user_id FROM users WHERE staff_id = ? AND user_id != ?");
        $dup->execute([$staff_id, $target_id]);
        if ($dup->fetch()) {
            header('Location: manage_users.php?edit_error=dup_id');
            exit;
        }

        $old = $pdo->prepare("SELECT name, staff_id, department FROM users WHERE user_id = ?");
        $old->execute([$target_id]);
        $old_data = $old->fetch(PDO::FETCH_ASSOC);

        $pdo->prepare("UPDATE users SET name = ?, staff_id = ?, department = ? WHERE user_id = ?")
            ->execute([$name, $staff_id, $department, $target_id]);

        $pdo->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, 'Edit User', ?)")
            ->execute([$_SESSION['user_id'],
                "Edited user_id:{$target_id} — name: '{$old_data['name']}'→'{$name}', staff_id: '{$old_data['staff_id']}'→'{$staff_id}', dept: '{$old_data['department']}'→'{$department}'"]);
    }
    header('Location: manage_users.php');
    exit;
}

// Handle activate/deactivate only — no delete
if (isset($_GET['action'])) {
    $user_id_action = intval($_GET['id']);

    // Prevent admin from deactivating themselves
    if ($user_id_action === intval($_SESSION['user_id'])) {
        header("Location: manage_users.php");
        exit;
    }

    // Only HOA can activate/deactivate other admin accounts
    if (in_array($_GET['action'], ['activate', 'deactivate'])) {
        $tgt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
        $tgt->execute([$user_id_action]);
        $tgt_row = $tgt->fetch(PDO::FETCH_ASSOC);
        if ($tgt_row && $tgt_row['role'] === 'admin' && empty($_SESSION['is_head_admin'])) {
            header("Location: manage_users.php");
            exit;
        }
    }

    switch ($_GET['action']) {
        case 'activate':
            $pdo->prepare("UPDATE users SET status = 'active' WHERE user_id = ?")
                ->execute([$user_id_action]);
            $pdo->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, 'Activate User', ?)")
                ->execute([$_SESSION['user_id'], "Activated user_id: $user_id_action"]);
            break;

        case 'deactivate':
            $pdo->prepare("UPDATE users SET status = 'inactive' WHERE user_id = ?")
                ->execute([$user_id_action]);
            $pdo->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, 'Deactivate User', ?)")
                ->execute([$_SESSION['user_id'], "Deactivated user_id: $user_id_action"]);
            break;
    }

    header("Location: manage_users.php");
    exit;
}

// Get all users with their groups and message count — HOA first, then admins by ID, then staff by ID
$stmt = $pdo->query("
    SELECT u.*,
           GROUP_CONCAT(DISTINCT g.group_name ORDER BY g.group_name SEPARATOR ', ') as user_groups,
           (SELECT COUNT(*) FROM messages WHERE sender_id = u.user_id) as message_count
    FROM users u
    LEFT JOIN group_members gm ON u.user_id = gm.user_id
    LEFT JOIN `groups` g ON gm.group_id = g.group_id
    GROUP BY u.user_id
    ORDER BY COALESCE(u.is_head_admin,0) DESC, FIELD(u.role,'admin','staff'), u.staff_id ASC
");
$users = $stmt->fetchAll();

$page_title = 'Manage Users';
include '../includes/header.php';
?>


<div class="page-header">
    <div>
        <h1>Manage Users</h1>
        <div class="page-subtitle">All system accounts</div>
    </div>
    <a href="register_staff.php" class="btn btn-gradient">
        <i class="fas fa-user-plus me-1"></i> Register New Staff
    </a>
</div>

<!-- User stats -->
<div class="stat-grid" style="margin-bottom:20px;">
    <div class="stat-card">
        <div class="stat-icon stat-icon--blue"><i data-lucide="users"></i></div>
        <div>
            <div class="stat-value"><?php echo count($users); ?></div>
            <div class="stat-label">Total Users</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon--green"><i data-lucide="user-check"></i></div>
        <div>
            <div class="stat-value"><?php echo count(array_filter($users, fn($u) => $u['status'] == 'active')); ?></div>
            <div class="stat-label">Active</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon--indigo"><i data-lucide="user"></i></div>
        <div>
            <div class="stat-value"><?php echo count(array_filter($users, fn($u) => $u['role'] == 'staff')); ?></div>
            <div class="stat-label">Staff</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon--amber"><i data-lucide="shield"></i></div>
        <div>
            <div class="stat-value"><?php echo count(array_filter($users, fn($u) => $u['role'] == 'admin')); ?></div>
            <div class="stat-label">Admin</div>
        </div>
    </div>
</div>

<!-- Search bar — full width, matching the card below -->
<div class="input-group mb-3">
    <span class="input-group-text bg-white" style="border-right:0;">
        <i class="fas fa-search text-muted" style="font-size:13px;"></i>
    </span>
    <input type="text" id="userSearch" class="form-control"
           placeholder="Search by ID, name, email, department, role or group..."
           style="font-size:13px;border-left:0;">
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header">
        <i data-lucide="users"></i> All Users
    </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="userTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Department</th>
                                    <th>Groups</th>
                                    <th>Messages</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr data-id="<?php echo strtolower(htmlspecialchars($user['staff_id'])); ?>"
                                        data-name="<?php echo strtolower(htmlspecialchars($user['name'])); ?>"
                                        data-email="<?php echo strtolower(htmlspecialchars($user['email'])); ?>"
                                        data-dept="<?php echo strtolower(htmlspecialchars($user['department'] ?? '')); ?>"
                                        data-role="<?php echo strtolower(htmlspecialchars($user['role'])); ?>"
                                        data-groups="<?php echo strtolower(htmlspecialchars($user['user_groups'] ?? '')); ?>">
                                        <td><?php echo htmlspecialchars($user['staff_id']); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                            <?php if ($user['user_id'] == $_SESSION['user_id']): ?>
                                                <span class="badge bg-primary">You</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <?php if ($user['role'] === 'admin'): ?>
                                                <span style="display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;">Admin</span>
                                            <?php else: ?>
                                                <span style="display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;">Staff</span>
                                            <?php endif; ?>
                                            <?php if (!empty($user['is_head_admin'])): ?>
                                                <span style="display:inline-block;padding:2px 7px;border-radius:20px;font-size:11px;font-weight:600;background:#fef3c7;color:#92400e;border:1px solid #fde68a;margin-left:3px;" title="Head of Admin"><i class="fas fa-crown"></i></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['department'] ?? '—'); ?></td>
                                        <td>
                                            <small>
                                                <?php echo $user['user_groups']
                                                    ? htmlspecialchars($user['user_groups'])
                                                    : 'No groups'; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo $user['message_count']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($user['status'] === 'active'): ?>
                                                <span style="display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:#dcfce7;color:#166534;border:1px solid #bbf7d0;">Active</span>
                                            <?php else: ?>
                                                <span style="display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1 align-items-center flex-wrap">
                                            <!-- Edit button: HOA can edit anyone; normal admin can only edit staff -->
                                            <?php if ($user['role'] === 'staff' || !empty($_SESSION['is_head_admin'])): ?>
                                            <button type="button" class="btn btn-sm"
                                                    style="background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;border-radius:6px;font-size:12px;font-weight:500;"
                                                    title="Edit info"
                                                    onclick="openEditModal(<?php echo $user['user_id']; ?>,'<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>','<?php echo htmlspecialchars($user['staff_id'], ENT_QUOTES); ?>','<?php echo htmlspecialchars($user['department'] ?? '', ENT_QUOTES); ?>')">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if ($user['user_id'] == $_SESSION['user_id']): ?>
                                                <button class="btn btn-sm" disabled
                                                    style="background:#f1f5f9;color:#94a3b8;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;font-weight:500;cursor:not-allowed;"
                                                    title="Cannot change your own status">
                                                    <i class="fas fa-user-shield"></i>
                                                </button>
                                            <?php elseif ($user['role'] === 'admin' && empty($_SESSION['is_head_admin'])): ?>
                                                <span style="font-size:12px;color:#94a3b8;font-weight:500;">
                                                    <i class="fas fa-crown" style="color:#f59e0b;"></i> HOA Only
                                                </span>
                                            <?php elseif ($user['status'] == 'active'): ?>
                                                <a href="manage_users.php?action=deactivate&id=<?php echo $user['user_id']; ?>"
                                                   onclick="event.preventDefault(); appConfirm('Deactivate Account','<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?> will no longer be able to log in.','danger','Deactivate',()=>location.href=this.href);"
                                                   class="btn btn-sm"
                                                   style="background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;border-radius:6px;font-size:12px;font-weight:500;"
                                                   title="Deactivate">
                                                    <i class="fas fa-user-slash me-1"></i>Deactivate
                                                </a>
                                            <?php else: ?>
                                                <a href="manage_users.php?action=activate&id=<?php echo $user['user_id']; ?>"
                                                   onclick="event.preventDefault(); appConfirm('Activate Account','Restore login access for <?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>?','success','Activate',()=>location.href=this.href);"
                                                   class="btn btn-sm"
                                                   style="background:#dcfce7;color:#166534;border:1px solid #bbf7d0;border-radius:6px;font-size:12px;font-weight:500;"
                                                   title="Activate">
                                                    <i class="fas fa-user-check me-1"></i>Activate
                                                </a>
                                            <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr id="noUserResults" style="display:none;">
                                    <td colspan="9" class="text-center text-muted py-4">
                                        <i class="fas fa-search me-1"></i> No users match your search.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Legend -->
            <div class="mt-3 px-1 py-2 d-flex flex-wrap gap-3 align-items-center" style="font-size:12px;color:#475569;">
                <strong style="color:#334155;">Legend:</strong>
                <span style="display:inline-flex;align-items:center;gap:5px;">
                    <span style="display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:#dcfce7;color:#166534;border:1px solid #bbf7d0;">Active</span>
                    — User can login
                </span>
                <span style="display:inline-flex;align-items:center;gap:5px;">
                    <span style="display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;">Inactive</span>
                    — User cannot login
                </span>
                <span style="display:inline-flex;align-items:center;gap:5px;">
                    <span style="display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;">Staff</span>
                    — Regular user
                </span>
                <span style="display:inline-flex;align-items:center;gap:5px;">
                    <span style="display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;">Admin</span>
                    — Administrator
                </span>
                <span style="display:inline-flex;align-items:center;gap:5px;">
                    <span style="display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:#fef3c7;color:#92400e;border:1px solid #fde68a;"><i class="fas fa-crown"></i></span>
                    — Head of Admin
                </span>
                <span style="display:inline-flex;align-items:center;gap:5px;">
                    <i class="fas fa-crown" style="color:#f59e0b;"></i> HOA Only — Action restricted to Head Admin
                </span>
            </div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:380px;">
        <div class="modal-content" style="border-radius:20px;overflow:hidden;border:none;box-shadow:0 12px 40px rgba(0,0,0,.15);">
            <div class="modal-header border-0" style="padding:20px 24px 8px;">
                <h5 class="modal-title" style="font-size:15px;font-weight:700;color:#0f172a;">Edit User Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="modal-body" style="padding:8px 24px;">
                    <?php if (isset($_GET['edit_error']) && $_GET['edit_error'] === 'dup_id'): ?>
                        <div style="background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:12px;">
                            <i class="fas fa-exclamation-circle me-1"></i> That Staff ID is already in use by another user.
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label style="font-size:12px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.4px;">Full Name *</label>
                        <input type="text" name="name" id="editUserName" class="form-control mt-1"
                               style="font-size:13px;border-radius:8px;" required>
                    </div>
                    <div class="mb-3">
                        <label style="font-size:12px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.4px;">Staff ID *</label>
                        <input type="text" name="staff_id" id="editUserStaffId" class="form-control mt-1"
                               style="font-size:13px;border-radius:8px;" required>
                    </div>
                    <div class="mb-1">
                        <label style="font-size:12px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.4px;">Department</label>
                        <input type="text" name="department" id="editUserDept" class="form-control mt-1"
                               style="font-size:13px;border-radius:8px;" placeholder="e.g. Finance">
                    </div>
                </div>
                <div class="modal-footer border-0" style="padding:12px 24px 20px;justify-content:flex-end;gap:4px;">
                    <button type="button" data-bs-dismiss="modal"
                            style="background:none;border:none;padding:9px 16px;font-size:13px;font-weight:500;color:#94a3b8;cursor:pointer;border-radius:10px;"
                            onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'">Cancel</button>
                    <button type="submit"
                            style="background:none;border:none;padding:9px 16px;font-size:13px;font-weight:600;color:#1e40af;cursor:pointer;border-radius:10px;"
                            onmouseover="this.style.background='#dbeafe'" onmouseout="this.style.background='none'">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function openEditModal(userId, name, staffId, dept) {
    document.getElementById('editUserId').value   = userId;
    document.getElementById('editUserName').value = name;
    document.getElementById('editUserStaffId').value = staffId;
    document.getElementById('editUserDept').value = dept;
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}
</script>

<script>
document.getElementById('userSearch').addEventListener('input', function () {
    var q = this.value.trim().toLowerCase();
    var visible = 0;
    document.querySelectorAll('#userTable tbody tr:not(#noUserResults)').forEach(function (row) {
        var match = !q ||
            (row.dataset.id     || '').includes(q) ||
            (row.dataset.name   || '').includes(q) ||
            (row.dataset.email  || '').includes(q) ||
            (row.dataset.dept   || '').includes(q) ||
            (row.dataset.role   || '').includes(q) ||
            (row.dataset.groups || '').includes(q);
        row.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    document.getElementById('noUserResults').style.display = visible ? 'none' : '';
});
</script>

<?php include '../includes/footer.php'; ?>
