<?php
class Config
{
    private static $pdo = null;
    private static $envLoaded = false;

    /**
     * Charge le fichier .env
     */
    private static function loadEnv()
    {
        if (self::$envLoaded) return;

        $envPath = __DIR__ . '/.env';

        if (!file_exists($envPath)) {
            die("Erreur: Fichier .env manquant à la racine du projet.");
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;
            if (strpos($line, '=') === false) continue;

            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }

        self::$envLoaded = true;
    }

    /**
     * Récupère une variable d'environnement
     */
    public static function getEnv($key, $default = null)
    {
        self::loadEnv();
        return $_ENV[$key] ?? $default;
    }

    /**
     * NOUVEAU : Clé API Groq
     */
    public static function getApiKey()
    {
        $key = self::getEnv('GROQ_API_KEY');
        if (!$key) {
            die("Erreur: GROQ_API_KEY non définie dans .env");
        }
        return $key;
    }

    /**
     * Votre connexion BDD existante (conservée)
     */
    public static function getConnexion()
    {
        if (!isset(self::$pdo)) {
            $servername = "localhost";
            $username = "root";
            $password = "";
            $dbname = "webnova";

            try {
                self::$pdo = new PDO(
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
}

// Gardé pour compatibilité
Config::getConnexion();
?>