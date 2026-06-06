<?php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit;
}

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify();

    $name       = trim($_POST['name']       ?? '');
    $staff_id   = trim($_POST['staff_id']   ?? '');
    $email      = trim($_POST['email']      ?? '');
    $department = trim($_POST['department'] ?? '');
    $is_fetch   = isset($_POST['crypto_done']);

    $allowed_departments = ['Finance', 'Payroll', 'Audit', 'HR', 'IT'];

    // Temp password: staffId + name with spaces removed (same pattern as recovery)
    $temp_password = $staff_id . preg_replace('/\s+/', '', $name);

    $errors = [];

    if (empty($name)) {
        $errors[] = 'Full name is required';
    } elseif (strlen($name) > 100) {
        $errors[] = 'Full name must not exceed 100 characters';
    }

    if (empty($staff_id)) {
        $errors[] = 'Staff ID is required';
    } elseif (strlen($staff_id) > 50) {
        $errors[] = 'Staff ID must not exceed 50 characters';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    } elseif (strlen($email) > 120) {
        $errors[] = 'Email must not exceed 120 characters';
    }
    // Temporarily disabled for email testing
    //if (!str_ends_with($email, '@uthm.edu.my')) {
    //    $errors[] = 'Email must be a UTHM email address (@uthm.edu.my)';
    //}

    if (!in_array($department, $allowed_departments, true)) {
        $errors[] = 'Invalid department selected';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()['count'] > 0) {
            $errors[] = 'Email already registered';
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE staff_id = ?");
        $stmt->execute([$staff_id]);
        if ($stmt->fetch()['count'] > 0) {
            $errors[] = 'Staff ID already exists';
        }
    }

    if (empty($errors)) {
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);


        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, role, staff_id, department, password_change_required)
            VALUES (?, ?, ?, 'staff', ?, ?, 1)
        ");
        $stmt->execute([$name, $email, $hashed_password, $staff_id, $department]);
        $new_user_id = $pdo->lastInsertId();

        $log_stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, details)
            VALUES (?, 'Register Staff', ?)
        ");
        $log_stmt->execute([$_SESSION['user_id'], "Registered staff: $name ($email)"]);

        // Send welcome email to the new staff member
        try {
            require_once '../includes/mailer.php';
            $html_body = "
            <div style='font-family:Arial,sans-serif;max-width:520px;margin:0 auto;'>
                <div style='background:#534AB7;padding:24px;text-align:center;border-radius:8px 8px 0 0;'>
                    <h2 style='color:#fff;margin:0;font-size:20px;'>UTHM Bursary Messaging</h2>
                    <p style='color:#ccc;margin:6px 0 0;font-size:13px;'>Your account has been created</p>
                </div>
                <div style='background:#f9f9f9;padding:28px;border-radius:0 0 8px 8px;border:1px solid #eee;'>
                    <p style='font-size:15px;color:#333;margin-top:0;'>
                        Dear <strong>" . htmlspecialchars($name) . "</strong>,
                    </p>
                    <p style='color:#555;font-size:14px;'>
                        Your account for the UTHM Bursary Secure Messaging System has been created by the administrator.
                    </p>
                    <div style='background:#EEEDFE;border-radius:10px;padding:20px;margin:20px 0;'>
                        <p style='margin:0 0 8px;font-size:13px;color:#534AB7;font-weight:bold;'>Account Details</p>
                        <table style='font-size:14px;color:#333;width:100%;'>
                            <tr><td style='padding:3px 0;color:#666;'>Email</td><td><strong>" . htmlspecialchars($email) . "</strong></td></tr>
                            <tr><td style='padding:3px 0;color:#666;'>Staff ID</td><td><strong>" . htmlspecialchars($staff_id) . "</strong></td></tr>
                            <tr><td style='padding:3px 0;color:#666;'>Department</td><td><strong>" . htmlspecialchars($department) . "</strong></td></tr>
                        </table>
                    </div>
                    <div style='background:#fff3cd;border-radius:8px;padding:14px;margin:16px 0;border-left:4px solid #f0ad4e;'>
                        <p style='margin:0 0 8px;font-size:13px;color:#856404;font-weight:bold;'>
                            <i>&#x26A0;</i> Temporary Password
                        </p>
                        <p style='margin:0 0 6px;font-size:13px;color:#856404;'>
                            Your temporary password is your <strong>Staff ID</strong> followed by your
                            <strong>Full Name (no spaces, case-sensitive)</strong>.
                        </p>
                        <p style='margin:0;font-size:12px;color:#856404;'>
                            You will be required to change this password on your first login.
                        </p>
                    </div>
                    <p style='color:#555;font-size:14px;'>
                        Log in at: <a href='http://localhost/uthm-system/' style='color:#534AB7;'>UTHM Bursary Messaging System</a>
                    </p>
                    <p style='color:#555;font-size:14px;'>
                        On your first login, the system will automatically activate end-to-end encryption using the
                        Signal Protocol for your account.
                    </p>
                    <hr style='border:none;border-top:1px solid #eee;margin:20px 0;'>
                    <p style='font-size:12px;color:#aaa;margin:0;text-align:center;'>
                        UTHM Bursary Office &bull; Secure Internal Messaging System
                    </p>
                </div>
            </div>";

            $plain_body = "Dear $name,\n\n"
                . "Your account for the UTHM Bursary Secure Messaging System has been created.\n\n"
                . "Email: $email\nStaff ID: $staff_id\nDepartment: $department\n\n"
                . "Your temporary password is your Staff ID followed by your Full Name (no spaces, case-sensitive).\n\n"
                . "You will be required to change this password on your first login.\n\n"
                . "UTHM Bursary Office - Secure Internal Messaging System";

            sendEmail($email, $name, 'UTHM Bursary — Your Account Has Been Created', $html_body, $plain_body);
        } catch (Exception $e) {
            error_log('Welcome email failed for ' . $email . ': ' . $e->getMessage());
            // Non-fatal — registration still succeeds
        }

        if ($is_fetch) {
            header('Content-Type: application/json');
            echo json_encode([
                'success'     => true,
                'new_user_id' => $new_user_id,
                'message'     => "Staff member '$name' registered successfully"
            ]);
            exit;
        }

        $message = "Staff member '" . htmlspecialchars($name) . "' registered successfully!";
        $success = true;
        $_POST   = [];

    } else {
        if ($is_fetch) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'errors'  => $errors
            ]);
            exit;
        }
        $message = implode('<br>', $errors);
    }
}

