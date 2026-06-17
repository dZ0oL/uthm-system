<?php
// ============================
// admin_forgot_password.php
// Public page — no auth needed
// ============================
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'staff/dashboard.php'));
    exit;
}

// Allow restart via GET
if (isset($_GET['restart'])) {
    unset($_SESSION['admin_reset_step'], $_SESSION['admin_reset_email']);
    header('Location: admin_forgot_password.php');
    exit;
}

// Step tracking via session (1 = email form, 2 = OTP form, 3 = submitted/waiting)
$step         = $_SESSION['admin_reset_step']  ?? 1;
$stored_email = $_SESSION['admin_reset_email'] ?? '';
$message      = '';
$success      = false;
$error        = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Step 1: Submit email → send verification OTP ─────────────────
    if ($step === 1) {
        $email = trim($_POST['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $stmt = $pdo->prepare("SELECT user_id, name FROM users WHERE email = ? AND role = 'admin' AND status = 'active'");
            $stmt->execute([$email]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin) {
                $error = 'No active admin account found with that email address.';
            } else {
                // If already OTP-verified and waiting for HOA, skip straight to done
                $ck = $pdo->prepare("SELECT request_id FROM admin_reset_requests WHERE user_id = ? AND status = 'pending' LIMIT 1");
                $ck->execute([$admin['user_id']]);
                if ($ck->fetch()) {
                    unset($_SESSION['admin_reset_step'], $_SESSION['admin_reset_email']);
                    $step    = 3;
                    $message = 'Your request is already submitted and awaiting Head Admin approval. You will receive an email once your password has been reset.';
                    $success = true;
                } else {
                    // Generate OTP
                    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

                    // Update existing otp_pending or insert new.
                    // Use DATE_ADD(NOW(), INTERVAL 10 MINUTE) so expiry is in MySQL's
                    // timezone — avoids PHP/MySQL timezone mismatch causing instant expiry.
                    $ck2 = $pdo->prepare("SELECT request_id FROM admin_reset_requests WHERE user_id = ? AND status = 'otp_pending' LIMIT 1");
                    $ck2->execute([$admin['user_id']]);
                    $existing = $ck2->fetch(PDO::FETCH_ASSOC);

                    if ($existing) {
                        $pdo->prepare("UPDATE admin_reset_requests SET otp=?, otp_expiry=DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE request_id=?")
                            ->execute([$otp, $existing['request_id']]);
                    } else {
                        $pdo->prepare("INSERT INTO admin_reset_requests (user_id, status, otp, otp_expiry) VALUES (?, 'otp_pending', ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))")
                            ->execute([$admin['user_id'], $otp]);
                    }

                    $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, 'Admin Reset OTP Sent', ?, ?)")
                        ->execute([$admin['user_id'], "Verification OTP sent to: $email", $_SERVER['REMOTE_ADDR'] ?? null]);

                    // Send OTP email
                    $mailSent = false;
                    try {
                        require_once 'includes/mailer.php';
                        $html_body = "
                        <div style='font-family:Arial,sans-serif;max-width:500px;margin:0 auto;'>
                            <div style='background:#534AB7;padding:24px;text-align:center;border-radius:8px 8px 0 0;'>
                                <h2 style='color:#fff;margin:0;'>UTHM Bursary Messaging</h2>
                                <p style='color:#ccc;margin:6px 0 0;font-size:13px;'>Password Reset — Identity Verification</p>
                            </div>
                            <div style='background:#f9f9f9;padding:28px;border-radius:0 0 8px 8px;border:1px solid #eee;'>
                                <p style='color:#333;'>Dear <strong>" . htmlspecialchars($admin['name']) . "</strong>,</p>
                                <p style='color:#555;font-size:14px;'>
                                    We received a password reset request for your admin account.
                                    Enter the OTP below to verify your identity. It is valid for 10 minutes.
                                </p>
                                <div style='text-align:center;background:#EEEDFE;border-radius:8px;padding:20px;margin:20px 0;'>
                                    <p style='color:#534AB7;font-weight:bold;margin:0 0 8px;font-size:13px;'>Verification OTP</p>
                                    <p style='font-size:40px;font-weight:bold;color:#333;letter-spacing:10px;margin:0;'>$otp</p>
                                    <p style='color:#888;font-size:12px;margin:10px 0 0;'>Valid for 10 minutes</p>
                                </div>
                                <p style='color:#555;font-size:14px;'>
                                    If you did not request this, you can safely ignore this email.
                                    Your password will not change without Head Admin approval.
                                </p>
                                <hr style='border:none;border-top:1px solid #eee;margin:20px 0;'>
                                <p style='font-size:12px;color:#aaa;text-align:center;margin:0;'>UTHM Bursary Office &bull; Secure Internal Messaging System</p>
                            </div>
                        </div>";
                        $plain = "Dear {$admin['name']},\n\n"
                               . "Verification OTP: $otp\nValid for 10 minutes.\n\n"
                               . "If you did not request this, ignore this email.\n\n"
                               . "UTHM Bursary Office";
                        $mailSent = sendEmail($email, $admin['name'], 'UTHM Bursary — Password Reset Verification OTP', $html_body, $plain);
                    } catch (Exception $e) {
                        error_log('Admin reset OTP email failed: ' . $e->getMessage());
                    }

                    // Dev fallback: store OTP in session so step 2 can display it when mail fails
                    if (!$mailSent) {
                        $_SESSION['admin_reset_dev_otp'] = $otp;
                    } else {
                        unset($_SESSION['admin_reset_dev_otp']);
                    }

                    $_SESSION['admin_reset_step']  = 2;
                    $_SESSION['admin_reset_email'] = $email;
                    $step         = 2;
                    $stored_email = $email;
                }
            }
        }

    // ── Step 2: Verify OTP → submit request to HOA ───────────────────
    } elseif ($step === 2) {
        $otp_input = trim($_POST['otp'] ?? '');
        $email     = $_SESSION['admin_reset_email'] ?? '';

        if (empty($email)) {
            unset($_SESSION['admin_reset_step'], $_SESSION['admin_reset_email']);
            $step  = 1;
            $error = 'Session expired. Please start over.';
        } else {
            $stmt = $pdo->prepare("
                SELECT arr.request_id, arr.user_id
                FROM admin_reset_requests arr
                JOIN users u ON arr.user_id = u.user_id
                WHERE u.email = ? AND arr.status = 'otp_pending'
                  AND arr.otp = ? AND arr.otp_expiry > NOW()
                ORDER BY arr.requested_at DESC LIMIT 1
            ");
            $stmt->execute([$email, $otp_input]);
            $req = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$req) {
                $error = 'Invalid or expired OTP. Please check your email and try again.';
            } else {
                $pdo->prepare("UPDATE admin_reset_requests SET status='pending', otp=NULL, otp_expiry=NULL WHERE request_id=?")
                    ->execute([$req['request_id']]);
                $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, 'Admin Reset Verified', ?, ?)")
                    ->execute([$req['user_id'], "Identity verified, reset pending HOA approval: $email", $_SERVER['REMOTE_ADDR'] ?? null]);

                unset($_SESSION['admin_reset_step'], $_SESSION['admin_reset_email'], $_SESSION['admin_reset_dev_otp']);
                $step    = 3;
                $message = 'Your identity has been verified. Your request has been forwarded to the Head Admin for approval. You will receive an email once your password has been reset.';
                $success = true;
            }
        }
    }
}

