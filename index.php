<?php
// ======================
// Index.php (Login Page)
// ======================
require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email    = $_POST['email'];
    $password = $_POST['password'];

    // Check user exists regardless of status first
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Password correct — now check account status
        if ($user['status'] === 'inactive') {
            $error = 'Your account has been deactivated. Please contact your administrator.';
        } else {
            // Active account — proceed with login
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['name']      = $user['name'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['password_change_required'] = ($user['password_change_required'] == 1);
            $_SESSION['is_head_admin'] = !empty($user['is_head_admin']);

            // Generate new session token — invalidates all other devices
            $session_token = bin2hex(random_bytes(32));
            $_SESSION['session_token'] = $session_token;

            // Save token to DB — overwrites any previous device's token
            $pdo->prepare("
                UPDATE users SET session_token = ? WHERE user_id = ?
            ")->execute([$session_token, $user['user_id']]);

            $log_stmt = $pdo->prepare("
                INSERT INTO audit_logs (user_id, action, details, ip_address)
                VALUES (?, 'Login', ?, ?)
            ");
            $log_stmt->execute([
                $user['user_id'],
                'New session started — previous device sessions invalidated',
                $_SERVER['REMOTE_ADDR']
            ]);

            if ($user['role'] == 'admin') {
                header('Location: admin/dashboard.php');
                exit;
    }
}
    } else {
        $error = 'Invalid email or password.';
    }
}

$page_title = 'Login - UTHM Messaging';
include 'includes/header.php';
?>

<?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'staff'): ?>
<!-- ── Staff just logged in — show loading screen while crypto runs ── -->
<div id="crypto-loading" style="
    position:fixed; top:0; left:0; width:100%; height:100%;
    background:#fff; display:flex; flex-direction:column;
    align-items:center; justify-content:center; z-index:9999;">
    <div class="spinner-border text-primary mb-3" role="status"></div>
    <p id="crypto-status" class="text-muted">Initialising secure session...</p>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const userId = <?php echo intval($_SESSION['user_id']); ?>;
    const status = document.getElementById('crypto-status');

    const password = sessionStorage.getItem('_tmp_pw');

    if (!password) {
        console.warn('[Crypto] No password in sessionStorage — redirecting (modal will appear)');
        window.location.href = 'staff/dashboard.php';
        return;
    }

    try {
        status.textContent = 'Fetching encryption keys...';
        const keyResponse  = await fetch('/uthm-system/api/get_user_keys.php');
        const keyData      = await keyResponse.json();

        if (!keyData.success) {
            console.error('[Crypto] Key fetch failed:', keyData.error);
            window.location.href = 'staff/dashboard.php';
            return;
        }

        status.textContent = 'Unlocking private key...';
        const privateKey   = await UTHMCrypto.unlockPrivateKey(
            keyData.encrypted_private_key,
            keyData.key_iv,
            keyData.key_auth_tag,
            password
        );

        UTHMCrypto.setSessionKey(userId, privateKey);
        console.log('[Crypto] Private key unlocked ✅');

        status.textContent  = 'Checking device registration...';
        const hasShare      = await UTHMCrypto.deviceHasShare(userId);

        if (!hasShare) {
            status.textContent = 'Registering this device...';
            const shareRes  = await fetch('/uthm-system/api/get_device_share.php');
            const shareData = await shareRes.json();

            if (shareData.success) {
                // Decrypt share 1 using our ECDH private key + the ephemeral public key
                // used by the admin's browser at registration time (ECIES).
                const plain = await UTHMCrypto.decryptMessage(
                    shareData.share_encrypted,
                    shareData.share_iv,
                    shareData.share_auth_tag,
                    privateKey,
                    shareData.eph_pub
                );
                await UTHMCrypto.saveShareToDevice(userId, { shareIndex: 1, shareData: plain }, password);
                console.log('[Crypto] Share 1 saved to this device ✅');
            } else {
                // 'not_found' is expected on re-login once the share is already on-device
                // or for recovery accounts (no share 1 stored after save_recovered_keys.php).
                console.log('[Crypto] No server-side device share available:', shareData.reason || shareData.error);
            }
        }

        status.textContent = 'Done! Redirecting...';
        window.location.href = 'staff/dashboard.php';

    } catch (err) {
        console.error('[Crypto] Error during login crypto:', err.message);
        window.location.href = 'staff/dashboard.php';
    }
});
</script>

<?php else: ?>
<!-- ── Normal login form — shown when not yet logged in ── -->
<div class="container-fluid p-0">
    <div class="row g-0" style="min-height:100vh;">

        <!-- Left hero panel -->
        <div class="col-md-6 gradient-bg login-hero">
            <div class="login-hero-inner">
                <span class="login-hero-icon"><i class="fas fa-lock"></i></span>
                <h1 class="login-hero-title">UTHM Secure<br>Messaging</h1>
                <p class="login-hero-sub">Internal Corporate Communication System</p>
                <p class="login-hero-tag">Bursary Office &bull; End-to-End Encrypted</p>
            </div>
        </div>

        <!-- Right login card -->
        <div class="col-md-6 login-card-panel">
            <div class="login-card">

                <!-- Mobile-only brand header (replaces hero on small screens) -->
                <div class="login-mobile-brand">
                    <div class="login-mobile-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="login-mobile-brand-name">UTHM Secure<br>Messaging</div>
                    <div class="login-mobile-brand-sub">Bursary Office &bull; End-to-End Encrypted</div>
                </div>

                <div class="login-card-title">Login</div>

                <div class="login-form-box">

                <?php if ($error): ?>
                    <div class="alert alert-<?php echo strpos($error, 'deactivated') !== false ? 'warning' : 'danger'; ?> mb-4">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="loginForm">
                    <div class="mb-3">
                        <label class="form-label" style="font-size:13px;font-weight:500;color:#475569;">Email Address</label>
                        <input type="email" name="email" class="form-control"
                               placeholder="yourname@uthm.edu.my" required
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    <div class="mb-4">
                        <label class="form-label" style="font-size:13px;font-weight:500;color:#475569;">Password</label>
                        <div class="login-pw-wrap">
                            <input type="password" name="password" id="login_password"
                                   class="form-control" placeholder="Password" required>
                            <button type="button" class="login-pw-toggle" id="pwToggle" tabindex="-1" aria-label="Show password">
                                <i class="fas fa-eye" id="pwToggleIcon"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-gradient w-100 mb-4" style="padding:11px;font-size:15px;font-weight:600;border-radius:8px;">
                        Login
                    </button>
                    <div class="text-center" style="font-size:13px;">
                        <a href="recover.php" class="text-decoration-none" style="color:var(--accent);">Recovery Request</a>
                        <span class="text-muted mx-2">&bull;</span>
                        <a href="admin_forgot_password.php" class="text-decoration-none" style="color:var(--accent);">Admin Forgot Password</a>
                    </div>
                </form>

                </div><!-- /.login-form-box -->
            </div>
        </div>

    </div>
</div>

<script>
document.getElementById('loginForm').addEventListener('submit', function () {
    const pw = document.getElementById('login_password').value;
    sessionStorage.setItem('_tmp_pw', pw);
});

document.getElementById('pwToggle').addEventListener('click', function () {
    var inp  = document.getElementById('login_password');
    var icon = document.getElementById('pwToggleIcon');
    var show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
});
</script>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>
