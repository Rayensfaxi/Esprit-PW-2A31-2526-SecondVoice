<?php
if (!headers_sent()) {
  $query = $_SERVER['QUERY_STRING'] ?? '';
  $target = '/view/frontoffice/assistant-accompagnements.php' . ($query !== '' ? ('?' . $query) : '');
  header('Location: ' . $target);
  exit;
}

session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controller/GoalController.php';
require_once __DIR__ . '/../../controller/GuideController.php';

function redirect_with_notice(string $path, string $type, string $message): void
{
  $separator = (strpos($path, '?') === false) ? '?' : '&';
  $query = http_build_query([
    'type' => $type,
    'msg' => $message,
  ]);
  header('Location: ' . $path . $separator . $query);
  exit;
}

// Check if user is agent/assistant
$_role = strtolower((string) ($_SESSION['role'] ?? ''));
$_userRole = strtolower((string) ($_SESSION['user_role'] ?? ''));
$isAssistant = in_array($_role, ['assistant', 'agent'], true) || in_array($_userRole, ['assistant', 'agent'], true);
if (!$isAssistant) {
    $next = urlencode($_SERVER['REQUEST_URI'] ?? '/view/backoffice/assistant-accompagnements.php');
    $msg = urlencode('Accès réservé aux assistants.');
    header("Location: /test-login.php?next={$next}&type=error&msg={$msg}");
    exit;
}

$assistant_id = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
if ($assistant_id <= 0) {
    $next = urlencode($_SERVER['REQUEST_URI'] ?? '/view/backoffice/assistant-accompagnements.php');
    $msg = urlencode('Session invalide. Veuillez vous reconnecter.');
    header("Location: /test-login.php?next={$next}&type=error&msg={$msg}");
    exit;
}

$goalCtrl  = new GoalController();
$guideCtrl = new GuideController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action']  ?? '';
    $goal_id = (int)($_POST['goal_id'] ?? 0);

    try {
        if ($action === 'evaluate' && $goal_id) {
            $decision = $_POST['decision'] ?? '';        // 'accepte' | 'refuse'
            $comment  = trim($_POST['comment'] ?? '');
            $priority = $_POST['priority'] ?? 'moyenne';
            $status   = $_POST['status']   ?? 'en_cours';
            $ok = $goalCtrl->evaluateGoalByAssistant($goal_id, $assistant_id, $decision, $comment, $priority, $status);
            if (!$ok) {
              redirect_with_notice('assistant-accompagnements.php', 'error', "Action impossible : la demande a déjà été traitée.");
            } else {
                if ($decision === 'accepte') {
                redirect_with_notice('assistant-accompagnements.php', 'success', "Accompagnement accepté. Vous pouvez maintenant créer les étapes (guides).");
                } else {
                redirect_with_notice('assistant-accompagnements.php', 'success', "Accompagnement refusé et supprimé.");
                }
            }
        }

        if ($action === 'finish_goal' && $goal_id) {
            $goalCtrl->markGoalAsFinished($goal_id);
            redirect_with_notice('assistant-accompagnements.php', 'success', "Accompagnement marqué comme terminé.");
        }

        if ($action === 'add_guide' && $goal_id) {
            $title   = trim($_POST['title']   ?? '');
            $content = trim($_POST['content'] ?? '');
            if ($title === '' || $content === '') {
              redirect_with_notice('assistant-accompagnements.php', 'error', "Veuillez remplir le titre et le contenu de l'étape.");
            } else {
                $guide = new Guide($goal_id, $title, $content);
                if ($guideCtrl->createGuideByAssistant($guide, $assistant_id)) {
                redirect_with_notice('assistant-accompagnements.php', 'success', "Étape ajoutée au guide avec succès.");
                } else {
                redirect_with_notice('assistant-accompagnements.php', 'error', "Vous n'êtes pas autorisé à ajouter une étape à cet accompagnement.");
                }
            }
        }

        if ($action === 'delete_guide') {
            $guide_id = (int)($_POST['guide_id'] ?? 0);
            if ($guide_id > 0 && $guideCtrl->deleteGuideByAssistant($guide_id, $assistant_id)) {
            redirect_with_notice('assistant-accompagnements.php', 'success', "Étape supprimée avec succès.");
            } else {
            redirect_with_notice('assistant-accompagnements.php', 'error', "Suppression impossible : action non autorisée.");
            }
        }
    } catch (Exception $e) {
        redirect_with_notice('assistant-accompagnements.php', 'error', "Erreur : " . $e->getMessage());
    }
}

    $noticeMessage = trim((string) ($_GET['msg'] ?? ''));
    $noticeTypeRaw = strtolower((string) ($_GET['type'] ?? 'info'));
    $noticeType = in_array($noticeTypeRaw, ['success', 'error', 'info'], true) ? $noticeTypeRaw : 'info';

