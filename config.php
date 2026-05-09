<?php
declare(strict_types=1);

define('DB_DRIVER', getenv('DB_DRIVER') ?: 'mysql');
define('DB_NAME', getenv('DB_NAME') ?: 'secondvoice');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');
define('DB_SQLITE_PATH', getenv('DB_SQLITE_PATH') ?: ':memory:');

$smtpLocalConfig = [];
if (is_file(__DIR__ . '/smtp_config.php')) {
    $smtpLocalConfig = require __DIR__ . '/smtp_config.php';
    if (!is_array($smtpLocalConfig)) {
        $smtpLocalConfig = [];
    }
}

define('SMTP_HOST', getenv('SMTP_HOST') ?: (string) ($smtpLocalConfig['host'] ?? ''));
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: (string) ($smtpLocalConfig['username'] ?? ''));
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: (string) ($smtpLocalConfig['password'] ?? ''));
define('SMTP_PORT', (int) (getenv('SMTP_PORT') ?: ($smtpLocalConfig['port'] ?? 587)));
define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: (string) ($smtpLocalConfig['encryption'] ?? 'tls'));
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: (string) ($smtpLocalConfig['from_email'] ?? (SMTP_USERNAME ?: 'no-reply@secondvoice.local')));
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: (string) ($smtpLocalConfig['from_name'] ?? 'SecondVoice'));
define('SMTP_DEBUG', (int) (getenv('SMTP_DEBUG') ?: ($smtpLocalConfig['debug'] ?? 0)));

class Config
{
    private static ?PDO $pdo = null;
    private static array $triedConfigs = [];

    private static array $mailManual = [
        'provider' => 'brevo'
    ];

    private static array $brevoManual = [
        'api_key' => '',
        'from_email' => 'sfrayen54@gmail.com',
        'from_name' => 'SecondVoice',
        'timeout' => 20
    ];

    private static array $smtpManual = [
        'host' => 'smtp.gmail.com',
        'port' => 465,
        'encryption' => 'ssl',
        'username' => '',
        'password' => '',
        'from_email' => '',
        'from_name' => 'SecondVoice',
        'timeout' => 20
    ];

    private static array $recaptchaManual = [
        'enabled' => true,
        'site_key' => '',
        'secret_key' => ''
    ];

    private static array $appManual = [
        'public_base_url' => '',
        'face_enroll_code' => 'SV-ADMIN-2026'
    ];

