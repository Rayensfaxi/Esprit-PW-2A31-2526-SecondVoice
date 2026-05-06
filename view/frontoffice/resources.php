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

// Handle AJAX actions
if ($action !== '') {
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input') ?: '', true);
    $input = is_array($input) ? $input : [];

    switch ($action) {
        case 'save_resources':
            $userId = (int) ($_SESSION['user_id'] ?? 0);
            if ($userId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Authentification requise.']);
                exit;
            }
            $eventId = (int) ($input['event_id'] ?? 0);
            $resourcesTitle = trim((string) ($input['resources_title'] ?? ''));
            $resourcesDescription = trim((string) ($input['resources_description'] ?? ''));
            $resources = $input['resources'] ?? [];
            echo json_encode($controller->saveResources($eventId, $userId, $resources, $resourcesTitle, $resourcesDescription));
            exit;

        case 'delete_resources':
            $userId = (int) ($_SESSION['user_id'] ?? 0);
            if ($userId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Authentification requise.']);
                exit;
            }
            $eventId = (int) ($input['event_id'] ?? $_REQUEST['event_id'] ?? 0);
            echo json_encode($controller->deleteResources($eventId, $userId));
            exit;

        case 'list_importable_events':
            $userId = (int) ($_SESSION['user_id'] ?? 0);
            if ($userId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Authentification requise.']);
                exit;
            }
            $currentEventId = (int) ($_GET['event_id'] ?? $input['event_id'] ?? 0);
            echo json_encode([
                'success' => true,
                'events' => $controller->getImportableResourceEventsForUser($userId, $currentEventId),
            ]);
            exit;

        case 'import_resources':
            $userId = (int) ($_SESSION['user_id'] ?? 0);
            if ($userId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Authentification requise.']);
                exit;
            }
            $sourceEventId = (int) ($input['source_event_id'] ?? $_REQUEST['source_event_id'] ?? 0);
            echo json_encode($controller->getImportableResourcesFromEvent($sourceEventId, $userId));
            exit;

        case 'get_resources':
            $eventId = (int) ($_GET['event_id'] ?? 0);
            $resources = $controller->getResourcesByEvent($eventId);
            $hasPendingRequest = $controller->hasPendingResourceModificationRequest($eventId);
            echo json_encode(['success' => true, 'resources' => $resources, 'has_pending_request' => $hasPendingRequest]);
            exit;

        case 'check_pending_request':
            $eventId = (int) ($_GET['event_id'] ?? 0);
            $hasPendingRequest = $controller->hasPendingResourceModificationRequest($eventId);
            echo json_encode(['success' => true, 'has_pending_request' => $hasPendingRequest]);
            exit;
    }

    echo json_encode(['success' => false, 'message' => 'Action non reconnue.']);
    exit;
}

// Page display
$eventId = (int) ($_GET['event_id'] ?? 0);
$event = $controller->getEventById($eventId);
$userId = (int) ($_SESSION['user_id'] ?? 0);

if (!$event) {
    echo '<p>Événement introuvable.</p>';
    exit;
}

$resources = $controller->getResourcesByEvent($eventId);
$materials = array_filter($resources, fn($r) => ($r['type'] ?? '') === 'materiel');
$rules = array_filter($resources, fn($r) => ($r['type'] ?? '') === 'regle');
$resourceMeta = $resources[0] ?? [];
$resourcesTitle = (string) ($resourceMeta['resources_title'] ?? '');
$resourcesDescription = (string) ($resourceMeta['resources_description'] ?? '');
$isOwner = ($userId > 0 && (int) ($event['created_by'] ?? 0) === $userId);
$isAdmin = currentUserIsAdmin();
$canManageResources = $isOwner || $isAdmin;
$hasPendingRequest = $canManageResources ? $controller->hasPendingResourceModificationRequest($eventId) : false;
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Gérer les ressources – <?= h($event['name'] ?? 'Événement') ?></title>

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
  <link rel="stylesheet" href="assets/css/resources-front.css" />