// Get the goals assigned to this assistant that are validated by Admin
$goals = $goalCtrl->getGoalsForAssistant($assistant_id);

$pendingCount = 0;
$activeCount = 0;
$doneCount = 0;
foreach ($goals as $goalItem) {
  if (($goalItem['assistant_validation_status'] ?? '') === 'en_attente') {
    $pendingCount++;
  }
  if (($goalItem['status'] ?? '') === 'en_cours') {
    $activeCount++;
  }
  if (($goalItem['status'] ?? '') === 'termine') {
    $doneCount++;
  }
}
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SecondVoice | Mes Missions d'accompagnement</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/style.css" />
    <style>
      .content-section { color: #dbe5ff; }
      .hero {
        background: linear-gradient(120deg, rgba(99,91,255,.24), rgba(76,201,240,.16));
        border: 1px solid rgba(255,255,255,.09);
        border-radius: 18px;
        padding: 20px;
        margin-bottom: 18px;
      }
      .hero h2 { margin: 0; font-size: 1.25rem; color: #fff; }
      .hero p { margin: 8px 0 0; color: #c6d2f2; }

      .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-top: 16px;
      }
      .stat {
        background: rgba(10, 16, 34, .62);
        border: 1px solid rgba(255,255,255,.09);
        border-radius: 12px;
        padding: 12px;
      }
      .stat-label { color: #97a8d3; font-size: .78rem; text-transform: uppercase; letter-spacing: .05em; }
      .stat-value { margin-top: 4px; color: #fff; font-size: 1.5rem; font-weight: 800; }

      .mission-card {
        background: #10182d;
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 16px;
        margin-bottom: 16px;
        overflow: hidden;
      }
      .mission-head {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: flex-start;
        padding: 16px 18px 10px;
        border-bottom: 1px solid rgba(255,255,255,.06);
      }
      .mission-title { margin: 4px 0; color: #fff; font-size: 1.05rem; }
      .mission-meta { color: #9db0dc; font-size: .84rem; }
      .mission-body { padding: 14px 18px 18px; }
      .mission-desc { color: #cfdbff; margin-bottom: 14px; }

      .chip {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 5px 11px;
        font-size: 12px;
        font-weight: 700;
      }
      .chip-pending { background: rgba(255,184,77,.18); color: #ffcf7d; }
      .chip-active  { background: rgba(76,201,240,.18); color: #89def6; }
      .chip-done    { background: rgba(49,208,170,.2); color: #72e3c7; }
      .chip-cancel  { background: rgba(255,107,107,.2); color: #ff9fa7; }

      .panel {
        background: rgba(255,255,255,.03);
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 12px;
        padding: 12px;
        margin-top: 12px;
      }
      .panel h4, .panel h5 { color: #eef3ff; margin-top: 0; }

      .guide-list { margin-top: 8px; }
      .guide-item {
        background: rgba(8, 13, 27, .78);
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 10px;
        padding: 10px;
        margin-bottom: 10px;
        position: relative;
      }
      .guide-item h5 { margin: 0 0 6px; color: #fff; }
      .guide-item p { margin: 0; color: #c8d5f7; }

      .btn {
        padding: 8px 14px;
        border: none;
        border-radius: 9px;
        cursor: pointer;
        color: #fff;
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
      }
      .btn-success { background: linear-gradient(135deg, #18b999, #128c74); }
      .btn-danger  { background: linear-gradient(135deg, #ef6a5b, #b63d31); }
      .btn-primary { background: linear-gradient(135deg, #2163ff, #1741aa); }
      .btn:hover { filter: brightness(1.04); }

      input.form-control, textarea.form-control, select.form-control {
        width: 100%;
        padding: 9px 10px;
        margin-bottom: 10px;
        border: 1px solid rgba(255,255,255,.16);
        border-radius: 8px;
        background: rgba(255,255,255,.03);
        color: #fff;
      }
      input.form-control::placeholder, textarea.form-control::placeholder { color: #9baed8; }

      .toolbar-actions { display: flex; gap: 10px; flex-wrap: wrap; }

      .notice {
        border-radius: 10px;
        padding: 11px 14px;
        margin-bottom: 14px;
        border: 1px solid transparent;
        font-weight: 600;
      }
      .notice-success { background: #e9f8ee; border-color: #b9e6c7; color: #1e6a39; }
      .notice-error { background: #fdecee; border-color: #f5c1c7; color: #8b1f2b; }
      .notice-info { background: #edf4ff; border-color: #cfe0ff; color: #1d4e9a; }

      .confirm-overlay {
        position: fixed;
        inset: 0;
        background: rgba(5, 9, 20, 0.72);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 2000;
        padding: 16px;
      }
      .confirm-overlay.open { display: flex; }
      .confirm-modal {
        width: min(460px, 100%);
        background: #111a30;
        border: 1px solid rgba(255,255,255,.12);
        border-radius: 14px;
        box-shadow: 0 24px 55px rgba(0,0,0,.45);
        padding: 16px;
      }
      .confirm-title {
        margin: 0;
        color: #fff;
        font-size: 1.05rem;
      }
      .confirm-text {
        margin: 8px 0 0;
        color: #c4d2f8;
        line-height: 1.4;
      }
      .confirm-actions {
        margin-top: 14px;
        display: flex;
        justify-content: flex-end;
        gap: 8px;
      }
      .btn-ghost {
        background: rgba(255,255,255,.08);
        color: #e8eeff;
      }
      @media (max-width: 980px) {
        .stats-grid { grid-template-columns: 1fr 1fr; }
      }
      @media (max-width: 640px) {
        .stats-grid { grid-template-columns: 1fr; }
      }
    </style>
  </head>
  <body data-page="chatbot">
    <div class="shell">
      <aside class="sidebar">
        <div class="sidebar-panel">
          <div class="brand-row">
            <a class="brand" href="index.php"><img class="brand-logo" src="assets/media/secondvoice-logo.png" alt="SecondVoice logo" /></a>
          </div>
          <div class="sidebar-scroll">
            <div class="nav-section">
              <div class="nav-title">Espace Assistant</div>
              <a class="nav-link" href="assistant-accompagnements.php" data-nav="chatbot"><span class="nav-icon icon-chat"></span><span>Mes Accompagnements</span></a>
              <a class="nav-link" href="assistant-guides.php" data-nav="guides"><span class="nav-icon icon-document"></span><span>Gestion des Guides</span></a>
            </div>
          </div>
        </div>
      </aside>

      <main class="page">
        <div class="topbar">
          <div><h1 class="page-title">Mes Missions d'accompagnement</h1></div>
          <div class="toolbar-actions">
            <a class="update-button" href="assistant-guides.php">📖 Voir tous mes guides</a>
            <a class="update-button" href="../frontoffice/logout.php">Déconnexion</a>
          </div>
        </div>

        <div class="page-grid">
          <section class="content-section">
            <?php if ($noticeMessage !== ''): ?>
              <div class="notice notice-<?= htmlspecialchars($noticeType) ?>"><?= htmlspecialchars($noticeMessage) ?></div>
            <?php endif; ?>

            <div class="hero">
              <h2>Interface Assistant Moderne</h2>
              <p>Validez les missions, créez des étapes concrètes et suivez l'avancement en un seul écran.</p>
              <div class="stats-grid">
                <div class="stat"><div class="stat-label">Total missions</div><div class="stat-value"><?= count($goals) ?></div></div>
                <div class="stat"><div class="stat-label">En attente</div><div class="stat-value"><?= $pendingCount ?></div></div>
                <div class="stat"><div class="stat-label">En cours</div><div class="stat-value"><?= $activeCount ?></div></div>
                <div class="stat"><div class="stat-label">Terminées</div><div class="stat-value"><?= $doneCount ?></div></div>
              </div>
            </div>

            <?php if (empty($goals)): ?>
              <div class="panel">
                <p>Aucune demande transférée pour le moment. Dès qu'un admin valide une demande, elle apparaîtra ici.</p>
              </div>
            <?php else: ?>
              <?php foreach ($goals as $g):
                $statusClass = 'chip-active';
                if ($g['status'] === 'termine') $statusClass = 'chip-done';
                if ($g['status'] === 'annule')  $statusClass = 'chip-cancel';
                if (($g['assistant_validation_status'] ?? '') === 'en_attente') $statusClass = 'chip-pending';
              ?>
                <div class="mission-card">
                  <div class="mission-head">
                    <div>
                      <div class="mission-meta">Demande #<?= (int) $g['id'] ?> • <?= htmlspecialchars($g['type']) ?></div>
                      <h3 class="mission-title"><?= htmlspecialchars($g['title']) ?></h3>
                      <div class="mission-meta">Client : <?= htmlspecialchars($g['user_name'] ?? 'Inconnu') ?></div>
                    </div>
                    <span class="chip <?= $statusClass ?>\"><?= strtoupper(htmlspecialchars((string) $g['status'])) ?></span>
                  </div>
                  <div class="mission-body">
                    <p class="mission-desc"><?= nl2br(htmlspecialchars($g['description'])) ?></p>

                  <!-- Action d'acceptation -->
                  <?php if ($g['assistant_validation_status'] == 'en_attente'): ?>
                    <form method="POST" class="panel">
                      <input type="hidden" name="action"  value="evaluate">
                      <input type="hidden" name="goal_id" value="<?= (int)$g['id'] ?>">
                      <label for="priority-<?= (int)$g['id'] ?>"><strong>Priorité</strong></label>
                      <select id="priority-<?= (int)$g['id'] ?>" name="priority" class="form-control" required>
                        <option value="basse">Basse</option>
                        <option value="moyenne" selected>Moyenne</option>
                        <option value="haute">Haute</option>
                      </select>
                      <label for="status-<?= (int)$g['id'] ?>"><strong>État</strong></label>
                      <select id="status-<?= (int)$g['id'] ?>" name="status" class="form-control" required>
                        <option value="en_cours" selected>En cours</option>
                        <option value="termine">Terminé</option>
                      </select>
                      <textarea class="form-control" name="comment" placeholder="Un commentaire motivant pour le citoyen..."></textarea>
                      <button type="submit" name="decision" value="accepte" class="btn btn-success">Accepter la mission</button>
                      <button type="submit" name="decision" value="refuse"  class="btn btn-danger"  onclick="return confirm('Refuser cette demande ? Elle sera supprimée.');">Refuser</button>
                    </form>
                  <?php endif; ?>

                  <!-- Gestion des guides (Si accepté) -->
                  <?php if ($g['assistant_validation_status'] == 'accepte'): ?>
                    <div class="guide-list panel">
                      <h4>Étapes / Guides créés :</h4>
                      <?php
                        $guides = $guideCtrl->getGuidesByGoal($g['id']);
                        if (!empty($guides)):
                          foreach ($guides as $guide):
                      ?>
                        <div class="guide-item">
                          <h5><?= htmlspecialchars($guide['title']) ?></h5>
                          <p><?= nl2br(htmlspecialchars($guide['content'])) ?></p>
                          <div style="position: absolute; top: 10px; right: 10px; display:flex; gap: 5px;">
                            <a href="assistant-guides.php?mode=edit&id=<?= (int)$guide['id'] ?>" class="btn btn-primary" style="padding: 4px 8px; font-size: 12px;">Modifier</a>
                            <form method="POST" style="margin: 0;" class="js-confirm-delete" data-confirm-message="Supprimer cette étape ? Cette action est définitive.">
                              <input type="hidden" name="action"   value="delete_guide">
                              <input type="hidden" name="guide_id" value="<?= (int)$guide['id'] ?>">
                              <button type="submit" class="btn btn-danger" style="padding: 4px 8px; font-size: 12px;">Supprimer</button>
                            </form>
                          </div>
                        </div>
                      <?php endforeach; else: ?>
                        <p>Aucune étape créée pour le moment.</p>
                      <?php endif; ?>

                      <?php if ($g['status'] == 'en_cours'): ?>
                        <form method="POST" class="panel" style="margin-top: 15px;">
                          <h5>Ajouter une étape</h5>
                          <input type="hidden" name="action"  value="add_guide">
                          <input type="hidden" name="goal_id" value="<?= (int)$g['id'] ?>">
                          <input type="text" name="title" class="form-control" placeholder="Titre de l'étape" required>
                          <textarea name="content" class="form-control" rows="3" placeholder="Contenu et actions à réaliser..." required></textarea>
                          <button class="btn btn-primary" type="submit">Enregistrer l'étape</button>
                        </form>

                        <form method="POST" style="margin-top: 15px; text-align: right;" onsubmit="return confirm('Marquer cet accompagnement comme terminé ?');">
                          <input type="hidden" name="action"  value="finish_goal">
                          <input type="hidden" name="goal_id" value="<?= (int)$g['id'] ?>">
                          <button class="btn btn-success" type="submit">Clôturer l'accompagnement</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </section>
        </div>
      </main>
    </div>
    <div id="confirmOverlay" class="confirm-overlay" aria-hidden="true">
      <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
        <h3 id="confirmTitle" class="confirm-title">Confirmer la suppression</h3>
        <p id="confirmText" class="confirm-text">Cette action est définitive.</p>
        <div class="confirm-actions">
          <button type="button" id="confirmCancel" class="btn btn-ghost">Annuler</button>
          <button type="button" id="confirmOk" class="btn btn-danger">Supprimer</button>
        </div>
      </div>
    </div>
    <script src="assets/app.js"></script>
    <script>
      (function () {
        const overlay = document.getElementById('confirmOverlay');
        const confirmText = document.getElementById('confirmText');
        const confirmCancel = document.getElementById('confirmCancel');
        const confirmOk = document.getElementById('confirmOk');
        if (!overlay || !confirmText || !confirmCancel || !confirmOk) return;

        let pendingForm = null;

        function openModal(message, form) {
          pendingForm = form;
          confirmText.textContent = message || 'Confirmer cette action ?';
          overlay.classList.add('open');
          overlay.setAttribute('aria-hidden', 'false');
        }

        function closeModal() {
          pendingForm = null;
          overlay.classList.remove('open');
          overlay.setAttribute('aria-hidden', 'true');
        }

        document.querySelectorAll('form.js-confirm-delete').forEach((form) => {
          form.addEventListener('submit', (event) => {
            event.preventDefault();
            openModal(form.dataset.confirmMessage, form);
          });
        });

        confirmCancel.addEventListener('click', closeModal);
        overlay.addEventListener('click', (event) => {
          if (event.target === overlay) closeModal();
        });

        confirmOk.addEventListener('click', () => {
          if (!pendingForm) return;
          const formToSubmit = pendingForm;
          closeModal();
          formToSubmit.submit();
        });

        document.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && overlay.classList.contains('open')) {
            closeModal();
          }
        });
      })();
    </script>
  </body>
</html>
