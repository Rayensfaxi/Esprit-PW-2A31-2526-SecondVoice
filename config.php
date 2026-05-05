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
            $dbname = "accompagnement";
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
}
Config::getConnexion();