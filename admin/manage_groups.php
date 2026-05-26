<?php
// ========================
// Admin/manage_groups.php
// ========================
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit;
}

// Handle group actions
if (isset($_GET['action'])) {
    $group_id = intval($_GET['id']);
    
    switch ($_GET['action']) {
        case 'delete':
            $stmt = $pdo->prepare("DELETE FROM `groups` WHERE group_id = ?");
            $stmt->execute([$group_id]);
            break;
    }
    
    header("Location: manage_groups.php");
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_group'])) {
        $group_name = $_POST['group_name'];
        $description = $_POST['description'];
        $members = isset($_POST['members']) ? $_POST['members'] : [];
        
        // Create group
        $stmt = $pdo->prepare("INSERT INTO `groups` (group_name, description, created_by) VALUES (?, ?, ?)");
        $stmt->execute([$group_name, $description, $_SESSION['user_id']]);
        $new_group_id = $pdo->lastInsertId();
        
        // Add selected members
        foreach ($members as $member_id) {
            $stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
            $stmt->execute([$new_group_id, $member_id]);
        }
        
        // Log activity
        $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, 'Create Group', ?)");
        $log_stmt->execute([$_SESSION['user_id'], "Created group: $group_name"]);
        
        header("Location: manage_groups.php");
        exit;
    }
    
    if (isset($_POST['add_member'])) {
        $group_id = intval($_POST['group_id']);
        $members  = isset($_POST['members']) ? $_POST['members'] : [];

        foreach ($members as $mid) {
            $mid = intval($mid);
            if (!$mid) continue;
            $ck = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?");
            $ck->execute([$group_id, $mid]);
            if (!$ck->fetch()) {
                $pdo->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)")
                    ->execute([$group_id, $mid]);
            }
        }

        header("Location: manage_groups.php?view=$group_id");
        exit;
    }
    
    if (isset($_POST['remove_member'])) {
        $group_id = intval($_POST['group_id']);
        $member_id = intval($_POST['member_id']);
        
        $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$group_id, $member_id]);
        
        header("Location: manage_groups.php?view=$group_id");
        exit;
    }
}

