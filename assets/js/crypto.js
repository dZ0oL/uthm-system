/**
 * crypto.js
 * ================================================================
 * UTHM Bursary Secure Messaging System
 * Browser-side cryptography library
 *
 * ALL crypto operations happen here — in the browser.
 * PHP server never sees any plaintext or raw private keys.
 *
 * Uses: Web Crypto API (built into all modern browsers — no library needed)
 *
 * Handles:
 *   1. ECDH P-256 key pair generation
 *   2. AES-256-GCM message encryption / decryption
 *   3. Private key protection (encrypted with user password)
 *   4. SSS share storage in IndexedDB (share 1)
 *   5. Key derivation from password (PBKDF2)
 *   6. File encryption / decryption (personal + group)
 * ================================================================
 */

const UTHMCrypto = (() => {

  // ── Constants ────────────────────────────────────────────────
  const CURVE        = 'P-256';
  const AES_MODE     = 'AES-GCM';
  const AES_LENGTH   = 256;
  const PBKDF2_ITER  = 100000;
  const PBKDF2_HASH  = 'SHA-256';
  const IV_LENGTH    = 12;
  const SALT_LENGTH  = 16;

  const IDB_NAME    = 'uthm_secure';
  const IDB_VERSION = 1;
  const IDB_STORE   = 'shares';

  const enc = new TextEncoder();
  const dec = new TextDecoder();

  // ================================================================
  // SECTION 1 — HELPER UTILITIES
  // ================================================================

  function bufToBase64(buf) {
    const bytes = buf instanceof Uint8Array ? buf : new Uint8Array(buf);
    let binary = '';
    const CHUNK = 0x8000;
    for (let i = 0; i < bytes.length; i += CHUNK) {
      binary += String.fromCharCode(...bytes.subarray(i, Math.min(i + CHUNK, bytes.length)));
    }
    return btoa(binary);
  }

  function base64ToBuf(b64) {
    return Uint8Array.from(atob(b64), c => c.charCodeAt(0));
  }

  function bufToHex(buf) {
    return Array.from(new Uint8Array(buf))
      .map(b => b.toString(16).padStart(2, '0'))
      .join('');
  }

  async function sha256Hex(buffer) {
    const hash = await crypto.subtle.digest('SHA-256', buffer);
    return bufToHex(hash);
  }

  function randomBytes(length) {
    return crypto.getRandomValues(new Uint8Array(length));
  }

  // ================================================================
  // SECTION 2 — PASSWORD-BASED KEY DERIVATION (PBKDF2)
  // ================================================================

  async function deriveKeyFromPassword(password, salt) {
    const keyMaterial = await crypto.subtle.importKey(
      'raw',
      enc.encode(password),
      'PBKDF2',
      false,
      ['deriveKey']
    );
    return crypto.subtle.deriveKey(
      { name: 'PBKDF2', salt, iterations: PBKDF2_ITER, hash: PBKDF2_HASH },
      keyMaterial,
      { name: AES_MODE, length: AES_LENGTH },
      false,
      ['encrypt', 'decrypt']
    );
  }

  // ================================================================
  // SECTION 3 — ECDH KEY PAIR GENERATION
  // ================================================================

  async function generateKeyPair(password) {
    const keyPair = await crypto.subtle.generateKey(
      { name: 'ECDH', namedCurve: CURVE },
      true,
      ['deriveKey', 'deriveBits']
    );

    const publicKeyJwk    = await crypto.subtle.exportKey('jwk', keyPair.publicKey);
    const privateKeyJwk   = await crypto.subtle.exportKey('jwk', keyPair.privateKey);
    const privateKeyBytes = enc.encode(JSON.stringify(privateKeyJwk));

    const salt        = randomBytes(SALT_LENGTH);
    const iv          = randomBytes(IV_LENGTH);
    const passwordKey = await deriveKeyFromPassword(password, salt);

    const encryptedBuffer = await crypto.subtle.encrypt(
      { name: AES_MODE, iv },
      passwordKey,
      privateKeyBytes
    );

    const encryptedBytes = new Uint8Array(encryptedBuffer);
    const ciphertext     = encryptedBytes.slice(0, -16);
    const authTag        = encryptedBytes.slice(-16);
    const keyHash        = await sha256Hex(privateKeyBytes);

    return {
      publicKeyJwk:        JSON.stringify(publicKeyJwk),
      encryptedPrivateKey: bufToBase64(ciphertext),
      keyIv:               bufToBase64(salt) + '.' + bufToBase64(iv),
      keyAuthTag:          bufToBase64(authTag),
      keyHash
    };
  }

  // ================================================================
  // SECTION 4 — PRIVATE KEY UNLOCK (LOGIN — non-extractable)
  // ================================================================

  /**
   * Unlock private key for SESSION USE.
   * Returns a NON-EXTRACTABLE key — secure for messaging.
   * Cannot be exported or re-used for password change.
   */
  async function unlockPrivateKey(encryptedPrivateKey, keyIv, keyAuthTag, password) {
    const privateKeyBytes = await _decryptPrivateKeyBytes(
      encryptedPrivateKey, keyIv, keyAuthTag, password
    );

    const privateKeyJwk = JSON.parse(dec.decode(privateKeyBytes));
    return crypto.subtle.importKey(
      'jwk',
      privateKeyJwk,
      { name: 'ECDH', namedCurve: CURVE },
      false,   // NOT extractable — session use only
      ['deriveKey', 'deriveBits']
    );
  }

  // ================================================================
  // SECTION 4b — PRIVATE KEY UNLOCK (PASSWORD CHANGE — extractable)
  // ================================================================

  /**
   * Unlock private key for PASSWORD CHANGE USE ONLY.
   * Returns an EXTRACTABLE key so it can be re-exported
   * and re-encrypted with the new password.
   * Never store this in the session — use unlockPrivateKey for that.
   */
  async function unlockPrivateKeyExtractable(encryptedPrivateKey, keyIv, keyAuthTag, password) {
    const privateKeyBytes = await _decryptPrivateKeyBytes(
      encryptedPrivateKey, keyIv, keyAuthTag, password
    );

    const privateKeyJwk = JSON.parse(dec.decode(privateKeyBytes));
    return crypto.subtle.importKey(
      'jwk',
      privateKeyJwk,
      { name: 'ECDH', namedCurve: CURVE },
      true,    // EXTRACTABLE — for re-encryption with new password only
      ['deriveKey', 'deriveBits']
    );
  }

  /**
   * Shared decryption logic for both unlock functions.
   * Returns raw decrypted bytes (not yet imported as CryptoKey).
   */
  async function _decryptPrivateKeyBytes(encryptedPrivateKey, keyIv, keyAuthTag, password) {
    const [saltB64, ivB64] = keyIv.split('.');
    const salt       = base64ToBuf(saltB64);
    const iv         = base64ToBuf(ivB64);
    const ciphertext = base64ToBuf(encryptedPrivateKey);
    const authTag    = base64ToBuf(keyAuthTag);

    const combined = new Uint8Array(ciphertext.length + authTag.length);
    combined.set(ciphertext);
    combined.set(authTag, ciphertext.length);

    const passwordKey = await deriveKeyFromPassword(password, salt);

    try {
      return await crypto.subtle.decrypt(
        { name: AES_MODE, iv },
        passwordKey,
        combined
      );
    } catch {
      throw new Error('Wrong password or corrupted key data');
    }
  }

  // ================================================================
  // SECTION 5 — ECDH SHARED SECRET + AES KEY DERIVATION
  // ================================================================

  async function deriveSharedKey(myPrivateKey, theirPublicKeyJwk) {
    const theirPublicKey = await crypto.subtle.importKey(
      'jwk',
      JSON.parse(theirPublicKeyJwk),
      { name: 'ECDH', namedCurve: CURVE },
      false,
      []
    );
    return crypto.subtle.deriveKey(
      { name: 'ECDH', public: theirPublicKey },
      myPrivateKey,
      { name: AES_MODE, length: AES_LENGTH },
      false,
      ['encrypt', 'decrypt']
    );
  }

  // ================================================================
  // SECTION 6 — MESSAGE ENCRYPTION
  // ================================================================

  async function encryptMessage(plaintext, senderPrivateKey, recipientPublicKeyJwk) {
    const sharedKey = await deriveSharedKey(senderPrivateKey, recipientPublicKeyJwk);
    const iv        = randomBytes(IV_LENGTH);

    const encryptedBuffer = await crypto.subtle.encrypt(
      { name: AES_MODE, iv },
      sharedKey,
      enc.encode(plaintext)
    );

    const encryptedBytes = new Uint8Array(encryptedBuffer);
    const ciphertext     = encryptedBytes.slice(0, -16);
    const authTag        = encryptedBytes.slice(-16);

    return {
      ciphertext: bufToBase64(ciphertext),
      iv:         bufToBase64(iv),
      authTag:    bufToBase64(authTag)
    };
  }

  async function encryptGroupMessage(plaintext, senderPrivateKey, members) {
    const messageKey = await crypto.subtle.generateKey(
      { name: AES_MODE, length: AES_LENGTH },
      true,
      ['encrypt', 'decrypt']
    );

    const iv = randomBytes(IV_LENGTH);
    const encryptedBuffer = await crypto.subtle.encrypt(
      { name: AES_MODE, iv },
      messageKey,
      enc.encode(plaintext)
    );

    const encryptedBytes = new Uint8Array(encryptedBuffer);
    const ciphertext     = encryptedBytes.slice(0, -16);
    const authTag        = encryptedBytes.slice(-16);

    const rawMessageKey = await crypto.subtle.exportKey('raw', messageKey);

    const encryptedKeys = {};
    for (const member of members) {
      const sharedKey = await deriveSharedKey(senderPrivateKey, member.publicKeyJwk);
      const keyIv     = randomBytes(IV_LENGTH);

      const encKeyBuffer = await crypto.subtle.encrypt(
        { name: AES_MODE, iv: keyIv },
        sharedKey,
        rawMessageKey
      );

      const encKeyBytes = new Uint8Array(encKeyBuffer);
      encryptedKeys[member.userId] = {
        encryptedKey: bufToBase64(encKeyBytes.slice(0, -16)),
        keyIv:        bufToBase64(keyIv),
        keyAuthTag:   bufToBase64(encKeyBytes.slice(-16))
      };
    }

    return {
      ciphertext:    bufToBase64(ciphertext),
      iv:            bufToBase64(iv),
      authTag:       bufToBase64(authTag),
      encryptedKeys
    };
  }

  // ================================================================
  // SECTION 7 — MESSAGE DECRYPTION
  // ================================================================

  async function decryptMessage(ciphertext, iv, authTag, recipientPrivateKey, senderPublicKeyJwk) {
    const sharedKey = await deriveSharedKey(recipientPrivateKey, senderPublicKeyJwk);

    const combined = new Uint8Array([
      ...base64ToBuf(ciphertext),
      ...base64ToBuf(authTag)
    ]);

    let decrypted;
    try {
      decrypted = await crypto.subtle.decrypt(
        { name: AES_MODE, iv: base64ToBuf(iv) },
        sharedKey,
        combined
      );
    } catch {
      return '[Decryption failed — key mismatch or tampered message]';
    }

    return dec.decode(decrypted);
  }

  async function decryptGroupMessage(ciphertext, iv, authTag, myEncryptedKey, myPrivateKey, senderPublicKeyJwk) {
    const sharedKey = await deriveSharedKey(myPrivateKey, senderPublicKeyJwk);

    const combinedKey = new Uint8Array([
      ...base64ToBuf(myEncryptedKey.encryptedKey),
      ...base64ToBuf(myEncryptedKey.keyAuthTag)
    ]);

    let rawMessageKey;
    try {
      rawMessageKey = await crypto.subtle.decrypt(
        { name: AES_MODE, iv: base64ToBuf(myEncryptedKey.keyIv) },
        sharedKey,
        combinedKey
      );
    } catch {
      return '[Could not unwrap message key]';
    }

    const messageKey = await crypto.subtle.importKey(
      'raw', rawMessageKey,
      { name: AES_MODE },
      false,
      ['decrypt']
    );

    const combined = new Uint8Array([
      ...base64ToBuf(ciphertext),
      ...base64ToBuf(authTag)
    ]);

    let decrypted;
    try {
      decrypted = await crypto.subtle.decrypt(
        { name: AES_MODE, iv: base64ToBuf(iv) },
        messageKey,
        combined
      );
    } catch {
      return '[Group message decryption failed]';
    }

    return dec.decode(decrypted);
  }

  // ================================================================
  // SECTION 8 — INDEXEDDB SHARE STORAGE
  // ================================================================

  function openIDB() {
    return new Promise((resolve, reject) => {
      const req = indexedDB.open(IDB_NAME, IDB_VERSION);
      req.onupgradeneeded = e => {
        const db = e.target.result;
        if (!db.objectStoreNames.contains(IDB_STORE)) {
          db.createObjectStore(IDB_STORE, { keyPath: 'userId' });
        }
      };
      req.onsuccess = () => resolve(req.result);
      req.onerror   = () => reject(new Error('IndexedDB open failed: ' + req.error));
    });
  }

  async function saveShareToDevice(userId, share, password) {
    const salt        = randomBytes(SALT_LENGTH);
    const iv          = randomBytes(IV_LENGTH);
    const passwordKey = await deriveKeyFromPassword(password, salt);

    const encBuffer = await crypto.subtle.encrypt(
      { name: AES_MODE, iv },
      passwordKey,
      enc.encode(share.shareData)
    );

    const packed = {
      salt:       bufToBase64(salt),
      iv:         bufToBase64(iv),
      ciphertext: bufToBase64(new Uint8Array(encBuffer).slice(0, -16)),
      authTag:    bufToBase64(new Uint8Array(encBuffer).slice(-16))
    };

    const db = await openIDB();
    return new Promise((resolve, reject) => {
      const tx  = db.transaction(IDB_STORE, 'readwrite');
      const st  = tx.objectStore(IDB_STORE);
      const req = st.put({
        userId,
        shareIndex: share.shareIndex,
        packed:     JSON.stringify(packed),
        savedAt:    new Date().toISOString()
      });
      req.onsuccess = () => resolve(true);
      req.onerror   = () => reject(new Error('Failed to save share: ' + req.error));
    });
  }

  async function getShareFromDevice(userId, password) {
    const db = await openIDB();
    const record = await new Promise((resolve, reject) => {
      const tx  = db.transaction(IDB_STORE, 'readonly');
      const st  = tx.objectStore(IDB_STORE);
      const req = st.get(userId);
      req.onsuccess = () => resolve(req.result || null);
      req.onerror   = () => reject(new Error('Failed to get share'));
    });

    if (!record) return null;

    const { salt, iv, ciphertext, authTag } = JSON.parse(record.packed);
    const passwordKey = await deriveKeyFromPassword(password, base64ToBuf(salt));

    const combined = new Uint8Array([
      ...base64ToBuf(ciphertext),
      ...base64ToBuf(authTag)
    ]);

    let shareData;
    try {
      const decrypted = await crypto.subtle.decrypt(
        { name: AES_MODE, iv: base64ToBuf(iv) },
        passwordKey,
        combined
      );
      shareData = dec.decode(decrypted);
    } catch {
      throw new Error('Wrong password or corrupted share data');
    }

    return { shareIndex: record.shareIndex, shareData };
  }

  async function deviceHasShare(userId) {
    const db = await openIDB();
    return new Promise((resolve, reject) => {
      const tx  = db.transaction(IDB_STORE, 'readonly');
      const st  = tx.objectStore(IDB_STORE);
      const req = st.get(userId);
      req.onsuccess = () => resolve(!!req.result);
      req.onerror   = () => reject(false);
    });
  }

  async function deleteShareFromDevice(userId) {
    const db = await openIDB();
    return new Promise((resolve, reject) => {
      const tx  = db.transaction(IDB_STORE, 'readwrite');
      const st  = tx.objectStore(IDB_STORE);
      const req = st.delete(userId);
      req.onsuccess = () => resolve(true);
      req.onerror   = () => reject(new Error('Failed to delete share'));
    });
  }

  // ================================================================
  // SECTION 8b — FILE ENCRYPTION / DECRYPTION
  // ================================================================

  /**
   * Encrypt a file for a PERSONAL message recipient.
   * Uses ECDH shared secret as AES key — same as personal messages.
   *
   * @param {ArrayBuffer} fileBuffer            - Raw file bytes
   * @param {CryptoKey}   senderPrivateKey      - Sender's unlocked private key
   * @param {string}      recipientPublicKeyJwk - Recipient's public key JWK string
   * @returns {Object} { encryptedBuffer, iv, authTag } — all needed to decrypt
   */
  async function encryptFile(fileBuffer, senderPrivateKey, recipientPublicKeyJwk) {
    const sharedKey = await deriveSharedKey(senderPrivateKey, recipientPublicKeyJwk);
    const iv        = randomBytes(IV_LENGTH);

    const encryptedBuffer = await crypto.subtle.encrypt(
      { name: AES_MODE, iv },
      sharedKey,
      fileBuffer
    );

    const encBytes   = new Uint8Array(encryptedBuffer);
    const ciphertext = encBytes.slice(0, -16);
    const authTag    = encBytes.slice(-16);

    return {
      encryptedBuffer: ciphertext.buffer,
      iv:              bufToBase64(iv),
      authTag:         bufToBase64(authTag)
    };
  }

  /**
   * Decrypt a PERSONAL file message.
   *
   * @param {ArrayBuffer} encryptedBuffer       - Encrypted file bytes
   * @param {string}      iv                    - base64
   * @param {string}      authTag               - base64
   * @param {CryptoKey}   recipientPrivateKey   - Recipient's unlocked private key
   * @param {string}      senderPublicKeyJwk    - Sender's public key JWK string
   * @returns {ArrayBuffer}                     - Decrypted file bytes
   */
  async function decryptFile(encryptedBuffer, iv, authTag, recipientPrivateKey, senderPublicKeyJwk) {
    const sharedKey = await deriveSharedKey(recipientPrivateKey, senderPublicKeyJwk);

    const authTagBytes = base64ToBuf(authTag);
    const combined     = new Uint8Array(encryptedBuffer.byteLength + authTagBytes.byteLength);
    combined.set(new Uint8Array(encryptedBuffer));
    combined.set(authTagBytes, encryptedBuffer.byteLength);

    try {
      return await crypto.subtle.decrypt(
        { name: AES_MODE, iv: base64ToBuf(iv) },
        sharedKey,
        combined
      );
    } catch {
      throw new Error('File decryption failed — key mismatch or corrupted file');
    }
  }

  /**
   * Encrypt a file for a GROUP message.
   * Hybrid approach: random one-time AES key encrypts the file,
   * that key is then wrapped per member using ECDH shared secret.
   *
   * @param {ArrayBuffer} fileBuffer       - Raw file bytes
   * @param {CryptoKey}   senderPrivateKey - Sender's unlocked private key
   * @param {Array}       members          - [{ userId, publicKeyJwk }, ...]
   * @returns {Object} { encryptedBuffer, iv, authTag, encryptedKeys }
   */
  async function encryptFileGroup(fileBuffer, senderPrivateKey, members) {
    // Generate random one-time AES key for this file
    const fileKey = await crypto.subtle.generateKey(
      { name: AES_MODE, length: AES_LENGTH },
      true,
      ['encrypt', 'decrypt']
    );

    const iv = randomBytes(IV_LENGTH);
    const encryptedBuffer = await crypto.subtle.encrypt(
      { name: AES_MODE, iv },
      fileKey,
      fileBuffer
    );

    const encBytes   = new Uint8Array(encryptedBuffer);
    const ciphertext = encBytes.slice(0, -16);
    const authTag    = encBytes.slice(-16);

    // Export file key and wrap per member
    const rawFileKey    = await crypto.subtle.exportKey('raw', fileKey);
    const encryptedKeys = {};

    for (const member of members) {
      const sharedKey = await deriveSharedKey(senderPrivateKey, member.publicKeyJwk);
      const keyIv     = randomBytes(IV_LENGTH);

      const encKeyBuffer = await crypto.subtle.encrypt(
        { name: AES_MODE, iv: keyIv },
        sharedKey,
        rawFileKey
      );

      const encKeyBytes = new Uint8Array(encKeyBuffer);
      encryptedKeys[member.userId] = {
        encryptedKey: bufToBase64(encKeyBytes.slice(0, -16)),
        keyIv:        bufToBase64(keyIv),
        keyAuthTag:   bufToBase64(encKeyBytes.slice(-16))
      };
    }

    return {
      encryptedBuffer: ciphertext.buffer,
      iv:              bufToBase64(iv),
      authTag:         bufToBase64(authTag),
      encryptedKeys
    };
  }

  /**
   * Decrypt a GROUP file message.
   *
   * @param {ArrayBuffer} encryptedBuffer  - Encrypted file bytes
   * @param {string}      iv               - base64
   * @param {string}      authTag          - base64
   * @param {Object}      myEncryptedKey   - { encryptedKey, keyIv, keyAuthTag }
   * @param {CryptoKey}   myPrivateKey     - My unlocked private key
   * @param {string}      senderPublicKeyJwk
   * @returns {ArrayBuffer}                - Decrypted file bytes
   */
  async function decryptFileGroup(encryptedBuffer, iv, authTag, myEncryptedKey, myPrivateKey, senderPublicKeyJwk) {
    // Unwrap file key using ECDH shared secret with sender
    const sharedKey = await deriveSharedKey(myPrivateKey, senderPublicKeyJwk);

    const combinedKey = new Uint8Array([
      ...base64ToBuf(myEncryptedKey.encryptedKey),
      ...base64ToBuf(myEncryptedKey.keyAuthTag)
    ]);

    let rawFileKey;
    try {
      rawFileKey = await crypto.subtle.decrypt(
        { name: AES_MODE, iv: base64ToBuf(myEncryptedKey.keyIv) },
        sharedKey,
        combinedKey
      );
    } catch {
      throw new Error('Could not unwrap file key');
    }

    // Import the file key
    const fileKey = await crypto.subtle.importKey(
      'raw', rawFileKey,
      { name: AES_MODE },
      false,
      ['decrypt']
    );

    // Decrypt the file
    const authTagBytes = base64ToBuf(authTag);
    const combined     = new Uint8Array(encryptedBuffer.byteLength + authTagBytes.byteLength);
    combined.set(new Uint8Array(encryptedBuffer));
    combined.set(authTagBytes, encryptedBuffer.byteLength);

    try {
      return await crypto.subtle.decrypt(
        { name: AES_MODE, iv: base64ToBuf(iv) },
        fileKey,
        combined
      );
    } catch {
      throw new Error('Group file decryption failed');
    }
  }

  // ================================================================
  // SECTION 9 — SESSION KEY STORAGE (In-memory only)
  // ================================================================

  let _sessionPrivateKey = null;
  let _sessionUserId     = null;

  function setSessionKey(userId, privateKey) {
    _sessionUserId     = userId;
    _sessionPrivateKey = privateKey;
  }

  function getSessionKey() {
    return _sessionPrivateKey;
  }

  function clearSession() {
    _sessionPrivateKey = null;
    _sessionUserId     = null;
  }

  // ================================================================
  // PUBLIC API
  // ================================================================
  return {
    // Key management
    generateKeyPair,
    unlockPrivateKey,
    unlockPrivateKeyExtractable,
    deriveSharedKey,

    // Message encryption
    encryptMessage,
    encryptGroupMessage,

    // Message decryption
    decryptMessage,
    decryptGroupMessage,

    // File encryption
    encryptFile,
    encryptFileGroup,

    // File decryption
    decryptFile,
    decryptFileGroup,

    // Device share storage (IndexedDB)
    saveShareToDevice,
    getShareFromDevice,
    deviceHasShare,
    deleteShareFromDevice,

    // Session (in-memory private key)
    setSessionKey,
    getSessionKey,
    clearSession,

    // Utilities
    bufToBase64,
    base64ToBuf,
    sha256Hex,
    randomBytes
  };

})();
