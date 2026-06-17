<?php
// ============================================================
// includes/header.php
// Shared header included on every page. Responsibilities:
//   1. Compute $base (relative path prefix for assets and links)
//   2. Enforce forced password-change redirects before HTML output
//   3. Pre-fetch unread notification counts for the topbar bell
//   4. Emit the HTML <head> (CSS, JS), the sidebar nav, and the topbar
//   5. Inject window.__APP_BASE, __API_BASE, __STAFF_USER_ID globals
//      needed by crypto.js / session.js / notifications.js
// ============================================================

// $base = '' for root pages, '../' for admin/ and staff/ subdirectories
$base = '';
$self = $_SERVER['PHP_SELF'];
if (strpos($self, '/admin/') !== false || strpos($self, '/staff/') !== false) {
    $base = '../';
}

// Force password change if required — redirect before any HTML output
if (
    isset($_SESSION['user_id']) &&
    isset($_SESSION['role']) &&
    $_SESSION['role'] === 'staff' &&
    !empty($_SESSION['password_change_required'])
) {
    $current_page = basename($_SERVER['PHP_SELF']);
    if ($current_page !== 'change_password_required.php') {
        header('Location: ' . $base . ($base === '' ? 'staff/' : '') . 'change_password_required.php');
        exit;
    }
}

if (
    isset($_SESSION['user_id']) &&
    isset($_SESSION['role']) &&
    $_SESSION['role'] === 'admin' &&
    !empty($_SESSION['password_change_required'])
) {
    $current_page = basename($_SERVER['PHP_SELF']);
    if ($current_page !== 'dashboard.php') {
        $in_subdir = ($base === '../');
        header('Location: ' . ($in_subdir ? 'dashboard.php' : 'admin/dashboard.php'));
        exit;
    }
}

$_cur_page = basename($_SERVER['PHP_SELF']);

