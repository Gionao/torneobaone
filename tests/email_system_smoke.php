<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/mailer.php';

if (!function_exists('send_email')) {
    fwrite(STDERR, "send_email() is missing\n");
    exit(1);
}

if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    fwrite(STDERR, "PHPMailer class is missing\n");
    exit(1);
}

echo "email system smoke test passed\n";
