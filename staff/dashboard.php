<?php
// ===================
// Staff/dashboard.php
// ===================
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user's groups
$stmt = $pdo->prepare("
    SELECT g.* FROM `groups` g
    JOIN group_members gm ON g.group_id = gm.group_id
    WHERE gm.user_id = ?
");
$stmt->execute([$user_id]);
$groups = $stmt->fetchAll();

// Get total messages received
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total FROM messages
    WHERE (receiver_id = ? OR group_id IN (
        SELECT group_id FROM group_members WHERE user_id = ?
    ))
");
$stmt->execute([$user_id, $user_id]);
$msg_count = $stmt->fetch()['total'];

$page_title = 'Dashboard';
include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?></h1>
        <div class="page-subtitle">Your secure messaging overview</div>
    </div>
</div>

<!-- Stat cards -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon stat-icon--blue">
            <i data-lucide="layers"></i>
        </div>
        <div>
            <div class="stat-value"><?php echo count($groups); ?></div>
            <div class="stat-label">Active Groups</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon--indigo">
            <i data-lucide="message-square"></i>
        </div>
        <div>
            <div class="stat-value"><?php echo $msg_count; ?></div>
            <div class="stat-label">Total Messages</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon--green">
            <i data-lucide="shield-check"></i>
        </div>
        <div>
            <div class="stat-value">E2E</div>
            <div class="stat-label">Encrypted</div>
        </div>
    </div>
</div>

<!-- Groups card -->
<div class="card">
    <div class="card-header">
        <i data-lucide="layers"></i>
        Your Groups
    </div>
    <div class="card-body">
        <?php if (empty($groups)): ?>
            <p class="text-muted mb-0">You are not assigned to any groups yet.</p>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($groups as $group): ?>
                    <div class="col-md-4 col-sm-6">
                        <div class="group-card">
                            <div class="group-card-icon">
                                <i data-lucide="users"></i>
                            </div>
                            <div>
                                <div class="fw-semibold" style="font-size:14px;">
                                    <?php echo htmlspecialchars($group['group_name']); ?>
                                </div>
                                <?php if (!empty($group['description'])): ?>
                                <div class="text-muted" style="font-size:12px;margin-top:2px;">
                                    <?php echo htmlspecialchars($group['description']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <a href="chat.php?type=group&id=<?php echo $group['group_id']; ?>"
                               class="btn btn-sm btn-gradient mt-1">
                                Open Chat
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
