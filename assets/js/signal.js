/**
 * signal.js
 * ================================================================
 * Simplified Signal Protocol for UTHM Secure Messaging System
 *
 * Implements:
 *   - X3DH (Extended Triple Diffie-Hellman) key agreement
 *   - Double Ratchet (symmetric + DH ratchet)
 *   - Sender Key protocol for group messages
 *
 * Uses P-256 (native Web Crypto API — no external libraries).
 * ================================================================
 */

const Signal = (() => {
    'use strict';

    // ── Constants ───────────────────────────────────────────────
    const CURVE       = 'P-256';
    const AES_MODE    = 'AES-GCM';
    const PBKDF2_ITER = 100000;
    const IV_LEN      = 12;
    const SALT_LEN    = 16;

    const IDB_NAME    = 'uthm_signal';
    const IDB_VER     = 1;

    const enc = new TextEncoder();
    const dec = new TextDecoder();

    // ── Utilities ────────────────────────────────────────────────

    function bufToBase64(buf) {
        return btoa(String.fromCharCode(...new Uint8Array(buf)));
    }

    function base64ToBuf(b64) {
        return Uint8Array.from(atob(b64), c => c.charCodeAt(0));
    }

    function randomBytes(n) {
        return crypto.getRandomValues(new Uint8Array(n));
    }

    // ── Crypto primitives ─────────────────────────────────────────

    // HKDF-SHA-256: input: Uint8Array, salt: Uint8Array, info: string
    async function hkdf(input, salt, info, byteLen) {
        const km = await crypto.subtle.importKey('raw', input, 'HKDF', false, ['deriveBits']);
        const bits = await crypto.subtle.deriveBits(
            { name: 'HKDF', hash: 'SHA-256', salt, info: enc.encode(info) },
            km, byteLen * 8
        );
        return new Uint8Array(bits);
    }

    // ECDH shared bits: privateKey (CryptoKey), publicKeyJwk (JSON string)
    async function dhBits(privateKey, publicKeyJwk) {
        const pub = await crypto.subtle.importKey(
            'jwk', JSON.parse(publicKeyJwk),
            { name: 'ECDH', namedCurve: CURVE }, false, []
        );
        const bits = await crypto.subtle.deriveBits({ name: 'ECDH', public: pub }, privateKey, 256);
        return new Uint8Array(bits);
    }

    // Chain key KDF: returns [new_ck_bytes, mk_bytes]
    async function kdfCK(ck) {
        const key = await crypto.subtle.importKey(
            'raw', ck, { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']
        );
        const mk  = new Uint8Array(await crypto.subtle.sign('HMAC', key, new Uint8Array([1])));
        const ck2 = new Uint8Array(await crypto.subtle.sign('HMAC', key, new Uint8Array([2])));
        return [ck2, mk];
    }

    // AES-256-GCM encrypt: returns { ciphertext, iv, authTag } — all base64
    async function aesEncrypt(keyBytes, plaintext, aad) {
        const k   = await crypto.subtle.importKey('raw', keyBytes, { name: AES_MODE }, false, ['encrypt']);
        const iv  = randomBytes(IV_LEN);
        const buf = await crypto.subtle.encrypt(
            { name: AES_MODE, iv, additionalData: aad || new Uint8Array(0) }, k, plaintext
        );
        const out = new Uint8Array(buf);
        return {
            ciphertext: bufToBase64(out.slice(0, -16)),
            iv:         bufToBase64(iv),
            authTag:    bufToBase64(out.slice(-16))
        };
    }

    // AES-256-GCM decrypt: returns Uint8Array
    async function aesDecrypt(keyBytes, ciphertext_b64, iv_b64, authTag_b64, aad) {
        const k        = await crypto.subtle.importKey('raw', keyBytes, { name: AES_MODE }, false, ['decrypt']);
        const combined = new Uint8Array([...base64ToBuf(ciphertext_b64), ...base64ToBuf(authTag_b64)]);
        try {
            const buf = await crypto.subtle.decrypt(
                { name: AES_MODE, iv: base64ToBuf(iv_b64), additionalData: aad || new Uint8Array(0) },
                k, combined
            );
            return new Uint8Array(buf);
        } catch {
            throw new Error('AES-GCM decryption failed — wrong key, corrupted data, or invalid AAD');
        }
    }

    // Generate ECDH keypair (extractable)
    async function genECDH() {
        return crypto.subtle.generateKey({ name: 'ECDH', namedCurve: CURVE }, true, ['deriveBits']);
    }

    // Generate ECDSA keypair (extractable)
    async function genECDSA() {
        return crypto.subtle.generateKey({ name: 'ECDSA', namedCurve: CURVE }, true, ['sign', 'verify']);
    }

    // ── Password-based encryption ─────────────────────────────────

    async function pwDeriveKey(password, salt) {
        const km = await crypto.subtle.importKey('raw', enc.encode(password), 'PBKDF2', false, ['deriveKey']);
        return crypto.subtle.deriveKey(
            { name: 'PBKDF2', salt, iterations: PBKDF2_ITER, hash: 'SHA-256' },
            km, { name: AES_MODE, length: 256 }, false, ['encrypt', 'decrypt']
        );
    }

    async function pwEncrypt(data, password) {
        const salt = randomBytes(SALT_LEN);
        const iv   = randomBytes(IV_LEN);
        const k    = await pwDeriveKey(password, salt);
        const buf  = await crypto.subtle.encrypt({ name: AES_MODE, iv }, k, enc.encode(data));
        const out  = new Uint8Array(buf);
        return {
            encrypted: bufToBase64(out.slice(0, -16)),
            iv:        bufToBase64(salt) + '.' + bufToBase64(iv),
            authTag:   bufToBase64(out.slice(-16))
        };
    }

    async function pwDecrypt(encrypted_b64, saltIv, authTag_b64, password) {
        const [saltB64, ivB64] = saltIv.split('.');
        const k       = await pwDeriveKey(password, base64ToBuf(saltB64));
        const combined = new Uint8Array([...base64ToBuf(encrypted_b64), ...base64ToBuf(authTag_b64)]);
        try {
            const buf = await crypto.subtle.decrypt(
                { name: AES_MODE, iv: base64ToBuf(ivB64) }, k, combined
            );
            return dec.decode(buf);
        } catch {
            throw new Error('Wrong password or corrupted key data');
        }
    }

    // ── IndexedDB ─────────────────────────────────────────────────

    function openIDB() {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open(IDB_NAME, IDB_VER);
            req.onupgradeneeded = e => {
                const db = e.target.result;
                ['sessions', 'sender_keys', 'prekeys', 'spk', 'decrypted', 'file_keys'].forEach(store => {
                    if (!db.objectStoreNames.contains(store)) {
                        db.createObjectStore(store, { keyPath: 'key' });
                    }
                });
            };
            req.onsuccess = () => resolve(req.result);
            req.onerror   = () => reject(req.error);
        });
    }

    async function idbPut(store, record) {
        const db = await openIDB();
        return new Promise((resolve, reject) => {
            const tx  = db.transaction(store, 'readwrite');
            const st  = tx.objectStore(store);
            const req = st.put(record);
            req.onsuccess = () => resolve(true);
            req.onerror   = () => reject(req.error);
        });
    }

    async function idbGet(store, key) {
        const db = await openIDB();
        return new Promise((resolve, reject) => {
            const tx  = db.transaction(store, 'readonly');
            const st  = tx.objectStore(store);
            const req = st.get(key);
            req.onsuccess = () => resolve(req.result || null);
            req.onerror   = () => reject(req.error);
        });
    }

    async function idbDelete(store, key) {
        const db = await openIDB();
        return new Promise((resolve, reject) => {
            const tx  = db.transaction(store, 'readwrite');
            const st  = tx.objectStore(store);
            const req = st.delete(key);
            req.onsuccess = () => resolve(true);
            req.onerror   = () => reject(req.error);
        });
    }

    // Wipe only cryptographic session state — called when a fresh identity is
    // generated (first login or post-recovery). Sessions, prekeys and sender keys
    // are bound to the old IK and cannot be used with the new one.
    // The decrypted/file_keys caches are kept: they are message history
    // (equivalent to Signal's local SQLite DB), not cryptographic key material.
    async function clearCryptoState() {
        const db     = await openIDB();
        const stores = ['sessions', 'sender_keys', 'prekeys', 'spk'];
        await Promise.all(stores.map(store => new Promise((resolve, reject) => {
            const tx  = db.transaction(store, 'readwrite');
            const req = tx.objectStore(store).clear();
            req.onsuccess = () => resolve();
            req.onerror   = () => reject(req.error);
        })));
    }

    // ── In-memory identity session ────────────────────────────────

    let _session = null;

    function setIdentitySession(userId, IK_dh_priv, IK_sign_priv, IK_dh_pub_jwk, IK_sign_pub_jwk) {
        _session = { userId, IK_dh_priv, IK_sign_priv, IK_dh_pub_jwk, IK_sign_pub_jwk };
    }

    function getIdentitySession() {
        if (!_session) throw new Error('Signal identity session not initialized');
        return _session;
    }

    function hasIdentitySession() { return _session !== null; }

    // ── Identity Key Generation & Unlock ──────────────────────────

    async function generateKeys(password) {
        const IK_dh   = await genECDH();
        const IK_sign = await genECDSA();

        const ik_dh_pub_jwk    = JSON.stringify(await crypto.subtle.exportKey('jwk', IK_dh.publicKey));
        const ik_dh_priv_jwk   = JSON.stringify(await crypto.subtle.exportKey('jwk', IK_dh.privateKey));
        const ik_sign_pub_jwk  = JSON.stringify(await crypto.subtle.exportKey('jwk', IK_sign.publicKey));
        const ik_sign_priv_jwk = JSON.stringify(await crypto.subtle.exportKey('jwk', IK_sign.privateKey));

        const enc_dh   = await pwEncrypt(ik_dh_priv_jwk, password);
        const enc_sign = await pwEncrypt(ik_sign_priv_jwk, password);

        return {
            ik_dh_public:    ik_dh_pub_jwk,
            ik_sign_public:  ik_sign_pub_jwk,
            encrypted_ik_dh: enc_dh.encrypted,
            ik_dh_iv:        enc_dh.iv,
            ik_dh_auth_tag:  enc_dh.authTag,
            encrypted_ik_sign: enc_sign.encrypted,
            ik_sign_iv:        enc_sign.iv,
            ik_sign_auth_tag:  enc_sign.authTag,
            // In-memory key objects (not sent to server)
            IK_dh,
            IK_sign
        };
    }

    async function unlockKeys(data, password) {
        const dh_priv_str   = await pwDecrypt(data.encrypted_ik_dh,   data.ik_dh_iv,   data.ik_dh_auth_tag,   password);
        const sign_priv_str = await pwDecrypt(data.encrypted_ik_sign, data.ik_sign_iv, data.ik_sign_auth_tag, password);

        const IK_dh_priv = await crypto.subtle.importKey(
            'jwk', JSON.parse(dh_priv_str),
            { name: 'ECDH', namedCurve: CURVE }, false, ['deriveBits']
        );
        const IK_sign_priv = await crypto.subtle.importKey(
            'jwk', JSON.parse(sign_priv_str),
            { name: 'ECDSA', namedCurve: CURVE }, false, ['sign']
        );
        return { IK_dh_priv, IK_sign_priv };
    }

    // Re-encrypt IK private keys with a new password (called during password change)
    async function reEncryptKeys(data, currentPw, newPw) {
        const dh_priv_str   = await pwDecrypt(data.encrypted_ik_dh,   data.ik_dh_iv,   data.ik_dh_auth_tag,   currentPw);
        const sign_priv_str = await pwDecrypt(data.encrypted_ik_sign, data.ik_sign_iv, data.ik_sign_auth_tag, currentPw);

        const dhEnc   = await pwEncrypt(dh_priv_str,   newPw);
        const signEnc = await pwEncrypt(sign_priv_str, newPw);

        return {
            encrypted_ik_dh:   dhEnc.encrypted,
            ik_dh_iv:          dhEnc.iv,
            ik_dh_auth_tag:    dhEnc.authTag,
            encrypted_ik_sign: signEnc.encrypted,
            ik_sign_iv:        signEnc.iv,
            ik_sign_auth_tag:  signEnc.authTag
        };
    }

    // ── Signed Prekey ─────────────────────────────────────────────

    async function generateSPK(IK_sign_priv) {
        const spk_id  = Date.now();
        const SPK     = await genECDH();
        const pub_jwk  = JSON.stringify(await crypto.subtle.exportKey('jwk', SPK.publicKey));
        const priv_jwk = JSON.stringify(await crypto.subtle.exportKey('jwk', SPK.privateKey));

        const sig = await crypto.subtle.sign(
            { name: 'ECDSA', hash: 'SHA-256' }, IK_sign_priv, enc.encode(pub_jwk)
        );

        return {
            spk_id,
            spk_public:    pub_jwk,
            spk_signature: bufToBase64(new Uint8Array(sig)),
            priv_jwk
        };
    }

    async function saveSPKtoIDB(spkData) {
        await idbPut('spk', { key: spkData.spk_id, pub_jwk: spkData.spk_public, priv_jwk: spkData.priv_jwk });
    }

    async function getSPKFromIDB(spk_id) {
        return idbGet('spk', spk_id);
    }

    // ── One-Time Prekeys ─────────────────────────────────────────

    async function generateOPKs(count) {
        const keys = [];
        for (let i = 0; i < count; i++) {
            const OPK      = await genECDH();
            const pub_jwk  = JSON.stringify(await crypto.subtle.exportKey('jwk', OPK.publicKey));
            const priv_jwk = JSON.stringify(await crypto.subtle.exportKey('jwk', OPK.privateKey));
            const key_id   = Date.now() * 100 + i;
            keys.push({ key_id, pub_jwk, priv_jwk });
        }
        return keys;
    }

    async function saveOPKsToIDB(opks) {
        for (const opk of opks) {
            await idbPut('prekeys', { key: opk.key_id, priv_jwk: opk.priv_jwk });
        }
    }

    async function getOPKFromIDB(key_id) {
        return idbGet('prekeys', key_id);
    }

    async function deleteOPKFromIDB(key_id) {
        return idbDelete('prekeys', key_id);
    }

    // ── X3DH ──────────────────────────────────────────────────────

    async function x3dhInitiate(IK_dh_priv, IK_dh_pub_jwk, bundle) {
        // Verify SPK signature
        const IK_sign_pub = await crypto.subtle.importKey(
            'jwk', JSON.parse(bundle.ik_sign_public),
            { name: 'ECDSA', namedCurve: CURVE }, false, ['verify']
        );
        const sigValid = await crypto.subtle.verify(
            { name: 'ECDSA', hash: 'SHA-256' }, IK_sign_pub,
            base64ToBuf(bundle.spk_signature), enc.encode(bundle.spk_public)
        );
        if (!sigValid) throw new Error('SPK signature verification failed — identity mismatch');

        // Ephemeral key
        const EK         = await genECDH();
        const EK_pub_jwk = JSON.stringify(await crypto.subtle.exportKey('jwk', EK.publicKey));

        // DH outputs
        const F   = new Uint8Array(32).fill(0xFF);
        const DH1 = await dhBits(IK_dh_priv,    bundle.spk_public);
        const DH2 = await dhBits(EK.privateKey,  bundle.ik_dh_public);
        const DH3 = await dhBits(EK.privateKey,  bundle.spk_public);

        const parts = [F, DH1, DH2, DH3];
        let opk_id = null;

        if (bundle.opk_id && bundle.opk_public) {
            parts.push(await dhBits(EK.privateKey, bundle.opk_public));
            opk_id = bundle.opk_id;
        }

        const ikm = concatBytes(...parts);
        const SK  = await hkdf(ikm, new Uint8Array(32), 'WhisperText', 32);

        return { SK, EK_pub_jwk, spk_id: bundle.spk_id, opk_id };
    }

    async function x3dhRespond(IK_dh_priv, spk_priv_jwk, opk_priv_jwk, pkData) {
        const SPK_priv = await crypto.subtle.importKey(
            'jwk', JSON.parse(spk_priv_jwk),
            { name: 'ECDH', namedCurve: CURVE }, false, ['deriveBits']
        );

        const F   = new Uint8Array(32).fill(0xFF);
        const DH1 = await dhBits(SPK_priv,    pkData.ik_dh_pub);
        const DH2 = await dhBits(IK_dh_priv,  pkData.ek_pub);
        const DH3 = await dhBits(SPK_priv,    pkData.ek_pub);

        const parts = [F, DH1, DH2, DH3];

        if (opk_priv_jwk && pkData.opk_id) {
            const OPK_priv = await crypto.subtle.importKey(
                'jwk', JSON.parse(opk_priv_jwk),
                { name: 'ECDH', namedCurve: CURVE }, false, ['deriveBits']
            );
            parts.push(await dhBits(OPK_priv, pkData.ek_pub));
            await deleteOPKFromIDB(pkData.opk_id); // one-time use
        }

        const ikm = concatBytes(...parts);
        const SK  = await hkdf(ikm, new Uint8Array(32), 'WhisperText', 32);
        return { SK };
    }

    function concatBytes(...arrays) {
        const total = arrays.reduce((s, a) => s + a.length, 0);
        const out   = new Uint8Array(total);
        let   off   = 0;
        for (const a of arrays) { out.set(a, off); off += a.length; }
        return out;
    }

    // ── Personal Session (Symmetric Ratchet) ─────────────────────

    // Both parties derive two independent chain keys from SK.
    // Initiator: sends on chain_A, receives on chain_B.
    // Responder: sends on chain_B, receives on chain_A.
    // dhsData: { pub_jwk, priv_jwk } — pass SPK data for responder; omit for initiator (fresh keypair generated)
    // dhrPubJwk: initiator's EK pub JWK string for responder; null for initiator
    async function initPersonalSession(SK, isInitiator, dhsData = null, dhrPubJwk = null) {
        const zero    = new Uint8Array(32);
        const chain_A = await hkdf(SK, zero, 'UTHMChainA', 32);
        const chain_B = await hkdf(SK, zero, 'UTHMChainB', 32);
        const root_key = await hkdf(SK, zero, 'UTHMRootKey', 32);

        let DHs;
        if (dhsData) {
            // Responder: use the SPK as the initial ratchet keypair
            const priv_key = await crypto.subtle.importKey(
                'jwk', JSON.parse(dhsData.priv_jwk),
                { name: 'ECDH', namedCurve: CURVE }, false, ['deriveBits']
            );
            DHs = { pub_jwk: dhsData.pub_jwk, priv_jwk: dhsData.priv_jwk, priv_key };
        } else {
            // Initiator: generate a fresh ratchet keypair
            const kp      = await genECDH();
            const pub_jwk  = JSON.stringify(await crypto.subtle.exportKey('jwk', kp.publicKey));
            const priv_jwk = JSON.stringify(await crypto.subtle.exportKey('jwk', kp.privateKey));
            DHs = { pub_jwk, priv_jwk, priv_key: kp.privateKey };
        }

        // Initiator with a known peer ratchet key (Bob's SPK): perform an initial DH ratchet
        // step so the first send_CK is DH-derived, not SK-derived. This lets Bob reproduce
        // the matching recv_CK via DH(Bob_SPK_priv, Alice_ratchet_pub) on first receive.
        let final_send_CK  = isInitiator ? chain_A : chain_B;
        let final_root_key = root_key;
        if (!dhsData && dhrPubJwk) {
            const dh_out  = await dhBits(DHs.priv_key, dhrPubJwk);
            const derived = await hkdf(dh_out, root_key, 'UTHMRatchet', 64);
            final_root_key = derived.slice(0, 32);
            final_send_CK  = derived.slice(32, 64);
        }

        return {
            send_CK:      bufToBase64(final_send_CK),
            recv_CK:      bufToBase64(isInitiator ? chain_B : chain_A),
            send_Ns:      0,
            recv_Nr:      0,
            recv_skipped: {},
            root_key:     bufToBase64(final_root_key),
            DHs,
            DHr:          dhrPubJwk || null,
            send_PN:      0
        };
    }

    async function savePersonalSession(myId, peerId, state) {
        // Exclude priv_key (CryptoKey is not JSON-serialisable) — priv_jwk string is kept for re-import on load
        const serializable = { ...state };
        if (state.DHs) {
            serializable.DHs = { pub_jwk: state.DHs.pub_jwk, priv_jwk: state.DHs.priv_jwk };
        }
        await idbPut('sessions', { key: `${myId}_${peerId}`, state: JSON.stringify(serializable) });
    }

    async function loadPersonalSession(myId, peerId) {
        const rec = await idbGet('sessions', `${myId}_${peerId}`);
        if (!rec) return null;
        const state = JSON.parse(rec.state);
        // Re-import DHs private key from stored JWK string
        if (state.DHs && state.DHs.priv_jwk) {
            const priv_key = await crypto.subtle.importKey(
                'jwk', JSON.parse(state.DHs.priv_jwk),
                { name: 'ECDH', namedCurve: CURVE }, false, ['deriveBits']
            );
            state.DHs = { pub_jwk: state.DHs.pub_jwk, priv_jwk: state.DHs.priv_jwk, priv_key };
        }
        return state;
    }

    // ── Message Key Derivation ────────────────────────────────────

    async function advanceSendChain(session) {
        const [ck2, mk] = await kdfCK(base64ToBuf(session.send_CK));
        session.send_CK = bufToBase64(ck2);
        session.send_Ns++;
        return mk; // Uint8Array
    }

    async function advanceRecvChain(session, targetN) {
        // Namespace skipped keys by DH epoch to avoid counter collisions across ratchet steps
        const epoch = session.DHr ? session.DHr.substring(0, 8) : 'init';
        // Cache keys for any skipped messages
        while (session.recv_Nr < targetN) {
            const [ck2, mk] = await kdfCK(base64ToBuf(session.recv_CK));
            session.recv_CK = bufToBase64(ck2);
            session.recv_skipped = session.recv_skipped || {};
            session.recv_skipped[`${epoch}:${session.recv_Nr}`] = bufToBase64(mk);
            session.recv_Nr++;
        }
        // Advance to targetN
        const [ck2, mk] = await kdfCK(base64ToBuf(session.recv_CK));
        session.recv_CK = bufToBase64(ck2);
        session.recv_Nr++;
        return mk; // Uint8Array
    }

    // ── DH Ratchet Step ──────────────────────────────────────────
    // Triggered when the receiver sees a new DH public key in an incoming header.
    // Advances root key twice: once to derive the new recv chain, once for the new send chain.
    async function advanceDHRatchet(session, newDHr_pub_jwk) {
        session.send_PN = session.send_Ns;  // save previous chain length
        session.send_Ns = 0;
        session.recv_Nr = 0;
        session.DHr     = newDHr_pub_jwk;

        // Step A: current DHs × new DHr → new recv chain key
        const dh1 = await dhBits(session.DHs.priv_key, newDHr_pub_jwk);
        const d1  = await hkdf(dh1, base64ToBuf(session.root_key), 'UTHMRatchet', 64);
        session.root_key = bufToBase64(d1.slice(0, 32));
        session.recv_CK  = bufToBase64(d1.slice(32, 64));

        // Step B: generate new DHs keypair for our next sending turn
        const kp      = await genECDH();
        const pub_jwk  = JSON.stringify(await crypto.subtle.exportKey('jwk', kp.publicKey));
        const priv_jwk = JSON.stringify(await crypto.subtle.exportKey('jwk', kp.privateKey));
        session.DHs    = { pub_jwk, priv_jwk, priv_key: kp.privateKey };

        // Step C: new DHs × same DHr → new send chain key
        const dh2 = await dhBits(kp.privateKey, newDHr_pub_jwk);
        const d2  = await hkdf(dh2, base64ToBuf(session.root_key), 'UTHMRatchet', 64);
        session.root_key = bufToBase64(d2.slice(0, 32));
        session.send_CK  = bufToBase64(d2.slice(32, 64));
    }

    // ── Decrypted Message Cache ───────────────────────────────────

    // Keys include userId so stale entries from a previous account with the
    // same DB-assigned message_id never poison a new account's cache.
    async function cacheDecrypted(userId, msgId, text) {
        await idbPut('decrypted', { key: `${userId}_msg_${msgId}`, text });
    }

    async function getCachedDecrypted(userId, msgId) {
        return idbGet('decrypted', `${userId}_msg_${msgId}`);
    }

    async function cacheFileKey(userId, msgId, data) {
        await idbPut('file_keys', { key: `${userId}_file_${msgId}`, ...data });
    }

    async function getCachedFileKey(userId, msgId) {
        return idbGet('file_keys', `${userId}_file_${msgId}`);
    }

    // ── Personal Message Encrypt ──────────────────────────────────

    // Returns: { message_content, iv, auth_tag, signal_header, signal_prekey_data }
    async function encryptPersonal(myUserId, peerUserId, plaintext, peerBundle) {
        const { IK_dh_priv, IK_dh_pub_jwk } = getIdentitySession();

        let session    = await loadPersonalSession(myUserId, peerUserId);
        let pkeyData   = null;

        if (session && peerBundle && peerBundle.spk_id) {
            // Drop session if peer's SPK changed (new device login / recovery) or if session
            // pre-dates this tracking field (treat as stale — forces a fresh X3DH).
            if (!session.peer_spk_id || session.peer_spk_id !== peerBundle.spk_id) {
                await idbDelete('sessions', `${myUserId}_${peerUserId}`);
                session = null;
            }
        }

        if (!session) {
            const x3dh = await x3dhInitiate(IK_dh_priv, IK_dh_pub_jwk, peerBundle);
            // Pass Bob's SPK as DHr so the initial send_CK is DH-derived (Signal spec requirement)
            session    = await initPersonalSession(x3dh.SK, true, null, peerBundle.spk_public);
            session.peer_spk_id = x3dh.spk_id;
            pkeyData   = { ik_dh_pub: IK_dh_pub_jwk, ek_pub: x3dh.EK_pub_jwk, spk_id: x3dh.spk_id, opk_id: x3dh.opk_id };
        }

        const mk     = await advanceSendChain(session);
        const msgN   = session.send_Ns - 1;
        const header = { n: msgN, pn: session.send_PN, dh: session.DHs.pub_jwk };
        const aad    = enc.encode(JSON.stringify(header));
        const result = await aesEncrypt(mk, enc.encode(plaintext), aad);

        await savePersonalSession(myUserId, peerUserId, session);

        return {
            message_content:    result.ciphertext,
            iv:                 result.iv,
            auth_tag:           result.authTag,
            signal_header:      JSON.stringify(header),
            signal_prekey_data: pkeyData ? JSON.stringify(pkeyData) : null
        };
    }

    // ── Personal Message Decrypt ──────────────────────────────────

    // Returns: decrypted plaintext string
    async function decryptPersonal(myUserId, peerUserId, msg) {
        const cached = await getCachedDecrypted(myUserId, msg.message_id);
        if (cached) return cached.text;

        const { IK_dh_priv } = getIdentitySession();
        let session = await loadPersonalSession(myUserId, peerUserId);

        if (!session) {
            if (!msg.signal_prekey_data) {
                throw new Error('Message was encrypted in a previous session — ask sender to resend');
            }
            const pkData = JSON.parse(msg.signal_prekey_data);
            const spkRec = await getSPKFromIDB(pkData.spk_id);
            if (!spkRec) throw new Error('Session key unavailable on this device — ask sender to resend');

            let opk_jwk = null;
            if (pkData.opk_id) {
                const opkRec = await getOPKFromIDB(pkData.opk_id);
                opk_jwk = opkRec ? opkRec.priv_jwk : null;
            }

            const { SK } = await x3dhRespond(IK_dh_priv, spkRec.priv_jwk, opk_jwk, pkData);
            session = await initPersonalSession(SK, false,
                { pub_jwk: spkRec.pub_jwk, priv_jwk: spkRec.priv_jwk },
                null  // Responder has no peer ratchet key yet; DHr is set when first header.dh arrives
            );
        }

        const header = JSON.parse(msg.signal_header);
        let mk;

        // DH ratchet: advance when peer presents a new ratchet public key
        if (header.dh && header.dh !== session.DHr) {
            await advanceDHRatchet(session, header.dh);
        }

        const epoch      = header.dh ? header.dh.substring(0, 8) : 'init';
        const skippedKey = `${epoch}:${header.n}`;

        if (session.recv_skipped && session.recv_skipped[skippedKey] !== undefined) {
            mk = base64ToBuf(session.recv_skipped[skippedKey]);
            delete session.recv_skipped[skippedKey];
        } else {
            mk = await advanceRecvChain(session, header.n);
        }

        const aad   = enc.encode(JSON.stringify(header));
        const plain = await aesDecrypt(mk, msg.message_content, msg.iv, msg.auth_tag, aad);
        const text  = dec.decode(plain);

        await savePersonalSession(myUserId, peerUserId, session);
        await cacheDecrypted(myUserId, msg.message_id, text);

        return text;
    }

    // ── Personal File Encrypt ─────────────────────────────────────

    // Encrypts file bytes. message_content = encrypted JSON {fk, fi, fa} (file key wrapped in ratchet).
    // Returns: { payload (for send_file.php), encryptedBuffer }
    async function encryptPersonalFile(myUserId, peerUserId, fileBuffer, peerBundle) {
        const { IK_dh_priv, IK_dh_pub_jwk } = getIdentitySession();

        let session  = await loadPersonalSession(myUserId, peerUserId);
        let pkeyData = null;

        if (session && peerBundle && peerBundle.spk_id) {
            if (!session.peer_spk_id || session.peer_spk_id !== peerBundle.spk_id) {
                await idbDelete('sessions', `${myUserId}_${peerUserId}`);
                session = null;
            }
        }

        if (!session) {
            const x3dh = await x3dhInitiate(IK_dh_priv, IK_dh_pub_jwk, peerBundle);
            session    = await initPersonalSession(x3dh.SK, true, null, peerBundle.spk_public);
            session.peer_spk_id = x3dh.spk_id;
            pkeyData   = { ik_dh_pub: IK_dh_pub_jwk, ek_pub: x3dh.EK_pub_jwk, spk_id: x3dh.spk_id, opk_id: x3dh.opk_id };
        }

        // Random file key for the actual file bytes
        const FK       = randomBytes(32);
        const fileEnc  = await aesEncrypt(FK, fileBuffer, new Uint8Array(0));

        // Wrap file key + file encryption params in the ratchet chain
        const mk     = await advanceSendChain(session);
        const msgN   = session.send_Ns - 1;
        const header = { n: msgN, pn: session.send_PN, dh: session.DHs.pub_jwk };
        const aad    = enc.encode(JSON.stringify(header));
        const wrapPayload = JSON.stringify({ fk: bufToBase64(FK), fi: fileEnc.iv, fa: fileEnc.authTag });
        const wrapped = await aesEncrypt(mk, enc.encode(wrapPayload), aad);

        // ECDH fallback: encrypt file key with static IK-to-IK shared secret so the
        // receiver can decrypt even if their Signal session state is lost from IndexedDB.
        // ECDH(sender_IK_priv, receiver_IK_pub) == ECDH(receiver_IK_priv, sender_IK_pub)
        const sharedFB   = await dhBits(IK_dh_priv, peerBundle.ik_dh_public);
        const fbEncKey   = await hkdf(sharedFB, new Uint8Array(32), 'UTHMFileKeyFallback', 32);
        const fbPayload  = JSON.stringify({ fk: bufToBase64(FK), fi: fileEnc.iv, fa: fileEnc.authTag });
        const fbEnc      = await aesEncrypt(fbEncKey, enc.encode(fbPayload), new Uint8Array(0));
        const ecdhFileKey = JSON.stringify({ ct: fbEnc.ciphertext, iv: fbEnc.iv, at: fbEnc.authTag });

        await savePersonalSession(myUserId, peerUserId, session);

        return {
            payload: {
                message_content:    wrapped.ciphertext,
                iv:                 wrapped.iv,
                auth_tag:           wrapped.authTag,
                signal_header:      JSON.stringify(header),
                signal_prekey_data: pkeyData ? JSON.stringify(pkeyData) : null,
                ecdh_file_key:      ecdhFileKey
            },
            encryptedBuffer: base64ToBuf(fileEnc.ciphertext).buffer,
            fileKeyData:     { fk: bufToBase64(FK), fi: fileEnc.iv, fa: fileEnc.authTag }
        };
    }

    // Decrypt personal file — call after loading the message, uses cached file key
    async function decryptPersonalFile(myUserId, peerUserId, msg, encryptedBuffer) {
        // Try cache first (msg already processed by decryptPersonal)
        let fileKeyData = await getCachedFileKey(myUserId, msg.message_id);

        if (!fileKeyData) {
            try {
                // Primary path: Signal double-ratchet wrapper
                const wrapJson = await decryptPersonal(myUserId, peerUserId, msg);
                const wrap     = JSON.parse(wrapJson);
                fileKeyData    = { fk: wrap.fk, fi: wrap.fi, fa: wrap.fa };
                await cacheFileKey(myUserId, msg.message_id, fileKeyData);
            } catch (signalErr) {
                // Fallback: ECDH static key (receiver side only)
                // Works when Signal session is missing from IndexedDB
                const ecdhRaw  = msg.encryptedAesKeyJson;
                const senderIK = msg.senderIKPub;
                if (!ecdhRaw || !senderIK || msg.isMine) throw signalErr;
                try {
                    const { IK_dh_priv } = getIdentitySession();
                    const sharedFB  = await dhBits(IK_dh_priv, senderIK);
                    const fbEncKey  = await hkdf(sharedFB, new Uint8Array(32), 'UTHMFileKeyFallback', 32);
                    const ecdh      = JSON.parse(ecdhRaw);
                    const fkPlain   = await aesDecrypt(fbEncKey, ecdh.ct, ecdh.iv, ecdh.at, new Uint8Array(0));
                    fileKeyData     = JSON.parse(dec.decode(fkPlain));
                    await cacheFileKey(myUserId, msg.message_id, fileKeyData);
                } catch (_) { throw signalErr; }
            }
        }

        const FK = base64ToBuf(fileKeyData.fk);
        return (await aesDecrypt(FK, bufToBase64(new Uint8Array(encryptedBuffer)), fileKeyData.fi, fileKeyData.fa, new Uint8Array(0))).buffer;
    }

    // Cache file key at load time (called during loadMessages for receiver's file messages)
    async function cachePersonalFileKey(myUserId, peerUserId, msg) {
        const cached = await getCachedFileKey(myUserId, msg.message_id);
        if (cached) return;
        try {
            const wrapJson = await decryptPersonal(myUserId, peerUserId, msg);
            const wrap     = JSON.parse(wrapJson);
            await cacheFileKey(myUserId, msg.message_id, { fk: wrap.fk, fi: wrap.fi, fa: wrap.fa });
        } catch (_) {
            // Signal session unavailable — try ECDH fallback so the key is cached now
            try {
                if (!msg.encrypted_aes_key || !msg.sender_ik_dh_public) return;
                const { IK_dh_priv } = getIdentitySession();
                const sharedFB  = await dhBits(IK_dh_priv, msg.sender_ik_dh_public);
                const fbEncKey  = await hkdf(sharedFB, new Uint8Array(32), 'UTHMFileKeyFallback', 32);
                const ecdh      = JSON.parse(msg.encrypted_aes_key);
                const fkPlain   = await aesDecrypt(fbEncKey, ecdh.ct, ecdh.iv, ecdh.at, new Uint8Array(0));
                await cacheFileKey(myUserId, msg.message_id, JSON.parse(dec.decode(fkPlain)));
            } catch (_) {}
        }
    }

    // ── Sender Key (Group) ────────────────────────────────────────

    async function generateSenderKey() {
        const sigKP        = await genECDSA();
        const sign_pub_jwk  = JSON.stringify(await crypto.subtle.exportKey('jwk', sigKP.publicKey));
        const sign_priv_jwk = JSON.stringify(await crypto.subtle.exportKey('jwk', sigKP.privateKey));
        return {
            CK:            bufToBase64(randomBytes(32)),
            iteration:     0,
            sign_pub_jwk,
            sign_priv_jwk
        };
    }

    // Encrypt sender key state (without priv key) for a group member
    async function encryptSKForMember(skState, myIK_dh_priv, memberIK_pub_jwk) {
        const shared  = await dhBits(myIK_dh_priv, memberIK_pub_jwk);
        const encKey  = await hkdf(shared, new Uint8Array(32), 'UTHMSKDist', 32);
        const payload = JSON.stringify({ CK: skState.CK, iteration: skState.iteration, sign_pub_jwk: skState.sign_pub_jwk });
        return aesEncrypt(encKey, enc.encode(payload), new Uint8Array(0));
    }

    // Decrypt sender key distribution from a sender
    async function decryptSKFromSender(encDist, myIK_dh_priv, senderIK_pub_jwk) {
        const shared  = await dhBits(myIK_dh_priv, senderIK_pub_jwk);
        const encKey  = await hkdf(shared, new Uint8Array(32), 'UTHMSKDist', 32);
        const plain   = await aesDecrypt(encKey, encDist.ciphertext, encDist.iv, encDist.authTag, new Uint8Array(0));
        return JSON.parse(dec.decode(plain));
    }

    async function saveSenderKeyToIDB(groupId, senderId, skState) {
        await idbPut('sender_keys', { key: `${groupId}_${senderId}`, state: JSON.stringify(skState) });
    }

    async function loadSenderKeyFromIDB(groupId, senderId) {
        const rec = await idbGet('sender_keys', `${groupId}_${senderId}`);
        return rec ? JSON.parse(rec.state) : null;
    }

    // ── Sender Key Encrypt ────────────────────────────────────────

    // Returns: { message_content, iv, auth_tag, signal_header } + updated skState
    async function skEncrypt(skState, plaintext) {
        const [ck2, mk] = await kdfCK(base64ToBuf(skState.CK));
        const iter      = skState.iteration;
        skState.CK      = bufToBase64(ck2);
        skState.iteration++;

        const result = await aesEncrypt(mk, enc.encode(plaintext), new Uint8Array(0));

        // Sign the ciphertext for authenticity
        const sign_priv = await crypto.subtle.importKey(
            'jwk', JSON.parse(skState.sign_priv_jwk),
            { name: 'ECDSA', namedCurve: CURVE }, false, ['sign']
        );
        const sig = await crypto.subtle.sign(
            { name: 'ECDSA', hash: 'SHA-256' }, sign_priv,
            enc.encode(result.ciphertext + result.iv + result.authTag)
        );

        return {
            message_content: result.ciphertext,
            iv:              result.iv,
            auth_tag:        result.authTag,
            signal_header:   JSON.stringify({ type: 'sk', iter, sig: bufToBase64(new Uint8Array(sig)) })
        };
    }

    // ── Sender Key Decrypt ────────────────────────────────────────

    async function skDecrypt(skState, msg) {
        const header   = JSON.parse(msg.signal_header);
        const targetIter = header.iter;

        // Advance chain to targetIter, caching skipped keys
        skState.sk_skipped = skState.sk_skipped || {};

        let mk;
        if (skState.sk_skipped[targetIter] !== undefined) {
            mk = base64ToBuf(skState.sk_skipped[targetIter]);
            delete skState.sk_skipped[targetIter];
        } else {
            while (skState.iteration < targetIter) {
                const [ck2, m] = await kdfCK(base64ToBuf(skState.CK));
                skState.CK = bufToBase64(ck2);
                skState.sk_skipped[skState.iteration] = bufToBase64(m);
                skState.iteration++;
            }
            const [ck2, m] = await kdfCK(base64ToBuf(skState.CK));
            skState.CK = bufToBase64(ck2);
            skState.iteration++;
            mk = m;
        }

        // Verify signature
        const sign_pub = await crypto.subtle.importKey(
            'jwk', JSON.parse(skState.sign_pub_jwk),
            { name: 'ECDSA', namedCurve: CURVE }, false, ['verify']
        );
        const sigValid = await crypto.subtle.verify(
            { name: 'ECDSA', hash: 'SHA-256' }, sign_pub,
            base64ToBuf(header.sig),
            enc.encode(msg.message_content + msg.iv + msg.auth_tag)
        );
        if (!sigValid) throw new Error('Sender key message authentication failed');

        const plain = await aesDecrypt(mk, msg.message_content, msg.iv, msg.auth_tag, new Uint8Array(0));
        return dec.decode(plain);
    }

    // ── Group Message Encrypt / Decrypt ──────────────────────────

    // Encrypt a group text message using the sender key
    // members: [{userId, ik_dh_public}] — needed only when distributing SK for the first time
    async function encryptGroup(myUserId, groupId, plaintext, members, apiBase) {
        const { IK_dh_priv, IK_dh_pub_jwk, IK_sign_priv } = getIdentitySession();

        let skState = await loadSenderKeyFromIDB(groupId, myUserId);

        if (!skState || !skState.sign_priv_jwk) {
            skState = await generateSenderKey();
            // Distribute BEFORE encrypting so members receive the initial CK (iteration=0).
            // Distributing after skEncrypt would give them iteration=1 and they could
            // never derive the key for the first message (iter=0).
            await distributeSenderKey(skState, myUserId, groupId, members, IK_dh_priv, apiBase);
        }

        const result = await skEncrypt(skState, plaintext);

        await saveSenderKeyToIDB(groupId, myUserId, skState);

        return {
            message_content: result.message_content,
            iv:              result.iv,
            auth_tag:        result.auth_tag,
            signal_header:   result.signal_header
        };
    }

    async function distributeSenderKey(skState, myUserId, groupId, members, IK_dh_priv, apiBase) {
        const distributions = [];
        for (const member of members) {
            if (!member.ik_dh_public || member.userId == myUserId) continue;
            try {
                const encDist = await encryptSKForMember(skState, IK_dh_priv, member.ik_dh_public);
                distributions.push({
                    member_id:     member.userId,
                    ciphertext:    encDist.ciphertext,
                    iv:            encDist.iv,
                    auth_tag:      encDist.authTag,
                    iteration:     skState.iteration
                });
            } catch (_) {}
        }
        if (distributions.length === 0) return;

        try {
            await fetch(`${apiBase}/signal_save_sender_key.php`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ group_id: groupId, distributions })
            });
        } catch (_) {}
    }

    // Decrypt a group text message
    async function decryptGroup(myUserId, groupId, senderId, senderIKPub, msg, apiBase) {
        const cached = await getCachedDecrypted(myUserId, msg.message_id);
        if (cached) return cached.text;

        let skState = await loadSenderKeyFromIDB(groupId, senderId);

        if (!skState) {
            skState = await fetchAndSetSenderKey(myUserId, groupId, senderId, senderIKPub, apiBase);
        }

        if (!skState) throw new Error('Sender key not available — cannot decrypt group message');

        let text;
        try {
            text = await skDecrypt(skState, msg);
        } catch (_) {
            // IDB state may be stale (e.g. sender re-generated their key).
            // Fetch the latest distribution from the server and retry once.
            const freshState = await fetchAndSetSenderKey(myUserId, groupId, senderId, senderIKPub, apiBase);
            if (!freshState) throw new Error('Sender key not available — cannot decrypt group message');
            text = await skDecrypt(freshState, msg);
            await saveSenderKeyToIDB(groupId, senderId, freshState);
            await cacheDecrypted(myUserId, msg.message_id, text);
            return text;
        }

        await saveSenderKeyToIDB(groupId, senderId, skState);
        await cacheDecrypted(myUserId, msg.message_id, text);

        return text;
    }

    async function fetchAndSetSenderKey(myUserId, groupId, senderId, senderIKPub, apiBase) {
        try {
            const { IK_dh_priv } = getIdentitySession();
            const res  = await fetch(`${apiBase}/signal_get_sender_key.php?group_id=${groupId}&sender_id=${senderId}`);
            const data = await res.json();
            if (!data.success || !data.distribution) return null;

            const skState = await decryptSKFromSender(
                { ciphertext: data.distribution.ciphertext, iv: data.distribution.iv, authTag: data.distribution.auth_tag },
                IK_dh_priv, senderIKPub
            );
            skState.sk_skipped = {};
            await saveSenderKeyToIDB(groupId, senderId, skState);
            return skState;
        } catch (_) {
            return null;
        }
    }

    // ── Group File Encrypt / Decrypt ──────────────────────────────

    async function encryptGroupFile(myUserId, groupId, fileBuffer, members, apiBase) {
        const { IK_dh_priv } = getIdentitySession();

        let skState = await loadSenderKeyFromIDB(groupId, myUserId);

        if (!skState || !skState.sign_priv_jwk) {
            skState = await generateSenderKey();
            // Distribute before encrypting — same reason as encryptGroup.
            await distributeSenderKey(skState, myUserId, groupId, members, IK_dh_priv, apiBase);
        }

        const FK      = randomBytes(32);
        const fileEnc = await aesEncrypt(FK, fileBuffer, new Uint8Array(0));
        const wrapStr = JSON.stringify({ fk: bufToBase64(FK), fi: fileEnc.iv, fa: fileEnc.authTag });
        const result  = await skEncrypt(skState, wrapStr);

        await saveSenderKeyToIDB(groupId, myUserId, skState);

        return {
            payload: {
                message_content: result.message_content,
                iv:              result.iv,
                auth_tag:        result.auth_tag,
                signal_header:   result.signal_header
            },
            encryptedBuffer: base64ToBuf(fileEnc.ciphertext).buffer,
            fileKeyData:     { fk: bufToBase64(FK), fi: fileEnc.iv, fa: fileEnc.authTag }
        };
    }

    async function decryptGroupFile(myUserId, groupId, senderId, senderIKPub, msg, encryptedBuffer, apiBase) {
        let fileKeyData = await getCachedFileKey(myUserId, msg.message_id);

        if (!fileKeyData) {
            const wrapJson = await decryptGroup(myUserId, groupId, senderId, senderIKPub, msg, apiBase);
            const wrap     = JSON.parse(wrapJson);
            fileKeyData    = { fk: wrap.fk, fi: wrap.fi, fa: wrap.fa };
            await cacheFileKey(myUserId, msg.message_id, fileKeyData);
        }

        const FK = base64ToBuf(fileKeyData.fk);
        return (await aesDecrypt(FK, bufToBase64(new Uint8Array(encryptedBuffer)), fileKeyData.fi, fileKeyData.fa, new Uint8Array(0))).buffer;
    }

    // Cache group file key at load time
    async function cacheGroupFileKey(myUserId, groupId, senderId, senderIKPub, msg, apiBase) {
        const cached = await getCachedFileKey(myUserId, msg.message_id);
        if (cached) return;
        try {
            const wrapJson = await decryptGroup(myUserId, groupId, senderId, senderIKPub, msg, apiBase);
            const wrap     = JSON.parse(wrapJson);
            await cacheFileKey(myUserId, msg.message_id, { fk: wrap.fk, fi: wrap.fi, fa: wrap.fa });
        } catch (_) {}
    }

    // ── Public API ────────────────────────────────────────────────
    return {
        // Identity session
        setIdentitySession,
        getIdentitySession,
        hasIdentitySession,

        // Key generation & unlock
        generateKeys,
        unlockKeys,
        reEncryptKeys,
        generateSPK,
        generateOPKs,

        // Prekey IDB management
        saveSPKtoIDB,
        getSPKFromIDB,
        saveOPKsToIDB,
        getOPKFromIDB,

        // X3DH (exposed for testing/recovery)
        x3dhInitiate,
        x3dhRespond,

        // Personal messaging
        encryptPersonal,
        decryptPersonal,
        encryptPersonalFile,
        decryptPersonalFile,
        cachePersonalFileKey,

        // Group messaging
        encryptGroup,
        decryptGroup,
        encryptGroupFile,
        decryptGroupFile,
        cacheGroupFileKey,
        distributeSenderKey,
        decryptSKFromSender,
        saveSenderKeyToIDB,
        loadSenderKeyFromIDB,

        // Message cache
        cacheDecrypted,
        getCachedDecrypted,
        cacheFileKey,
        getCachedFileKey,

        // Session IDB
        savePersonalSession,
        loadPersonalSession,

        // Identity reset
        clearCryptoState,

        // Utilities
        bufToBase64,
        base64ToBuf
    };
})();
