<?php
/**
 * staff/profile.php
 */
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT user_id, name, email, staff_id, department,
           created_at, ecdh_public_key, key_hash,
           ik_dh_public, spk_id
    FROM users WHERE user_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT timestamp FROM audit_logs
    WHERE user_id = ? AND action = 'Login'
    ORDER BY timestamp DESC LIMIT 2
");
$stmt->execute([$user_id]);
$logins     = $stmt->fetchAll(PDO::FETCH_ASSOC);
$last_login = isset($logins[1]) ? $logins[1]['timestamp'] : ($logins[0]['timestamp'] ?? null);

$has_signal_keys = !empty($user['ik_dh_public']) && !empty($user['spk_id']);

$page_title = 'My Profile';
include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>My Profile</h1>
        <div class="page-subtitle">Manage your account information and security settings</div>
    </div>
</div>

<div id="profile-status" class="alert" style="display:none;"></div>

<!-- Segmented tab control -->
<div class="d-flex justify-content-center mb-4">
<div class="tab-pill" style="margin-bottom:0;">
    <button class="tab-pill-btn active" data-tab="tab-info" title="Information">
        <i data-lucide="user"></i>
        <span class="tab-full-label">Information</span>
        <span class="tab-short-label">Info</span>
    </button>
    <button class="tab-pill-btn" data-tab="tab-password" title="Change Password">
        <i data-lucide="key"></i>
        <span class="tab-full-label">Change Password</span>
        <span class="tab-short-label">Password</span>
    </button>
    <button class="tab-pill-btn" data-tab="tab-security" title="Device & Security">
        <i data-lucide="shield"></i>
        <span class="tab-full-label">Device & Security</span>
        <span class="tab-short-label">Security</span>
    </button>
</div>
</div>

