<?php
// ======================
// Index.php (Login Page)
// ======================
require_once 'config/database.php';

$error      = '';
$error_type = 'danger';

// Lock account after 5 wrong passwords for 5 minutes
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 5);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Reject forged form submissions (CSRF protection)
    csrf_verify();

    $email    = $_POST['email'];
    $password = $_POST['password'];

    // Look up the account by email — use prepared statement to prevent SQL injection
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // Return a generic error — don't reveal whether the email exists
        $error = 'Invalid email or password.';
    } else {
        $now        = new DateTime();
        // Check if the account is still within its lockout window
        $is_locked  = !empty($user['locked_until']) && new DateTime($user['locked_until']) > $now;

        if ($is_locked) {
            // Show how many minutes remain in the lockout
            $diff              = $now->diff(new DateTime($user['locked_until']));
            $minutes_remaining = $diff->h * 60 + $diff->i + ($diff->s > 0 ? 1 : 0);
            $error      = "Account temporarily locked due to too many failed login attempts. Try again in {$minutes_remaining} minute(s), or contact your administrator.";
            $error_type = 'warning';

        } elseif (password_verify($password, $user['password'])) {
            // password_verify() compares the plain password against the bcrypt hash in the DB
            if ($user['status'] === 'inactive') {
                $error      = 'Your account has been deactivated. Please contact your administrator.';
                $error_type = 'warning';
            } else {
                // Successful login — clear lockout state
                $pdo->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE user_id = ?")
                    ->execute([$user['user_id']]);

                // Write the user's identity into the session
                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['name']      = $user['name'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['password_change_required'] = $user['password_change_required'] == 1;
                $_SESSION['is_head_admin'] = !empty($user['is_head_admin']);
                // Recovered users have ecdh_public_key set (new key from recovery) + password_change_required.
                // New staff have ecdh_public_key = NULL at first login.
                $_SESSION['account_just_recovered'] = $user['password_change_required'] == 1 && !empty($user['ecdh_public_key']);

                // Single-device enforcement: generate a fresh session token and write it to the DB.
                // Any other device whose stored token no longer matches will be kicked out by check_session.php.
                $session_token = bin2hex(random_bytes(32));
                $_SESSION['session_token'] = $session_token;

                // Overwrite the token on the server — invalidates all other active sessions
                $pdo->prepare("UPDATE users SET session_token = ? WHERE user_id = ?")
                    ->execute([$session_token, $user['user_id']]);

                // Audit trail — logs the login event with IP address
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
                // Staff fall through to the crypto loading screen below
            }
        } else {
            // Wrong password — count against the lockout threshold.
            // If the previous lock already expired, reset the counter to 1.
            $lock_expired    = !empty($user['locked_until']) && new DateTime($user['locked_until']) <= $now;
            $base_attempts   = $lock_expired ? 0 : $user['failed_login_attempts'];
            $new_attempts    = $base_attempts + 1;

            if ($new_attempts >= LOGIN_MAX_ATTEMPTS) {
                // Threshold reached — lock the account for LOGIN_LOCKOUT_MINUTES minutes
                $pdo->prepare("UPDATE users SET failed_login_attempts = ?, locked_until = DATE_ADD(NOW(), INTERVAL " . LOGIN_LOCKOUT_MINUTES . " MINUTE) WHERE user_id = ?")
                    ->execute([$new_attempts, $user['user_id']]);
                $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, 'Account Locked', ?, ?)")
                    ->execute([$user['user_id'], "Account locked after {$new_attempts} consecutive failed login attempts", $_SERVER['REMOTE_ADDR'] ?? null]);
                $error      = "Too many failed login attempts. Your account has been locked for " . LOGIN_LOCKOUT_MINUTES . " minutes.";
                $error_type = 'danger';
            } else {
                // Not locked yet — increment the counter and show remaining attempts
                $pdo->prepare("UPDATE users SET failed_login_attempts = ?, locked_until = NULL WHERE user_id = ?")
                    ->execute([$new_attempts, $user['user_id']]);
                $remaining  = LOGIN_MAX_ATTEMPTS - $new_attempts;
                $error      = "Invalid email or password. {$remaining} attempt(s) remaining before account lockout.";
                $error_type = 'danger';
            }
        }
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
// Runs once after the staff login succeeds — unlocks the ECDH private key and saves SSS share 1
document.addEventListener('DOMContentLoaded', async () => {
    const userId = <?php echo intval($_SESSION['user_id']); ?>;
    const status = document.getElementById('crypto-status');

    // Password was saved to sessionStorage by the form submit handler below
    const password = sessionStorage.getItem('_tmp_pw');

    if (!password) {
        // sessionStorage cleared (e.g. new tab) — session.js will show the unlock modal on dashboard
        console.warn('[Crypto] No password in sessionStorage — redirecting (modal will appear)');
        window.location.href = 'staff/dashboard.php';
        return;
    }

    try {
        // Fetch the encrypted private key from the server (server stores ciphertext, not plaintext)
        status.textContent = 'Fetching encryption keys...';
        const keyResponse  = await fetch((window.__API_BASE || '/api') + '/get_user_keys.php');
        const keyData      = await keyResponse.json();

        if (!keyData.success) {
            console.error('[Crypto] Key fetch failed:', keyData.error);
            window.location.href = 'staff/dashboard.php';
            return;
        }

        // Decrypt the private key using the user's password (PBKDF2 + AES-GCM)
        status.textContent = 'Unlocking private key...';
        const privateKey   = await UTHMCrypto.unlockPrivateKey(
            keyData.encrypted_private_key,
            keyData.key_iv,
            keyData.key_auth_tag,
            password
        );

        // Hold the key in memory for this page session — never stored on disk
        UTHMCrypto.setSessionKey(userId, privateKey);
        console.log('[Crypto] Private key unlocked ✅');

        // Check if Shamir share 1 is already in the browser's IndexedDB (uthm_secure)
        status.textContent  = 'Checking device registration...';
        const hasShare      = await UTHMCrypto.deviceHasShare(userId);

        if (!hasShare) {
            // First login on this device — fetch share 1 from the server and save it locally
            status.textContent = 'Registering this device...';
            const shareRes  = await fetch((window.__API_BASE || '/api') + '/get_device_share.php');
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
                // Save the decrypted share to IndexedDB, password-protected
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
                    <div class="alert alert-<?php echo $error_type; ?> mb-4">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="loginForm">
                    <?php echo csrf_field(); ?>
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
// Save password to sessionStorage BEFORE form submits so the crypto loading screen can use it
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