// Fetch unread notification data for staff topbar bell (uses conversation_reads table)
$_notif_items = [];
$_notif_total = 0;
if (isset($_SESSION['user_id'], $_SESSION['role'], $pdo) && $_SESSION['role'] === 'staff') {
    try {
        $_uid = intval($_SESSION['user_id']);
        $_ns = $pdo->prepare("
            SELECT m.sender_id AS chat_id, 'personal' AS chat_type, u.name AS chat_name,
                   COUNT(*) AS unread_count
            FROM messages m
            JOIN users u ON m.sender_id = u.user_id
            LEFT JOIN conversation_reads cr
                   ON cr.user_id = ? AND cr.chat_type = 'personal' AND cr.chat_id = m.sender_id
            WHERE m.receiver_id = ?
              AND m.message_type IN ('personal','personal_file')
              AND (cr.last_read_at IS NULL OR m.timestamp > cr.last_read_at)
            GROUP BY m.sender_id, u.name
            UNION ALL
            SELECT m.group_id AS chat_id, 'group' AS chat_type, g.group_name AS chat_name,
                   COUNT(*) AS unread_count
            FROM messages m
            JOIN `groups` g ON m.group_id = g.group_id
            JOIN group_members gm ON gm.group_id = m.group_id AND gm.user_id = ?
            LEFT JOIN conversation_reads cr
                   ON cr.user_id = ? AND cr.chat_type = 'group' AND cr.chat_id = m.group_id
            WHERE m.sender_id != ?
              AND m.message_type IN ('group','group_file')
              AND (cr.last_read_at IS NULL OR m.timestamp > cr.last_read_at)
            GROUP BY m.group_id, g.group_name
            ORDER BY unread_count DESC
            LIMIT 15
        ");
        $_ns->execute([$_uid, $_uid, $_uid, $_uid, $_uid]);
        $_notif_items = $_ns->fetchAll(PDO::FETCH_ASSOC);
        foreach ($_notif_items as $_ni) $_notif_total += intval($_ni['unread_count']);
    } catch (Exception $_ne) { /* non-fatal */ }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'UTHM Messaging'; ?></title>
    <!-- Inter font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome (kept for existing inline icon usage) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Lucide Icons (UMD) -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <!-- Design system -->
    <link rel="stylesheet" href="<?php echo $base; ?>assets/css/theme.css">
    <!-- Crypto JS -->
    <script src="<?php echo $base; ?>assets/js/crypto.js"></script>
    <script src="<?php echo $base; ?>assets/js/sss.js"></script>
    <script src="<?php echo $base; ?>assets/js/signal.js"></script>
    <script>
        window.__APP_BASE = '<?= $basePath ?>';
        window.__API_BASE = '<?= $apiBase ?>';
    </script>
    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'staff'): ?>
    <script>window.__STAFF_USER_ID = <?php echo intval($_SESSION['user_id']); ?>;</script>
    <?php endif; ?>
    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin'): ?>
    <script>window.__ADMIN_USER_ID = <?php echo intval($_SESSION['user_id']); ?>;</script>
    <?php endif; ?>
    <script src="<?php echo $base; ?>assets/js/session.js"></script>
    <?php if (isset($_SESSION['user_id'])): ?>
    <script src="<?php echo $base; ?>assets/js/notifications.js?v=2"></script>
    <?php endif; ?>
</head>
<body>
<?php if (isset($_SESSION['user_id'])): ?>
<!-- ═══════════════════════════════════════════════════════════
     APP SHELL — sidebar + main wrapper
     ═══════════════════════════════════════════════════════════ -->
<div class="app-layout">

  <!-- ── Sidebar ─────────────────────────────────────────────── -->
  <aside class="sidebar" id="sidebar">

    <div class="sidebar-header">
      <a href="<?php echo $base; ?><?php echo $_SESSION['role'] === 'admin' ? 'admin' : 'staff'; ?>/dashboard.php" class="sidebar-logo">
        <div class="sidebar-logo-icon">U</div>
        UTHM Bursary
      </a>
      <button class="sidebar-close" id="sidebarClose" aria-label="Close sidebar">
        <i data-lucide="x" style="width:18px;height:18px;"></i>
      </button>
    </div>

    <nav class="sidebar-nav">
    <?php if ($_SESSION['role'] === 'staff'): ?>

      <a href="dashboard.php" class="nav-item <?php echo $_cur_page === 'dashboard.php' ? 'active' : ''; ?>">
        <i data-lucide="layout-dashboard"></i> Dashboard
      </a>
      <a href="chat.php" class="nav-item <?php echo $_cur_page === 'chat.php' ? 'active' : ''; ?>">
        <i data-lucide="message-square"></i> Chat
      </a>
      <a href="contacts.php" class="nav-item <?php echo $_cur_page === 'contacts.php' ? 'active' : ''; ?>">
        <i data-lucide="users"></i> Contacts
      </a>
      <a href="profile.php" class="nav-item <?php echo $_cur_page === 'profile.php' ? 'active' : ''; ?>">
        <i data-lucide="user"></i> Profile
      </a>
      <a href="logout.php" class="nav-item nav-logout">
        <i data-lucide="log-out"></i> Logout
      </a>

    <?php elseif ($_SESSION['role'] === 'admin'): ?>

      <a href="dashboard.php" class="nav-item <?php echo $_cur_page === 'dashboard.php' ? 'active' : ''; ?>">
        <i data-lucide="layout-dashboard"></i> Dashboard
      </a>
      <a href="manage_users.php" class="nav-item <?php echo $_cur_page === 'manage_users.php' ? 'active' : ''; ?>">
        <i data-lucide="users"></i> Manage Users
      </a>
      <a href="register_staff.php" class="nav-item <?php echo $_cur_page === 'register_staff.php' ? 'active' : ''; ?>">
        <i data-lucide="user-plus"></i> Register Staff
      </a>
      <?php if (!empty($_SESSION['is_head_admin'])): ?>
      <a href="register_admin.php" class="nav-item <?php echo $_cur_page === 'register_admin.php' ? 'active' : ''; ?>">
        <i data-lucide="shield"></i> Register Admin
      </a>
      <?php endif; ?>
      <a href="manage_groups.php" class="nav-item <?php echo $_cur_page === 'manage_groups.php' ? 'active' : ''; ?>">
        <i data-lucide="layers"></i> Manage Groups
      </a>
      <a href="recovery_requests.php" class="nav-item <?php echo $_cur_page === 'recovery_requests.php' ? 'active' : ''; ?>">
        <i data-lucide="key"></i> Recovery Requests
      </a>
      <a href="audit_logs.php" class="nav-item <?php echo $_cur_page === 'audit_logs.php' ? 'active' : ''; ?>">
        <i data-lucide="scroll-text"></i> Audit Logs
      </a>
      <a href="logout.php" class="nav-item nav-logout">
        <i data-lucide="log-out"></i> Logout
      </a>

    <?php endif; ?>
    </nav>

    <!-- User footer -->
    <div class="sidebar-user">
      <div class="sidebar-user-avatar">
        <?php echo mb_strtoupper(mb_substr($_SESSION['name'], 0, 1)); ?>
      </div>
      <div class="sidebar-user-info">
        <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['name']); ?></div>
        <div class="sidebar-user-role">
          <?php
            if ($_SESSION['role'] === 'admin') {
                echo !empty($_SESSION['is_head_admin']) ? 'Head Administrator' : 'Administrator';
            } else {
                echo 'Staff';
            }
          ?>
        </div>
      </div>
    </div>

  </aside><!-- /.sidebar -->

  <!-- Mobile overlay -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- ── Main Wrapper ────────────────────────────────────────── -->
  <div class="main-wrapper">

    <!-- Topbar -->
    <header class="topbar">
      <button class="topbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
        <i data-lucide="menu"></i>
      </button>
      <div class="topbar-spacer"></div>

      <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'staff'): ?>
      <!-- Notification bell -->
      <div class="notif-wrap" id="notifWrap">
        <button class="notif-btn" id="notifBtn" aria-label="Notifications">
          <i data-lucide="bell"></i>
          <span class="notif-bell-badge" id="notifBellBadge"
                <?php echo $_notif_total > 0 ? '' : 'style="display:none;"'; ?>>
            <?php echo min($_notif_total, 99); ?>
          </span>
        </button>

        <!-- Dropdown panel -->
        <div class="notif-panel" id="notifPanel">
          <div class="notif-panel-header">
            <span class="notif-panel-title">Notifications</span>
          </div>
          <div class="notif-list" id="notifList">
            <?php if (empty($_notif_items)): ?>
              <div class="notif-empty">
                <i data-lucide="check-circle" style="width:32px;height:32px;opacity:.3;"></i>
                <span>You're all caught up!</span>
              </div>
            <?php else: ?>
              <?php foreach ($_notif_items as $_ni): ?>
              <a href="<?= $basePath ?>/staff/chat.php?type=<?php echo htmlspecialchars($_ni['chat_type']); ?>&id=<?php echo intval($_ni['chat_id']); ?>"
                 class="notif-item">
                <div class="notif-avatar <?php echo $_ni['chat_type'] === 'group' ? 'notif-avatar--group' : ''; ?>">
                  <?php echo mb_strtoupper(mb_substr($_ni['chat_name'], 0, 1)); ?>
                </div>
                <div class="notif-content">
                  <div class="notif-title"><?php echo htmlspecialchars($_ni['chat_name']); ?></div>
                  <div class="notif-sub">
                    <?php
                      $uc = intval($_ni['unread_count']);
                      echo $uc . ' unread message' . ($uc !== 1 ? 's' : '');
                    ?>
                  </div>
                </div>
                <span class="notif-count-badge"><?php echo intval($_ni['unread_count']); ?></span>
              </a>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </header>

    <!-- Main content area -->
    <main class="main-content <?php echo !empty($main_no_padding) ? 'main-content--no-pad' : ''; ?>">
<?php endif; /* isset($_SESSION['user_id']) */ ?>
