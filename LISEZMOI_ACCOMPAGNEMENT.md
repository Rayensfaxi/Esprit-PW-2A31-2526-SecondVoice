# Intégration du Module Accompagnement (Goals & Guides)

## 1. Base de Données
Le fichier `database_accompagnement.sql` a été placé à la racine de votre projet. Importez-le dans votre base phpMyAdmin `secondvoice` afin de créer les 2 nouvelles tables : `goals` et `guides`.

Si vous n'avez pas de table `utilisateurs`, veillez à l'avoir puisque ces tables se rattachent avec des clefs étrangères aux colonnes `user_id` (créateur) et `selected_assistant_id` (l'assistant du projet).

## 2. Models et Controllers
J'ai généré quatre fichiers :
- `model/goal.php` : Classe entité pour vos objectifs d'accompagnement.
- `model/guide.php` : Classe entité pour les guides / étapes.
- `controller/GoalController.php` : Tous les contrôles de la vie d'un Goal (soumission, modération côté Admin, validation et suivi côté Assistant).
- `controller/GuideController.php` : Contrôle des étapes par l'Assistant lié.

## 3. Comment les utiliser dans vos Vues (Views/Routes)

Vous utiliserez maintenant ces classes dans vos pages PHP (ex: `gestion-accompagnements.php`, `service-details.php`, `mes-goals.php`, etc).

### Exemple : L'utilisateur crée un objectif "Refaire mon CV" :

```php
<?php
session_start();
require_once '../controller/GoalController.php';

// Si l'utilisateur a cliqué sur Submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $goalCtrl = new GoalController();
    $titre = $_POST['title'];
    $desc = $_POST['description'];
    $type = $_POST['type']; // cv, interview...
    $assistant_id = $_POST['assistant_id']; // récupéré via un <select> dans le front
    $user_id = $_SESSION['user_id']; // id du citoyen connecté

    // Crée l'objet Goal
    $newGoal = new Goal($user_id, $assistant_id, $titre, $desc, $type);
    
    // Insertion
    $goalCtrl->createGoal($newGoal);
    
    // Redirection
    header('Location: mes-goals.php');
}
?>
```

### Exemple : L'Administrateur valide depuis `gestion-accompagnements.php` :

```php
<?php
session_start();
// Vérifier ROLE => admin
if($_SESSION['role'] !== 'admin') die('Accès refusé');

require_once '../controller/GoalController.php';
$goalCtrl = new GoalController();

if(isset($_GET['action']) && $_GET['action'] == 'valider') {
     $idGoal = $_GET['id'];
     $comment = "Mairie : Demande légitime, transmise.";
     // Modération => 'valide'
     $goalCtrl->moderateGoalByAdmin($idGoal, 'valide', $comment);
}

// Pour lister
$pendingGoals = $goalCtrl->getPendingGoalsForAdmin();
?>
```

### Exemple : L'Assistant liste ses accompagnements :

```php
<?php
session_start();
if($_SESSION['role'] !== 'assistant') die('Accès refusé');

require_once '../controller/GoalController.php';
$goalCtrl = new GoalController();
$myId = $_SESSION['user_id'];

$mesMissions = $goalCtrl->getGoalsForAssistant($myId);
// => Boucler sur $mesMissions en HTML...
?>
```

## Rappel Sécurité (RBAC) :
N'oubliez jamais de vérifier `$_SESSION['role']` au début de chaque fichier FrontOffice ou BackOffice qui fait appel à vos Controllers pour interdire l'accès à un non-assistant aux guides d'édition, par exemple.