<!-- ══ Tab 1: Information ══════════════════════════════════════ -->
<div class="tab-pane" id="tab-info">
    <div class="row g-4">
        <div class="col-md-6 order-2 order-md-1">
            <div class="card h-100">
                <div class="card-header">
                    <i data-lucide="credit-card"></i> Profile Information
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Full Name</label>
                            <div class="form-control bg-light"><?php echo htmlspecialchars($user['name']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Staff ID</label>
                            <div class="form-control bg-light"><?php echo htmlspecialchars($user['staff_id'] ?? '—'); ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Email Address</label>
                            <div class="form-control bg-light"><?php echo htmlspecialchars($user['email']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Department</label>
                            <div class="form-control bg-light"><?php echo htmlspecialchars($user['department'] ?? '—'); ?></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Account Created</label>
                            <div class="form-control bg-light"><?php echo date('d M Y', strtotime($user['created_at'])); ?></div>
                        </div>
                    </div>
                    <p class="text-muted small mt-3 mb-0">
                        <i data-lucide="info" style="width:13px;height:13px;vertical-align:-2px;"></i>
                        Profile information can only be updated by an administrator.
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-6 order-1 order-md-2">
            <div class="card h-100 text-center">
                <div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
                    <div style="width:72px;height:72px;border-radius:50%;background:var(--primary);
                                color:#fff;display:flex;align-items:center;justify-content:center;
                                font-size:28px;font-weight:700;margin:0 auto 16px;">
                        <?php echo mb_strtoupper(mb_substr($user['name'], 0, 1)); ?>
                    </div>
                    <div class="fw-semibold" style="font-size:16px;"><?php echo htmlspecialchars($user['name']); ?></div>
                    <div class="text-muted small mt-1"><?php echo htmlspecialchars($user['staff_id'] ?? ''); ?></div>
                    <div class="text-muted small"><?php echo htmlspecialchars($user['department'] ?? ''); ?></div>
                    <div class="mt-3">
                        <?php if ($has_signal_keys): ?>
                            <span class="enc-badge enc-badge--signal">
                                <i data-lucide="shield-check" style="width:12px;height:12px;"></i>
                                Signal E2E Active
                            </span>
                        <?php else: ?>
                            <span class="enc-badge enc-badge--none">
                                <i data-lucide="alert-triangle" style="width:12px;height:12px;"></i>
                                Encryption not set up
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted small mt-3">
                        Last login: <?php echo $last_login ? date('d M Y, H:i', strtotime($last_login)) : '—'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ══ Tab 2: Change Password ══════════════════════════════════ -->
<div class="tab-pane" id="tab-password" style="display:none;">
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <i data-lucide="key"></i> Change Password
                </div>
                <div class="card-body">
                    <div class="alert alert-info py-2 mb-3">
                        <small>
                            <i class="fas fa-shield-alt"></i>
                            Changing your password will also re-encrypt your private encryption key.
                            This happens securely in your browser — the server never sees your password.
                        </small>
                    </div>

                    <div id="pw-error"   class="alert alert-danger  py-2" style="display:none;"></div>
                    <div id="pw-success" class="alert alert-success py-2" style="display:none;"></div>

                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" id="current-password" class="form-control"
                               placeholder="Enter current password" autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" id="new-password" class="form-control"
                               placeholder="Minimum 8 characters" autocomplete="new-password">
                        <div id="pw-strength" class="mt-1"></div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" id="confirm-password" class="form-control"
                               placeholder="Repeat new password" autocomplete="new-password">
                    </div>

                    <button id="change-pw-btn" class="btn btn-gradient w-100">
                        <i class="fas fa-key"></i> Update Password
                    </button>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <i data-lucide="lightbulb"></i> Security Tips
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0 small">
                        <li class="d-flex gap-2 mb-3">
                            <i class="fas fa-check-circle text-success mt-1 flex-shrink-0"></i>
                            <span>Use a mix of uppercase, lowercase, numbers <strong>and</strong> symbols — e.g. <code>P@ss#2025!</code></span>
                        </li>
                        <li class="d-flex gap-2 mb-3">
                            <i class="fas fa-check-circle text-success mt-1 flex-shrink-0"></i>
                            <span>Minimum 8 characters — 12 or more is strongly recommended</span>
                        </li>
                        <li class="d-flex gap-2 mb-3">
                            <i class="fas fa-check-circle text-success mt-1 flex-shrink-0"></i>
                            <span>Never share your password with anyone, including IT staff</span>
                        </li>
                        <li class="d-flex gap-2 mb-3">
                            <i class="fas fa-check-circle text-success mt-1 flex-shrink-0"></i>
                            <span>Do not reuse passwords from other systems or websites</span>
                        </li>
                        <li class="d-flex gap-2">
                            <i class="fas fa-check-circle text-success mt-1 flex-shrink-0"></i>
                            <span>Your new password <strong>re-encrypts your private key</strong> — only you can read your messages</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ══ Tab 3: Security ═════════════════════════════════════════ -->
<div class="tab-pane" id="tab-security" style="display:none;">
    <div class="row g-4 justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <i data-lucide="info"></i> About Your Security
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0 small">
                        <li class="d-flex gap-2 mb-3">
                            <i class="fas fa-check-circle text-success mt-1 flex-shrink-0"></i>
                            <span>Your messages are <strong>end-to-end encrypted</strong> — the server cannot read them</span>
                        </li>
                        <li class="d-flex gap-2 mb-3">
                            <i class="fas fa-check-circle text-success mt-1 flex-shrink-0"></i>
                            <span>Always log out when using shared or public computers</span>
                        </li>
                        <li class="d-flex gap-2 mb-3">
                            <i class="fas fa-check-circle text-success mt-1 flex-shrink-0"></i>
                            <span>Keep this device secure — your encryption share is stored here</span>
                        </li>
                        <li class="d-flex gap-2 mb-3">
                            <i class="fas fa-check-circle text-success mt-1 flex-shrink-0"></i>
                            <span>If you suspect your account is compromised, submit a <strong>Recovery Request</strong> immediately</span>
                        </li>
                        <li class="d-flex gap-2">
                            <i class="fas fa-check-circle text-success mt-1 flex-shrink-0"></i>
                            <span>Do not share your password with anyone — it also protects your private encryption key</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ── Tab switching ─────────────────────────────────────────────
document.querySelectorAll('.tab-pill-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.tab-pill-btn').forEach(function (b) {
            b.classList.remove('active');
        });
        document.querySelectorAll('.tab-pane').forEach(function (p) {
            p.style.display = 'none';
        });
        btn.classList.add('active');
        document.getElementById(btn.dataset.tab).style.display = '';
        // Re-init Lucide icons for newly visible panel
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
});

const USER_ID  = <?php echo intval($user_id); ?>;
const API_BASE = window.__API_BASE || '/api';

// ── Password strength indicator ───────────────────────────────
document.getElementById('new-password').addEventListener('input', function () {
    const pw       = this.value;
    const strength = document.getElementById('pw-strength');
    if (!pw) { strength.innerHTML = ''; return; }

    let score = 0;
    if (pw.length >= 8)           score++;
    if (pw.length >= 12)          score++;
    if (/[A-Z]/.test(pw))        score++;
    if (/[0-9]/.test(pw))        score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;

    const levels = [
        { label: 'Very weak',   color: 'danger'  },
        { label: 'Weak',        color: 'danger'  },
        { label: 'Fair',        color: 'warning' },
        { label: 'Good',        color: 'info'    },
        { label: 'Strong',      color: 'success' },
        { label: 'Very strong', color: 'success' }
    ];
    const level = levels[Math.min(score, 5)];
    strength.innerHTML =
        `<small class="text-${level.color}"><i class="fas fa-circle"></i> ${level.label}</small>`;
});

