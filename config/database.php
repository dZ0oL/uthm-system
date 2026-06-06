<?php
// ===================
// Config/database.php
// ===================

$host     = 'localhost';
$dbname   = 'uthm_messaging';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

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
    $basePath = rtrim(dirname(dirname($_script)), '/');
} else {
    $basePath = rtrim(dirname($_script), '/');
}
$apiBase = $basePath . '/api';
$appUrl  = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http')
         . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $basePath;
unset($_script);

// ── CSRF helpers ──────────────────────────────────────────────
// Call csrf_field() inside every HTML form to embed the hidden token.
// Call csrf_verify() at the top of every POST handler to reject forgeries.

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify(): void {
    $submitted = $_POST['csrf_token'] ?? '';
    $expected  = $_SESSION['csrf_token'] ?? '';
    if (!$expected || !hash_equals($expected, $submitted)) {
        http_response_code(403);
        die('Invalid request. Please go back and try again.');
    }
}