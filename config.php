<?php
declare(strict_types=1);

/**
 * Configuration de base de données avec auto-détection et diagnostic
 * Compatible XAMPP / MySQL local
 */

// Configuration par défaut (peut être surchargée via variables d'environnement)
define('DB_DRIVER', getenv('DB_DRIVER') ?: 'mysql');
define('DB_NAME', getenv('DB_NAME') ?: 'projetweb');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');
define('DB_SQLITE_PATH', getenv('DB_SQLITE_PATH') ?: ':memory:');

class Config
{
    private static ?PDO $pdo = null;
    private static array $triedConfigs = [];

    /**
     * Tente de se connecter avec une configuration spécifique
     */
    private static function tryConnection(string $host, int $port, string $dbName): ?PDO
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $host,
                $port,
                $dbName,
                DB_CHARSET
            );

            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 3, // Timeout de 3 secondes
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

    /**
     * Détecte automatiquement la configuration MySQL disponible
     */
    private static function detectMySQLConnection(): ?PDO
    {
        // Configurations à tester (ordre de priorité)
        $configs = [
            ['host' => '127.0.0.1', 'port' => 3306, 'db' => DB_NAME],
            ['host' => 'localhost', 'port' => 3306, 'db' => DB_NAME],
            ['host' => '127.0.0.1', 'port' => 3307, 'db' => DB_NAME], // Port alternatif XAMPP
            ['host' => 'localhost', 'port' => 3307, 'db' => DB_NAME],
            ['host' => '127.0.0.1', 'port' => 3308, 'db' => DB_NAME], // Autre port alternatif
            ['host' => '127.0.0.1', 'port' => 3306, 'db' => 'mysql'], // Base système par défaut
        ];

        // Si variables d'environnement spécifiques sont définies, tester d'abord
        if (getenv('DB_HOST') && getenv('DB_PORT')) {
            array_unshift($configs, [
                'host' => getenv('DB_HOST'),
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

    /**
     * Génère un message d'erreur détaillé avec diagnostic
     */
    private static function generateErrorMessage(): string
    {
        $message = "ERREUR DE CONNEXION MYSQL - DIAGNOSTIC\n";
        $message .= str_repeat("=", 50) . "\n\n";

        // Vérifier si MySQL semble démarré
        $mysqlRunning = false;
        $portsToCheck = [3306, 3307, 3308];
        foreach ($portsToCheck as $port) {
            $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
            if ($connection) {
                fclose($connection);
                $mysqlRunning = true;
                $message .= "✓ MySQL détecté sur le port {$port}\n";
            }
        }

        if (!$mysqlRunning) {
            $message .= "✗ ERREUR CRITIQUE : MySQL ne semble pas démarré !\n";
            $message .= "  → Vérifiez XAMPP Control Panel\n";
            $message .= "  → Assurez-vous que le service 'MySQL' est démarré (bouton 'Start')\n\n";
        }

        $message .= "Configurations testées :\n";
        foreach (self::$triedConfigs as $i => $config) {
            $message .= "  " . ($i + 1) . ") " . $config['dsn'] . "\n";
            $message .= "     Erreur : " . $config['error'] . "\n\n";
        }

        $message .= "\nSOLUTIONS POSSIBLES :\n";
        $message .= "1. Démarrez MySQL dans XAMPP Control Panel\n";
        $message .= "2. Si MySQL utilise un port différent, modifiez config.php\n";
        $message .= "3. Vérifiez que la base '" . DB_NAME . "' existe dans phpMyAdmin\n";
        $message .= "4. Vérifiez le nom d'utilisateur et le mot de passe\n";

        return $message;
    }

    /**
     * Obtient une connexion PDO à la base de données
     * @return PDO
     * @throws RuntimeException si la connexion échoue
     */
    public static function getConnexion(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        // Mode SQLite
        if (strtolower(DB_DRIVER) === 'sqlite') {
            try {
                $dsn = 'sqlite:' . DB_SQLITE_PATH;
                self::$pdo = new PDO($dsn);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                self::$pdo->exec('PRAGMA foreign_keys = ON');
                return self::$pdo;
            } catch (PDOException $e) {
                throw new RuntimeException('Erreur de connexion SQLite : ' . $e->getMessage(), 0, $e);
            }
        }

        // Mode MySQL avec auto-détection
        self::$pdo = self::detectMySQLConnection();

        if (self::$pdo === null) {
            $errorMessage = self::generateErrorMessage();

            // En mode développement, afficher le diagnostic
            if (getenv('ENVIRONMENT') === 'dev' || !getenv('ENVIRONMENT')) {
                echo "<pre style='background:#ffebee;padding:20px;border:2px solid #c62828;'>";
                echo htmlspecialchars($errorMessage);
                echo "</pre>";
            }

            error_log($errorMessage);

            throw new RuntimeException(
                "Impossible de se connecter à MySQL. " .
                "Vérifiez que MySQL est démarré dans XAMPP. " .
                "Voir les logs pour plus de détails.",
                0
            );
        }

        return self::$pdo;
    }

    /**
     * Test rapide de la connexion (pour diagnostic)
     * @return array résultat du test
     */
    public static function testConnection(): array
    {
        try {
            $pdo = self::getConnexion();
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
            return [
                'success' => true,
                'message' => 'Connexion réussie',
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
}
