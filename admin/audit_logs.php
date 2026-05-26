<?php
// =====================
// Admin/audit_logs.php
// =====================
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit;
}

// Handle log filters
$user_filter = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$action_filter = isset($_GET['action']) ? $_GET['action'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query with filters
$query = "
    SELECT a.*, u.name as user_name, u.role as user_role 
    FROM audit_logs a 
    JOIN users u ON a.user_id = u.user_id 
    WHERE 1=1
";
$params = [];

if ($user_filter > 0) {
    $query .= " AND a.user_id = ?";
    $params[] = $user_filter;
}

if (!empty($action_filter)) {
    $query .= " AND a.action LIKE ?";
    $params[] = "%$action_filter%";
}

if (!empty($date_from)) {
    $query .= " AND DATE(a.timestamp) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(a.timestamp) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY a.timestamp DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get total count for statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM audit_logs");
$total_logs = $stmt->fetch()['total'];

// Get unique actions for filter dropdown
$stmt = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action");
$actions = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get all users for filter dropdown
$stmt = $pdo->query("SELECT user_id, name, role FROM users ORDER BY name");
$all_users = $stmt->fetchAll();

// Handle export to CSV
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Log ID', 'User', 'Role', 'Action', 'Details', 'IP Address', 'Timestamp']);
    
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['log_id'],
            $log['user_name'],
            $log['user_role'],
            $log['action'],
            $log['details'] ?? '',
            $log['ip_address'] ?? '',
            $log['timestamp']
        ]);
    }
    
    fclose($output);
    exit;
}

$page_title = 'Audit Logs';
include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Audit Logs</h1>
        <div class="page-subtitle">All system activity records</div>
    </div>
    <div>
        <a href="audit_logs.php?export=csv" class="btn btn-success btn-sm">
            <i class="fas fa-file-export me-1"></i> Export CSV
        </a>
    </div>
</div>
            
<?php
$today        = date('Y-m-d');
$stmt_today   = $pdo->prepare("SELECT COUNT(*) as count FROM audit_logs WHERE DATE(timestamp) = ?");
$stmt_today->execute([$today]);
$today_count  = $stmt_today->fetch()['count'];
$stmt_users   = $pdo->query("SELECT COUNT(DISTINCT user_id) as count FROM audit_logs");
$users_count  = $stmt_users->fetch()['count'];
$stmt_acts    = $pdo->query("SELECT COUNT(DISTINCT action) as count FROM audit_logs");
$acts_count   = $stmt_acts->fetch()['count'];
?>

<!-- Stat grid -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon stat-icon--blue"><i data-lucide="scroll-text"></i></div>
        <div>
            <div class="stat-value"><?php echo number_format($total_logs); ?></div>
            <div class="stat-label">Total Log Entries</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon--green"><i data-lucide="calendar-check"></i></div>
        <div>
            <div class="stat-value"><?php echo number_format($today_count); ?></div>
            <div class="stat-label">Today's Activities</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon--indigo"><i data-lucide="users"></i></div>
        <div>
            <div class="stat-value"><?php echo number_format($users_count); ?></div>
            <div class="stat-label">Users Logged</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon--amber"><i data-lucide="zap"></i></div>
        <div>
            <div class="stat-value"><?php echo number_format($acts_count); ?></div>
            <div class="stat-label">Unique Actions</div>
        </div>
    </div>
</div>
            
            <!-- Filter Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <i data-lucide="filter"></i> Filter Logs
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label" style="font-size:13px;font-weight:500;color:#475569;">User</label>
                            <select name="user_id" class="form-select" style="font-size:13px;">
                                <option value="">All Users</option>
                                <?php foreach ($all_users as $user): ?>
                                    <option value="<?php echo $user['user_id']; ?>"
                                            <?php echo ($user_filter == $user['user_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['name']); ?>
                                        (<?php echo $user['role']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" style="font-size:13px;font-weight:500;color:#475569;">Action</label>
                            <select name="action" class="form-select" style="font-size:13px;">
                                <option value="">All Actions</option>
                                <?php foreach ($actions as $action): ?>
                                    <option value="<?php echo $action; ?>"
                                            <?php echo ($action_filter == $action) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($action); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" style="font-size:13px;font-weight:500;color:#475569;">Date From</label>
                            <input type="date" name="date_from" class="form-control" style="font-size:13px;" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" style="font-size:13px;font-weight:500;color:#475569;">Date To</label>
                            <input type="date" name="date_to" class="form-control" style="font-size:13px;" value="<?php echo $date_to; ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-gradient btn-sm" style="font-size:13px;font-weight:500;">
                                <i class="fas fa-filter me-1"></i>Filter
                            </button>
                            <a href="audit_logs.php" class="btn btn-sm" style="font-size:13px;font-weight:500;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;">
                                <i class="fas fa-redo me-1"></i>Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Logs Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i data-lucide="list"></i> System Activities (<?php echo count($logs); ?> records)</span>
                    <small class="text-muted">Showing filtered results</small>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($logs)): ?>
                        <div class="text-center py-5">
                            <i data-lucide="inbox" style="width:48px;height:48px;opacity:.25;"></i>
                            <h5 class="mt-3">No Audit Logs Found</h5>
                            <p class="text-muted">No activities match your filter criteria.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height:520px;overflow-y:auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Timestamp</th>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Action</th>
                                        <th>Details</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td><?php echo $log['log_id']; ?></td>
                                            <td>
                                                <small>
                                                    <?php echo date('d/m/Y', strtotime($log['timestamp'])); ?><br>
                                                    <?php echo date('h:i:s A', strtotime($log['timestamp'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($log['user_name']); ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($log['user_role'] === 'admin'): ?>
                                                    <span style="display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;">Admin</span>
                                                <?php else: ?>
                                                    <span style="display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;">Staff</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                    $action_icons = [
                                                        'Login' => 'fa-sign-in-alt',
                                                        'Logout' => 'fa-sign-out-alt',
                                                        'Send Message' => 'fa-paper-plane',
                                                        'Add Contact' => 'fa-user-plus',
                                                        'Update Profile' => 'fa-user-edit',
                                                        'Change Password' => 'fa-key',
                                                        'Register Staff' => 'fa-user-plus',
                                                        'Create Group' => 'fa-users',
                                                        'Approve Recovery' => 'fa-check-circle',
                                                        'Reject Recovery' => 'fa-times-circle',
                                                        'Complete Recovery' => 'fa-check-double'
                                                    ];
                                                    $icon = $action_icons[$log['action']] ?? 'fa-history';
                                                ?>
                                                <span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;">
                                                    <i class="fas <?php echo $icon; ?> me-1"></i><?php echo htmlspecialchars($log['action']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($log['details']): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($log['details']); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?php echo $log['ip_address'] ?: 'N/A'; ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            

<?php include '../includes/footer.php'; ?>