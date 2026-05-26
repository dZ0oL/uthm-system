<?php
// ============================================================
// recover.php — Account Recovery Request
// Staff submits recovery request after OTP verification.
// No login required.
// ============================================================
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'staff/dashboard.php'));
    exit;
}

$page_title = 'Account Recovery';
include 'includes/header.php';
?>

<div class="container-fluid p-0">
<div class="row g-0" style="min-height:100vh;">

    <!-- ── Left: branding panel ── -->
    <div class="col-md-6 gradient-bg login-hero">
        <div class="login-hero-inner">
            <span class="login-hero-icon"><i class="fas fa-key"></i></span>
            <h1 class="login-hero-title">UTHM Secure<br>Messaging</h1>
            <p class="login-hero-sub">Account Recovery</p>
            <p class="login-hero-tag">Bursary Office &bull; End-to-End Encrypted</p>
        </div>
    </div>

    <!-- ── Right: form panel ── -->
    <div class="col-md-6 login-card-panel">
        <div class="login-card">

            <div class="login-mobile-brand">
                <div class="login-mobile-icon"><i class="fas fa-key"></i></div>
                <div class="login-mobile-brand-name">UTHM Secure<br>Messaging</div>
                <div class="login-mobile-brand-sub">Bursary Office &bull; End-to-End Encrypted</div>
            </div>

            <div class="login-form-box">

                <!-- Step 1: Enter email -->
                <div id="step-email">
                    <div class="login-card-title">Account Recovery</div>
                    <p class="text-muted small text-center mb-4">
                        Enter your UTHM email to receive a verification code.
                    </p>

                    <div id="email-error" class="alert alert-danger" style="display:none;"></div>

                    <div class="mb-3">
                        <label class="form-label" style="font-size:13px;font-weight:500;color:#475569;">UTHM Email Address</label>
                        <input type="email" id="recovery-email" class="form-control"
                               placeholder="yourname@uthm.edu.my" autofocus>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-size:13px;font-weight:500;color:#475569;">Reason for Recovery</label>
                        <select id="recovery-reason" class="form-select">
                            <option value="Device lost or damaged">Device lost or damaged</option>
                            <option value="Forgot password">Forgot password</option>
                            <option value="Account access issue">Account access issue</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <button id="send-otp-btn" class="btn btn-gradient w-100 mb-4" style="padding:11px;font-size:15px;font-weight:600;border-radius:8px;">
                        <i class="fas fa-paper-plane"></i> Send Verification Code
                    </button>

                    <div class="text-center" style="font-size:13px;">
                        <a href="index.php" class="text-decoration-none" style="color:var(--accent);">
                            <i class="fas fa-arrow-left"></i> Back to Login
                        </a>
                    </div>
                </div>

                <!-- Step 2: Enter OTP -->
                <div id="step-otp" style="display:none;">
                    <div class="login-card-title">Enter Verification Code</div>
                    <p class="text-muted small text-center mb-4">
                        A 6-digit code has been sent to<br>
                        <strong id="otp-email-display"></strong>
                    </p>

                    <div id="otp-error" class="alert alert-danger" style="display:none;"></div>

                    <div id="dev-otp-notice" class="alert alert-warning" style="display:none;">
                        <small>
                            <strong>Development mode:</strong>
                            Mail not configured. Your OTP is:
                            <strong id="dev-otp-value"></strong>
                        </small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" style="font-size:13px;font-weight:500;color:#475569;">6-Digit Verification Code</label>
                        <input type="text" id="otp-input"
                               class="form-control text-center"
                               placeholder="000000" maxlength="6"
                               style="letter-spacing:8px;font-size:24px;">
                        <div class="form-text">Valid for 10 minutes. Check your email inbox.</div>
                    </div>

                    <button id="verify-otp-btn" class="btn btn-gradient w-100 mb-4" style="padding:11px;font-size:15px;font-weight:600;border-radius:8px;">
                        <i class="fas fa-check"></i> Verify and Submit Request
                    </button>

                    <div class="text-center" style="font-size:13px;">
                        <button id="resend-btn" class="btn btn-link p-0" style="color:var(--accent);font-size:13px;">
                            Didn't receive code? Resend
                        </button>
                    </div>
                </div>

                <!-- Step 3: Success -->
                <div id="step-success" style="display:none;" class="text-center">
                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                    <h4 class="mb-3">Request Submitted</h4>
                    <p class="text-muted small">
                        Your recovery request has been submitted successfully.
                        An administrator will review and process your request.
                    </p>
                    <a href="index.php" class="btn btn-gradient w-100" style="padding:11px;font-size:15px;font-weight:600;border-radius:8px;">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </div>

            </div>
        </div>
    </div>

