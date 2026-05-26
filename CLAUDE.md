# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

UTHM Secure Messaging System — a browser-based end-to-end encrypted messaging platform for UTHM's Bursary Office. All encryption/decryption happens in the browser via the Web Crypto API; the server stores and routes only ciphertext.

## Tech Stack

- **Backend**: PHP 7.4+ with PDO (no framework)
- **Frontend**: Bootstrap 5.3 + Vanilla JS (Web Crypto API), loaded via CDN
- **Database**: MySQL — two databases: `uthm_messaging` (main) and `uthm_messaging_secure` (backup mirror)
- **Email**: PHPMailer via Gmail SMTP (configured in `config/email.php`)
- **No build tools** — plain PHP served by Laragon or any local web server

## Running Locally

1. Configure `config/database.php` with MySQL credentials (default: root, no password)
2. Configure `config/email.php` with a Gmail App Password
3. Create the admin account via CLI only: `php create_admin.php` (master password: `uthm@2025`)
4. Access at `http://localhost/uthm-system/`

## Architecture

### Three-Role System

| Role | Pages | Purpose |
|------|-------|---------|
| admin | `admin/` | User management, groups, recovery requests, audit logs |
| staff | `staff/` | Messaging, contacts, profile |
| (unauthenticated) | `index.php`, `recover.php` | Login, account recovery |

All API endpoints live in `api/` and return JSON.

### Cryptographic Flow

1. **Key Generation** (first login): Browser generates ECDH P-256 keypair. Public key stored in DB; private key AES-encrypted with a PBKDF2 key derived from the user's password.
2. **Key Splitting**: A master key is split into 5 Shamir Secret Sharing (SSS) shares (threshold: 3).
   - Share 1 → browser IndexedDB (device-local)
   - Shares 2–5 → server (`recovery_shares` table), OpenSSL-encrypted at rest
3. **Sending a message**: Browser fetches recipient public key → ECDH → AES-256-GCM encrypt → POST ciphertext + IV + auth_tag + encrypted_aes_key to `api/send_message.php`
4. **Receiving**: Browser fetches ciphertext → ECDH → decrypt AES key → AES-GCM decrypt with auth tag verification
5. **Recovery**: Admin sends OTP (6-digit, 10-min expiry) → staff verifies OTP → submits 3+ shares → SSS reconstructs master key in browser

Key JS files: [assets/js/crypto.js](assets/js/crypto.js) (Web Crypto API wrappers), [assets/js/sss.js](assets/js/sss.js) (custom SSS in Galois field), [assets/js/session.js](assets/js/session.js) (session polling + key unlock).

### Session Model

- Single-device login: each login generates a new `session_token` stored in DB, invalidating all other devices
- `api/check_session.php` is polled by `session.js` to detect displacement
- `_tmp_pw` in `sessionStorage` holds the password for in-memory key decryption; cleared on page reload

### Backup DB

`api/send_message.php` mirrors each message to `uthm_messaging_secure.messages_backup` after the main transaction commits. Backup failure is non-fatal (caught and logged).

## API Endpoint Pattern

Every endpoint in `api/` follows this structure — use it when adding new endpoints:

```php
<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once '../config/database.php';
ob_clean();

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorised']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

try {
    $pdo->beginTransaction();
    // ... DB work ...
    $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)")
        ->execute([$_SESSION['user_id'], 'Action', 'Detail', $_SERVER['REMOTE_ADDR'] ?? null]);
    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('endpoint error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed']);
}
```

The `ob_start()`/`ob_clean()` pair suppresses any PHP warnings that would corrupt JSON output from `config/database.php`.

## Key Conventions

- **Audit logging is mandatory** on every state-changing operation
- **Prepared statements everywhere** — no string interpolation in SQL
- **Transactions** for any operation touching multiple tables
- `includes/header.php` auto-detects subdirectory (`admin/` or `staff/`) and sets `$base = '../'` for asset paths
- `window.__STAFF_USER_ID` and `window.__API_BASE` are injected by `includes/header.php` for staff pages
- `create_admin.php` checks `php_sapi_name() === 'cli'` and cannot be run via browser

## Important Security Notes (Pre-Production)

- `create_admin.php` has a hardcoded master password `'uthm@2025'` — must change before deployment
- `api/register_keys.php` has a hardcoded `SHARE_ENCRYPTION_KEY` — move to environment variable
- OTP expiry is 10 minutes; do not increase without a security review
