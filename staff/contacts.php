<?php
// ============================================================
// staff/contacts.php
// Staff directory — lists all active staff with their Signal
// key status so users can see who is ready for encrypted messaging.
// ============================================================
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch all other active staff — include key columns so UI can show key setup status
$stmt = $pdo->prepare("
    SELECT user_id, name, email, staff_id, department, ik_dh_public, ecdh_public_key
    FROM users
    WHERE role = 'staff' AND status = 'active' AND user_id != ?
    ORDER BY name
");
$stmt->execute([$user_id]);
$staff_list = $stmt->fetchAll();

$page_title = 'Contacts';
include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Staff Directory</h1>
        <div class="page-subtitle">All active staff members</div>
    </div>
    <div style="max-width:260px;width:100%;">
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="fas fa-search text-muted" style="font-size:13px;"></i></span>
            <input type="text" id="staff-search" class="form-control"
                   placeholder="Search name or department..." style="font-size:13px;">
        </div>
    </div>
</div>

<?php if (empty($staff_list)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-users fa-4x text-muted mb-3"></i>
            <h4>No Other Staff Found</h4>
            <p class="text-muted">There are no other active staff members registered yet.</p>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3" id="staff-grid">
        <?php foreach ($staff_list as $s): ?>
        <div class="col-md-4 col-sm-6 staff-card"
             data-name="<?php echo strtolower(htmlspecialchars($s['name'])); ?>"
             data-dept="<?php echo strtolower(htmlspecialchars($s['department'])); ?>">
            <div class="contact-card">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="contact-avatar">
                        <?php echo htmlspecialchars(mb_strtoupper(mb_substr($s['name'], 0, 1))); ?>
                    </div>
                    <div style="min-width:0;">
                        <div class="fw-semibold" style="font-size:14px;">
                            <?php echo htmlspecialchars($s['name']); ?>
                        </div>
                        <div class="text-muted" style="font-size:12px;">
                            <?php echo htmlspecialchars($s['staff_id']); ?>
                        </div>
                    </div>
                </div>

                <div class="text-muted mb-1" style="font-size:12.5px;">
                    <i class="fas fa-building me-1" style="font-size:11px;"></i>
                    <?php echo htmlspecialchars($s['department']); ?>
                </div>
                <div class="text-muted mb-3" style="font-size:12.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($s['email']); ?>">
                    <i class="fas fa-envelope me-1" style="font-size:11px;"></i>
                    <?php echo htmlspecialchars($s['email']); ?>
                </div>

                <div class="d-flex align-items-center justify-content-between">
                    <?php if (!empty($s['ik_dh_public'])): ?>
                        <span class="enc-badge enc-badge--signal">
                            <i data-lucide="shield-check" style="width:12px;height:12px;"></i>
                            Signal E2E
                        </span>
                    <?php elseif (!empty($s['ecdh_public_key'])): ?>
                        <span class="enc-badge enc-badge--ecdh">
                            <i data-lucide="lock" style="width:12px;height:12px;"></i>
                            Encrypted
                        </span>
                    <?php else: ?>
                        <span class="enc-badge enc-badge--none">
                            <i data-lucide="alert-triangle" style="width:12px;height:12px;"></i>
                            Not activated
                        </span>
                    <?php endif; ?>

                    <a href="chat.php?type=personal&id=<?php echo $s['user_id']; ?>"
                       class="btn btn-sm btn-gradient">
                        <i class="fas fa-comment me-1"></i>Message
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <p id="no-results" class="text-muted text-center py-4" style="display:none;">
        No staff match your search.
    </p>
<?php endif; ?>

<script>
document.getElementById('staff-search')?.addEventListener('input', function () {
    const q     = this.value.trim().toLowerCase();
    let visible = 0;
    document.querySelectorAll('.staff-card').forEach(card => {
        const match = !q || card.dataset.name.includes(q) || card.dataset.dept.includes(q);
        card.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    document.getElementById('no-results').style.display = visible ? 'none' : 'block';
});
</script>

<?php include '../includes/footer.php'; ?>
