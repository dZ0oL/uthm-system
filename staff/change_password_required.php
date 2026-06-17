<?php
// ============================================================
// staff/change_password_required.php
// Shown to staff after account recovery when password_change_required=1.
// Staff sets a new password here; the browser then re-encrypts the
// private key and all SSS shares with the new password before saving.
// After completion, _recoveryCutoff is written to localStorage so
// chat.php knows to hide messages that existed before the recovery.
// ============================================================
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../index.php');
    exit;
}

// If no forced change is pending, this page shouldn't be open
if (empty($_SESSION['password_change_required'])) {
    header('Location: dashboard.php');
    exit;
}

$page_title = 'Change Your Password';
$user_id    = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT name, staff_id FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Dashboard background queries — rendered behind the modal overlay
$stmt = $pdo->prepare("
    SELECT g.* FROM `groups` g
    JOIN group_members gm ON g.group_id = gm.group_id
    WHERE gm.user_id = ?
");
$stmt->execute([$user_id]);
$groups = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT COUNT(*) as total FROM messages
    WHERE (receiver_id = ? OR group_id IN (
        SELECT group_id FROM group_members WHERE user_id = ?
    ))
");
$stmt->execute([$user_id, $user_id]);
$msg_count = $stmt->fetch()['total'];

include '../includes/header.php';
?>

<!-- ── Dashboard rendered as background (blurred by the fixed overlay) ── -->
<div class="page-header">
    <div>
        <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?></h1>
        <div class="page-subtitle">Your secure messaging overview</div>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon stat-icon--blue"><i data-lucide="layers"></i></div>
        <div>
            <div class="stat-value"><?php echo count($groups); ?></div>
            <div class="stat-label">Active Groups</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon--indigo"><i data-lucide="message-square"></i></div>
        <div>
            <div class="stat-value"><?php echo $msg_count; ?></div>
            <div class="stat-label">Total Messages</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon--green"><i data-lucide="shield-check"></i></div>
        <div>
            <div class="stat-value">E2E</div>
            <div class="stat-label">Encrypted</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><i data-lucide="layers"></i> Your Groups</div>
    <div class="card-body">
        <?php if (empty($groups)): ?>
            <p class="text-muted mb-0">You are not assigned to any groups yet.</p>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($groups as $group): ?>
                    <div class="col-md-4 col-sm-6">
                        <div class="group-card">
                            <div class="group-card-icon"><i data-lucide="users"></i></div>
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
                            <span class="btn btn-sm btn-gradient mt-1">Open Chat</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Fixed blur overlay + modal card ── -->
<div style="
    position:fixed; inset:0; z-index:1050;
    background:rgba(15,23,42,.4);
    backdrop-filter:blur(8px);
    -webkit-backdrop-filter:blur(8px);
    display:flex; align-items:center; justify-content:center;
    padding:16px;">

    <div class="card shadow-lg" style="width:460px;max-width:100%;">
        <div class="card-body p-5">

            <div class="text-center mb-4">
                <div style="width:64px;height:64px;background:#EEEDFE;border-radius:50%;
                            display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                    <i class="fas fa-key fa-2x" style="color:#534AB7;"></i>
                </div>
                <h4 class="mb-1">Password Change Required</h4>
                <p class="text-muted small">
                    A temporary password was set for your account. You must choose a new password before continuing.
                </p>
            </div>

            <div class="alert alert-warning py-2 mb-4">
                <small>
                    <i class="fas fa-exclamation-triangle"></i>
                    This is a one-time temporary password. Choose a strong new password that only you know.
                </small>
            </div>

            <div id="error-msg" class="alert alert-danger py-2" style="display:none;"></div>
            <div id="success-msg" class="alert alert-success py-2" style="display:none;"></div>

            <div class="mb-3">
                <label class="form-label">Current Temporary Password</label>
                <input type="password" id="current-pw" class="form-control"
                       placeholder="Enter your temporary password">
            </div>
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" id="new-pw" class="form-control"
                       placeholder="Minimum 8 characters">
                <div id="pw-strength" class="mt-1"></div>
            </div>
            <div class="mb-4">
                <label class="form-label">Confirm New Password</label>
                <input type="password" id="confirm-pw" class="form-control"
                       placeholder="Repeat new password">
            </div>

            <button id="change-btn" class="btn btn-gradient w-100">
                <i class="fas fa-lock"></i> Set New Password & Continue
            </button>

        </div>
    </div>
</div>

<script>
const USER_ID  = <?php echo intval($user_id); ?>;
const API_BASE = window.__API_BASE || '/api';
<?php
$_just_recovered = !empty($_SESSION['account_just_recovered']);
unset($_SESSION['account_just_recovered']);
?>
const ACCOUNT_JUST_RECOVERED = <?= $_just_recovered ? 'true' : 'false' ?>;

document.getElementById('new-pw').addEventListener('input', function () {
    const pw  = this.value;
    const str = document.getElementById('pw-strength');
    if (!pw) { str.innerHTML = ''; return; }
    let score = 0;
    if (pw.length >= 8)           score++;
    if (pw.length >= 12)          score++;
    if (/[A-Z]/.test(pw))        score++;
    if (/[0-9]/.test(pw))        score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    const levels = [
        {label:'Very weak',color:'danger'},
        {label:'Weak',color:'danger'},
        {label:'Fair',color:'warning'},
        {label:'Good',color:'info'},
        {label:'Strong',color:'success'},
        {label:'Very strong',color:'success'}
    ];
    const l = levels[Math.min(score, 5)];
    str.innerHTML = `<small class="text-${l.color}"><i class="fas fa-circle"></i> ${l.label}</small>`;
});

document.getElementById('change-btn').addEventListener('click', async () => {
    const btn       = document.getElementById('change-btn');
    const currentPw = document.getElementById('current-pw').value;
    const newPw     = document.getElementById('new-pw').value;
    const confirmPw = document.getElementById('confirm-pw').value;
    const errDiv    = document.getElementById('error-msg');
    const okDiv     = document.getElementById('success-msg');

    errDiv.style.display = 'none';
    okDiv.style.display  = 'none';

    if (!currentPw || !newPw || !confirmPw) {
        errDiv.textContent   = 'Please fill in all fields.';
        errDiv.style.display = 'block';
        return;
    }
    if (newPw.length < 8) {
        errDiv.textContent   = 'New password must be at least 8 characters.';
        errDiv.style.display = 'block';
        return;
    }
    if (newPw !== confirmPw) {
        errDiv.textContent   = 'New passwords do not match.';
        errDiv.style.display = 'block';
        return;
    }
    if (newPw === currentPw) {
        errDiv.textContent   = 'New password must be different from your temporary password.';
        errDiv.style.display = 'block';
        return;
    }

    btn.disabled  = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

    try {
        const keyRes  = await fetch(`${API_BASE}/get_user_keys.php`);
        const keyData = await keyRes.json();
        if (!keyData.success) throw new Error('Could not fetch key data');

        const extractableKey = await UTHMCrypto.unlockPrivateKeyExtractable(
            keyData.encrypted_private_key,
            keyData.key_iv,
            keyData.key_auth_tag,
            currentPw
        );

        const exportedJwk  = await crypto.subtle.exportKey('jwk', extractableKey);
        const privateBytes = new TextEncoder().encode(JSON.stringify(exportedJwk));

        const salt        = crypto.getRandomValues(new Uint8Array(16));
        const iv          = crypto.getRandomValues(new Uint8Array(12));
        const keyMaterial = await crypto.subtle.importKey(
            'raw', new TextEncoder().encode(newPw), 'PBKDF2', false, ['deriveKey']
        );
        const aesKey = await crypto.subtle.deriveKey(
            { name:'PBKDF2', salt, iterations:100000, hash:'SHA-256' },
            keyMaterial,
            { name:'AES-GCM', length:256 }, false, ['encrypt']
        );
        const encBuffer  = await crypto.subtle.encrypt({ name:'AES-GCM', iv }, aesKey, privateBytes);
        const encBytes   = new Uint8Array(encBuffer);
        const b64        = buf => btoa(String.fromCharCode(...new Uint8Array(buf)));
        const newEncKey  = b64(encBytes.slice(0, -16));
        const newKeyIv   = b64(salt) + '.' + b64(iv);
        const newAuthTag = b64(encBytes.slice(-16));

        let signalKeyPayload = {};
        if (keyData.encrypted_ik_dh) {
            signalKeyPayload = await Signal.reEncryptKeys(keyData, currentPw, newPw);
        }

        const res = await fetch(`${API_BASE}/change_password.php`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                current_password:      currentPw,
                new_password:          newPw,
                encrypted_private_key: newEncKey,
                key_iv:                newKeyIv,
                key_auth_tag:          newAuthTag,
                clear_pw_change_flag:  true,
                ...signalKeyPayload
            })
        });

        const result = await res.json();
        if (!result.success) throw new Error(result.error || 'Password update failed');

        // After recovery: clear old message history cache so pre-recovery messages
        // (encrypted with old keys) don't appear in chat.
        // Do NOT delete the entire uthm_signal IDB — session.js already ran
        // _initSignalKeys on page load, registering a fresh SPK and prekeys.
        // Deleting the DB now would wipe that SPK, causing _regeneratePreKeys to
        // create a second SPK on the next page, breaking X3DH for any bundle
        // the peer fetched while the first SPK was active.
        // Delete uthm_secure so the new device share (from recovery) gets re-fetched
        // by the hasShare check below.
        if (ACCOUNT_JUST_RECOVERED) {
            try { await Signal.clearMessageHistory(); } catch (_) {}
            await new Promise(resolve => { const r = indexedDB.deleteDatabase('uthm_secure'); r.onsuccess = r.onerror = () => resolve(); });
        }

        sessionStorage.setItem('_tmp_pw', newPw);
        const sessionKey = await UTHMCrypto.unlockPrivateKey(newEncKey, newKeyIv, newAuthTag, newPw);
        UTHMCrypto.setSessionKey(USER_ID, sessionKey);

        try {
            const hasShare = await UTHMCrypto.deviceHasShare(USER_ID);
            if (!hasShare) {
                const shareRes  = await fetch(`${API_BASE}/get_device_share.php`);
                const shareData = await shareRes.json();
                if (shareData.success) {
                    const plain = await UTHMCrypto.decryptMessage(
                        shareData.share_encrypted,
                        shareData.share_iv,
                        shareData.share_auth_tag,
                        sessionKey,
                        shareData.eph_pub
                    );
                    await UTHMCrypto.saveShareToDevice(USER_ID, { shareIndex: 1, shareData: plain }, newPw);
                }
            }
        } catch (shareErr) {
            console.warn('[Crypto] Share 1 save skipped:', shareErr.message);
        }

        if (keyData.encrypted_ik_dh && Object.keys(signalKeyPayload).length) {
            const mergedKeyData = { ...keyData, ...signalKeyPayload };
            const { IK_dh_priv, IK_sign_priv } = await Signal.unlockKeys(mergedKeyData, newPw);
            Signal.setIdentitySession(USER_ID, IK_dh_priv, IK_sign_priv, keyData.ik_dh_public, keyData.ik_sign_public);
        }

        if (ACCOUNT_JUST_RECOVERED) {
            localStorage.setItem(`_recovery_cutoff_${USER_ID}`, new Date().toISOString());
        }

        okDiv.textContent   = '✅ Password updated successfully! Redirecting...';
        okDiv.style.display = 'block';

        setTimeout(() => { window.location.href = 'dashboard.php'; }, 2000);

    } catch (err) {
        console.error('Password change error:', err);
        errDiv.textContent   = err.message || 'Failed. Please check your temporary password.';
        errDiv.style.display = 'block';
        btn.disabled  = false;
        btn.innerHTML = '<i class="fas fa-lock"></i> Set New Password & Continue';
    }
});
</script>

<?php include '../includes/footer.php'; ?>
