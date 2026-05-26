#!/usr/bin/env php
<?php
/**
 * UTHM Secure Messaging System
 * CLI Admin Account Creator
 */

// Check if running from CLI
if (php_sapi_name() !== 'cli') {
    die("This script must be run from command line!\n");
}

// ANSI color codes
class Colors {
    public static $RESET = "\033[0m";
    public static $RED = "\033[31m";
    public static $GREEN = "\033[32m";
    public static $YELLOW = "\033[33m";
    public static $BLUE = "\033[34m";
    public static $CYAN = "\033[36m";
    public static $BOLD = "\033[1m";
}

// Database configuration
$host = 'localhost';
$dbname = 'uthm_messaging';
$username = 'root';
$password = '';

// Master password
define('MASTER_PASSWORD', 'uthm@2026');

// Banner
echo Colors::$CYAN . Colors::$BOLD;
echo "\n╔════════════════════════════════════════════╗\n";
echo "║   UTHM Secure Messaging System - CLI      ║\n";
echo "║        Admin Account Creator               ║\n";
echo "╚════════════════════════════════════════════╝\n";
echo Colors::$RESET . "\n";

// Step 1: Master password
echo Colors::$YELLOW . "🔐 Master Password Required\n" . Colors::$RESET;
echo "Enter master password: ";
$input_master = trim(fgets(STDIN));

if ($input_master !== MASTER_PASSWORD) {
    echo Colors::$RED . "❌ Invalid master password!\n" . Colors::$RESET;
    exit(1);
}
echo Colors::$GREEN . "✅ Verified!\n\n" . Colors::$RESET;

// Step 2: Database
echo Colors::$BLUE . "📡 Connecting to database...\n" . Colors::$RESET;
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo Colors::$GREEN . "✅ Connected!\n\n" . Colors::$RESET;
} catch(PDOException $e) {
    echo Colors::$RED . "❌ Failed: " . $e->getMessage() . "\n" . Colors::$RESET;
    exit(1);
}

// Step 3: Menu
echo Colors::$CYAN . "Choose an action:\n" . Colors::$RESET;
echo "1. Create new admin account\n";
echo "2. Reset admin password\n";
echo "3. List all admins\n";
echo "4. Delete admin account\n";
echo "5. Exit\n";
echo "\nEnter choice (1-5): ";
$choice = trim(fgets(STDIN));

switch ($choice) {
    case '1': createAdmin($pdo); break;
    case '2': resetAdminPassword($pdo); break;
    case '3': listAdmins($pdo); break;
    case '4': deleteAdmin($pdo); break;
    case '5': echo Colors::$CYAN . "👋 Goodbye!\n" . Colors::$RESET; exit(0);
    default: echo Colors::$RED . "❌ Invalid choice!\n" . Colors::$RESET; exit(1);
}

// FUNCTION: Create New Admin
function createAdmin($pdo) {
    echo Colors::$CYAN . "\n📝 Create New Admin Account\n" . Colors::$RESET;
    echo str_repeat("-", 50) . "\n";
    
    echo "Admin Name: ";
    $name = trim(fgets(STDIN));
    
    echo "Email (@uthm.edu.my): ";
    $email = trim(fgets(STDIN));
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo Colors::$RED . "❌ Invalid email!\n" . Colors::$RESET;
        return;
    }
    
    // Check if email exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo Colors::$RED . "❌ Email already exists!\n" . Colors::$RESET;
        return;
    }
    
    echo "Staff ID (e.g., ADM002): ";
    $staff_id = trim(fgets(STDIN));
    
    // Check if staff_id exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE staff_id = ?");
    $stmt->execute([$staff_id]);
    if ($stmt->fetch()) {
        echo Colors::$RED . "❌ Staff ID already exists!\n" . Colors::$RESET;
        return;
    }
    
    echo "Department (default: Administration): ";
    $department = trim(fgets(STDIN));
    if (empty($department)) {
        $department = 'Administration';
    }
    
    echo "Password (default: admin123): ";
    $plain_password = trim(fgets(STDIN));
    if (empty($plain_password)) {
        $plain_password = 'admin123';
    }
    
    $hashed_password = password_hash($plain_password, PASSWORD_BCRYPT);
    
    // Confirm
    echo "\n" . Colors::$YELLOW . "Confirm creation?\n" . Colors::$RESET;
    echo "Name: $name\n";
    echo "Email: $email\n";
    echo "Staff ID: $staff_id\n";
    echo "Department: $department\n";
    echo "Password: $plain_password\n";
    echo "\nProceed? (yes/no): ";
    $confirm = trim(fgets(STDIN));
    
    if (strtolower($confirm) !== 'yes') {
        echo Colors::$YELLOW . "⚠️ Cancelled.\n" . Colors::$RESET;
        return;
    }
    
    // Insert - FIXED COLUMN NAMES
    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, `password`, role, staff_id, department, status) 
                               VALUES (?, ?, ?, 'admin', ?, ?, 'active')");
        $stmt->execute([$name, $email, $hashed_password, $staff_id, $department]);
        
        $user_id = $pdo->lastInsertId();
        
        echo Colors::$GREEN . "\n✅ Admin created successfully!\n" . Colors::$RESET;
        echo "\n" . Colors::$CYAN . "Login Credentials:\n" . Colors::$RESET;
        echo "Email: " . Colors::$BOLD . $email . Colors::$RESET . "\n";
        echo "Password: " . Colors::$BOLD . $plain_password . Colors::$RESET . "\n";
        echo "User ID: " . Colors::$BOLD . $user_id . Colors::$RESET . "\n";
        
        // Log
        try {
            $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)");
            $log_stmt->execute([$user_id, 'Admin Created via CLI', "Admin: $email"]);
        } catch(PDOException $e) {
            // Ignore logging errors
        }
        
    } catch(PDOException $e) {
        echo Colors::$RED . "❌ Error: " . $e->getMessage() . "\n" . Colors::$RESET;
    }
}

