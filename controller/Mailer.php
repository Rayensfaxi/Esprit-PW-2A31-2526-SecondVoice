<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/SmtpMailer.php';
require_once __DIR__ . '/BrevoMailer.php';

$GLOBALS['LAST_MAIL_ERROR'] = '';

function getLastMailError(): string
{
    return (string) ($GLOBALS['LAST_MAIL_ERROR'] ?? '');
}

function sendMail(string $to, string $subject, string $message): bool
{
    $GLOBALS['LAST_MAIL_ERROR'] = '';
    $to = trim($to);

    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $GLOBALS['LAST_MAIL_ERROR'] = 'Destinataire invalide.';
        return false;
    }

    $provider = strtolower((string) Config::getMailProvider());
    $error = '';

    if ($provider === 'brevo' || $provider === 'auto') {
        $brevoMailer = new BrevoMailer(Config::getBrevoConfig());
        if ($brevoMailer->send($to, $subject, $message)) {
            return true;
        }
        $error = $brevoMailer->getLastError();
    }

    if ($provider === 'smtp' || $provider === 'auto') {
        $smtpMailer = new SmtpMailer(Config::getSmtpConfig());
        if ($smtpMailer->send($to, $subject, $message)) {
            return true;
        }
        $smtpError = $smtpMailer->getLastError();
        $error = $error !== '' ? ($error . ' | SMTP: ' . $smtpError) : $smtpError;
    }

    $GLOBALS['LAST_MAIL_ERROR'] = $error !== '' ? $error : 'Aucun fournisseur mail valide.';
    return false;
}
