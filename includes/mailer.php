<?php
// ===========================
// includes/mailer.php
// Reusable email sender
// ===========================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../config/email.php';

/**
 * Send an email using PHPMailer + Gmail SMTP.
 *
 * @param string $to_email   Recipient email address
 * @param string $to_name    Recipient name
 * @param string $subject    Email subject
 * @param string $html_body  HTML email body
 * @param string $plain_body Plain text fallback
 * @return bool              True on success, false on failure
 */
function sendEmail($to_email, $to_name, $subject, $html_body, $plain_body = '') {
    $mail = new PHPMailer(true);

    try {
        // Debug — output goes to PHP error log only, never to browser
        $mail->SMTPDebug  = 0;
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = MAIL_PORT;

        // Sender
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);

        // Recipient
        $mail->addAddress($to_email, $to_name);

        // Content
        $mail->CharSet  = PHPMailer::CHARSET_UTF8;
        $mail->Encoding = PHPMailer::ENCODING_BASE64;
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_body;
        $mail->AltBody = $plain_body ?: strip_tags($html_body);

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('PHPMailer error: ' . $mail->ErrorInfo);
        return false;
    }
}