</div>
</div>

<script>
const API_BASE = '/uthm-system/api';
let   currentEmail = '';

// ── Step 1: Send OTP ─────────────────────────────────────────
document.getElementById('send-otp-btn').addEventListener('click', async () => {
    const email  = document.getElementById('recovery-email').value.trim();
    const reason = document.getElementById('recovery-reason').value;
    const errDiv = document.getElementById('email-error');
    const btn    = document.getElementById('send-otp-btn');

    errDiv.style.display = 'none';

    if (!email) {
        errDiv.textContent   = 'Please enter your email address.';
        errDiv.style.display = 'block';
        return;
    }

    btn.disabled  = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

    try {
        const response = await fetch(`${API_BASE}/send_otp.php`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ email, reason })
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Failed to send OTP');
        }

        currentEmail = email;
        sessionStorage.setItem('_recovery_reason', reason);

        document.getElementById('step-email').style.display = 'none';
        document.getElementById('step-otp').style.display   = 'block';
        document.getElementById('otp-email-display').textContent = email;

        if (data.dev_otp) {
            document.getElementById('dev-otp-notice').style.display = 'block';
            document.getElementById('dev-otp-value').textContent    = data.dev_otp;
        }

        document.getElementById('otp-input').focus();

    } catch (err) {
        errDiv.textContent   = err.message;
        errDiv.style.display = 'block';
    } finally {
        btn.disabled  = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Verification Code';
    }
});

// ── Step 2: Verify OTP ───────────────────────────────────────
document.getElementById('verify-otp-btn').addEventListener('click', async () => {
    const otp    = document.getElementById('otp-input').value.replace(/\D/g, '').trim();
    const errDiv = document.getElementById('otp-error');
    const btn    = document.getElementById('verify-otp-btn');
    const reason = sessionStorage.getItem('_recovery_reason')
                   || 'Account recovery requested by staff';

    errDiv.style.display = 'none';

    if (!otp || otp.length !== 6) {
        errDiv.textContent   = 'Please enter the 6-digit code.';
        errDiv.style.display = 'block';
        return;
    }

    btn.disabled  = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';

    try {
        const response = await fetch(`${API_BASE}/verify_otp.php`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ email: currentEmail, otp, reason })
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Verification failed');
        }

        sessionStorage.removeItem('_recovery_reason');
        document.getElementById('step-otp').style.display     = 'none';
        document.getElementById('step-success').style.display = 'block';

    } catch (err) {
        errDiv.textContent   = err.message;
        errDiv.style.display = 'block';
    } finally {
        btn.disabled  = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Verify and Submit Request';
    }
});

// ── OTP input: numbers only ───────────────────────────────────
document.getElementById('otp-input').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 6);
});

document.getElementById('otp-input').addEventListener('keydown', e => {
    if (e.key === 'Enter') document.getElementById('verify-otp-btn').click();
});

// ── Resend: go back to step 1 ────────────────────────────────
document.getElementById('resend-btn').addEventListener('click', () => {
    document.getElementById('step-otp').style.display   = 'none';
    document.getElementById('step-email').style.display = 'block';
    document.getElementById('otp-input').value          = '';
    document.getElementById('dev-otp-notice').style.display = 'none';
});
</script>

<?php include 'includes/footer.php'; ?>
