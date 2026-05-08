<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controller/EventController.php';

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function currentUserIsAdmin(): bool
{
    return in_array(strtolower((string) ($_SESSION['user_role'] ?? 'client')), ['admin', 'agent'], true);
}

function currentUserIsConnected(): bool
{
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

 $controller = new EventController();
 $action = strtolower(trim((string) ($_REQUEST['action'] ?? '')));

if ($action !== '') {
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input') ?: '', true);
    $input = is_array($input) ? $input : [];

    try {
        switch ($action) {
            case 'create':
                error_log('=== BACKEND: CRÉATION ÉVÉNEMENT ===');
                error_log('Données reçues: ' . json_encode($input));
                
                if (!currentUserIsConnected()) {
                    error_log('ERREUR: Utilisateur non connecté');
                    echo json_encode(['success' => false, 'message' => 'Vous devez être connecté pour créer un événement.']);
                    exit;
                }
                
                // Définir le statut selon le type d'utilisateur
                // Admin → "validé" directement, Utilisateur → "en cours" (demande)
                $isAdmin = currentUserIsAdmin();
                $input['status'] = $isAdmin ? 'validé' : 'en cours';
                // Ajouter l'ID du créateur
                $input['created_by'] = (int) ($_SESSION['user_id'] ?? 0);
                error_log('Admin: ' . ($isAdmin ? 'OUI' : 'NON'));
                error_log('Statut forcé: ' . $input['status']);
                error_log('Créateur: ' . $input['created_by']);

                $result = $controller->createEvent($input);
                error_log('Résultat création: ' . json_encode($result));
                
                echo json_encode($result);
                exit;

            case 'update':
                $userId = (int) ($_SESSION['user_id'] ?? 0);
                $id = (int) ($input['id'] ?? 0);
                $isAdmin = currentUserIsAdmin();

                // Vérifier si l'utilisateur est le propriétaire
                if (!$controller->isEventOwner($id, $userId)) {
                    echo json_encode(['success' => false, 'message' => 'Vous ne pouvez modifier que vos propres événements.']);
                    exit;
                }

                // Si c'est un admin → modification directe
                // Si c'est un utilisateur → créer une demande de modification
                if ($isAdmin) {
                    echo json_encode($controller->updateEvent($id, $input));
                } else {
                    echo json_encode($controller->requestEventModification($id, $userId, $input));
                }
                exit;

            case 'delete':
                $userId = (int) ($_SESSION['user_id'] ?? 0);
                $id = (int) ($input['id'] ?? 0);
                $isAdmin = currentUserIsAdmin();

                // Vérifier si l'utilisateur est le propriétaire
                if (!$controller->isEventOwner($id, $userId)) {
                    echo json_encode(['success' => false, 'message' => 'Vous ne pouvez supprimer que vos propres événements.']);
                    exit;
                }

                // Si c'est un admin → suppression directe
                // Si c'est un utilisateur → créer une demande de suppression
                if ($isAdmin) {
                    echo json_encode($controller->deleteEvent($id));
                } else {
                    echo json_encode($controller->requestEventDeletion($id, $userId));
                }
                exit;

            case 'get':
                if (!currentUserIsAdmin()) {
                    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
                    exit;
                }
                $id = (int) ($_GET['id'] ?? 0);
                $event = $controller->getEventById($id);
                if (!$event) {
                    echo json_encode(['success' => false, 'message' => 'Événement introuvable.']);
                    exit;
                }
                $event['resources'] = $controller->getResourcesByEvent($id);
                echo json_encode(['success' => true, 'event' => $event]);
                exit;

            case 'register':
                $userId = (int) ($_SESSION['user_id'] ?? 0);
                if ($userId <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Authentification requise.']);
                    exit;
                }
                $eventId = (int) ($input['event_id'] ?? 0);
                echo json_encode($controller->registerUser($userId, $eventId));
                exit;

            case 'unregister':
                $userId = (int) ($_SESSION['user_id'] ?? 0);
                if ($userId <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Authentification requise.']);
                    exit;
                }
                $eventId = (int) ($input['event_id'] ?? 0);
                echo json_encode($controller->unregisterUser($userId, $eventId));
                exit;

            case 'approve':
                if (!currentUserIsAdmin()) {
                    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
                    exit;
                }
                $id = (int) ($input['id'] ?? 0);
                echo json_encode($controller->updateEventStatus($id, 'validé'));
                exit;

            case 'reject':
                if (!currentUserIsAdmin()) {
                    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
                    exit;
                }
                $id = (int) ($input['id'] ?? 0);
                echo json_encode($controller->updateEventStatus($id, 'refusé'));
                exit;

            case 'approve_deletion':
                if (!currentUserIsAdmin()) {
                    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
                    exit;
                }
                $requestId = (int) ($input['request_id'] ?? 0);
                $adminId = (int) ($_SESSION['user_id'] ?? 0);
                echo json_encode($controller->approveDeletionRequest($requestId, $adminId));
                exit;

            case 'reject_deletion':
                if (!currentUserIsAdmin()) {
                    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
                    exit;
                }
                $requestId = (int) ($input['request_id'] ?? 0);
                $adminId = (int) ($_SESSION['user_id'] ?? 0);
                echo json_encode($controller->rejectDeletionRequest($requestId, $adminId));
                exit;

            case 'approve_modification':
                if (!currentUserIsAdmin()) {
                    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
                    exit;
                }
                $requestId = (int) ($input['request_id'] ?? 0);
                $adminId = (int) ($_SESSION['user_id'] ?? 0);
                echo json_encode($controller->approveModificationRequest($requestId, $adminId));
                exit;

            case 'reject_modification':
                if (!currentUserIsAdmin()) {
                    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
                    exit;
                }
                $requestId = (int) ($input['request_id'] ?? 0);
                $adminId = (int) ($_SESSION['user_id'] ?? 0);
                echo json_encode($controller->rejectModificationRequest($requestId, $adminId));
                exit;

            default:
                echo json_encode(['success' => false, 'message' => 'Action inconnue.']);
                exit;
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur serveur.']);
        exit;
    }
}

 $userId = (int) ($_SESSION['user_id'] ?? 0);
 $isAdmin = currentUserIsAdmin();
 $events = $controller->getValidatedEvents();
 $userRegistrations = $userId > 0 ? $controller->getUserRegistrations($userId) : [];
 $userRegistrationIds = array_map('intval', array_column($userRegistrations, 'event_id'));
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Événements | SecondVoice</title>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/media/favicon-32.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="assets/media/favicon-16.png" />
    <link rel="apple-touch-icon" href="assets/media/apple-touch-icon.png" />
    <link rel="shortcut icon" href="assets/media/favicon.png" />
    
    <script>
      const savedTheme = localStorage.getItem("theme");
      const initialTheme =
        savedTheme || (window.matchMedia("(prefers-color-scheme: light)").matches ? "light" : "dark");
      document.documentElement.dataset.theme = initialTheme;
    </script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet" />
    
    <link rel="stylesheet" href="assets/css/style.css" />
    <link rel="stylesheet" href="assets/css/events-front.css" />
    
    <style>
      /* Specific layout tweaks for tabs */
      .tab-panel { display: none; }
      .tab-panel.active { display: block; }
      .admin-controls { display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; margin-bottom: 2rem; }
      .admin-controls .search { flex: 1; min-width: 250px; }
    </style>
  </head>
  <body>
    <div class="page-shell">
      
      <header class="site-header">
        <div class="container nav-inner">
          <a class="brand" href="index.php"><img class="brand-logo" src="assets/media/secondvoice-logo.png" alt="SecondVoice logo" /></a>
          <button class="menu-toggle" type="button" data-menu-toggle aria-label="Ouvrir le menu">
            <span class="icon-lines"></span>
          </button>
          <div class="nav" data-nav>
            <nav>
              <ul class="nav-links">
                <li><a href="index.php">Accueil</a></li>
                <li><a href="about.php">A propos</a></li>
                <li><a class="is-active" href="services.php">Services</a></li>
                <li><a href="blog.php">Blog</a></li>
                <li><a href="contact.php">Contact</a></li>
              </ul>
            </nav>
            <div class="header-actions">
              <button class="icon-btn theme-toggle" type="button" data-theme-toggle aria-label="Changer le theme">
                <span class="theme-toggle-label" data-theme-label>Clair</span>
              </button>
              <div class="user-shell" data-user-shell>
                <a class="icon-btn user-trigger" href="profile.php" aria-label="Ouvrir le profil utilisateur"><span>Profil</span></a>
                <div class="user-backdrop"></div>
                <div class="user-panel">
                  <div class="user-panel-head">
                    <div class="user-panel-intro">
                      <div class="user-avatar">JT</div>
                      <div>
                        <p class="user-panel-title">Bon retour</p>
                        <p class="user-modal-copy">Connectez-vous pour acceder a vos projets, factures et demandes de support.</p>
                      </div>
                    </div>
                    <button class="icon-btn user-close" type="button" data-user-close aria-label="Fermer la fenetre utilisateur">X</button>
                  </div>
                  <div class="auth-tabs">
                    <a class="auth-tab is-active" href="login.php">Connexion</a>
                    <a class="auth-tab" href="register.php">Inscription</a>
                  </div>
                  <section class="auth-panel is-active" data-auth-panel="login">
                    <h3 class="auth-title">Connexion Client</h3>
                    <p class="auth-helper">Utilisez votre e-mail et mot de passe pour continuer.</p>
                    <form class="auth-form">
                      <input class="field" type="email" placeholder="Adresse e-mail" />
                      <input class="field" type="password" placeholder="Mot de passe" />
                      <div class="auth-options">
                        <label class="check-row"><input type="checkbox" /> Se souvenir de moi</label>
                        <a href="contact.php">Mot de passe oublie ?</a>
                      </div>
                      <button class="btn btn-primary" type="button">Se connecter</button>
                    </form>
                    <div class="user-panel-footer">
                      <a class="btn btn-secondary" href="contact.php">Support client</a>
                    </div>
                  </section>
                  <section class="auth-panel" data-auth-panel="register">
                    <h3 class="auth-title">Creer un compte</h3>
                    <p class="auth-helper">Creez un compte de demonstration pour le suivi, le support et la gestion de vos demandes.</p>
                    <form class="auth-form">
                      <input class="field" type="text" placeholder="Nom complet" />
                      <input class="field" type="email" placeholder="E-mail professionnel" />
                      <input class="field" type="password" placeholder="Creer un mot de passe" />
                      <button class="btn btn-primary" type="button">Creer un compte</button>
                    </form>
                    <ul class="auth-links">
                      <li><a href="services.php">Voir les offres de service</a></li>
                      <li><a href="contact.php">Demander un acces entreprise</a></li>
                    </ul>
                  </section>
                </div>
              </div>
            </div>
          </div>
        </div>
      </header>

      <main>
        <section class="page-hero">
          <div class="container">
            <div class="page-hero-card fade-up">
              <div class="breadcrumbs"><span>Accueil</span><span>/</span><span>Services</span><span>/</span><span>Événements</span></div>
              <h1>Événements SecondVoice</h1>
              <p>Consultez les événements validés et gérez vos inscriptions.</p>
            </div>
          </div>
        </section>

        <section class="section">
          <div class="container">
            
            <!-- Controls Bar -->
            <div class="admin-controls fade-up">
              <div class="search">
                <input id="events-search" class="field" type="search" placeholder="Rechercher un événement, une date ou un lieu..." aria-label="Rechercher les événements" />
              </div>
              
              <button class="btn tab-toggle active" type="button" data-target="#tab-consult">Consulter</button>
              <button class="btn tab-toggle" type="button" data-target="#tab-my">Mes inscriptions</button>
              
              <?php if ($userId > 0): ?>
                <button id="btn-show-add-form" class="btn tab-toggle" type="button" data-target="#tab-add-form" <?= $userId <= 0 ? 'disabled' : '' ?>>Ajouter un événement</button>
              <?php endif; ?>
            </div>

            <div id="events-feedback" class="alert d-none" role="alert"></div>

            <!-- Tab: Consult Events -->
            <section id="tab-consult" class="tab-panel active">
              <div class="grid-3" id="events-grid">
                <?php if ($events === []): ?>
                  <p class="muted">Aucun événement validé n’est disponible pour le moment.</p>
                <?php endif; ?>

                <?php foreach ($events as $event): ?>
                  <?php
                    $eventId = (int) $event['id'];
                    $resources = $controller->getResourcesByEvent($eventId);
                    $isRegistered = in_array($eventId, $userRegistrationIds, true);
                    $materials = array_values(array_filter(array_map(static fn(array $row): ?string => ($row['type'] ?? '') === 'material' ? (string) $row['resource_name'] : null, $resources)));
                    $rules = array_values(array_filter(array_map(static fn(array $row): ?string => ($row['type'] ?? '') === 'rule' ? (string) $row['resource_name'] : null, $resources)));
                  ?>
                  <article class="event-card fade-up"
                           data-id="<?= $eventId ?>"
                           data-name="<?= h((string) ($event['name'] ?? '')) ?>"
                           data-desc="<?= h((string) ($event['description'] ?? '')) ?>"
                           data-start="<?= h((string) ($event['start_date'] ?? '')) ?>"
                           data-end="<?= h((string) ($event['end_date'] ?? '')) ?>"
                           data-deadline="<?= h((string) ($event['deadline'] ?? '')) ?>"
                           data-location="<?= h((string) ($event['location'] ?? '')) ?>"
                           data-max="<?= (int) ($event['max'] ?? 0) ?>"
                           data-current="<?= (int) ($event['current'] ?? 0) ?>"
                           data-status="<?= h((string) ($event['status'] ?? 'en cours')) ?>"
                           data-created-by="<?= (int) ($event['created_by'] ?? 0) ?>"
                           data-materials='<?= h(json_encode($materials, JSON_UNESCAPED_UNICODE)) ?>'
                           data-rules='<?= h(json_encode($rules, JSON_UNESCAPED_UNICODE)) ?>'>
                    <!-- Ligne titre + statut (comme back office) -->
                    <div class="row between">
                      <h3><?= h((string) ($event['name'] ?? '')) ?></h3>
                    </div>

                    <!-- Description -->
                    <p class="desc"><?= h((string) ($event['description'] ?? '')) ?></p>

                    <!-- Métadonnées principales -->
                    <div class="meta"><?= h((string) ($event['start_date'] ?? '')) ?> — <?= h((string) ($event['end_date'] ?? '')) ?> • <?= h((string) ($event['location'] ?? '')) ?></div>
                    <div class="meta small">Date limite : <?= h((string) ($event['deadline'] ?? '')) ?></div>
                    <div class="meta small">Places : <?= (int) ($event['current'] ?? 0) ?> / <?= (int) ($event['max'] ?? 0) ?></div>

                    <!-- Matériels et règles -->
                    <?php if ($materials !== []): ?>
                      <div class="small">Matériels : <?= h($materials !== [] ? implode(', ', $materials) : 'Aucun') ?></div>
                    <?php endif; ?>
                    <?php if ($rules !== []): ?>
                      <div class="small">Règles : <?= h($rules !== [] ? implode(', ', $rules) : 'Aucune') ?></div>
                    <?php endif; ?>

                    <!-- Zone d'actions en bas (comme back office) -->
                    <div class="actions">
                      <?php
                      $eventCreatedBy = (int)($event['created_by'] ?? 0);
                      $isOwner = ($userId > 0 && $eventCreatedBy === $userId);
                      ?>
                      <?php if ($userId > 0): ?>
                        <?php if ($isRegistered): ?>
                          <button class="btn outline unregister" type="button" data-id="<?= $eventId ?>">Se désinscrire</button>
                        <?php else: ?>
                          <button class="btn register" type="button" data-id="<?= $eventId ?>">S'inscrire</button>
                        <?php endif; ?>
                        <?php if ($isOwner): ?>
                          <button class="btn outline modify" type="button" data-id="<?= $eventId ?>">Modifier</button>
                          <button class="btn outline delete" type="button" data-id="<?= $eventId ?>">Supprimer</button>
                        <?php endif; ?>
                      <?php else: ?>
                        <a class="btn" href="login.php">Connexion pour s'inscrire</a>
                      <?php endif; ?>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            </section>

            <!-- Tab: My Registrations -->
            <section id="tab-my" class="tab-panel" style="display: none;">
              <div id="my-registrations">
                <h2>Mes inscriptions</h2>
                <?php if ($userId <= 0): ?>
                  <p class="muted fade-up">Connectez-vous pour voir vos inscriptions.</p>
                <?php elseif ($userRegistrations === []): ?>
                  <p class="muted fade-up">Vous n’êtes inscrit à aucun événement.</p>
                <?php else: ?>
                  <?php foreach ($userRegistrations as $registration): ?>
                    <div class="reg-item fade-up">
                      <div class="row between">
                        <div>
                          <strong><?= h((string) ($registration['name'] ?? '')) ?></strong>
                          <div class="small"><?= h((string) ($registration['start_date'] ?? '')) ?> • <?= h((string) ($registration['location'] ?? '')) ?></div>
                        </div>
                        <div><button class="btn outline unregister" type="button" data-id="<?= (int) ($registration['event_id'] ?? 0) ?>">Se désinscrire</button></div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </section>

            <!-- Tab: Add Event Form (Hidden by default) -->
            <?php if (currentUserIsConnected()): ?>
            <section id="tab-add-form" class="tab-panel" style="display: none;">
              <div class="service-layout fade-up">
                <div class="post-content">
                  <h2>Créer un nouvel événement</h2>
                  <p>Remplissez les informations ci-dessous. Le statut par défaut sera "en cours".</p>
                  <form id="event-form" novalidate>
                    <input type="hidden" name="id" id="evt-id" />
                    <!-- Status is forced in PHP, hidden input here for safety -->
                    <input type="hidden" name="status" id="evt-status" value="en cours" />
                    
                    <div class="mb-3">
                      <label for="evt-name" class="form-label">Titre <span style="color: red; font-weight: bold;">*</span></label>
                      <input type="text" class="field" id="evt-name" name="name" required />
                      <div class="error-message" id="evt-name-error"></div>
                    </div>
                    <div class="mb-3">
                      <label for="evt-description" class="form-label">Description</label>
                      <textarea class="field" id="evt-description" name="description" rows="3"></textarea>
                    </div>
                    <div class="grid-3 mb-3">
                      <div>
                        <label for="evt-start" class="form-label">Date début <span style="color: red; font-weight: bold;">*</span></label>
                        <input type="datetime-local" class="field" id="evt-start" name="start_date" />
                        <div class="error-message" id="evt-start-error"></div>
                      </div>
                      <div>
                        <label for="evt-end" class="form-label">Date fin <span style="color: red; font-weight: bold;">*</span></label>
                        <input type="datetime-local" class="field" id="evt-end" name="end_date" />
                        <div class="error-message" id="evt-end-error"></div>
                      </div>
                      <div>
                        <label for="evt-deadline" class="form-label">Date limite <span style="color: red; font-weight: bold;">*</span></label>
                        <input type="datetime-local" class="field" id="evt-deadline" name="deadline" />
                        <div class="error-message" id="evt-deadline-error"></div>
                      </div>
                    </div>
                    <div class="grid-2 mb-3">
                      <div>
                        <label for="evt-location" class="form-label">Lieu <span style="color: red; font-weight: bold;">*</span></label>
                        <input type="text" class="field" id="evt-location" name="location" />
                        <div class="error-message" id="evt-location-error"></div>
                      </div>
                      <div>
                        <label for="evt-max" class="form-label">Capacité max</label>
                        <input type="number" min="1" class="field" id="evt-max" name="max" value="1" />
                        <div class="error-message" id="evt-max-error"></div>
                      </div>
                    </div>
                                        <div class="actions">
                      <button type="button" class="btn btn-primary" id="btn-save-event">Enregistrer l'événement</button>
                      <button type="button" class="btn outline" id="btn-cancel-add">Annuler</button>
                    </div>
                  </form>
                </div>
              </div>
            </section>
            <?php endif; ?>

          </div>
        </section>
      </main>

      <footer class="footer">
        <div class="container">
          <div class="footer-bottom">
            <span>&copy; 2026 SecondVoice. Tous droits reserves.</span>
            <div class="footer-links">
              <a href="index.php">Confidentialite</a>
              <a href="index.php">Conditions</a>
            </div>
          </div>
        </div>
      </footer>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
      // Simple Vanilla JS to handle tabs since Bootstrap is removed
      document.addEventListener('DOMContentLoaded', () => {
        
        // 1. Handle Tab Switching (Consulter / Mes inscriptions)
        const tabToggles = document.querySelectorAll('.tab-toggle');
        const tabPanels = document.querySelectorAll('.tab-panel');

        tabToggles.forEach(btn => {
          btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            
            // Remove active from buttons
            tabToggles.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            // Hide all panels, show target
            tabPanels.forEach(panel => {
              panel.classList.remove('active');
            });
            
            const targetPanel = document.querySelector(targetId);
            if (targetPanel) {
              targetPanel.classList.add('active');
            }
          });
        });

        // 2. Handle "Ajouter un événement" button
        const btnShowAdd = document.getElementById('btn-show-add-form');
        const tabAddForm = document.getElementById('tab-add-form');
        const btnCancelAdd = document.getElementById('btn-cancel-add');

        if (btnShowAdd && tabAddForm) {
          btnShowAdd.addEventListener('click', () => {
            // Remove active from tab buttons so none look selected
            tabToggles.forEach(b => b.classList.remove('active'));
            
            // Hide all panels
            tabPanels.forEach(panel => panel.classList.remove('active'));
            
            // Show form panel
            tabAddForm.classList.add('active');
          });
        }

        if (btnCancelAdd) {
          btnCancelAdd.addEventListener('click', () => {
            // Reset form
            document.getElementById('event-form').reset();
            document.getElementById('evt-id').value = '';
            document.getElementById('evt-status').value = 'en cours';

            // Hide form, go back to Consulter
            tabAddForm.classList.remove('active');
            document.getElementById('tab-consult').classList.add('active');
            
            // Reactivate Consulter button
            tabToggles.forEach(b => {
              if(b.getAttribute('data-target') === '#tab-consult') b.classList.add('active');
            });
          });
        }

        // 3. Re-initialize your external events-front.js logic safely
        if (typeof initEventsFront !== 'undefined') {
          initEventsFront();
        }

        // Simple tab navigation for all tab buttons
        function showTab(tabId, clickedBtn) {
          console.log('[TAB] Showing tab:', tabId);

          // Hide all tab panels
          document.querySelectorAll('.tab-panel').forEach(function(panel) {
            panel.style.display = 'none';
            panel.classList.remove('active');
          });

          // Show target panel
          const targetPanel = document.getElementById(tabId);
          if (targetPanel) {
            targetPanel.style.display = 'block';
            targetPanel.classList.add('active');
            console.log('[TAB] Panel shown:', tabId);
          } else {
            console.error('[TAB] Panel not found:', tabId);
          }

          // Update active state on buttons
          document.querySelectorAll('.tab-toggle').forEach(function(btn) {
            btn.classList.remove('active');
          });
          if (clickedBtn) {
            clickedBtn.classList.add('active');
          }
        }

        // Consulter tab
        const btnConsult = document.querySelector('.tab-toggle[data-target="#tab-consult"]');
        if (btnConsult) {
          btnConsult.addEventListener('click', function(e) {
            e.preventDefault();
            showTab('tab-consult', this);
          });
        }

        // Mes inscriptions tab
        const btnMySubs = document.querySelector('.tab-toggle[data-target="#tab-my"]');
        if (btnMySubs) {
          btnMySubs.addEventListener('click', function(e) {
            e.preventDefault();
            showTab('tab-my', this);
          });
        }

        // Ajouter un événement tab
        const btnAddEvent = document.getElementById('btn-show-add-form');
        if (btnAddEvent) {
          btnAddEvent.addEventListener('click', function(e) {
            e.preventDefault();
            showTab('tab-add-form', this);
          });
        }

        console.log('[TAB] Tab navigation initialized');
      });
    </script>
    <script src="assets/js/events-front.js"></script>
  </body>
</html>