// ── Change password ───────────────────────────────────────────
document.getElementById('change-pw-btn').addEventListener('click', async () => {
    const btn        = document.getElementById('change-pw-btn');
    const currentPw  = document.getElementById('current-password').value;
    const newPw      = document.getElementById('new-password').value;
    const confirmPw  = document.getElementById('confirm-password').value;
    const errDiv     = document.getElementById('pw-error');
    const successDiv = document.getElementById('pw-success');

    errDiv.style.display     = 'none';
    successDiv.style.display = 'none';

    if (!currentPw || !newPw || !confirmPw) {
        errDiv.textContent = 'Please fill in all password fields.';
        errDiv.style.display = 'block'; return;
    }
    if (newPw.length < 8) {
        errDiv.textContent = 'New password must be at least 8 characters.';
        errDiv.style.display = 'block'; return;
    }
    if (newPw !== confirmPw) {
        errDiv.textContent = 'New passwords do not match.';
        errDiv.style.display = 'block'; return;
    }
    if (newPw === currentPw) {
        errDiv.textContent = 'New password must be different from current password.';
        errDiv.style.display = 'block'; return;
    }
    if (!Signal.hasIdentitySession() && !UTHMCrypto.getSessionKey()) {
        errDiv.textContent = 'Encryption key not loaded. Please re-login first.';
        errDiv.style.display = 'block'; return;
    }

    btn.disabled  = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

    try {
        const keyRes  = await fetch(`${API_BASE}/get_user_keys.php`);
        const keyData = await keyRes.json();
        if (!keyData.success) throw new Error('Could not fetch current key data');

        const extractableKey = await UTHMCrypto.unlockPrivateKeyExtractable(
            keyData.encrypted_private_key, keyData.key_iv, keyData.key_auth_tag, currentPw
        );

        const exportedJwk  = await crypto.subtle.exportKey('jwk', extractableKey);
        const privateBytes = new TextEncoder().encode(JSON.stringify(exportedJwk));

        const salt        = crypto.getRandomValues(new Uint8Array(16));
        const iv          = crypto.getRandomValues(new Uint8Array(12));
        const keyMaterial = await crypto.subtle.importKey(
            'raw', new TextEncoder().encode(newPw), 'PBKDF2', false, ['deriveKey']
        );
        const aesKey = await crypto.subtle.deriveKey(
            { name: 'PBKDF2', salt, iterations: 100000, hash: 'SHA-256' },
            keyMaterial, { name: 'AES-GCM', length: 256 }, false, ['encrypt']
        );
        const encBuffer = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, aesKey, privateBytes);

        const encBytes        = new Uint8Array(encBuffer);
        const b64             = buf => btoa(String.fromCharCode(...new Uint8Array(buf)));
        const newEncryptedKey = b64(encBytes.slice(0, -16));
        const newKeyIv        = b64(salt) + '.' + b64(iv);
        const newAuthTag      = b64(encBytes.slice(-16));

        let signalKeyPayload = {};
        if (keyData.encrypted_ik_dh) {
            signalKeyPayload = await Signal.reEncryptKeys(keyData, currentPw, newPw);
        }

        const res = await fetch(`${API_BASE}/change_password.php`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                current_password: currentPw, new_password: newPw,
                encrypted_private_key: newEncryptedKey, key_iv: newKeyIv,
                key_auth_tag: newAuthTag, ...signalKeyPayload
            })
        });

        const result = await res.json();
        if (!result.success) throw new Error(result.error || 'Password update failed');

        sessionStorage.setItem('_tmp_pw', newPw);

        const sessionKey = await UTHMCrypto.unlockPrivateKey(
            newEncryptedKey, newKeyIv, newAuthTag, newPw
        );
        UTHMCrypto.setSessionKey(USER_ID, sessionKey);

        if (keyData.encrypted_ik_dh && Object.keys(signalKeyPayload).length) {
            const mergedKeyData = { ...keyData, ...signalKeyPayload };
            const { IK_dh_priv, IK_sign_priv } = await Signal.unlockKeys(mergedKeyData, newPw);
            Signal.setIdentitySession(USER_ID, IK_dh_priv, IK_sign_priv,
                keyData.ik_dh_public, keyData.ik_sign_public);
        }

        document.getElementById('current-password').value = '';
        document.getElementById('new-password').value     = '';
        document.getElementById('confirm-password').value = '';
        document.getElementById('pw-strength').innerHTML  = '';

        successDiv.textContent   = '✅ Password updated successfully. Your encryption key has been re-secured.';
        successDiv.style.display = 'block';

    } catch (err) {
        console.error('Password change error:', err);
        errDiv.textContent   = err.message || 'Failed to update password. Check your current password.';
        errDiv.style.display = 'block';
    } finally {
        btn.disabled  = false;
        btn.innerHTML = '<i class="fas fa-key"></i> Update Password';
    }
});
</script>

<?php include '../includes/footer.php'; ?>
