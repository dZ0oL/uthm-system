/**
 * session.js
 * Unlocks ECDH private key on every staff page load.
 * Also validates session token to terminate previous device sessions.
 */
(async () => {
    const staffUserId = window.__STAFF_USER_ID;
    const adminUserId = window.__ADMIN_USER_ID;
    if (!staffUserId && !adminUserId) return;
    if (adminUserId && !staffUserId) {
        await checkSessionValidity();
        setInterval(checkSessionValidity, 60000); // poll every 60 s so displaced admins see the modal
        return;
    }

    // Key already in memory for this page load — nothing to do
    if (UTHMCrypto.getSessionKey()) {
        console.log('[Session] Key already in memory ✅');
        await checkSessionValidity();
        return;
    }

    let password = sessionStorage.getItem('_tmp_pw');

    // No password available — show modal
    if (!password) {
        console.warn('[Session] No password — showing unlock modal');
        password = await askForPassword();
        if (!password) return;
    }

    await unlockKey(staffUserId, password);
    await checkSessionValidity();
})();

// ── Session token validation ──────────────────────────────────
// Checks on every page load that this device's session is still valid.
// Shows a clean overlay message before redirecting.
async function checkSessionValidity() {
    try {
        const apiBase = window.__API_BASE || '/uthm-system/api';
        const res     = await fetch(apiBase + '/check_session.php');
        const data    = await res.json();

        if (!data.valid) {
            if (data.reason === 'session_terminated') {
                showSessionMessage(
                    'Session Terminated',
                    'Your account was logged in from another device. ' +
                    'You have been signed out of this device.',
                    'warning'
                );
            } else if (data.reason === 'account_deactivated') {
                showSessionMessage(
                    'Account Deactivated',
                    'Your account has been deactivated by an administrator. ' +
                    'Please contact your administrator for assistance.',
                    'danger'
                );
            } else {
                // Generic reason — redirect silently
                window.location.href = '/uthm-system/index.php';
                return;
            }

            // Delay redirect so user can read the message
            setTimeout(() => {
                window.location.href = '/uthm-system/index.php';
            }, 3000);
        }
    } catch (err) {
        // Network error — don't force logout, just warn
        console.warn('[Session] Could not verify session token:', err.message);
    }
}

// Show a clean overlay message instead of a jarring alert()
function showSessionMessage(title, message, type) {
    // Remove any existing modal first
    const existing = document.getElementById('_session_modal');
    if (existing) existing.remove();

    const colors = {
        warning: {
            bg:     '#fff3cd',
            border: '#f0ad4e',
            text:   '#856404',
            icon:   'fa-exclamation-triangle'
        },
        danger: {
            bg:     '#f8d7da',
            border: '#f5c6cb',
            text:   '#842029',
            icon:   'fa-ban'
        }
    };
    const c = colors[type] || colors.warning;

    const overlay = document.createElement('div');
    overlay.id = '_session_modal';
    overlay.style.cssText = `
        position:fixed; top:0; left:0; width:100%; height:100%;
        background:rgba(0,0,0,0.6); z-index:99999;
        display:flex; align-items:center; justify-content:center;
    `;
    overlay.innerHTML = `
        <div style="background:#fff; border-radius:12px; padding:32px;
                    width:400px; box-shadow:0 8px 32px rgba(0,0,0,0.2);
                    text-align:center;">
            <div style="width:56px;height:56px;border-radius:50%;
                        background:${c.bg};border:2px solid ${c.border};
                        display:flex;align-items:center;justify-content:center;
                        margin:0 auto 16px;">
                <i class="fas ${c.icon}" style="color:${c.text};font-size:22px;"></i>
            </div>
            <h5 style="margin:0 0 10px;color:#2c3e50;">${title}</h5>
            <p style="margin:0 0 20px;color:#666;font-size:14px;line-height:1.6;">
                ${message}
            </p>
            <div style="background:${c.bg};border-radius:8px;padding:10px;
                        font-size:13px;color:${c.text};">
                <i class="fas fa-clock"></i> Redirecting to login in 3 seconds...
            </div>
        </div>
    `;
    document.body.appendChild(overlay);
}