$page_title = 'Register Staff';
include '../includes/header.php';
?>


<div class="page-header">
    <div>
        <h1>Register New Staff</h1>
        <div class="page-subtitle">Add new staff members to the secure messaging system</div>
    </div>
</div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?> alert-dismissible fade show">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <i data-lucide="user-plus"></i> Staff Information
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <?php echo csrf_field(); ?>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Full Name *</label>
                                        <input type="text" name="name" class="form-control"
                                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                                               required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Staff ID *</label>
                                        <input type="text" name="staff_id" class="form-control"
                                               value="<?php echo isset($_POST['staff_id']) ? htmlspecialchars($_POST['staff_id']) : ''; ?>"
                                               placeholder="e.g., ST001" required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email Address *</label>
                                        <input type="email" name="email" class="form-control"
                                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                               placeholder="name@uthm.edu.my" required>
                                        <div class="form-text">Must be a UTHM email (@uthm.edu.my)</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Department *</label>
                                        <select name="department" class="form-select" required>
                                            <option value="">-- Select Department --</option>
                                            <option value="Finance"  <?php echo (isset($_POST['department']) && $_POST['department'] == 'Finance')  ? 'selected' : ''; ?>>Finance</option>
                                            <option value="Payroll"  <?php echo (isset($_POST['department']) && $_POST['department'] == 'Payroll')  ? 'selected' : ''; ?>>Payroll</option>
                                            <option value="Audit"    <?php echo (isset($_POST['department']) && $_POST['department'] == 'Audit')    ? 'selected' : ''; ?>>Audit</option>
                                            <option value="HR"       <?php echo (isset($_POST['department']) && $_POST['department'] == 'HR')       ? 'selected' : ''; ?>>Human Resources</option>
                                            <option value="IT"       <?php echo (isset($_POST['department']) && $_POST['department'] == 'IT')       ? 'selected' : ''; ?>>Information Technology</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between mt-2">
                                    <a href="manage_users.php" class="btn btn-secondary">Cancel</a>
                                    <button type="button" id="registerBtn" class="btn btn-gradient">
                                        <i class="fas fa-user-plus"></i> Register Staff
                                    </button>
                                </div>

                                <input type="hidden" id="crypto_done" name="crypto_done" value="0">
                                <input type="hidden" id="new_user_id" name="new_user_id" value="">

                            </form>
                        </div>
                    </div>
                </div>

                <!-- Notes panel -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <i data-lucide="info"></i> Registration Notes
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning py-2 mb-0">
                                <small>
                                    <div class="fw-semibold mb-1">Important</div>
                                    <ul class="mb-0">
                                        <li>Email must be @uthm.edu.my format</li>
                                        <li>Temp password: Staff ID + Full Name (no spaces)</li>
                                        <li>Staff must change password on first login</li>
                                    </ul>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