    private static function tryConnection(string $host, int $port, string $dbName): ?PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $dbName, DB_CHARSET);

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 3,
            ]);

            return $pdo;
        } catch (PDOException $e) {
            self::$triedConfigs[] = [
                'dsn' => $dsn,
                'error' => $e->getMessage()
            ];
            return null;
        }
    }

    private static function detectMySQLConnection(): ?PDO
    {
        $configs = [
            ['host' => '127.0.0.1', 'port' => 3306, 'db' => DB_NAME],
            ['host' => 'localhost', 'port' => 3306, 'db' => DB_NAME],
            ['host' => '127.0.0.1', 'port' => 3307, 'db' => DB_NAME],
            ['host' => 'localhost', 'port' => 3307, 'db' => DB_NAME],
            ['host' => '127.0.0.1', 'port' => 3308, 'db' => DB_NAME],
        ];

        if (getenv('DB_HOST') && getenv('DB_PORT')) {
            array_unshift($configs, [
                'host' => (string) getenv('DB_HOST'),
                'port' => (int) getenv('DB_PORT'),
                'db' => DB_NAME
            ]);
        }

        foreach ($configs as $config) {
            $pdo = self::tryConnection($config['host'], $config['port'], $config['db']);
            if ($pdo !== null) {
                return $pdo;
            }
        }

        return null;
    }

    private static function generateErrorMessage(): string
    {
        $message = "ERREUR DE CONNEXION MYSQL - DIAGNOSTIC\n";
        $message .= str_repeat('=', 50) . "\n\n";

        $mysqlRunning = false;
        foreach ([3306, 3307, 3308] as $port) {
            $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
            if ($connection) {
                fclose($connection);
                $mysqlRunning = true;
                $message .= "MySQL detecte sur le port {$port}\n";
            }
        }

        if (!$mysqlRunning) {
            $message .= "MySQL ne semble pas demarre. Verifiez XAMPP Control Panel.\n\n";
        }

        $message .= "Configurations testees :\n";
        foreach (self::$triedConfigs as $i => $config) {
            $message .= '  ' . ($i + 1) . ') ' . $config['dsn'] . "\n";
            $message .= '     Erreur : ' . $config['error'] . "\n\n";
        }

        $message .= "Solutions possibles : demarrer MySQL, verifier le port, verifier la base '" . DB_NAME . "'.\n";

        return $message;
    }

    public static function getConnexion(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        if (strtolower((string) DB_DRIVER) === 'sqlite') {
            try {
                self::$pdo = new PDO('sqlite:' . DB_SQLITE_PATH);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                self::$pdo->exec('PRAGMA foreign_keys = ON');
                return self::$pdo;
            } catch (PDOException $e) {
                throw new RuntimeException('Erreur de connexion SQLite : ' . $e->getMessage(), 0, $e);
            }
        }

        self::$pdo = self::detectMySQLConnection();
        if (self::$pdo === null) {
            $errorMessage = self::generateErrorMessage();
            error_log($errorMessage);
            throw new RuntimeException('Impossible de se connecter a MySQL. Voir les logs pour plus de details.');
        }

        return self::$pdo;
    }

    public static function testConnection(): array
    {
        try {
            $pdo = self::getConnexion();
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
            return [
                'success' => true,
                'message' => 'Connexion reussie',
                'mysql_version' => $version
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'details' => self::$triedConfigs
            ];
        }
    }

    public static function getSmtpConfig(): array
    {
        $host = getenv('SECONDVOICE_SMTP_HOST') ?: getenv('SMTP_HOST');
        $port = getenv('SECONDVOICE_SMTP_PORT') ?: getenv('SMTP_PORT');
        $encryption = getenv('SECONDVOICE_SMTP_ENCRYPTION') ?: getenv('SMTP_ENCRYPTION');
        $username = getenv('SECONDVOICE_SMTP_USER') ?: getenv('SMTP_USER');
        $password = getenv('SECONDVOICE_SMTP_PASS') ?: getenv('SMTP_PASS');
        $fromEmail = getenv('SECONDVOICE_MAIL_FROM') ?: getenv('MAIL_FROM');
        $fromName = getenv('SECONDVOICE_MAIL_FROM_NAME') ?: getenv('MAIL_FROM_NAME');

        $manual = self::$smtpManual;
        $defaultUsername = (string) ($manual['username'] ?? '');

        return [
            'host' => $host !== false && $host !== '' ? $host : (string) ($manual['host'] ?? 'smtp.gmail.com'),
            'port' => $port !== false && $port !== '' ? (int) $port : (int) ($manual['port'] ?? 465),
            'encryption' => $encryption !== false && $encryption !== '' ? strtolower((string) $encryption) : strtolower((string) ($manual['encryption'] ?? 'ssl')),
            'username' => $username !== false && $username !== '' ? $username : $defaultUsername,
            'password' => $password !== false && $password !== '' ? $password : (string) ($manual['password'] ?? ''),
            'from_email' => $fromEmail !== false && $fromEmail !== '' ? $fromEmail : (string) (($manual['from_email'] ?? '') ?: (($username !== false && $username !== '') ? $username : $defaultUsername)),
            'from_name' => $fromName !== false && $fromName !== '' ? $fromName : (string) ($manual['from_name'] ?? 'SecondVoice'),
            'timeout' => (int) ($manual['timeout'] ?? 20)
        ];
    }

    public static function getMailProvider(): string
    {
        $env = getenv('SECONDVOICE_MAIL_PROVIDER') ?: getenv('MAIL_PROVIDER');
        if ($env !== false && $env !== '') {
            $provider = strtolower(trim((string) $env));
            if (in_array($provider, ['brevo', 'smtp', 'auto'], true)) {
                return $provider;
            }
        }

        return strtolower((string) (self::$mailManual['provider'] ?? 'brevo'));
    }

    public static function getBrevoConfig(): array
    {
        $apiKey = getenv('SECONDVOICE_BREVO_API_KEY') ?: getenv('BREVO_API_KEY');
        $fromEmail = getenv('SECONDVOICE_MAIL_FROM') ?: getenv('MAIL_FROM');
        $fromName = getenv('SECONDVOICE_MAIL_FROM_NAME') ?: getenv('MAIL_FROM_NAME');
        $smtpUser = getenv('SECONDVOICE_SMTP_USER') ?: getenv('SMTP_USER');

        $manual = self::$brevoManual;
        $resolvedFromEmail = (string) ($manual['from_email'] ?? '');
        if ($fromEmail !== false && $fromEmail !== '' && filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $resolvedFromEmail = (string) $fromEmail;
        } elseif ($resolvedFromEmail === '' && $smtpUser !== false) {
            $resolvedFromEmail = (string) $smtpUser;
        }

        return [
            'api_key' => $apiKey !== false && trim((string) $apiKey) !== '' ? trim((string) $apiKey) : (string) ($manual['api_key'] ?? ''),
            'from_email' => $resolvedFromEmail,
            'from_name' => $fromName !== false && trim((string) $fromName) !== '' ? trim((string) $fromName) : (string) ($manual['from_name'] ?? 'SecondVoice'),
            'timeout' => (int) ($manual['timeout'] ?? 20)
        ];
    }

    public static function getRecaptchaConfig(): array
    {
        $enabled = getenv('SECONDVOICE_RECAPTCHA_ENABLED') ?: getenv('RECAPTCHA_ENABLED');
        $siteKey = getenv('SECONDVOICE_RECAPTCHA_SITE_KEY') ?: getenv('RECAPTCHA_SITE_KEY');
        $secretKey = getenv('SECONDVOICE_RECAPTCHA_SECRET_KEY') ?: getenv('RECAPTCHA_SECRET_KEY');

        $manual = self::$recaptchaManual;
        $enabledValue = (bool) ($manual['enabled'] ?? true);
        if ($enabled !== false && $enabled !== '') {
            $enabledValue = !in_array(strtolower(trim((string) $enabled)), ['0', 'false', 'off', 'no'], true);
        }

        return [
            'enabled' => $enabledValue,
            'site_key' => $siteKey !== false && trim((string) $siteKey) !== '' ? trim((string) $siteKey) : trim((string) ($manual['site_key'] ?? '')),
            'secret_key' => $secretKey !== false && trim((string) $secretKey) !== '' ? trim((string) $secretKey) : trim((string) ($manual['secret_key'] ?? ''))
        ];
    }

    public static function getPublicBaseUrl(): string
    {
        $env = getenv('SECONDVOICE_PUBLIC_BASE_URL') ?: getenv('PUBLIC_BASE_URL');
        if ($env !== false && trim((string) $env) !== '') {
            return rtrim(trim((string) $env), '/');
        }

        return rtrim((string) (self::$appManual['public_base_url'] ?? ''), '/');
    }

    public static function getFaceEnrollCode(): string
    {
        $env = getenv('SECONDVOICE_FACE_ENROLL_CODE') ?: getenv('FACE_ENROLL_CODE');
        if ($env !== false && trim((string) $env) !== '') {
            return trim((string) $env);
        }

        return trim((string) (self::$appManual['face_enroll_code'] ?? ''));
    }
}