// Get all groups with member count
$stmt = $pdo->query("
    SELECT g.*, 
           u.name as creator_name,
           u.staff_id as creator_staff_id,
           u.role as creator_role,
           COUNT(gm.user_id) as member_count
    FROM `groups` g
    LEFT JOIN users u ON g.created_by = u.user_id
    LEFT JOIN group_members gm ON g.group_id = gm.group_id
    GROUP BY g.group_id
    ORDER BY g.group_name
");
$groups = $stmt->fetchAll();

// Get all active staff for member selection
$stmt = $pdo->query("SELECT * FROM users WHERE role = 'staff' AND status = 'active' ORDER BY name");
$all_staff = $stmt->fetchAll();

// Get specific group details if viewing
$view_group = null;
$group_members = [];
if (isset($_GET['view'])) {
    $group_id = intval($_GET['view']);
    
    // FIXED: Join with users to get creator info
    $stmt = $pdo->prepare("
        SELECT g.*,
               u.name as creator_name,
               u.staff_id as creator_staff_id,
               u.role as creator_role
        FROM `groups` g
        LEFT JOIN users u ON g.created_by = u.user_id
        WHERE g.group_id = ?
    ");
    $stmt->execute([$group_id]);
    $view_group = $stmt->fetch();
    
    if ($view_group) {
        $stmt = $pdo->prepare("
            SELECT u.* FROM group_members gm
            JOIN users u ON gm.user_id = u.user_id
            WHERE gm.group_id = ?
            ORDER BY u.name
        ");
        $stmt->execute([$group_id]);
        $group_members = $stmt->fetchAll();
    }
}

$page_title = 'Manage Groups';
include '../includes/header.php';
?>


<div class="page-header">
    <div>
        <h1>Manage Groups</h1>
        <div class="page-subtitle">Create and manage staff messaging groups</div>
    </div>
    <button type="button" class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#createGroupModal">
        <i class="fas fa-plus-circle me-1"></i> Create New Group
    </button>
</div>
            
            <?php if (isset($_GET['view']) && $view_group): ?>
                <!-- Group Details View -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span style="font-size:15px;font-weight:600;color:var(--text);">
                            <i class="fas fa-users" style="color:#1e40af;margin-right:6px;"></i>
                            <?php echo htmlspecialchars($view_group['group_name']); ?>
                            <span class="text-muted fw-normal" style="font-size:13px;"> — <?php echo htmlspecialchars($view_group['description']); ?></span>
                        </span>
                        <div class="d-flex gap-2">
                            <a href="manage_groups.php"
                               class="btn btn-sm"
                               style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;font-weight:500;">
                                <i class="fas fa-arrow-left me-1"></i>Back to All Groups
                            </a>
                            <a href="manage_groups.php?action=delete&id=<?php echo $view_group['group_id']; ?>"
                               class="btn btn-sm"
                               style="background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;border-radius:6px;font-size:12px;font-weight:500;"
                               onclick="event.preventDefault(); appConfirm('Delete Group','All messages in this group will be permanently lost.','danger','Delete',()=>location.href=this.href);">
                                <i class="fas fa-trash me-1"></i>Delete Group
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <p style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:10px;">
                                    <i class="fas fa-users" style="color:#475569;margin-right:5px;"></i>
                                    Group Members (<?php echo count($group_members); ?>)
                                </p>
                                <?php if (empty($group_members)): ?>
                                    <div style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;border-radius:8px;padding:12px 16px;font-size:13px;">
                                        <i class="fas fa-info-circle me-1"></i> No members in this group yet. Add members on the right.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Staff ID</th>
                                                    <th>Department</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($group_members as $member): ?>
                                                    <tr>
                                                        <td>
                                                            <i class="fas fa-user" style="color:#1e40af;margin-right:5px;"></i>
                                                            <?php echo htmlspecialchars($member['name']); ?>
                                                        </td>
                                                        <td style="font-size:13px;"><?php echo htmlspecialchars($member['email']); ?></td>
                                                        <td>
                                                            <span style="font-size:11px;font-weight:600;color:#475569;"><?php echo htmlspecialchars($member['staff_id']); ?></span>
                                                        </td>
                                                        <td style="font-size:13px;"><?php echo htmlspecialchars($member['department']); ?></td>
                                                        <td>
                                                            <form method="POST" style="display:inline;">
                                                                <input type="hidden" name="group_id" value="<?php echo $view_group['group_id']; ?>">
                                                                <input type="hidden" name="member_id" value="<?php echo $member['user_id']; ?>">
                                                                <button type="submit" name="remove_member"
                                                                        class="btn btn-sm"
                                                                        style="background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;border-radius:6px;font-size:11px;font-weight:500;"
                                                                        onclick="event.preventDefault(); var _f=this.closest('form'); appConfirm('Remove Member','Remove <?php echo htmlspecialchars($member['name'], ENT_QUOTES); ?> from this group?','danger','Remove',function(){_f.submit();});">
                                                                    <i class="fas fa-user-minus me-1"></i>Remove
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-4">
                                <!-- Add Member Card -->
                                <?php
                                $available_members = array_filter($all_staff, function($staff) use ($group_members) {
                                    foreach ($group_members as $member) {
                                        if ($staff['user_id'] == $member['user_id']) return false;
                                    }
                                    return true;
                                });
                                $add_depts = array_unique(array_column(array_values($available_members), 'department'));
                                sort($add_depts);
                                ?>
                                <div class="card mb-3">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <span><i data-lucide="user-plus" style="width:14px;height:14px;margin-right:5px;"></i> Add Member to Group</span>
                                        <span id="addSelectedCount" style="font-size:11px;font-weight:600;color:#1e40af;"></span>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($available_members)): ?>
                                            <p class="text-muted text-center mb-0" style="font-size:13px;">
                                                <i class="fas fa-check-circle" style="color:#166534;margin-right:4px;"></i>All staff already in this group.
                                            </p>
                                        <?php else: ?>
                                        <form method="POST" id="addMemberForm">
                                            <input type="hidden" name="group_id" value="<?php echo $view_group['group_id']; ?>">
                                            <div class="mb-2">
                                                <input type="text" id="addMemberSearch" class="form-control form-control-sm"
                                                       placeholder="Search by name, ID, or department..." style="font-size:12px;">
                                            </div>
                                            <div class="mb-2">
                                                <select id="addDeptFilter" class="form-select form-select-sm" style="font-size:12px;">
                                                    <option value="">All Departments</option>
                                                    <?php foreach ($add_depts as $dept): ?>
                                                        <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-2 d-flex gap-2">
                                                <button type="button" class="btn btn-sm flex-fill"
                                                        style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;font-size:12px;"
                                                        onclick="addSelectAllVisible()">All</button>
                                                <button type="button" class="btn btn-sm flex-fill"
                                                        style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;font-size:12px;"
                                                        onclick="addDeselectAll()">None</button>
                                            </div>
                                            <div id="addMemberList" style="max-height:220px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:6px;padding:8px;background:#f8fafc;">
                                                <?php foreach ($available_members as $staff): ?>
                                                    <div class="form-check mb-1 add-member-item"
                                                         data-name="<?php echo strtolower(htmlspecialchars($staff['name'])); ?>"
                                                         data-sid="<?php echo strtolower(htmlspecialchars($staff['staff_id'])); ?>"
                                                         data-dept="<?php echo htmlspecialchars($staff['department']); ?>">
                                                        <input class="form-check-input add-member-cb" type="checkbox"
                                                               name="members[]" value="<?php echo $staff['user_id']; ?>"
                                                               id="addm<?php echo $staff['user_id']; ?>">
                                                        <label class="form-check-label w-100" for="addm<?php echo $staff['user_id']; ?>" style="cursor:pointer;font-size:12px;">
                                                            <strong><?php echo htmlspecialchars($staff['name']); ?></strong>
                                                            <span class="text-muted ms-1" style="font-size:11px;"><?php echo htmlspecialchars($staff['staff_id']); ?></span><br>
                                                            <span class="text-muted" style="font-size:11px;"><?php echo htmlspecialchars($staff['department']); ?></span>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <button type="submit" name="add_member" class="btn btn-gradient w-100 mt-3" style="font-size:13px;">
                                                <i class="fas fa-user-plus me-1"></i>Add Selected to Group
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Group Information Card -->
                                <div class="card">
                                    <div class="card-header">
                                        <i data-lucide="info" style="width:14px;height:14px;margin-right:5px;"></i> Group Information
                                    </div>
                                    <div class="card-body" style="font-size:13px;">
                                        <div class="mb-3">
                                            <span style="font-size:12px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.4px;">Created By</span><br>
                                            <span style="font-size:13px;color:var(--text);">
                                                <?php
                                                if (!empty($view_group['creator_name'])) {
                                                    echo htmlspecialchars($view_group['creator_name']);
                                                    if (!empty($view_group['creator_staff_id'])) {
                                                        echo ' <span style="font-size:11px;color:#94a3b8;">(' . htmlspecialchars($view_group['creator_staff_id']) . ')</span>';
                                                    }
                                                } else {
                                                    echo '<span class="text-muted">Unknown Admin</span>';
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <div class="mb-3">
                                            <span style="font-size:12px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.4px;">Created On</span><br>
                                            <?php echo date('d/m/Y H:i', strtotime($view_group['created_at'])); ?>
                                        </div>
                                        <div class="mb-3">
                                            <span style="font-size:12px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.4px;">Total Members</span><br>
                                            <span style="display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;">
                                                <i class="fas fa-user me-1"></i><?php echo count($group_members); ?> members
                                            </span>
                                        </div>
                                        <div>
                                            <span style="font-size:12px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.4px;">Security</span><br>
                                            <span style="display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:#dcfce7;color:#166534;border:1px solid #bbf7d0;">
                                                <i class="fas fa-lock me-1"></i>End-to-End Encrypted
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- All Groups List -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> All Groups (<?php echo count($groups); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($groups)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-user-group fa-4x text-muted mb-3"></i>
                                <h4>No Groups Yet</h4>
                                <p class="text-muted">Create your first group to enable team communication.</p>
                                <button type="button" class="btn btn-gradient btn-lg" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                                    <i class="fas fa-plus-circle"></i> Create First Group
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Group Name</th>
                                            <th>Description</th>
                                            <th>Created By</th>
                                            <th>Members</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($groups as $group): ?>
                                            <tr>
                                                <td>
                                                    <strong><i class="fas fa-users" style="color:#1e40af;"></i> <?php echo htmlspecialchars($group['group_name']); ?></strong>
                                                    <br>
                                                    <span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;margin-top:3px;">
                                                        <i class="fas fa-lock me-1"></i>Secure Group
                                                    </span>
                                                </td>
                                                <td style="font-size:13px;"><?php echo htmlspecialchars($group['description']); ?></td>
                                                <td style="font-size:13px;">
                                                    <?php
                                                    if (!empty($group['creator_name'])) {
                                                        echo htmlspecialchars($group['creator_name']);
                                                        if (!empty($group['creator_staff_id'])) {
                                                            echo "<br><small style='color:#94a3b8;font-size:11px;'>" . htmlspecialchars($group['creator_staff_id']) . "</small>";
                                                        }
                                                    } else {
                                                        echo "<span class='text-muted'>Unknown</span>";
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <span style="display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;">
                                                        <i class="fas fa-user me-1"></i><?php echo $group['member_count']; ?> members
                                                    </span>
                                                </td>
                                                <td style="font-size:13px;"><?php echo date('d/m/Y', strtotime($group['created_at'])); ?></td>
                                                <td style="white-space:nowrap;">
                                                    <a href="manage_groups.php?view=<?php echo $group['group_id']; ?>"
                                                       class="btn btn-sm me-1"
                                                       style="background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;border-radius:6px;font-size:11px;font-weight:500;">
                                                        <i class="fas fa-eye me-1"></i>View
                                                    </a>
                                                    <a href="manage_groups.php?action=delete&id=<?php echo $group['group_id']; ?>"
                                                       class="btn btn-sm"
                                                       style="background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;border-radius:6px;font-size:11px;font-weight:500;"
                                                       onclick="event.preventDefault(); appConfirm('Delete Group','All messages in this group will be permanently lost.','danger','Delete',()=>location.href=this.href);">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
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
        </div>
    </div>
</div>

<!-- Create Group Modal -->
<div class="modal fade" id="createGroupModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header gradient-bg text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Create New Group</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-tag"></i> Group Name *</label>
                        <input type="text" name="group_name" class="form-control" 
                               placeholder="e.g., Finance Team" required>
                        <div class="form-text">Choose a descriptive name for your group</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-align-left"></i> Description</label>
                        <textarea name="description" class="form-control" rows="3" 
                                  placeholder="Brief description of the group's purpose"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-users"></i> Select Group Members (Optional)</label>

                        <?php if (!empty($all_staff)): ?>
                        <!-- Filter controls -->
                        <div class="row g-2 mb-2">
                            <div class="col-md-5">
                                <input type="text" id="memberSearch" class="form-control form-control-sm"
                                       placeholder="Search name, ID or department...">
                            </div>
                            <div class="col-md-4">
                                <select id="deptFilter" class="form-select form-select-sm">
                                    <option value="">All Departments</option>
                                    <?php
                                    $depts = array_unique(array_column($all_staff, 'department'));
                                    sort($depts);
                                    foreach ($depts as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept); ?>">
                                            <?php echo htmlspecialchars($dept); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-primary w-50"
                                        onclick="selectAllVisible()">All</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary w-50"
                                        onclick="deselectAllMembers()">None</button>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div id="memberList" style="max-height:250px;overflow-y:auto;border:1px solid #dee2e6;border-radius:5px;padding:12px;background:#f8f9fa;">
                            <?php if (empty($all_staff)): ?>
                                <p class="text-muted text-center">No staff members available</p>
                            <?php else: ?>
                                <?php foreach ($all_staff as $staff): ?>
                                    <div class="form-check mb-2 member-item"
                                         data-name="<?php echo strtolower(htmlspecialchars($staff['name'])); ?>"
                                         data-sid="<?php echo strtolower(htmlspecialchars($staff['staff_id'])); ?>"
                                         data-dept="<?php echo htmlspecialchars($staff['department']); ?>">
                                        <input class="form-check-input member-cb" type="checkbox"
                                               name="members[]"
                                               value="<?php echo $staff['user_id']; ?>"
                                               id="staff_<?php echo $staff['user_id']; ?>">
                                        <label class="form-check-label" for="staff_<?php echo $staff['user_id']; ?>">
                                            <strong><?php echo htmlspecialchars($staff['name']); ?></strong>
                                            <span class="text-muted ms-1 small"><?php echo htmlspecialchars($staff['staff_id']); ?></span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($staff['email']); ?> &bull;
                                                <span class="badge bg-light text-secondary border"><?php echo htmlspecialchars($staff['department']); ?></span>
                                            </small>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="form-text d-flex justify-content-between">
                            <span><i class="fas fa-info-circle"></i> You can add members later from the group details page.</span>
                            <span id="selectedCount" class="text-primary fw-semibold"></span>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" name="create_group" class="btn btn-gradient">
                            <i class="fas fa-check"></i> Create Group
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// ── Create-group modal filter ────────────────────────────────────
(function () {
    var searchInput = document.getElementById('memberSearch');
    var deptFilter  = document.getElementById('deptFilter');

    function filterMembers() {
        var search = searchInput ? searchInput.value.toLowerCase() : '';
        var dept   = deptFilter  ? deptFilter.value : '';
        document.querySelectorAll('.member-item').forEach(function (item) {
            var matchSearch = !search ||
                item.dataset.name.includes(search) ||
                item.dataset.sid.includes(search) ||
                item.dataset.dept.toLowerCase().includes(search);
            var matchDept = !dept || item.dataset.dept === dept;
            item.style.display = (matchSearch && matchDept) ? '' : 'none';
        });
        updateCount();
    }

    function updateCount() {
        var count = document.querySelectorAll('.member-item input[type=checkbox]:checked').length;
        var el = document.getElementById('selectedCount');
        if (el) el.textContent = count > 0 ? count + ' selected' : '';
    }

    window.selectAllVisible = function () {
        document.querySelectorAll('.member-item:not([style*="display: none"]) .member-cb')
            .forEach(function (cb) { cb.checked = true; });
        updateCount();
    };

    window.deselectAllMembers = function () {
        document.querySelectorAll('.member-cb')
            .forEach(function (cb) { cb.checked = false; });
        updateCount();
    };

    if (searchInput) searchInput.addEventListener('input', filterMembers);
    if (deptFilter)  deptFilter.addEventListener('change', filterMembers);
    document.querySelectorAll('.member-cb')
        .forEach(function (cb) { cb.addEventListener('change', updateCount); });
})();

// ── View-group "Add Member" filter ───────────────────────────────
(function () {
    var searchInput = document.getElementById('addMemberSearch');
    var deptFilter  = document.getElementById('addDeptFilter');

    function filterMembers() {
        var search = searchInput ? searchInput.value.toLowerCase() : '';
        var dept   = deptFilter  ? deptFilter.value : '';
        document.querySelectorAll('.add-member-item').forEach(function (item) {
            var matchSearch = !search ||
                item.dataset.name.includes(search) ||
                item.dataset.sid.includes(search) ||
                item.dataset.dept.toLowerCase().includes(search);
            var matchDept = !dept || item.dataset.dept === dept;
            item.style.display = (matchSearch && matchDept) ? '' : 'none';
        });
        updateCount();
    }

    function updateCount() {
        var count = document.querySelectorAll('.add-member-cb:checked').length;
        var el = document.getElementById('addSelectedCount');
        if (el) el.textContent = count > 0 ? count + ' selected' : '';
    }

    window.addSelectAllVisible = function () {
        document.querySelectorAll('.add-member-item:not([style*="display: none"]) .add-member-cb')
            .forEach(function (cb) { cb.checked = true; });
        updateCount();
    };

    window.addDeselectAll = function () {
        document.querySelectorAll('.add-member-cb')
            .forEach(function (cb) { cb.checked = false; });
        updateCount();
    };

    if (searchInput) searchInput.addEventListener('input', filterMembers);
    if (deptFilter)  deptFilter.addEventListener('change', filterMembers);
    document.querySelectorAll('.add-member-cb')
        .forEach(function (cb) { cb.addEventListener('change', updateCount); });
})();
</script>

<?php include '../includes/footer.php'; ?>