</head>
<body>
  <div class="page-shell">

    <header class="site-header">
      <div class="container nav-inner">
        <a class="brand" href="index.html"><img class="brand-logo" src="assets/media/secondvoice-logo.png" alt="SecondVoice logo" /></a>
        <button class="menu-toggle" type="button" data-menu-toggle aria-label="Ouvrir le menu">
          <span class="icon-lines"></span>
        </button>
        <div class="nav" data-nav>
          <nav>
            <ul class="nav-links">
              <li><a href="index.html">Accueil</a></li>
              <li><a href="about.html">A propos</a></li>
              <li><a class="is-active" href="services.html">Services</a></li>
              <li><a href="blog.html">Blog</a></li>
              <li><a href="contact.html">Contact</a></li>
            </ul>
          </nav>
          <div class="header-actions">
            <button class="icon-btn theme-toggle" type="button" data-theme-toggle aria-label="Changer le theme">
              <span class="theme-toggle-label" data-theme-label>Clair</span>
            </button>
            <div class="user-shell" data-user-shell>
              <a class="icon-btn user-trigger" href="profile.php" aria-label="Ouvrir le profil utilisateur"><span>Profil</span></a>
            </div>
          </div>
        </div>
      </div>
    </header>

    <main class="events-page">
      <section class="container">
        <div class="service-layout fade-up">
          <div class="post-content">

            <!-- Breadcrumb -->
            <div class="breadcrumb" style="margin-bottom: 16px;">
              <a href="events.php" style="color: var(--primary); text-decoration: none;">← Retour aux événements</a>
            </div>

            <h2>Ressources de l'événement</h2>
            <h3 style="color: var(--primary); margin-bottom: 8px;"><?= h($event['name'] ?? '') ?></h3>
            <p class="meta small" style="margin-bottom: 24px;"><?= h($event['start_date'] ?? '') ?> — <?= h($event['location'] ?? '') ?></p>

            <?php if (!$canManageResources): ?>
              <p class="muted">Vous n'êtes pas autorisé à gérer les ressources de cet événement.</p>
            <?php else: ?>

            <?php if ($hasPendingRequest): ?>
              <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 16px; margin-bottom: 20px; color: #856404;">
                <strong>Demande en attente</strong><br>
                Une demande concernant les ressources est actuellement en cours d'examen par un administrateur.
                Les changements ne seront visibles qu'après approbation.
              </div>
            <?php endif; ?>

            <form id="resources-form" novalidate>
              <input type="hidden" id="event-id" name="event_id" value="<?= $eventId ?>" />

              <div class="resource-section">
                <div class="section-header">
                  <h4>Informations générales</h4>
                </div>
                <div class="resource-fields">
                  <div class="mb-3">
                    <label class="form-label">Titre <span class="required" id="general-resource-title-required" style="display: none;">*</span></label>
                    <input type="text" class="field" id="general-resource-title" value="<?= h($resourcesTitle) ?>" />
                    <div class="error-message" id="general-resource-title-error"></div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="field" id="general-resource-description" rows="3"><?= h($resourcesDescription) ?></textarea>
                  </div>
                </div>
              </div>

              <!-- Section Matériels -->
              <div class="resource-section">
                <div class="section-header">
                  <h4>Matériels</h4>
                  <button type="button" class="btn add-resource" data-type="materiel">+</button>
                </div>
                <div id="materiels-list" class="resources-list">
                  <?php foreach ($materials as $mat): ?>
                    <div class="resource-block" data-id="<?= (int) ($mat['id'] ?? 0) ?>">
                      <div class="resource-block-header">
                        <button type="button" class="btn outline remove-resource">−</button>
                      </div>
                      <div class="resource-fields">
                        <div class="mb-3">
                          <label class="form-label">Nom <span class="required">*</span></label>
                          <input type="text" class="field resource-name" value="<?= h($mat['name'] ?? '') ?>" />
                          <div class="error-message"></div>
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Quantité <span class="required">*</span></label>
                          <input type="text" class="field resource-quantity" value="<?= h((string) ($mat['quantity'] ?? '')) ?>" inputmode="numeric" required />
                          <div class="error-message"></div>
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Description</label>
                          <textarea class="field resource-description" rows="2"><?= h($mat['description'] ?? '') ?></textarea>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                  <?php if ($materials === []): ?>
                    <!-- Placeholder for add button -->
                  <?php endif; ?>
                </div>
              </div>

              <!-- Section Règles -->
              <div class="resource-section">
                <div class="section-header">
                  <h4>Règles</h4>
                  <button type="button" class="btn add-resource" data-type="regle">+</button>
                </div>
                <div id="regles-list" class="resources-list">
                  <?php foreach ($rules as $rule): ?>
                    <div class="resource-block" data-id="<?= (int) ($rule['id'] ?? 0) ?>">
                      <div class="resource-block-header">
                        <button type="button" class="btn outline remove-resource">−</button>
                      </div>
                      <div class="resource-fields">
                        <div class="mb-3">
                          <label class="form-label">Nom <span class="required">*</span></label>
                          <input type="text" class="field resource-name" value="<?= h($rule['name'] ?? '') ?>" />
                          <div class="error-message"></div>
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Description</label>
                          <textarea class="field resource-description" rows="2"><?= h($rule['description'] ?? '') ?></textarea>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                  <?php if ($rules === []): ?>
                    <!-- Placeholder for add button -->
                  <?php endif; ?>
                </div>
              </div>

              <!-- Actions -->
              <div class="actions" style="margin-top: 24px;">
                <button type="submit" class="btn" id="btn-save-resources" name="save_resources">Enregistrer</button>
                <button type="button" class="btn outline" id="import-resources-btn" data-event-id="<?= $eventId ?>">Importer ressources</button>
                <button type="button" class="btn outline" id="delete-resources-btn" data-event-id="<?= $eventId ?>">Supprimer ressources</button>
                <a href="events.php" class="btn outline">Annuler</a>
              </div>
            </form>

            <?php endif; ?>
          </div>
        </div>
      </section>
    </main>

    <footer class="footer">
      <div class="container">
        <div class="footer-bottom">
          <span>&copy; 2026 SecondVoice. Tous droits reserves.</span>
          <div class="footer-links">
            <a href="index.html">Confidentialite</a>
            <a href="index.html">Conditions</a>
          </div>
        </div>
      </div>
    </footer>

  </div>

  <script src="assets/js/main.js"></script>
  <script src="assets/js/resources-front.js?v=20260505-import-resources"></script>
</body>
</html>
