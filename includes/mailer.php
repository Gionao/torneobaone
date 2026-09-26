<?php
// includes/mailer.php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function write_email_log(string $to, string $subject, string $body): void
{
    $dir = __DIR__ . '/../storage/email_log';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $file = $dir . '/email_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.html';
    $html = '<!DOCTYPE html><html><body>'
        . '<h2>' . htmlspecialchars($subject) . '</h2>'
        . '<p><strong>To:</strong> ' . htmlspecialchars($to) . '</p>'
        . '<hr>'
        . nl2br(htmlspecialchars($body), false)
        . '</body></html>';

    file_put_contents($file, $html);
}

function send_mail_message(string $to, string $subject, string $body): bool
{
    if (DEBUG_MODE) {
        write_email_log($to, $subject, $body);
        return true;
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . SITE_NAME . ' <noreply@' . parse_url(SITE_URL, PHP_URL_HOST) . '>',
    ];

    $ok = @mail($to, $subject, $body, implode("\r\n", $headers));
    if (!$ok) {
        write_email_log($to, $subject, $body);
    }

    return $ok;
}