// FUNCTION: Reset Admin Password
function resetAdminPassword($pdo) {
    echo Colors::$CYAN . "\n🔑 Reset Admin Password\n" . Colors::$RESET;
    echo str_repeat("-", 50) . "\n";
    
    echo "Admin Email: ";
    $email = trim(fgets(STDIN));
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        echo Colors::$RED . "❌ Admin not found!\n" . Colors::$RESET;
        return;
    }
    
    echo Colors::$GREEN . "✅ Found: " . $admin['name'] . "\n" . Colors::$RESET;
    
    echo "\nNew Password (default: admin123): ";
    $new_password = trim(fgets(STDIN));
    if (empty($new_password)) {
        $new_password = 'admin123';
    }
    
    echo "\n" . Colors::$YELLOW . "Reset password?\n" . Colors::$RESET;
    echo "Proceed? (yes/no): ";
    $confirm = trim(fgets(STDIN));
    
    if (strtolower($confirm) !== 'yes') {
        echo Colors::$YELLOW . "⚠️ Cancelled.\n" . Colors::$RESET;
        return;
    }
    
    try {
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET `password` = ? WHERE email = ?");
        $stmt->execute([$hashed_password, $email]);
        
        echo Colors::$GREEN . "\n✅ Password reset!\n" . Colors::$RESET;
        echo "New Password: " . Colors::$BOLD . $new_password . Colors::$RESET . "\n";
        
        // Log
        try {
            $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)");
            $log_stmt->execute([$admin['user_id'], 'Password Reset via CLI', "Admin: $email"]);
        } catch(PDOException $e) {
            // Ignore
        }
        
    } catch(PDOException $e) {
        echo Colors::$RED . "❌ Error: " . $e->getMessage() . "\n" . Colors::$RESET;
    }
}

// FUNCTION: List All Admins
function listAdmins($pdo) {
    echo Colors::$CYAN . "\n👥 All Admin Accounts\n" . Colors::$RESET;
    echo str_repeat("-", 90) . "\n";
    
    $stmt = $pdo->query("SELECT user_id, name, email, staff_id, department, status, created_at 
                         FROM users WHERE role = 'admin' ORDER BY created_at DESC");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($admins)) {
        echo Colors::$YELLOW . "⚠️ No admins found.\n" . Colors::$RESET;
        return;
    }
    
    printf("%-5s %-20s %-30s %-10s %-15s %-10s\n", "ID", "Name", "Email", "Staff ID", "Department", "Status");
    echo str_repeat("-", 90) . "\n";
    
    foreach ($admins as $admin) {
        $status_color = ($admin['status'] === 'active') ? Colors::$GREEN : Colors::$RED;
        printf("%-5d %-20s %-30s %-10s %-15s %s%-10s%s\n", 
            $admin['user_id'],
            substr($admin['name'], 0, 19),
            substr($admin['email'], 0, 29),
            $admin['staff_id'],
            substr($admin['department'] ?? '', 0, 14),
            $status_color,
            $admin['status'],
            Colors::$RESET
        );
    }
    
    echo "\n" . Colors::$CYAN . "Total: " . count($admins) . "\n" . Colors::$RESET;
}

// FUNCTION: Delete Admin
function deleteAdmin($pdo) {
    echo Colors::$CYAN . "\n🗑️ Delete Admin Account\n" . Colors::$RESET;
    echo str_repeat("-", 50) . "\n";
    echo Colors::$RED . "⚠️ WARNING: Cannot be undone!\n" . Colors::$RESET;
    
    echo "\nAdmin Email: ";
    $email = trim(fgets(STDIN));
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        echo Colors::$RED . "❌ Admin not found!\n" . Colors::$RESET;
        return;
    }
    
    // Count admins
    $count_stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
    $admin_count = $count_stmt->fetch()['count'];
    
    if ($admin_count <= 1) {
        echo Colors::$RED . "❌ Cannot delete last admin!\n" . Colors::$RESET;
        return;
    }
    
    echo Colors::$YELLOW . "\nDeleting:\n" . Colors::$RESET;
    echo "Name: " . $admin['name'] . "\n";
    echo "Email: " . $admin['email'] . "\n";
    
    echo "\n" . Colors::$RED . "Type 'DELETE' to confirm: " . Colors::$RESET;
    $confirm = trim(fgets(STDIN));
    
    if ($confirm !== 'DELETE') {
        echo Colors::$YELLOW . "⚠️ Cancelled.\n" . Colors::$RESET;
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        echo Colors::$GREEN . "\n✅ Deleted!\n" . Colors::$RESET;
        
    } catch(PDOException $e) {
        echo Colors::$RED . "❌ Error: " . $e->getMessage() . "\n" . Colors::$RESET;
    }
}

echo "\n" . Colors::$CYAN . "Done!\n" . Colors::$RESET;
?>