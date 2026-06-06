<?php
// Copy this file to config/email.php and fill in your values.
// config/email.php is in .gitignore — never commit real credentials.

define('MAIL_HOST',       'smtp.gmail.com');
define('MAIL_PORT',       465);
define('MAIL_USERNAME',   'your-gmail@gmail.com');    // your Gmail address
define('MAIL_PASSWORD',   'xxxx xxxx xxxx xxxx');     // Gmail App Password
define('MAIL_FROM_EMAIL', 'your-gmail@gmail.com');
define('MAIL_FROM_NAME',  'UTHM Bursary Messaging System');
define('MAIL_ENCRYPTION', PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS);
