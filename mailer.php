<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/PHPMailer/src/Exception.php';
require_once __DIR__ . '/vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

function getLastMailError(): string
{
    return (string) ($GLOBALS['LAST_MAIL_ERROR'] ?? '');
}

function mailDebugLog(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    error_log($line, 3, __DIR__ . '/mail_debug.log');
}

function getSmtpConfig(): array
{
    $config = require __DIR__ . '/smtp_config.php';
    if (!is_array($config)) {
        throw new RuntimeException('smtp_config.php doit retourner un tableau.');
    }

    return [
        'host' => trim((string) ($config['host'] ?? '')),
        'username' => trim((string) ($config['username'] ?? '')),
        'password' => (string) ($config['password'] ?? ''),
        'port' => (int) ($config['port'] ?? 587),
        'encryption' => strtolower(trim((string) ($config['encryption'] ?? 'tls'))),
        'from_email' => trim((string) ($config['from_email'] ?? ($config['username'] ?? ''))),
        'from_name' => trim((string) ($config['from_name'] ?? 'SecondVoice')),
        'debug' => (int) ($config['debug'] ?? 0),
    ];
}

function sendMail(string $to, string $subject, string $message): bool
{
    $GLOBALS['LAST_MAIL_ERROR'] = '';
    $to = trim($to);
    error_log('sendMail appelee vers : ' . $to);
    mailDebugLog('sendMail appelee vers : ' . $to . ' | sujet="' . $subject . '"');

    try {
        $config = getSmtpConfig();
    } catch (Throwable $e) {
        $GLOBALS['LAST_MAIL_ERROR'] = 'Configuration SMTP illisible: ' . $e->getMessage();
        error_log('Mail non envoye: ' . $GLOBALS['LAST_MAIL_ERROR']);
        mailDebugLog('ECHEC: ' . $GLOBALS['LAST_MAIL_ERROR']);
        return false;
    }

    mailDebugLog(
        'SMTP config: host="' . $config['host']
        . '", port=' . $config['port']
        . ', username="' . $config['username']
        . '", password_present=' . ($config['password'] !== '' ? 'yes' : 'no')
        . ', encryption="' . $config['encryption']
        . '", from="' . $config['from_email'] . '"'
    );

    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $GLOBALS['LAST_MAIL_ERROR'] = 'Destinataire invalide: ' . $to;
        error_log('Mail non envoye: ' . $GLOBALS['LAST_MAIL_ERROR']);
        mailDebugLog('ECHEC: ' . $GLOBALS['LAST_MAIL_ERROR']);
        return false;
    }

    if ($config['host'] === '' || $config['username'] === '' || $config['password'] === '') {
        $GLOBALS['LAST_MAIL_ERROR'] = 'Configuration SMTP incomplete. host="' . $config['host'] . '", username="' . $config['username'] . '", password_present=' . ($config['password'] !== '' ? 'yes' : 'no');
        error_log('Mail non envoye: ' . $GLOBALS['LAST_MAIL_ERROR']);
        mailDebugLog('ECHEC: ' . $GLOBALS['LAST_MAIL_ERROR']);
        return false;
    }

    if ($config['from_email'] === '' || !filter_var($config['from_email'], FILTER_VALIDATE_EMAIL)) {
        $GLOBALS['LAST_MAIL_ERROR'] = 'Adresse from_email SMTP invalide: ' . $config['from_email'];
        error_log('Mail non envoye: ' . $GLOBALS['LAST_MAIL_ERROR']);
        mailDebugLog('ECHEC: ' . $GLOBALS['LAST_MAIL_ERROR']);
        return false;
    }

    try {
        $mail = new PHPMailer(true);
        $mail->SMTPDebug = $config['debug'] > 0 ? $config['debug'] : SMTP::DEBUG_OFF;

        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->Port = $config['port'];

        if ($config['encryption'] === 'ssl' || $config['encryption'] === PHPMailer::ENCRYPTION_SMTPS) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($config['encryption'] === 'tls' || $config['encryption'] === PHPMailer::ENCRYPTION_STARTTLS) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->CharSet = 'UTF-8';
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags(str_replace(["<br>", "<br/>", "<br />"], "\n", $message));
        $mail->isHTML(false);

        $sent = $mail->send();
        if (!$sent) {
            $GLOBALS['LAST_MAIL_ERROR'] = $mail->ErrorInfo;
            error_log('Erreur PHPMailer vers ' . $to . ': ' . $GLOBALS['LAST_MAIL_ERROR']);
            mailDebugLog('ECHEC PHPMailer vers ' . $to . ': ' . $GLOBALS['LAST_MAIL_ERROR']);
            return false;
        }

        error_log('Mail envoye avec succes vers=' . $to);
        mailDebugLog('OK: mail envoye vers ' . $to);

        return true;
    } catch (MailException $e) {
        $errorInfo = isset($mail) && $mail instanceof PHPMailer ? $mail->ErrorInfo : '';
        $GLOBALS['LAST_MAIL_ERROR'] = $e->getMessage() . ($errorInfo !== '' ? ' | ErrorInfo: ' . $errorInfo : '');
        error_log('Erreur PHPMailer vers ' . $to . ': ' . $GLOBALS['LAST_MAIL_ERROR']);
        mailDebugLog('ECHEC PHPMailer vers ' . $to . ': ' . $GLOBALS['LAST_MAIL_ERROR']);
        return false;
    } catch (Throwable $e) {
        $GLOBALS['LAST_MAIL_ERROR'] = $e->getMessage();
        error_log('Erreur mail vers ' . $to . ': ' . $GLOBALS['LAST_MAIL_ERROR']);
        mailDebugLog('ECHEC mail vers ' . $to . ': ' . $GLOBALS['LAST_MAIL_ERROR']);
        return false;
    }
}
