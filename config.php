<?php
// ➡️ On déclare une classe appelée config.
// Elle va gérer la connexion à la base de données
class Config
{


    // private → accessible uniquement dans la classe.
    // static → appartient à la classe, pas aux objets.
    // $pdo → variable qui va contenir la connexion.
    // = null → au début, il n’y a pas de connexion. Cette variable va stocker l’objet PDO une seule fois.
    private static $pdo = null;
    private static array $mailManual = [
        // Provider: 'brevo', 'smtp', ou 'auto' (brevo puis smtp)
        'provider' => 'brevo'
    ];
    private static array $brevoManual = [
        // REMPLIS CES VALEURS POUR BREVO API
        'api_key' => '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',
        'from_email' => 'sfrayen54@gmail.com',
        'from_name' => 'SecondVoice',
        'timeout' => 20
    ];
    private static array $smtpManual = [
        // REMPLIS CES VALEURS POUR UN ENVOI REEL GMAIL SMTP
        'host' => 'smtp.gmail.com',
        'port' => 465,
        'encryption' => 'ssl', // ssl (465) ou tls (587)
        'username' => '', // ex: votreadresse@gmail.com
        'password' => '', // App Password Gmail (16 caracteres)
        'from_email' => '', // laisser vide pour reprendre username
        'from_name' => 'SecondVoice',
        'timeout' => 20
    ];
    public static function getConnexion()
    {
        //         public → accessible partout.

        // static → on peut appeler la méthode sans créer d’objet.

        // getConnexion() → méthode pour récupérer la connexion.
        if (!isset(self::$pdo)) {
            // self::$pdo → on accède à la variable statique.
            // isset() → vérifie si elle existe.
            // ! → signifie "si elle n'existe pas".
            $servername = "localhost";
            $username = "root";
            $password = "";
            $dbname = "secondvoice";
            try {
                // On crée une nouvelle connexion PDO et on la stocke dans $pdo.
                self::$pdo = new PDO(
                    // DSN (Data Source Name)
                    // Indique :
                    // type = mysql
                    // host = localhost
                    // base = secondvoice
                    "mysql:host=$servername;dbname=$dbname",
                    $username,
                    $password

                );
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                die('Erreur: ' . $e->getMessage());
            }
        }
        return self::$pdo;
    }

    public static function getSmtpConfig(): array
    {
        $host = getenv('SECONDVOICE_SMTP_HOST');
        $port = getenv('SECONDVOICE_SMTP_PORT');
        $encryption = getenv('SECONDVOICE_SMTP_ENCRYPTION');
        $username = getenv('SECONDVOICE_SMTP_USER');
        $password = getenv('SECONDVOICE_SMTP_PASS');
        $fromEmail = getenv('SECONDVOICE_MAIL_FROM');
        $fromName = getenv('SECONDVOICE_MAIL_FROM_NAME');

        $manual = self::$smtpManual;
        $defaultHost = (string) ($manual['host'] ?? 'smtp.gmail.com');
        $defaultPort = (int) ($manual['port'] ?? 465);
        $defaultEncryption = strtolower((string) ($manual['encryption'] ?? 'ssl'));
        $defaultUsername = (string) ($manual['username'] ?? '');
        $defaultPassword = (string) ($manual['password'] ?? '');
        $defaultFromEmail = (string) ($manual['from_email'] ?? '');
        $defaultFromName = (string) ($manual['from_name'] ?? 'SecondVoice');
        $defaultTimeout = (int) ($manual['timeout'] ?? 20);

        return [
            'host' => $host !== false && $host !== '' ? $host : $defaultHost,
            'port' => $port !== false && $port !== '' ? (int) $port : $defaultPort,
            'encryption' => $encryption !== false && $encryption !== '' ? strtolower($encryption) : $defaultEncryption,
            'username' => $username !== false && $username !== '' ? $username : $defaultUsername,
            'password' => $password !== false && $password !== '' ? $password : $defaultPassword,
            'from_email' => $fromEmail !== false && $fromEmail !== '' ? $fromEmail : ($defaultFromEmail !== '' ? $defaultFromEmail : ($username !== false && $username !== '' ? $username : $defaultUsername)),
            'from_name' => $fromName !== false && $fromName !== '' ? $fromName : $defaultFromName,
            'timeout' => $defaultTimeout
        ];
    }

    public static function getMailProvider(): string
    {
        $env = getenv('SECONDVOICE_MAIL_PROVIDER');
        if ($env !== false && $env !== '') {
            return strtolower(trim($env));
        }

        return strtolower((string) (self::$mailManual['provider'] ?? 'brevo'));
    }

    public static function getBrevoConfig(): array
    {
        $apiKey = getenv('SECONDVOICE_BREVO_API_KEY');
        $fromEmail = getenv('SECONDVOICE_MAIL_FROM');
        $fromName = getenv('SECONDVOICE_MAIL_FROM_NAME');

        $manual = self::$brevoManual;
        $defaultApiKey = (string) ($manual['api_key'] ?? '');
        $defaultFromEmail = (string) ($manual['from_email'] ?? '');
        $defaultFromName = (string) ($manual['from_name'] ?? 'SecondVoice');
        $defaultTimeout = (int) ($manual['timeout'] ?? 20);

        return [
            'api_key' => $apiKey !== false && $apiKey !== '' ? $apiKey : $defaultApiKey,
            'from_email' => $fromEmail !== false && $fromEmail !== '' ? $fromEmail : $defaultFromEmail,
            'from_name' => $fromName !== false && $fromName !== '' ? $fromName : $defaultFromName,
            'timeout' => $defaultTimeout
        ];
    }
}
Config::getConnexion();