async function unlockKey(staffUserId, password) {
    try {
        const apiBase = window.__API_BASE || '/uthm-system/api';
        const response = await fetch(apiBase + '/get_user_keys.php');

        if (!response.ok) {
            console.error('[Session] API returned', response.status);
            return;
        }

        const keyData = await response.json();

        if (!keyData.success) {
            console.error('[Session] Key fetch failed:', keyData.error);
            sessionStorage.removeItem('_tmp_pw');
            return;
        }

        // ── Legacy ECDH key (needed for decrypting old messages) ──
        const privateKey = await UTHMCrypto.unlockPrivateKey(
            keyData.encrypted_private_key,
            keyData.key_iv,
            keyData.key_auth_tag,
            password
        );
        UTHMCrypto.setSessionKey(staffUserId, privateKey);

        // ── Signal Protocol keys ───────────────────────────────────
        if (keyData.encrypted_ik_dh) {
            // Signal keys exist on server — unlock them
            try {
                const { IK_dh_priv, IK_sign_priv } = await Signal.unlockKeys(keyData, password);
                Signal.setIdentitySession(
                    staffUserId, IK_dh_priv, IK_sign_priv,
                    keyData.ik_dh_public, keyData.ik_sign_public
                );

                // Check if SPK private key is in IDB (needed for X3DH respond)
                const spkRec = keyData.spk_id ? await Signal.getSPKFromIDB(keyData.spk_id) : null;
                if (!spkRec) {
                    // SPK missing from IDB (new device or IDB cleared) — regenerate
                    await _regeneratePreKeys(staffUserId, IK_sign_priv, apiBase);
                }
                console.log('[Session] Signal keys unlocked ✅');
            } catch (signalErr) {
                console.warn('[Session] Signal key unlock failed:', signalErr.message);
            }
        } else {
            // First login — generate Signal keys
            try {
                await _initSignalKeys(staffUserId, password, apiBase);
                console.log('[Session] Signal keys generated ✅');
            } catch (signalErr) {
                console.warn('[Session] Signal key generation failed:', signalErr.message);
            }
        }

        console.log('[Session] Keys unlocked ✅');
        sessionStorage.setItem('_tmp_pw', password);

        const modal = document.getElementById('_session_modal');
        if (modal) modal.remove();

        if (typeof initChat === 'function') {
            initChat();
        }

    } catch (err) {
        console.error('[Session] Unlock failed:', err.message);
        sessionStorage.removeItem('_tmp_pw');
        showPasswordError();
    }
}

// Generate Signal keys for the first time (called once per account)
async function _initSignalKeys(userId, password, apiBase) {
    // Wipe stale cryptographic session state (sessions, prekeys, sender keys).
    // These are bound to the old IK and invalid once a new identity is generated.
    // Message history cache (decrypted/file_keys) is intentionally kept —
    // it is local content, not key material, same as Signal's own local DB.
    await Signal.clearCryptoState();

    const identKeys = await Signal.generateKeys(password);
    const spkData   = await Signal.generateSPK(identKeys.IK_sign.privateKey);
    const opks      = await Signal.generateOPKs(10);

    // Save prekey private keys to IDB
    await Signal.saveSPKtoIDB(spkData);
    await Signal.saveOPKsToIDB(opks);

    // Register with server
    const res = await fetch(apiBase + '/signal_register_keys.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({
            ik_dh_public:    identKeys.ik_dh_public,
            ik_sign_public:  identKeys.ik_sign_public,
            encrypted_ik_dh: identKeys.encrypted_ik_dh,
            ik_dh_iv:        identKeys.ik_dh_iv,
            ik_dh_auth_tag:  identKeys.ik_dh_auth_tag,
            encrypted_ik_sign: identKeys.encrypted_ik_sign,
            ik_sign_iv:        identKeys.ik_sign_iv,
            ik_sign_auth_tag:  identKeys.ik_sign_auth_tag,
            spk_id:       spkData.spk_id,
            spk_public:   spkData.spk_public,
            spk_signature:spkData.spk_signature,
            opk_keys: opks.map(k => ({ key_id: k.key_id, public_key: k.pub_jwk }))
        })
    });
    if (!res.ok) throw new Error('Signal key registration failed: ' + res.status);

    Signal.setIdentitySession(
        userId,
        identKeys.IK_dh.privateKey,
        identKeys.IK_sign.privateKey,
        identKeys.ik_dh_public,
        identKeys.ik_sign_public
    );
}

