<?php
/**
 * setup_signal.php
 * One-time DB migration: adds Signal Protocol tables and columns.
 * Run once as admin from CLI or browser (then delete or restrict access).
 */
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/database.php';
ob_clean();

// Prevent accidental repeat runs or browser access in production.
// Comment this block out before running.
// if (php_sapi_name() !== 'cli') { http_response_code(403); exit('CLI only'); }

// Helper: add a column only if it doesn't exist yet
function addCol($pdo, $table, $column, $definition, $after = null) {
    $after_sql = $after ? "AFTER `$after`" : '';
    // Check if column exists
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);
    if ($stmt->fetchColumn() > 0) {
        echo "<p style='color:grey'>SKIP: $table.$column already exists</p>";
        return;
    }
    $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition $after_sql");
    echo "<p style='color:green'>OK: added $table.$column</p>";
}

$errors = [];

// ── 1. Add Signal columns to users ───────────────────────────
try {
    addCol($pdo, 'users', 'ik_dh_public',      'MEDIUMTEXT DEFAULT NULL',   'ecdh_public_key');
    addCol($pdo, 'users', 'ik_sign_public',    'MEDIUMTEXT DEFAULT NULL',   'ik_dh_public');
    addCol($pdo, 'users', 'spk_id',            'BIGINT UNSIGNED DEFAULT NULL','ik_sign_public');
    addCol($pdo, 'users', 'spk_public',        'MEDIUMTEXT DEFAULT NULL',   'spk_id');
    addCol($pdo, 'users', 'spk_signature',     'TEXT DEFAULT NULL',         'spk_public');
    addCol($pdo, 'users', 'encrypted_ik_dh',   'MEDIUMTEXT DEFAULT NULL',   'spk_signature');
    addCol($pdo, 'users', 'ik_dh_iv',          'VARCHAR(200) DEFAULT NULL', 'encrypted_ik_dh');
    addCol($pdo, 'users', 'ik_dh_auth_tag',    'VARCHAR(100) DEFAULT NULL', 'ik_dh_iv');
    addCol($pdo, 'users', 'encrypted_ik_sign', 'MEDIUMTEXT DEFAULT NULL',   'ik_dh_auth_tag');
    addCol($pdo, 'users', 'ik_sign_iv',        'VARCHAR(200) DEFAULT NULL', 'encrypted_ik_sign');
    addCol($pdo, 'users', 'ik_sign_auth_tag',  'VARCHAR(100) DEFAULT NULL', 'ik_sign_iv');
} catch (PDOException $e) { $errors[] = $e->getMessage(); echo '<p style="color:red">ERR: ' . htmlspecialchars($e->getMessage()) . '</p>'; }

// ── 2. Add Signal columns to messages ────────────────────────
try {
    addCol($pdo, 'messages', 'signal_header',      'MEDIUMTEXT DEFAULT NULL', 'auth_tag');
    addCol($pdo, 'messages', 'signal_prekey_data', 'MEDIUMTEXT DEFAULT NULL', 'signal_header');
} catch (PDOException $e) { $errors[] = $e->getMessage(); echo '<p style="color:red">ERR: ' . htmlspecialchars($e->getMessage()) . '</p>'; }

// ── 3. One-time prekeys ───────────────────────────────────────
$queries = [];
$queries[] = "CREATE TABLE IF NOT EXISTS signal_prekeys (
  prekey_id  BIGINT UNSIGNED NOT NULL,
  user_id    INT NOT NULL,
  public_key MEDIUMTEXT NOT NULL,
  used       TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (prekey_id, user_id),
  INDEX idx_user_available (user_id, used)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// ── 4. Sender key distributions (group) ──────────────────────
$queries[] = "CREATE TABLE IF NOT EXISTS signal_sender_keys (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id       INT NOT NULL,
  sender_id      INT NOT NULL,
  member_id      INT NOT NULL,
  encrypted_dist MEDIUMTEXT NOT NULL,
  dist_iv        VARCHAR(100) NOT NULL,
  dist_auth_tag  VARCHAR(100) NOT NULL,
  iteration      INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_dist (group_id, sender_id, member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

foreach ($queries as $sql) {
    try {
        $pdo->exec($sql);
        echo '<p style="color:green">OK: ' . htmlspecialchars(substr($sql, 0, 80)) . '...</p>';
    } catch (PDOException $e) {
        $errors[] = $e->getMessage();
        echo '<p style="color:red">ERR: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
}

// ── 5. Backup DB: add signal columns to messages_backup ──────
try {
    $pdo_secure = new PDO('mysql:host=localhost;dbname=uthm_messaging_secure', 'root', '');
    $pdo_secure->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    addCol($pdo_secure, 'messages_backup', 'signal_header',      'MEDIUMTEXT DEFAULT NULL', 'message_type');
    addCol($pdo_secure, 'messages_backup', 'signal_prekey_data', 'MEDIUMTEXT DEFAULT NULL', 'signal_header');
} catch (PDOException $e) {
    $errors[] = $e->getMessage();
    echo '<p style="color:orange">SKIP backup DB: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

if (empty($errors)) {
    echo '<h3 style="color:green">Migration complete. Delete this file before going to production.</h3>';
} else {
    echo '<h3 style="color:red">Migration completed with errors — review above.</h3>';
}