<script>
document.getElementById('registerBtn').addEventListener('click', async () => {
    const btn     = document.getElementById('registerBtn');
    const name    = document.querySelector('[name="name"]').value.trim();
    const email   = document.querySelector('[name="email"]').value.trim();
    const staffId = document.querySelector('[name="staff_id"]').value.trim();

    // Temp password matches what PHP generates: staffId + name (no spaces)
    const password = staffId + name.replace(/\s+/g, '');

    if (!name || !email || !staffId) {
        alert('Please fill in all required fields first.');
        return;
    }
    // Temporarily disabled for email testing
    //if (!email.endsWith('@uthm.edu.my')) {
    //    alert('Email must be a UTHM email address (@uthm.edu.my).');
    //    return;
    //}

    btn.disabled  = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registering...';

    try {
        // Step 1: Register staff via fetch — returns JSON
        const formData = new FormData(document.querySelector('form'));
        formData.set('crypto_done', '1');

        const regResponse = await fetch('register_staff.php', {
            method: 'POST',
            body:   formData
        });

        const regResult = await regResponse.json();

        if (!regResult.success) {
            alert('Registration failed:\n' + (regResult.errors || []).join('\n'));
            btn.disabled  = false;
            btn.innerHTML = '<i class="fas fa-user-plus"></i> Register Staff';
            return;
        }

        const newUserId = regResult.new_user_id;

        // Step 2: Generate ECDH key pair
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating keys...';
        const keys = await UTHMCrypto.generateKeyPair(password);

        // Step 3: Generate SSS shares (5 shares, threshold 3)
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Distributing shares...';
        const encoder   = new TextEncoder();
        const secretBuf = encoder.encode(keys.keyHash);
        const shares    = UTHMSS.split(new Uint8Array(secretBuf.buffer), 5, 3);

        // Step 3b: Encrypt share 1 with the staff's own ECDH public key via an
        // ephemeral keypair (ECIES). The server stores ciphertext it cannot read;
        // only the staff's private key can decrypt it on first login.
        const ephKP     = await crypto.subtle.generateKey(
            { name: 'ECDH', namedCurve: 'P-256' }, true, ['deriveKey', 'deriveBits']
        );
        const ephPubJwk = JSON.stringify(await crypto.subtle.exportKey('jwk', ephKP.publicKey));
        const share1Enc = await UTHMCrypto.encryptMessage(
            shares[0].shareData, ephKP.privateKey, keys.publicKeyJwk
        );

        // Step 4: Send keys + all share data to server
        const apiResponse = await fetch('/uthm-system/api/register_keys.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id:               newUserId,
                public_key:            keys.publicKeyJwk,
                encrypted_private_key: keys.encryptedPrivateKey,
                key_iv:                keys.keyIv,
                key_auth_tag:          keys.keyAuthTag,
                key_hash:              keys.keyHash,
                share1_encrypted:      share1Enc.ciphertext,
                share1_iv:             share1Enc.iv,
                share1_auth_tag:       share1Enc.authTag,
                share1_eph_pub:        ephPubJwk,
                share2:                shares[1].shareData,
                share3:                shares[2].shareData,
                share4:                shares[3].shareData,
                share5:                shares[4].shareData
            })
        });

        const apiResult = await apiResponse.json();
        if (!apiResult.success) {
            throw new Error(apiResult.error || 'Key storage failed');
        }

        console.log(`[Crypto] Keys and all shares stored for user ${newUserId}`);
        console.log(`[Crypto] Share 1 encrypted with staff public key — awaiting device pickup`);

        btn.innerHTML = '<i class="fas fa-check"></i> Done!';
        setTimeout(() => {
            window.location.href = 'manage_users.php?registered=1';
        }, 1200);

    } catch (err) {
        console.error('Registration error:', err);
        btn.disabled  = false;
        btn.innerHTML = '<i class="fas fa-user-plus"></i> Register Staff';
        alert('Error: ' + err.message);
    }
});
</script>

<?php include '../includes/footer.php'; ?>
