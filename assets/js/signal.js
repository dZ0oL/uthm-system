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
    const PBKDF2_ITER = 100000;  // PBKDF2 iteration count — higher = harder to brute-force
    const IV_LEN      = 12;      // AES-GCM nonce size in bytes
    const SALT_LEN    = 16;      // Salt size for PBKDF2

    const IDB_NAME    = 'uthm_signal';  // IndexedDB database name
    const IDB_VER     = 1;

    const enc = new TextEncoder();
    const dec = new TextDecoder();

    // ── Utilities ────────────────────────────────────────────────

    // Convert a binary buffer to a base64 string (safe for large buffers)
    function bufToBase64(buf) {
        const bytes = buf instanceof Uint8Array ? buf : new Uint8Array(buf);
        let binary = '';
        const CHUNK = 0x8000;
        // Process in chunks to avoid stack overflow on large arrays
        for (let i = 0; i < bytes.length; i += CHUNK) {
            binary += String.fromCharCode(...bytes.subarray(i, Math.min(i + CHUNK, bytes.length)));
        }
        return btoa(binary);
    }

    // Convert a base64 string back to a Uint8Array
    function base64ToBuf(b64) {
        return Uint8Array.from(atob(b64), c => c.charCodeAt(0));
    }

    // Generate n random bytes using the browser's cryptographic RNG
    function randomBytes(n) {
        return crypto.getRandomValues(new Uint8Array(n));
    }

    // ── Crypto primitives ─────────────────────────────────────────

    // HKDF-SHA-256: stretches key material into a derived key of byteLen bytes
    async function hkdf(input, salt, info, byteLen) {
        const km = await crypto.subtle.importKey('raw', input, 'HKDF', false, ['deriveBits']);
        const bits = await crypto.subtle.deriveBits(
            { name: 'HKDF', hash: 'SHA-256', salt, info: enc.encode(info) },
            km, byteLen * 8
        );
        return new Uint8Array(bits);
    }

    // ECDH: combine our private key with the peer's public key to get a shared secret
    async function dhBits(privateKey, publicKeyJwk) {
        const pub = await crypto.subtle.importKey(
            'jwk', JSON.parse(publicKeyJwk),
            { name: 'ECDH', namedCurve: CURVE }, false, []
        );
        const bits = await crypto.subtle.deriveBits({ name: 'ECDH', public: pub }, privateKey, 256);
        return new Uint8Array(bits);
    }

    // Double Ratchet chain key KDF: advances the chain and returns [new_chain_key, message_key]
    async function kdfCK(ck) {
        const key = await crypto.subtle.importKey(
            'raw', ck, { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']
        );
        // Byte 0x01 derives the message key, byte 0x02 derives the next chain key
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
        // AES-GCM appends a 16-byte auth tag to the ciphertext — split them apart
        return {
            ciphertext: bufToBase64(out.slice(0, -16)),
            iv:         bufToBase64(iv),
            authTag:    bufToBase64(out.slice(-16))
        };
    }

    // AES-256-GCM decrypt: reattach the auth tag, then decrypt and return plaintext bytes
    async function aesDecrypt(keyBytes, ciphertext_b64, iv_b64, authTag_b64, aad) {
        const k        = await crypto.subtle.importKey('raw', keyBytes, { name: AES_MODE }, false, ['decrypt']);
        const _ct = base64ToBuf(ciphertext_b64);
        const _at = base64ToBuf(authTag_b64);
        // Web Crypto expects ciphertext+authTag as a single concatenated buffer
        const combined = new Uint8Array(_ct.length + _at.length);
        combined.set(_ct);
        combined.set(_at, _ct.length);
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

    // Generate a new ECDH keypair (used for X3DH identity key and ratchet keys)
    async function genECDH() {
        return crypto.subtle.generateKey({ name: 'ECDH', namedCurve: CURVE }, true, ['deriveBits']);
    }

    // Generate a new ECDSA keypair (used for signing SPK and sender key messages)
    async function genECDSA() {
        return crypto.subtle.generateKey({ name: 'ECDSA', namedCurve: CURVE }, true, ['sign', 'verify']);
    }

    // ── Password-based encryption ─────────────────────────────────

    // Derive an AES key from a password using PBKDF2 — slow by design to resist brute-force
    async function pwDeriveKey(password, salt) {
        const km = await crypto.subtle.importKey('raw', enc.encode(password), 'PBKDF2', false, ['deriveKey']);
        return crypto.subtle.deriveKey(
            { name: 'PBKDF2', salt, iterations: PBKDF2_ITER, hash: 'SHA-256' },
            km, { name: AES_MODE, length: 256 }, false, ['encrypt', 'decrypt']
        );
    }

    // Encrypt arbitrary string data with a password (used to protect private keys at rest)
    async function pwEncrypt(data, password) {
        const salt = randomBytes(SALT_LEN);
        const iv   = randomBytes(IV_LEN);
        const k    = await pwDeriveKey(password, salt);
        const buf  = await crypto.subtle.encrypt({ name: AES_MODE, iv }, k, enc.encode(data));
        const out  = new Uint8Array(buf);
        // Store salt and IV together (salt needed to re-derive the key on decrypt)
        return {
            encrypted: bufToBase64(out.slice(0, -16)),
            iv:        bufToBase64(salt) + '.' + bufToBase64(iv),
            authTag:   bufToBase64(out.slice(-16))
        };
    }

    // Decrypt data that was protected with pwEncrypt — throws if password is wrong
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

    // Open (or create) the local IDB database with all required object stores
    function openIDB() {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open(IDB_NAME, IDB_VER);
            req.onupgradeneeded = e => {
                const db = e.target.result;
                // Each store holds a different type of cryptographic state
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

    // Write a record to an IDB store (insert or overwrite)
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

    // Read a record from an IDB store by key
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

    // Delete a record from an IDB store by key
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

    // Wipe only the local message history caches (decrypted text + file keys).
    // Called after account recovery so pre-recovery messages (encrypted with old keys)
    // no longer appear in chat. Cryptographic key material (SPK, prekeys, sessions)
    // is intentionally preserved — _initSignalKeys already ran on page load and
    // registered fresh SPK/prekeys; deleting them here would cause a second key
    // registration on the next page load, invalidating any in-flight bundles.
    async function clearMessageHistory() {
        const db     = await openIDB();
        const stores = ['decrypted', 'file_keys'];
        await Promise.all(stores.map(store => new Promise((resolve, reject) => {
            const tx  = db.transaction(store, 'readwrite');
            const req = tx.objectStore(store).clear();
            req.onsuccess = () => resolve();
            req.onerror   = () => reject(req.error);
        })));
    }

    // ── In-memory identity session ────────────────────────────────

    // Holds the unlocked Signal identity keys in memory for the current page session
    let _session = null;

    // Store identity keys in memory after unlocking (never saved to server)
    function setIdentitySession(userId, IK_dh_priv, IK_sign_priv, IK_dh_pub_jwk, IK_sign_pub_jwk) {
        _session = { userId, IK_dh_priv, IK_sign_priv, IK_dh_pub_jwk, IK_sign_pub_jwk };
    }

    // Retrieve the in-memory identity session — throws if not unlocked yet
    function getIdentitySession() {
        if (!_session) throw new Error('Signal identity session not initialized');
        return _session;
    }

    // Check if keys are currently unlocked (used to decide whether Signal is available)
    function hasIdentitySession() { return _session !== null; }

    // ── Identity Key Generation & Unlock ──────────────────────────

    // [KEY GENERATION] Create brand-new Signal identity keys (IK) protected by the user's password
    // IK_dh = Diffie-Hellman key (for X3DH), IK_sign = signing key (for SPK authenticity)
    async function generateKeys(password) {
        const IK_dh   = await genECDH();
        const IK_sign = await genECDSA();

        // Export public keys (sent to server) and private keys (encrypted before storage)
        const ik_dh_pub_jwk    = JSON.stringify(await crypto.subtle.exportKey('jwk', IK_dh.publicKey));
        const ik_dh_priv_jwk   = JSON.stringify(await crypto.subtle.exportKey('jwk', IK_dh.privateKey));
        const ik_sign_pub_jwk  = JSON.stringify(await crypto.subtle.exportKey('jwk', IK_sign.publicKey));
        const ik_sign_priv_jwk = JSON.stringify(await crypto.subtle.exportKey('jwk', IK_sign.privateKey));

        // Encrypt private keys with the user's password before sending to server
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

    // [KEY UNLOCK] Decrypt IK private keys from server storage using the user's password
    async function unlockKeys(data, password) {
        const dh_priv_str   = await pwDecrypt(data.encrypted_ik_dh,   data.ik_dh_iv,   data.ik_dh_auth_tag,   password);
        const sign_priv_str = await pwDecrypt(data.encrypted_ik_sign, data.ik_sign_iv, data.ik_sign_auth_tag, password);

        // Re-import as non-extractable CryptoKey objects for use in WebCrypto operations
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
        // Decrypt with old password then re-encrypt with new password
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

    // [X3DH] Generate a Signed Prekey (SPK) — an ECDH key signed by the identity key
    // Senders verify this signature to confirm they're talking to the right person
    async function generateSPK(IK_sign_priv) {
        const spk_id  = Date.now();
        const SPK     = await genECDH();
        const pub_jwk  = JSON.stringify(await crypto.subtle.exportKey('jwk', SPK.publicKey));
        const priv_jwk = JSON.stringify(await crypto.subtle.exportKey('jwk', SPK.privateKey));

        // Sign the SPK public key with our identity signing key
        const sig = await crypto.subtle.sign(
            { name: 'ECDSA', hash: 'SHA-256' }, IK_sign_priv, enc.encode(pub_jwk)
        );

        return {
            spk_id,
            spk_public:    pub_jwk,
            spk_signature: bufToBase64(new Uint8Array(sig)),
            priv_jwk       // kept locally in IDB, never sent to server
        };
    }

    // Save SPK private key to IndexedDB so we can respond to X3DH initiations
    async function saveSPKtoIDB(spkData) {
        await idbPut('spk', { key: spkData.spk_id, pub_jwk: spkData.spk_public, priv_jwk: spkData.priv_jwk });
    }

    // Look up a stored SPK private key by its ID (needed when processing X3DH from a sender)
    async function getSPKFromIDB(spk_id) {
        return idbGet('spk', spk_id);
    }

    // ── One-Time Prekeys ─────────────────────────────────────────

    // [X3DH] Generate a batch of One-Time Prekeys (OPKs) — each used for exactly one session
    // Using an OPK adds forward secrecy: even if long-term keys are stolen, past sessions are safe
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

    // Save OPK private keys to IDB so they're available when a sender uses one
    async function saveOPKsToIDB(opks) {
        for (const opk of opks) {
            await idbPut('prekeys', { key: opk.key_id, priv_jwk: opk.priv_jwk });
        }
    }

    // Look up a stored OPK private key by its ID
    async function getOPKFromIDB(key_id) {
        return idbGet('prekeys', key_id);
    }

    // Delete an OPK after it has been used — each OPK is one-time use only
    async function deleteOPKFromIDB(key_id) {
        return idbDelete('prekeys', key_id);
    }

    // ── X3DH ──────────────────────────────────────────────────────

    // [X3DH SENDER SIDE] Alice initiates a session with Bob using his published key bundle
    // Performs 3-4 DH operations to create a shared secret SK that only Alice and Bob can derive
    async function x3dhInitiate(IK_dh_priv, IK_dh_pub_jwk, bundle) {
        // Step 1: Verify the SPK signature — confirms the bundle really belongs to Bob
        const IK_sign_pub = await crypto.subtle.importKey(
            'jwk', JSON.parse(bundle.ik_sign_public),
            { name: 'ECDSA', namedCurve: CURVE }, false, ['verify']
        );
        const sigValid = await crypto.subtle.verify(
            { name: 'ECDSA', hash: 'SHA-256' }, IK_sign_pub,
            base64ToBuf(bundle.spk_signature), enc.encode(bundle.spk_public)
        );
        if (!sigValid) throw new Error('SPK signature verification failed — identity mismatch');

        // Step 2: Generate an ephemeral key — used only for this one session
        const EK         = await genECDH();
        const EK_pub_jwk = JSON.stringify(await crypto.subtle.exportKey('jwk', EK.publicKey));

        // Step 3: Perform the four DH operations (F is a fixed padding constant)
        const F   = new Uint8Array(32).fill(0xFF);
        const DH1 = await dhBits(IK_dh_priv,    bundle.spk_public);   // IK_A × SPK_B
        const DH2 = await dhBits(EK.privateKey,  bundle.ik_dh_public); // EK_A × IK_B
        const DH3 = await dhBits(EK.privateKey,  bundle.spk_public);   // EK_A × SPK_B

        const parts = [F, DH1, DH2, DH3];
        let opk_id = null;

        // Step 4: If Bob has a One-Time Prekey, include it for extra forward secrecy
        if (bundle.opk_id && bundle.opk_public) {
            parts.push(await dhBits(EK.privateKey, bundle.opk_public)); // EK_A × OPK_B
            opk_id = bundle.opk_id;
        }

        // Step 5: Combine all DH outputs through HKDF to get the final shared secret SK
        const ikm = concatBytes(...parts);
        const SK  = await hkdf(ikm, new Uint8Array(32), 'WhisperText', 32);

        return { SK, EK_pub_jwk, spk_id: bundle.spk_id, opk_id };
    }

    // [X3DH RECEIVER SIDE] Bob processes Alice's X3DH message and derives the same SK
    async function x3dhRespond(IK_dh_priv, spk_priv_jwk, opk_priv_jwk, pkData) {
        const SPK_priv = await crypto.subtle.importKey(
            'jwk', JSON.parse(spk_priv_jwk),
            { name: 'ECDH', namedCurve: CURVE }, false, ['deriveBits']
        );

        // Bob mirrors the same DH operations as Alice, in reverse order
        const F   = new Uint8Array(32).fill(0xFF);
        const DH1 = await dhBits(SPK_priv,    pkData.ik_dh_pub); // SPK_B × IK_A
        const DH2 = await dhBits(IK_dh_priv,  pkData.ek_pub);    // IK_B  × EK_A
        const DH3 = await dhBits(SPK_priv,    pkData.ek_pub);    // SPK_B × EK_A

        const parts = [F, DH1, DH2, DH3];

        if (opk_priv_jwk && pkData.opk_id) {
            const OPK_priv = await crypto.subtle.importKey(
                'jwk', JSON.parse(opk_priv_jwk),
                { name: 'ECDH', namedCurve: CURVE }, false, ['deriveBits']
            );
            parts.push(await dhBits(OPK_priv, pkData.ek_pub));
            await deleteOPKFromIDB(pkData.opk_id); // one-time use — delete after use
        }

        const ikm = concatBytes(...parts);
        const SK  = await hkdf(ikm, new Uint8Array(32), 'WhisperText', 32);
        return { SK };
    }

    // Concatenate multiple Uint8Arrays into one
    function concatBytes(...arrays) {
        const total = arrays.reduce((s, a) => s + a.length, 0);
        const out   = new Uint8Array(total);
        let   off   = 0;
        for (const a of arrays) { out.set(a, off); off += a.length; }
        return out;
    }

    // ── Personal Session (Symmetric Ratchet) ─────────────────────

    // [DOUBLE RATCHET] Initialise the ratchet state from the X3DH shared secret SK
    // Both parties derive two independent chain keys from SK.
    // Initiator: sends on chain_A, receives on chain_B.
    // Responder: sends on chain_B, receives on chain_A.
    // dhsData: { pub_jwk, priv_jwk } — pass SPK data for responder; omit for initiator (fresh keypair generated)
    // dhrPubJwk: initiator's EK pub JWK string for responder; null for initiator
    async function initPersonalSession(SK, isInitiator, dhsData = null, dhrPubJwk = null) {
        const zero    = new Uint8Array(32);
        // Derive two chain keys and a root key from the shared secret
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
            send_Ns:      0,   // message counter for sending chain
            recv_Nr:      0,   // message counter for receiving chain
            recv_skipped: {},  // stores keys for out-of-order messages
            root_key:     bufToBase64(final_root_key),
            DHs,               // our current DH ratchet keypair
            DHr:          dhrPubJwk || null,  // peer's last known ratchet public key
            send_PN:      0
        };
    }

    // Save a personal session to IDB (private key is stored as JWK string, not CryptoKey)
    async function savePersonalSession(myId, peerId, state) {
        // Exclude priv_key (CryptoKey is not JSON-serialisable) — priv_jwk string is kept for re-import on load
        const serializable = { ...state };
        if (state.DHs) {
            serializable.DHs = { pub_jwk: state.DHs.pub_jwk, priv_jwk: state.DHs.priv_jwk };
        }
        await idbPut('sessions', { key: `${myId}_${peerId}`, state: JSON.stringify(serializable) });
    }

    // Load a personal session from IDB and re-import the DH private key as a CryptoKey
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

    // [DOUBLE RATCHET] Advance the sending chain by one step and return the next message key
    async function advanceSendChain(session) {
        const [ck2, mk] = await kdfCK(base64ToBuf(session.send_CK));
        session.send_CK = bufToBase64(ck2);
        session.send_Ns++;
        return mk; // Uint8Array — use this to AES-encrypt the message
    }

    // [DOUBLE RATCHET] Advance the receiving chain to message number targetN, caching skipped keys
    async function advanceRecvChain(session, targetN) {
        // Namespace skipped keys by DH epoch to avoid counter collisions across ratchet steps
        const epoch = session.DHr ? session.DHr.substring(0, 8) : 'init';
        // Cache keys for any skipped messages (in case they arrive out of order)
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
    // [DOUBLE RATCHET] Triggered when the receiver sees a new DH public key in an incoming header.
    // Advances root key twice: once to derive the new recv chain, once for the new send chain.
    // This gives "break-in recovery" — a new DH exchange heals forward secrecy after a compromise.
    async function advanceDHRatchet(session, newDHr_pub_jwk) {
        session.send_PN = session.send_Ns;  // save previous chain length for out-of-order handling
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

    // Look up a previously decrypted message from IDB to avoid re-running the chain
    async function getCachedDecrypted(userId, msgId) {
        return idbGet('decrypted', `${userId}_msg_${msgId}`);
    }

    // Cache the file key data for a file message (so download doesn't re-decrypt the chain)
    async function cacheFileKey(userId, msgId, data) {
        await idbPut('file_keys', { key: `${userId}_file_${msgId}`, ...data });
    }

    // Retrieve a cached file key (fk, fi, fa) by message ID
    async function getCachedFileKey(userId, msgId) {
        return idbGet('file_keys', `${userId}_file_${msgId}`);
    }

    // ── Personal Message Encrypt ──────────────────────────────────

    // [DOUBLE RATCHET ENCRYPT] Encrypt a personal message for one recipient
    // Returns: { message_content, iv, auth_tag, signal_header, signal_prekey_data }
    async function encryptPersonal(myUserId, peerUserId, plaintext, peerBundle) {
        const { IK_dh_priv, IK_dh_pub_jwk } = getIdentitySession();

        let session    = await loadPersonalSession(myUserId, peerUserId);
        let pkeyData   = null;

        if (session && peerBundle) {
            // Drop session if the peer's IK or SPK changed.
            // IK changes on full key reset (SSS account recovery) — this is the primary
            // guard against sending to a stale session after a peer's account recovery.
            // SPK changes on routine rotation or new-device login.
            const ikChanged  = session.peer_ik_pub && peerBundle.ik_dh_public
                                && session.peer_ik_pub !== peerBundle.ik_dh_public;
            const spkChanged = peerBundle.spk_id
                                && (!session.peer_spk_id || session.peer_spk_id !== peerBundle.spk_id);
            if (ikChanged || spkChanged) {
                // Peer's identity changed — start a fresh X3DH session
                await idbDelete('sessions', `${myUserId}_${peerUserId}`);
                session = null;
            }
        }

        if (!session) {
            // No existing session — run X3DH to establish a new one
            const x3dh = await x3dhInitiate(IK_dh_priv, IK_dh_pub_jwk, peerBundle);
            // Pass Bob's SPK as DHr so the initial send_CK is DH-derived (Signal spec requirement)
            session    = await initPersonalSession(x3dh.SK, true, null, peerBundle.spk_public);
            session.peer_spk_id = x3dh.spk_id;
            session.peer_ik_pub = peerBundle.ik_dh_public; // track peer IK to detect recovery later
            pkeyData   = { ik_dh_pub: IK_dh_pub_jwk, ek_pub: x3dh.EK_pub_jwk, spk_id: x3dh.spk_id, opk_id: x3dh.opk_id };
        }

        // Advance the sending chain and use the derived message key to encrypt
        const mk     = await advanceSendChain(session);
        const msgN   = session.send_Ns - 1;
        const header = { n: msgN, pn: session.send_PN, dh: session.DHs.pub_jwk };
        const aad    = enc.encode(JSON.stringify(header)); // header is AAD — protects against reordering
        const result = await aesEncrypt(mk, enc.encode(plaintext), aad);

        await savePersonalSession(myUserId, peerUserId, session);

        return {
            message_content:    result.ciphertext,
            iv:                 result.iv,
            auth_tag:           result.authTag,
            signal_header:      JSON.stringify(header),
            signal_prekey_data: pkeyData ? JSON.stringify(pkeyData) : null // only for first message
        };
    }

    // ── Personal Message Decrypt ──────────────────────────────────

    // [DOUBLE RATCHET DECRYPT] Decrypt a personal message from a peer
    // Returns: decrypted plaintext string
    async function decryptPersonal(myUserId, peerUserId, msg) {
        // Return cached plaintext if already decrypted this session
        const cached = await getCachedDecrypted(myUserId, msg.message_id);
        if (cached) return cached.text;

        const { IK_dh_priv } = getIdentitySession();
        let session = await loadPersonalSession(myUserId, peerUserId);

        if (!session) {
            // No session in IDB — this must be the first message, so signal_prekey_data must exist
            if (!msg.signal_prekey_data) {
                throw new Error('Message was encrypted in a previous session — ask sender to resend');
            }
            const pkData = JSON.parse(msg.signal_prekey_data);
            // Look up the SPK private key the sender used
            const spkRec = await getSPKFromIDB(pkData.spk_id);
            if (!spkRec) throw new Error('Session key unavailable on this device — ask sender to resend');

            let opk_jwk = null;
            if (pkData.opk_id) {
                const opkRec = await getOPKFromIDB(pkData.opk_id);
                opk_jwk = opkRec ? opkRec.priv_jwk : null;
            }

            // Run X3DH respond to derive the same SK the sender computed
            const { SK } = await x3dhRespond(IK_dh_priv, spkRec.priv_jwk, opk_jwk, pkData);
            session = await initPersonalSession(SK, false,
                { pub_jwk: spkRec.pub_jwk, priv_jwk: spkRec.priv_jwk },
                null  // Responder has no peer ratchet key yet; DHr is set when first header.dh arrives
            );
        }

        const header = JSON.parse(msg.signal_header);
        let mk;

        // DH ratchet: advance when peer presents a new ratchet public key in the header
        if (header.dh && header.dh !== session.DHr) {
            await advanceDHRatchet(session, header.dh);
        }

        // Check if this message key was already cached (out-of-order delivery)
        const epoch      = header.dh ? header.dh.substring(0, 8) : 'init';
        const skippedKey = `${epoch}:${header.n}`;

        if (session.recv_skipped && session.recv_skipped[skippedKey] !== undefined) {
            mk = base64ToBuf(session.recv_skipped[skippedKey]);
            delete session.recv_skipped[skippedKey];
        } else {
            mk = await advanceRecvChain(session, header.n);
        }

        // Decrypt the message — header is used as AAD to prevent header tampering
        const aad   = enc.encode(JSON.stringify(header));
        const plain = await aesDecrypt(mk, msg.message_content, msg.iv, msg.auth_tag, aad);
        const text  = dec.decode(plain);

        await savePersonalSession(myUserId, peerUserId, session);
        await cacheDecrypted(myUserId, msg.message_id, text); // cache so repeat decryption is free

        return text;
    }

    // ── Personal File Encrypt ─────────────────────────────────────

    // Encrypt a file for a personal chat recipient
    // message_content = encrypted JSON {fk, fi, fa} (file key wrapped in ratchet).
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
            // Establish a fresh X3DH session before encrypting
            const x3dh = await x3dhInitiate(IK_dh_priv, IK_dh_pub_jwk, peerBundle);
            session    = await initPersonalSession(x3dh.SK, true, null, peerBundle.spk_public);
            session.peer_spk_id = x3dh.spk_id;
            pkeyData   = { ik_dh_pub: IK_dh_pub_jwk, ek_pub: x3dh.EK_pub_jwk, spk_id: x3dh.spk_id, opk_id: x3dh.opk_id };
        }

        // Step 1: Encrypt the file with a fresh random AES key (FK)
        // Encrypt file directly via Web Crypto API — no base64 roundtrip on large buffers
        const FK      = randomBytes(32);
        const fileIv  = randomBytes(IV_LEN);
        const fileKey = await crypto.subtle.importKey('raw', FK, { name: AES_MODE }, false, ['encrypt']);
        const fileBuf = await crypto.subtle.encrypt(
            { name: AES_MODE, iv: fileIv, additionalData: new Uint8Array(0) }, fileKey, fileBuffer
        );
        const fileOut     = new Uint8Array(fileBuf);
        const cipherBytes = fileOut.slice(0, -16);
        const fileAuthTag = fileOut.slice(-16);

        const fkB64 = bufToBase64(FK);
        const fiB64 = bufToBase64(fileIv);
        const faB64 = bufToBase64(fileAuthTag);

        // Step 2: Wrap the file key (FK) in the Double Ratchet chain so only the recipient can read it
        const mk     = await advanceSendChain(session);
        const msgN   = session.send_Ns - 1;
        const header = { n: msgN, pn: session.send_PN, dh: session.DHs.pub_jwk };
        const aad    = enc.encode(JSON.stringify(header));
        const wrapPayload = JSON.stringify({ fk: fkB64, fi: fiB64, fa: faB64 });
        const wrapped = await aesEncrypt(mk, enc.encode(wrapPayload), aad);

        // Step 3: ECDH fallback — also encrypt FK with IK-to-IK shared secret
        // Allows the receiver to get FK even if their Signal session IDB is lost
        // ECDH(sender_IK_priv, receiver_IK_pub) == ECDH(receiver_IK_priv, sender_IK_pub)
        const sharedFB   = await dhBits(IK_dh_priv, peerBundle.ik_dh_public);
        const fbEncKey   = await hkdf(sharedFB, new Uint8Array(32), 'UTHMFileKeyFallback', 32);
        const fbPayload  = JSON.stringify({ fk: fkB64, fi: fiB64, fa: faB64 });
        const fbEnc      = await aesEncrypt(fbEncKey, enc.encode(fbPayload), new Uint8Array(0));
        const ecdhFileKey = JSON.stringify({ ct: fbEnc.ciphertext, iv: fbEnc.iv, at: fbEnc.authTag });

        await savePersonalSession(myUserId, peerUserId, session);

        return {
            payload: {
                message_content:    wrapped.ciphertext, // Signal-wrapped file key
                iv:                 wrapped.iv,
                auth_tag:           wrapped.authTag,
                signal_header:      JSON.stringify(header),
                signal_prekey_data: pkeyData ? JSON.stringify(pkeyData) : null,
                ecdh_file_key:      ecdhFileKey // fallback file key
            },
            encryptedBuffer: cipherBytes.buffer, // the actual encrypted file bytes
            fileKeyData:     { fk: fkB64, fi: fiB64, fa: faB64 }
        };
    }

    // Decrypt personal file — call after loading the message, uses cached file key
    async function decryptPersonalFile(myUserId, peerUserId, msg, encryptedBuffer) {
        // Try cache first (msg already processed by decryptPersonal)
        let fileKeyData = await getCachedFileKey(myUserId, msg.message_id);

        if (!fileKeyData) {
            try {
                // Primary path: Signal double-ratchet wrapper (decryptPersonal returns the FK JSON)
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

        // Use the file key to decrypt the actual file bytes
        const FK      = base64ToBuf(fileKeyData.fk);
        const fileKey = await crypto.subtle.importKey('raw', FK, { name: AES_MODE }, false, ['decrypt']);
        const authTag = base64ToBuf(fileKeyData.fa);
        const encArr  = new Uint8Array(encryptedBuffer);
        // Reattach auth tag so WebCrypto can verify integrity
        const combined = new Uint8Array(encArr.length + authTag.length);
        combined.set(encArr);
        combined.set(authTag, encArr.length);
        return crypto.subtle.decrypt(
            { name: AES_MODE, iv: base64ToBuf(fileKeyData.fi), additionalData: new Uint8Array(0) },
            fileKey, combined
        );
    }

    // Pre-cache the file key during message load so the download button works instantly
    async function cachePersonalFileKey(myUserId, peerUserId, msg) {
        const cached = await getCachedFileKey(myUserId, msg.message_id);
        if (cached) return;
        try {
            // Primary: derive file key via Signal ratchet
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

    // [SENDER KEY] Generate a new sender key state for group messaging
    // CK = chain key (ratchets forward each message), sign key = authenticates messages
    async function generateSenderKey() {
        const sigKP        = await genECDSA();
        const sign_pub_jwk  = JSON.stringify(await crypto.subtle.exportKey('jwk', sigKP.publicKey));
        const sign_priv_jwk = JSON.stringify(await crypto.subtle.exportKey('jwk', sigKP.privateKey));
        return {
            CK:            bufToBase64(randomBytes(32)), // starting chain key
            iteration:     0,                            // message counter
            sign_pub_jwk,
            sign_priv_jwk
        };
    }

    // Encrypt our sender key state for a group member using ECDH (IK-to-IK shared secret)
    async function encryptSKForMember(skState, myIK_dh_priv, memberIK_pub_jwk) {
        const shared  = await dhBits(myIK_dh_priv, memberIK_pub_jwk);
        const encKey  = await hkdf(shared, new Uint8Array(32), 'UTHMSKDist', 32);
        // Only distribute the chain key and signing public key — never the signing private key
        const payload = JSON.stringify({ CK: skState.CK, iteration: skState.iteration, sign_pub_jwk: skState.sign_pub_jwk });
        return aesEncrypt(encKey, enc.encode(payload), new Uint8Array(0));
    }

    // Decrypt a sender key distribution received from a group member
    async function decryptSKFromSender(encDist, myIK_dh_priv, senderIK_pub_jwk) {
        // Derive the same ECDH shared secret the sender used
        const shared  = await dhBits(myIK_dh_priv, senderIK_pub_jwk);
        const encKey  = await hkdf(shared, new Uint8Array(32), 'UTHMSKDist', 32);
        const plain   = await aesDecrypt(encKey, encDist.ciphertext, encDist.iv, encDist.authTag, new Uint8Array(0));
        return JSON.parse(dec.decode(plain));
    }

    // Save a group sender key state to IDB keyed by groupId + senderId
    async function saveSenderKeyToIDB(groupId, senderId, skState) {
        await idbPut('sender_keys', { key: `${groupId}_${senderId}`, state: JSON.stringify(skState) });
    }

    // Load a group sender key state from IDB
    async function loadSenderKeyFromIDB(groupId, senderId) {
        const rec = await idbGet('sender_keys', `${groupId}_${senderId}`);
        return rec ? JSON.parse(rec.state) : null;
    }

    // ── Sender Key Encrypt ────────────────────────────────────────

    // [SENDER KEY ENCRYPT] Encrypt a group message using the sender's chain key
    // Returns: { message_content, iv, auth_tag, signal_header } + updated skState
    async function skEncrypt(skState, plaintext) {
        // Advance the chain to derive this message's key
        const [ck2, mk] = await kdfCK(base64ToBuf(skState.CK));
        const iter      = skState.iteration;
        skState.CK      = bufToBase64(ck2);
        skState.iteration++;

        const result = await aesEncrypt(mk, enc.encode(plaintext), new Uint8Array(0));

        // Sign the ciphertext so group members can verify it came from us
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
            // signal_header carries the chain iteration number and signature
            signal_header:   JSON.stringify({ type: 'sk', iter, sig: bufToBase64(new Uint8Array(sig)) })
        };
    }

    // ── Sender Key Decrypt ────────────────────────────────────────

    // [SENDER KEY DECRYPT] Decrypt a group message from a specific sender
    async function skDecrypt(skState, msg) {
        const header   = JSON.parse(msg.signal_header);
        const targetIter = header.iter; // which iteration of the sender's chain was used

        // Cache skipped iterations in case messages arrive out of order
        skState.sk_skipped = skState.sk_skipped || {};

        let mk;
        if (skState.sk_skipped[targetIter] !== undefined) {
            // Use previously saved skipped key
            mk = base64ToBuf(skState.sk_skipped[targetIter]);
            delete skState.sk_skipped[targetIter];
        } else {
            // Advance the chain, caching any skipped message keys along the way
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

        // Verify the ECDSA signature to confirm the message came from the real sender
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

    // [GROUP ENCRYPT] Encrypt a group text message using the sender key
    // members: [{userId, ik_dh_public}] — needed only when distributing SK for the first time
    async function encryptGroup(myUserId, groupId, plaintext, members, apiBase) {
        const { IK_dh_priv, IK_dh_pub_jwk, IK_sign_priv } = getIdentitySession();

        let skState = await loadSenderKeyFromIDB(groupId, myUserId);

        if (!skState || !skState.sign_priv_jwk) {
            // First message to this group — generate and distribute a new sender key
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

    // Distribute our sender key to each group member by encrypting it with their IK
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
            // POST sender key distributions to server for members to retrieve
            await fetch(`${apiBase}/signal_save_sender_key.php`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ group_id: groupId, distributions })
            });
        } catch (_) {}
    }

    // [GROUP DECRYPT] Decrypt a group text message from another member
    async function decryptGroup(myUserId, groupId, senderId, senderIKPub, msg, apiBase) {
        // Return cached plaintext if already decrypted
        const cached = await getCachedDecrypted(myUserId, msg.message_id);
        if (cached) return cached.text;

        let skState = await loadSenderKeyFromIDB(groupId, senderId);

        if (!skState) {
            // No local sender key — fetch it from the server distribution
            skState = await fetchAndSetSenderKey(myUserId, groupId, senderId, senderIKPub, apiBase);
        }

        if (!skState) throw new Error('Sender key not available — cannot decrypt group message');

        let text;
        try {
            text = await skDecrypt(skState, msg);
        } catch (_) {
            // IDB state is stale (e.g. sender regenerated their key after account recovery).
            // Delete the cached state and fetch the latest distribution from server, then retry.
            await idbDelete('sender_keys', `${groupId}_${senderId}`);
            const freshState = await fetchAndSetSenderKey(myUserId, groupId, senderId, senderIKPub, apiBase);
            if (!freshState) throw new Error('Sender key not available — ask sender to send a new message');
            text = await skDecrypt(freshState, msg);
            await saveSenderKeyToIDB(groupId, senderId, freshState);
            await cacheDecrypted(myUserId, msg.message_id, text);
            return text;
        }

        await saveSenderKeyToIDB(groupId, senderId, skState);
        await cacheDecrypted(myUserId, msg.message_id, text);

        return text;
    }

    // Fetch the sender key distribution from the server and decrypt it with our IK
    async function fetchAndSetSenderKey(myUserId, groupId, senderId, senderIKPub, apiBase) {
        try {
            const { IK_dh_priv } = getIdentitySession();
            const res  = await fetch(`${apiBase}/signal_get_sender_key.php?group_id=${groupId}&sender_id=${senderId}`);
            const data = await res.json();
            if (!data.success || !data.distribution) return null;

            // Decrypt the distribution using ECDH with the sender's IK
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

    // [GROUP FILE ENCRYPT] Encrypt a file for a group chat using the sender key
    async function encryptGroupFile(myUserId, groupId, fileBuffer, members, apiBase) {
        const { IK_dh_priv } = getIdentitySession();

        let skState = await loadSenderKeyFromIDB(groupId, myUserId);

        if (!skState || !skState.sign_priv_jwk) {
            skState = await generateSenderKey();
            // Distribute before encrypting — same reason as encryptGroup.
            await distributeSenderKey(skState, myUserId, groupId, members, IK_dh_priv, apiBase);
        }

        // Step 1: Encrypt the file with a fresh random AES key
        // Encrypt file directly via Web Crypto API — no base64 roundtrip on large buffers
        const FK      = randomBytes(32);
        const fileIv  = randomBytes(IV_LEN);
        const fileKey = await crypto.subtle.importKey('raw', FK, { name: AES_MODE }, false, ['encrypt']);
        const fileBuf = await crypto.subtle.encrypt(
            { name: AES_MODE, iv: fileIv, additionalData: new Uint8Array(0) }, fileKey, fileBuffer
        );
        const fileOut     = new Uint8Array(fileBuf);
        const cipherBytes = fileOut.slice(0, -16);
        const fileAuthTag = fileOut.slice(-16);

        const fkB64 = bufToBase64(FK);
        const fiB64 = bufToBase64(fileIv);
        const faB64 = bufToBase64(fileAuthTag);

        // Step 2: Wrap the file key in the sender key chain so group members can get it
        const wrapStr = JSON.stringify({ fk: fkB64, fi: fiB64, fa: faB64 });
        const result  = await skEncrypt(skState, wrapStr);

        await saveSenderKeyToIDB(groupId, myUserId, skState);

        return {
            payload: {
                message_content: result.message_content, // sender-key-wrapped file key
                iv:              result.iv,
                auth_tag:        result.auth_tag,
                signal_header:   result.signal_header
            },
            encryptedBuffer: cipherBytes.buffer, // the actual encrypted file bytes
            fileKeyData:     { fk: fkB64, fi: fiB64, fa: faB64 }
        };
    }

    // [GROUP FILE DECRYPT] Decrypt a group file using the cached or fetched sender key
    async function decryptGroupFile(myUserId, groupId, senderId, senderIKPub, msg, encryptedBuffer, apiBase) {
        let fileKeyData = await getCachedFileKey(myUserId, msg.message_id);

        if (!fileKeyData) {
            // Decrypt the sender key wrapper to get the file key
            const wrapJson = await decryptGroup(myUserId, groupId, senderId, senderIKPub, msg, apiBase);
            const wrap     = JSON.parse(wrapJson);
            fileKeyData    = { fk: wrap.fk, fi: wrap.fi, fa: wrap.fa };
            await cacheFileKey(myUserId, msg.message_id, fileKeyData);
        }

        // Use the extracted file key to decrypt the actual file bytes
        const FK      = base64ToBuf(fileKeyData.fk);
        const fileKey = await crypto.subtle.importKey('raw', FK, { name: AES_MODE }, false, ['decrypt']);
        const authTag = base64ToBuf(fileKeyData.fa);
        const encArr  = new Uint8Array(encryptedBuffer);
        const combined = new Uint8Array(encArr.length + authTag.length);
        combined.set(encArr);
        combined.set(authTag, encArr.length);
        return crypto.subtle.decrypt(
            { name: AES_MODE, iv: base64ToBuf(fileKeyData.fi), additionalData: new Uint8Array(0) },
            fileKey, combined
        );
    }

    // Pre-cache group file key during message load so the download button works instantly
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
        clearMessageHistory,

        // Utilities
        bufToBase64,
        base64ToBuf
    };
})();