$page_title = 'Admin Forgot Password';
include 'includes/header.php';
?>

<div class="container-fluid p-0">
<div class="row g-0" style="min-height:100vh;">

    <div class="col-md-6 gradient-bg login-hero">
        <div class="login-hero-inner">
            <span class="login-hero-icon"><i class="fas fa-lock"></i></span>
            <h1 class="login-hero-title">UTHM Secure<br>Messaging</h1>
            <p class="login-hero-sub">Administrator Password Reset</p>
            <p class="login-hero-tag">Bursary Office &bull; Secure Internal System</p>
        </div>
    </div>

    <div class="col-md-6 login-card-panel">
        <div class="login-card">

            <div class="login-mobile-brand">
                <div class="login-mobile-icon"><i class="fas fa-lock"></i></div>
                <div class="login-mobile-brand-name">UTHM Secure<br>Messaging</div>
                <div class="login-mobile-brand-sub">Bursary Office &bull; Secure Internal System</div>
            </div>

            <div class="login-form-box">

                <?php if ($step === 3 && $success): ?>
                    <div class="text-center">
                        <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                        <h4 class="mb-3">Request Submitted</h4>
                        <p class="text-muted small"><?php echo htmlspecialchars($message); ?></p>
                        <a href="index.php" class="btn btn-gradient w-100 mt-2">
                            <i class="fas fa-arrow-left"></i> Back to Login
                        </a>
                    </div>

                <?php elseif ($step === 2): ?>
                    <div class="login-card-title">Enter Verification Code</div>
                    <p class="text-muted small text-center mb-4">
                        A 6-digit code has been sent to<br>
                        <strong><?php echo htmlspecialchars($stored_email); ?></strong>
                    </p>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($_SESSION['admin_reset_dev_otp'])): ?>
                    <div class="alert alert-warning">
                        <small>
                            <strong>Development mode:</strong>
                            Mail not configured. Your OTP is:
                            <strong><?php echo htmlspecialchars($_SESSION['admin_reset_dev_otp']); ?></strong>
                        </small>
                    </div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label" style="font-size:13px;font-weight:500;color:#475569;">6-Digit Verification Code</label>
                            <input type="text" name="otp" class="form-control text-center"
                                   placeholder="000000" maxlength="6" required
                                   style="letter-spacing:8px;font-size:24px;" autofocus>
                            <div class="form-text">Valid for 10 minutes. Check your email inbox.</div>
                        </div>
                        <button type="submit" class="btn btn-gradient w-100 mb-4" style="padding:11px;font-size:15px;font-weight:600;border-radius:8px;">
                            <i class="fas fa-check"></i> Verify &amp; Submit Request
                        </button>
                        <div class="text-center" style="font-size:13px;">
                            <a href="?restart=1" class="btn btn-link p-0" style="color:var(--accent);font-size:13px;">Didn't receive code? Resend</a>
                        </div>
                    </form>

                <?php else: ?>
                    <div class="login-card-title">Admin Forgot Password</div>
                    <p class="text-muted small text-center mb-4">
                        Enter your admin email. A verification OTP will be sent to confirm your identity,
                        then your request will be forwarded to the Head Admin for approval.
                    </p>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label" style="font-size:13px;font-weight:500;color:#475569;">Admin Email</label>
                            <input type="email" name="email" class="form-control"
                                   placeholder="yourname@uthm.edu.my" required autofocus>
                        </div>
                        <button type="submit" class="btn btn-gradient w-100 mb-4" style="padding:11px;font-size:15px;font-weight:600;border-radius:8px;">
                            <i class="fas fa-paper-plane"></i> Send Verification OTP
                        </button>
                        <div class="text-center" style="font-size:13px;">
                            <a href="index.php" class="text-decoration-none" style="color:var(--accent);"><i class="fas fa-arrow-left"></i> Back to Login</a>
                        </div>
                    </form>
                <?php endif; ?>

            </div>
        </div>
    </div>

</div>
</div>

<?php include 'includes/footer.php'; ?>
