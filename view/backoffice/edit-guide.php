<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controller/GoalController.php';
require_once __DIR__ . '/../../controller/GuideController.php';

// Check if user is agent/assistant
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'agent') {
    die("Accès non autorisé. Vous devez être connecté en tant qu'agent/assistant.");
}

$assistant_id = $_SESSION['user_id'];
$guideCtrl = new GuideController();
$goalCtrl = new GoalController();

$success = null;
$error = null;
$guide_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$guideData = $guideCtrl->getGuideById($guide_id);
if (!$guideData) {
    die("L'étape est introuvable.");
}

$goalData = $goalCtrl->getGoalById($guideData['goal_id']);
if (!$goalData || $goalData['selected_assistant_id'] != $assistant_id) {
    die("Vous n'êtes pas autorisé à modifier cette étape.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_guide') {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);

        if (empty($title) || empty($content)) {
            $error = "Tous les champs sont requis.";
        } else {
            $updatedGuide = new Guide($guideData['goal_id'], $title, $content, $guide_id);
            $guideCtrl->updateGuide($updatedGuide, $guide_id);
            header("Location: assistant-accompagnements.php?status=guide_updated");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Modifier le Guide | SecondVoice</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/style.css" />
    <style>
      .form-container { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); max-width: 600px; margin: 40px auto; }
      .form-control { width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px; }
      .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; color: #fff; font-weight: 500; text-decoration: none; }
      .btn-primary { background: #2196f3; }
      .btn-secondary { background: #6c757d; }
    </style>
  </head>
  <body data-page="chatbot">
    <div class="shell">
      <main class="page" style="margin-left:0; width:100%;">
        <div class="topbar">
          <div><h1 class="page-title">Modifier l'étape : <?= htmlspecialchars($goalData['title']) ?></h1></div>
        </div>

        <div class="page-grid">
          <section class="content-section">
            <div class="form-container">
              <?php if($error): ?>
                <div style="padding:15px; background:#f8d7da; color:#721c24; border-radius:4px; margin-bottom:15px;"><?= htmlspecialchars($error) ?></div>
              <?php endif; ?>

              <form method="POST">
                <input type="hidden" name="action" value="update_guide">
                <label style="font-weight:bold; margin-bottom:5px; display:block;">Titre de l'étape</label>
                <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($guideData['title']) ?>" required>
                
                <label style="font-weight:bold; margin-bottom:5px; display:block; margin-top: 15px;">Contenu / Actions</label>
                <textarea name="content" class="form-control" rows="8" required><?= htmlspecialchars($guideData['content']) ?></textarea>
                
                <div style="margin-top: 20px; display:flex; gap:10px;">
                  <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                  <a href="assistant-accompagnements.php" class="btn btn-secondary">Annuler</a>
                </div>
              </form>
            </div>
          </section>
        </div>
      </main>
    </div>
    <script src="assets/app.js"></script>
  </body>
</html>