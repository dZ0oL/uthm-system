<?php
// ============================================================
// api/send_otp.php
// First step of staff account recovery.
// Validates the email, generates a 6-digit OTP, saves it with
// a 10-minute expiry, and emails it via PHPMailer.
// Does NOT require a login session — called from recover.php.
// Returns success=true even if email not found (prevents enumeration).
// ============================================================
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once '../config/database.php';
ob_clean();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data  = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid email is required']);
    exit;
}

// Check user exists and is active
$stmt = $pdo->prepare("
    SELECT user_id, name, role
    FROM users
    WHERE email = ? AND status = 'active'
");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // Don't reveal whether email exists — security best practice
    echo json_encode([
        'success' => true,
        'message' => 'If this email exists, an OTP has been sent.'
    ]);
    exit;
}

if ($user['role'] === 'admin') {
    http_response_code(400);
    echo json_encode(['error' => 'Admin accounts use a different recovery process.']);
    exit;
}

// Invalidate any existing unused OTPs for this email
$pdo->prepare("
    UPDATE recovery_otps SET used = 1
    WHERE email = ? AND used = 0
")->execute([$email]);

// Generate 6-digit OTP
$otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

$pdo->prepare("
    INSERT INTO recovery_otps (email, otp_code, expires_at)
    VALUES (?, ?, NOW() + INTERVAL 10 MINUTE)
")->execute([$email, $otp]);

// ── Send OTP via PHPMailer ────────────────────────────────────
require_once '../includes/mailer.php';

$html_body = "
<div style='font-family:Arial,sans-serif;max-width:520px;margin:0 auto;'>
    <div style='background:#534AB7;padding:24px;text-align:center;border-radius:8px 8px 0 0;'>
        <h2 style='color:#fff;margin:0;font-size:20px;'>UTHM Bursary Messaging</h2>
        <p style='color:#ccc;margin:6px 0 0;font-size:13px;'>Secure Internal Communication System</p>
    </div>
    <div style='background:#f9f9f9;padding:28px;border-radius:0 0 8px 8px;border:1px solid #eee;'>
        <p style='font-size:15px;color:#333;margin-top:0;'>
            Dear <strong>" . htmlspecialchars($user['name']) . "</strong>,
        </p>
        <p style='color:#555;font-size:14px;'>
            We received a request to recover your account.
            Use the verification code below to proceed:
        </p>
        <div style='background:#EEEDFE;border-radius:10px;padding:24px;text-align:center;margin:24px 0;'>
            <p style='margin:0 0 8px;font-size:13px;color:#534AB7;'>Your OTP code</p>
            <span style='font-size:40px;font-weight:bold;color:#3C3489;letter-spacing:10px;'>
                {$otp}
            </span>
        </div>
        <p style='color:#555;font-size:14px;'>
            This code expires in <strong>10 minutes</strong>.
            Do not share it with anyone.
        </p>
        <p style='color:#e74c3c;font-size:13px;'>
            If you did not request this, please contact your administrator immediately.
        </p>
        <hr style='border:none;border-top:1px solid #eee;margin:24px 0;'>
        <p style='font-size:12px;color:#aaa;margin:0;text-align:center;'>
            UTHM Bursary Office &bull; Secure Internal Messaging System
        </p>
    </div>
</div>";

$plain_body = "Dear {$user['name']},\n\n"
            . "Your account recovery OTP is: {$otp}\n\n"
            . "This code expires in 10 minutes.\n\n"
            . "If you did not request this, please contact your administrator immediately.\n\n"
            . "UTHM Bursary Office - Secure Internal Messaging System";

$mailSent = sendEmail(
    $email,
    $user['name'],
    'UTHM Bursary — Account Recovery OTP',
    $html_body,
    $plain_body
);
// ─────────────────────────────────────────────────────────────

$response = [
    'success' => true,
    'message' => 'OTP sent to your email.'
];

if (!$mailSent) {
    // Mail failed — show OTP for development only
    // REMOVE dev_otp lines before final submission
    $response['dev_otp']  = $otp;
    $response['dev_note'] = 'PHPMailer failed — OTP shown for development only. Remove in production.';
}

echo json_encode($response);
