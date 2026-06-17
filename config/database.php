<?php
// ===================
// Config/database.php
// Loaded by every PHP page — sets up the DB connection, session, base paths, and CSRF helpers.
// ===================

$host     = 'localhost';
$dbname   = 'uthm_messaging';
$username = 'root';
$password = '';

// Connect to MySQL using PDO — PDO throws exceptions instead of returning false on error
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // throw on error
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // rows as associative arrays
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"      // force UTF-8 for emoji support
        ]
    );
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Start PHP session so $_SESSION is available on every page
session_start();

// ── App base-path helpers ─────────────────────────────────────
// Computes the URL prefix of the app root regardless of whether it is
// mounted at /uthm-system/ (Laragon) or / (VPS).
// Available as $basePath, $apiBase, and $appUrl in every file that
// includes config/database.php.
$_script  = $_SERVER['SCRIPT_NAME'] ?? '';
if (strpos($_script, '/admin/') !== false ||
    strpos($_script, '/staff/') !== false ||
    strpos($_script, '/api/')   !== false) {
    // Script is one level deep — go up one directory to find the app root
    $basePath = rtrim(dirname(dirname($_script)), '/');
} else {
    $basePath = rtrim(dirname($_script), '/');
}
$apiBase = $basePath . '/api';
// Build the full base URL including scheme and hostname
$appUrl  = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http')
         . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $basePath;
unset($_script);

// ── CSRF helpers ──────────────────────────────────────────────
// CSRF (Cross-Site Request Forgery) protection — an attacker's page cannot forge
// a valid POST because they cannot read the token from our session.
// Call csrf_field() inside every HTML form to embed the hidden token.
// Call csrf_verify() at the top of every POST handler to reject forgeries.

// Generate a random token and store it in the session (stays the same for the session)
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Return an HTML hidden input containing the CSRF token — paste into every form
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

// Verify the submitted token matches the session token — abort with 403 if not
function csrf_verify(): void {
    $submitted = $_POST['csrf_token'] ?? '';
    $expected  = $_SESSION['csrf_token'] ?? '';
    // hash_equals() uses constant-time comparison to prevent timing attacks
    if (!$expected || !hash_equals($expected, $submitted)) {
        http_response_code(403);
        die('Invalid request. Please go back and try again.');
    }
}