// Regenerate SPK + OPKs (when IDB is cleared on existing account)
async function _regeneratePreKeys(userId, IK_sign_priv, apiBase) {
    const spkData = await Signal.generateSPK(IK_sign_priv);
    const opks    = await Signal.generateOPKs(10);
    await Signal.saveSPKtoIDB(spkData);
    await Signal.saveOPKsToIDB(opks);

    await fetch(apiBase + '/signal_register_keys.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({
            // Not changing IK — only updating SPK + OPKs
            // Send empty strings for IK fields to keep existing values
            spk_id:       spkData.spk_id,
            spk_public:   spkData.spk_public,
            spk_signature:spkData.spk_signature,
            opk_keys: opks.map(k => ({ key_id: k.key_id, public_key: k.pub_jwk }))
        })
    }).catch(() => {});
}

function askForPassword() {
    return new Promise((resolve) => {
        const existing = document.getElementById('_session_modal');
        if (existing) existing.remove();

        const modal = document.createElement('div');
        modal.id    = '_session_modal';
        modal.style.cssText = `
            position:fixed; top:0; left:0; width:100%; height:100%;
            background:rgba(0,0,0,0.6); z-index:99999;
            display:flex; align-items:center; justify-content:center;
        `;
        modal.innerHTML = `
            <div style="background:#fff; border-radius:12px; padding:32px;
                        width:380px; box-shadow:0 8px 32px rgba(0,0,0,0.2);">
                <h5 style="margin:0 0 8px; color:#2c3e50;">
                    <i class="fas fa-lock"></i> Session Verification
                </h5>
                <p style="margin:0 0 20px; color:#666; font-size:14px;">
                    Enter your password to unlock your encrypted session.
                </p>
                <div id="_session_error" style="display:none; color:#dc3545;
                     font-size:13px; margin-bottom:12px;">
                    Incorrect password. Please try again.
                </div>
                <input type="password" id="_session_pw_input"
                       placeholder="Enter your password"
                       style="width:100%; padding:10px 14px; border:1px solid #dee2e6;
                              border-radius:8px; font-size:15px; margin-bottom:16px;
                              box-sizing:border-box;"
                       autofocus>
                <button id="_session_pw_btn"
                        style="width:100%; padding:10px;
                               background:linear-gradient(135deg,#667eea,#764ba2);
                               border:none; border-radius:8px; color:#fff;
                               font-size:15px; cursor:pointer;">
                    Unlock Session
                </button>
            </div>
        `;

        document.body.appendChild(modal);

        const input = document.getElementById('_session_pw_input');
        const btn   = document.getElementById('_session_pw_btn');

        const submit = () => {
            const pw = input.value.trim();
            if (!pw) return;
            btn.textContent = 'Unlocking...';
            btn.disabled    = true;
            resolve(pw);
        };

        btn.addEventListener('click', submit);
        input.addEventListener('keydown', e => {
            if (e.key === 'Enter') submit();
        });
    });
}

function showPasswordError() {
    const errDiv = document.getElementById('_session_error');
    const btn    = document.getElementById('_session_pw_btn');
    const input  = document.getElementById('_session_pw_input');

    if (errDiv) {
        errDiv.textContent   = 'Incorrect password. Please try again.';
        errDiv.style.display = 'block';
    }
    if (btn)   { btn.textContent = 'Unlock Session'; btn.disabled = false; }
    if (input) { input.value = ''; input.focus(); }

    // Ask for password again
    askForPassword().then(pw => {
        if (pw) unlockKey(window.__STAFF_USER_ID, pw);
    });
}
