<?php
// Copy this file to config/database.php and fill in your values.
// config/database.php is in .gitignore — never commit real credentials.

$host     = 'localhost';
$dbname   = 'uthm_messaging';
$username = 'root';
$password = '';          // Set your MySQL password here

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
} catch (PDOException $e) { die("Connection failed: " . $e->getMessage()); }

session_start();

// App base-path helpers
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

// CSRF helpers
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
