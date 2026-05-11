<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controller/GoalController.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$goalCtrl = new GoalController();
$db = Config::getConnexion();

// Fetch assistants for the select dropdown
$assistants = [];
try {
  foreach (['utilisateurs', 'utilisateur'] as $userTable) {
    try {
      $stmt = $db->query("SELECT id, nom FROM {$userTable} WHERE LOWER(role) IN ('assistant', 'agent') ORDER BY nom ASC");
      $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
      if (!empty($rows)) {
        $assistants = $rows;
        break;
      }
    } catch (Throwable $inner) {
      // try next table name
    }
  }
} catch (Throwable $e) {
    // ignore
}

$success = null;
$error = null;
$goal_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$goalData = $goalCtrl->getGoalById($goal_id);

if (!$goalData || $goalData['user_id'] != $user_id) {
    die("Demande introuvable ou vous n'avez pas l'autorisation de la modifier.");
}

if ($goalData['admin_validation_status'] !== 'en_attente') {
    die("Cette demande ne peut plus être modifiée car elle est déjà traitée par un administrateur.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_goal') {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $type = $_POST['type'];
        $assistant_id = (int)$_POST['assistant_id'];

        if (empty($title) || empty($description) || empty($assistant_id)) {
            $error = "Veuillez remplir tous les champs obligatoires.";
        } else {
            $updatedGoal = new Goal($user_id, $assistant_id, $title, $description, $type);
            $goalCtrl->updateGoalByUser($updatedGoal, $goal_id);
            $success = "Votre demande d'accompagnement a été modifiée avec succès !";
            // Refresh data
            $goalData = $goalCtrl->getGoalById($goal_id);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Modifier Accompagnement | SecondVoice</title>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/media/favicon-32.png" />
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/style.css" />
    <style>
      .form-container { max-width: 600px; margin: 40px auto; background: var(--surface-1); padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
      .form-group { margin-bottom: 20px; }
      .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-1); }
      .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 12px; border: 1px solid var(--surface-3); border-radius: 8px; background: var(--surface-2); color: var(--text-1); }
      .btn-submit { background: var(--brand); color: white; border: none; padding: 14px 24px; border-radius: 8px; cursor: pointer; font-weight: 600; width: 100%; transition: opacity 0.2s; }
      .btn-submit:hover { opacity: 0.9; }
      .alert-success { padding: 15px; background: #d4edda; color: #155724; border-radius: 8px; margin-bottom: 20px; }
      .alert-error { padding: 15px; background: #f8d7da; color: #721c24; border-radius: 8px; margin-bottom: 20px; }
    </style>
  </head>
  <body>
    <div class="page-shell">
      <header class="site-header">
        <div class="container nav-inner">
          <a class="brand" href="index.html"><img class="brand-logo" src="assets/media/secondvoice-logo.png" alt="SecondVoice" /></a>
          <div class="nav">
            <nav>
              <ul class="nav-links">
                <li><a href="index.html">Accueil</a></li>
                <li><a href="service-accompagnement.php">Nouvel Accompagnement</a></li>
                <li><a class="is-active" href="profile.php">Mon Profil</a></li>
              </ul>
            </nav>
            <div class="header-actions">
              <a class="btn btn-primary" href="mes-accompagnements.php" style="padding: 10px 20px;">Retour</a>
            </div>
          </div>
        </div>
      </header>

      <main>
        <section class="page-hero">
          <div class="container">
            <div class="page-hero-card fade-up">
              <h1>Modifier votre demande</h1>
              <p>Mettez à jour vos besoins avant que l'administration ne valide la demande.</p>
            </div>
          </div>
        </section>

        <section class="section">
          <div class="container">
            <div class="form-container fade-up">
              <?php if($success): ?>
                <div class="alert-success"><?= htmlspecialchars($success) ?></div>
              <?php endif; ?>
              <?php if($error): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
              <?php endif; ?>

              <form method="POST" action="">
                <input type="hidden" name="action" value="update_goal">
                
                <div class="form-group">
                  <label for="title">Titre de la demande</label>
                  <input type="text" id="title" name="title" value="<?= htmlspecialchars($goalData['title']) ?>" required>
                </div>

                <div class="form-group">
                  <label for="type">Type d'accompagnement</label>
                  <select id="type" name="type" required>
                    <option value="cv" <?= $goalData['type']=='cv'?'selected':'' ?>>Création de CV</option>
                    <option value="cover_letter" <?= $goalData['type']=='cover_letter'?'selected':'' ?>>Lettre de motivation</option>
                    <option value="linkedin" <?= $goalData['type']=='linkedin'?'selected':'' ?>>Profil LinkedIn</option>
                    <option value="interview" <?= $goalData['type']=='interview'?'selected':'' ?>>Préparation Entretien</option>
                    <option value="other" <?= $goalData['type']=='other'?'selected':'' ?>>Autre</option>
                  </select>
                </div>

                <div class="form-group">
                  <label for="assistant_id">Choisissez votre Assistant</label>
                  <select id="assistant_id" name="assistant_id" required>
                    <option value="">-- Sélectionner un assistant --</option>
                    <?php foreach($assistants as $ast): ?>
                      <option value="<?= $ast['id'] ?>" <?= $goalData['selected_assistant_id']==$ast['id']?'selected':'' ?>><?= htmlspecialchars($ast['nom']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="form-group">
                  <label for="description">Description détaillée</label>
                  <textarea id="description" name="description" rows="5" required><?= htmlspecialchars($goalData['description']) ?></textarea>
                </div>

                <button type="submit" class="btn-submit">Mettre à jour la demande</button>
              </form>
            </div>
          </div>
        </section>
      </main>
    </div>
    <script src="assets/js/main.js"></script>
  </body>
</html>