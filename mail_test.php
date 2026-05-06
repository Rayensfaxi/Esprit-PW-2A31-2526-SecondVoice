<?php
declare(strict_types=1);

require_once __DIR__ . '/mailer.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    session_start();
    if (!isset($_SESSION['user_id']) || strtolower((string) ($_SESSION['user_role'] ?? '')) !== 'admin') {
        http_response_code(403);
        echo 'Acces refuse. Connectez-vous en admin pour lancer le test mail.';
        exit;
    }
}

try {
    $pdo = Config::getConnexion();
    $stmt = $pdo->prepare("SELECT email FROM utilisateur WHERE LOWER(role) = 'admin' AND email IS NOT NULL AND email <> '' ORDER BY id ASC LIMIT 1");
    $stmt->execute();
    $adminEmail = trim((string) $stmt->fetchColumn());
    $targetEmail = $isCli
        ? trim((string) ($argv[1] ?? $adminEmail))
        : trim((string) ($_GET['to'] ?? $adminEmail));

    if ($targetEmail === '') {
        echo "Aucun email cible trouve. Ajoutez un admin avec role = 'admin' ou passez une adresse en argument.";
        exit;
    }

    if (!filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
        echo 'Adresse email de test invalide : ' . htmlspecialchars($targetEmail, ENT_QUOTES, 'UTF-8');
        exit;
    }

    $sent = sendMail(
        $targetEmail,
        'Test SecondVoice',
        "Mail de test\nDate : " . date('Y-m-d H:i:s')
    );

    if ($sent) {
        echo 'Mail de test envoye avec succes a ' . htmlspecialchars($targetEmail, ENT_QUOTES, 'UTF-8');
        exit;
    }

    echo 'Echec du mail de test vers ' . htmlspecialchars($targetEmail, ENT_QUOTES, 'UTF-8') . "\n";
    echo 'Erreur PHPMailer/SMTP : ' . htmlspecialchars(getLastMailError(), ENT_QUOTES, 'UTF-8');
} catch (Throwable $e) {
    echo 'Erreur test mail : ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
