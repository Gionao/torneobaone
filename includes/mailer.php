<?php
// includes/mailer.php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function write_email_log(string $to, string $subject, string $body, ?string $error = null): void
{
    $dir = __DIR__ . '/../storage/email_log';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    try {
        $file = $dir . '/email_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.log';
        $recipientHash = hash('sha256', strtolower(trim($to)));
        $entry = sprintf("%s recipient_sha256=%s subject=%s error=%s\n", date(DATE_ATOM), $recipientHash, json_encode($subject), json_encode($error ?? 'unknown'));
        @file_put_contents($file, $entry, LOCK_EX);
    } catch (Throwable $e) {
        error_log('[MAILER] Could not write email failure log.');
    }
}

function build_email_template(string $title, string $intro, string $ctaLabel, string $ctaUrl, ?string $expiryText = null, ?string $altText = null): string
{
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeIntro = nl2br(htmlspecialchars($intro, ENT_QUOTES, 'UTF-8'), false);
    $safeCta = htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8');
    $safeLink = htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8');
    $safeExpiry = $expiryText ? '<p style="margin: 1rem 0 0; color: #6b7280; font-size: 12px;">' . htmlspecialchars($expiryText, ENT_QUOTES, 'UTF-8') . '</p>' : '';
    $safeAlt = $altText ? '<p style="margin-top: 1rem; font-size: 12px; color: #6b7280;">Se il pulsante non funziona, copia e incolla questo link nel browser:<br><a href="' . $safeLink . '" style="color:#1a3a5c; word-break:break-all;">' . $safeLink . '</a></p>' : '';

    return '<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>' . $safeTitle . '</title>
</head>
<body style="margin:0; padding:0; background:#eef2f7; font-family:Arial,Helvetica,sans-serif; color:#1f2937;">
  <div style="max-width:640px; margin:24px auto; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">
    <div style="background:#1a3a5c; padding:20px 28px; color:#ffffff;">
      <div style="font-size:12px; letter-spacing:0.12em; text-transform:uppercase; opacity:0.8;">' . htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') . '</div>
      <h1 style="margin:8px 0 0; font-size:28px; line-height:1.2;">' . $safeTitle . '</h1>
    </div>
    <div style="padding:28px;">
      <p style="margin:0 0 18px; font-size:16px; line-height:1.6;">' . $safeIntro . '</p>
      <div style="text-align:center; margin:24px 0;">
        <a href="' . $safeLink . '" style="display:inline-block; padding:14px 24px; background:#d4af37; color:#111827; text-decoration:none; border-radius:8px; font-weight:bold;">' . $safeCta . '</a>
      </div>
      ' . $safeAlt . '
      ' . $safeExpiry . '
      <p style="margin-top:24px; font-size:12px; color:#6b7280; line-height:1.6;">Se non hai richiesto questa comunicazione, puoi ignorare questa email.</p>
    </div>
  </div>
</body>
</html>';
}

function send_email(string $recipient, string $subject, string $htmlBody, ?string &$failureReason = null): bool
{
    $failureReason = null;
    $recipient = trim($recipient);
    $subject = trim($subject);

    if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        $failureReason = 'Indirizzo email destinatario non valido.';
        error_log('[MAILER] Invalid recipient address.');
        return false;
    }

    $smtpHost = getenv('SMTP_HOST') ?: SMTP_HOST;
    $smtpUser = getenv('SMTP_USERNAME') ?: SMTP_USERNAME;
    $smtpPass = getenv('SMTP_PASSWORD') ?: SMTP_PASSWORD;
    $smtpPort = (int) (getenv('SMTP_PORT') ?: SMTP_PORT);
    $smtpEnc = (string) (getenv('SMTP_ENCRYPTION') ?: SMTP_ENCRYPTION);

    $smtpPort = (int) $smtpPort;
    $smtpEnc = strtolower(trim($smtpEnc));
    $smtpAuthPartial = (($smtpUser === '') xor ($smtpPass === ''));
    $isSandboxHost = stripos($smtpHost, 'sandbox.smtp.mailtrap.io') !== false;
    $fromDomain = strtolower(substr(strrchr((string) MAIL_FROM_ADDRESS, '@') ?: '', 1));
    $invalidProductionSender = ENV === 'production' && preg_match('/\.(test|localhost)$/', $fromDomain);
    if ($smtpHost === '' || $smtpPort < 1 || $smtpPort > 65535 || $smtpAuthPartial || !in_array($smtpEnc, ['tls', 'ssl', 'none'], true) || !filter_var(MAIL_FROM_ADDRESS, FILTER_VALIDATE_EMAIL) || (ENV === 'production' && $isSandboxHost) || $invalidProductionSender) {
        $failureReason = 'Configurazione SMTP incompleta o non valida.';
        write_email_log($recipient, $subject, '', $failureReason);
        error_log('[MAILER] Invalid SMTP or sender configuration. Check host, port, authentication pair, encryption and sender address.');
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = ($smtpUser !== '');
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = $smtpEnc === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : ($smtpEnc === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : false);
        $mail->Port = $smtpPort > 0 ? $smtpPort : 587;
        $mail->Timeout = 15;
        //$mail->Timelimit = 15;
        $mail->CharSet = 'UTF-8';
        $mail->SMTPKeepAlive = false;
        $mail->Mailer = 'smtp';
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addReplyTo(MAIL_REPLY_TO ?: MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($recipient);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);

        $mail->send();
        return true;
    } catch (Throwable $e) {
        $failureReason = 'Il server email non ha accettato il messaggio. Riprova tra poco o contatta il capitano.';
        write_email_log($recipient, $subject, '', $e->getMessage());
        error_log('[MAILER] SMTP send failed: ' . $e->getMessage());
        return false;
    }
}

function send_mail_message(string $to, string $subject, string $body): bool
{
    return send_email($to, $subject, $body);
}
