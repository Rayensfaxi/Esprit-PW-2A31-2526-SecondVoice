<?php
declare(strict_types=1);
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../frontoffice/login.php?status=auth_required');
    exit;
}

if (!in_array(strtolower((string) ($_SESSION['user_role'] ?? 'client')), ['admin', 'agent'], true)) {
    header('Location: ../frontoffice/profile.php?status=forbidden');
    exit;
}

require_once __DIR__ . '/../../controller/EventController.php';

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$controller = new EventController();
error_log('ADMIN: Récupération des événements validés...');
$events = $controller->getValidatedEvents();
error_log('ADMIN: Nombre d\'événements validés: ' . count($events));
error_log('ADMIN: Événements validés: ' . json_encode($events));

error_log('ADMIN: Récupération des demandes (statut en cours)...');
$requests = $controller->getPendingEvents();
error_log('ADMIN: Nombre de demandes: ' . count($requests));
error_log('ADMIN: Demandes: ' . json_encode($requests));

error_log('ADMIN: Récupération des demandes de suppression...');
$deletionRequests = $controller->getPendingDeletionRequests();
error_log('ADMIN: Nombre de demandes de suppression: ' . count($deletionRequests));
error_log('ADMIN: Demandes de suppression: ' . json_encode($deletionRequests));

error_log('ADMIN: Récupération des demandes de modification...');
$modificationRequests = $controller->getPendingModificationRequests();
error_log('ADMIN: Nombre de demandes de modification: ' . count($modificationRequests));
error_log('ADMIN: Demandes de modification: ' . json_encode($modificationRequests));

// Récupérer l'ID de l'admin connecté
$adminId = (int) ($_SESSION['user_id'] ?? 0);
error_log('ADMIN: Admin connecté ID: ' . $adminId);
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SecondVoice | Gestion des événements</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/style.css" />
    <link rel="stylesheet" href="assets/events-admin.css" />
  </head>
  <body data-page="events-admin">
    <div class="overlay" data-overlay></div>
    <div class="shell">
      <aside class="sidebar">
        <div class="sidebar-panel">
          <div class="brand-row">
            <a class="brand" href="index.php"><img class="brand-logo" src="assets/media/secondvoice-logo.png" alt="SecondVoice logo" /></a>
          </div>

          <div class="sidebar-scroll">
            <div class="nav-section">
              <div class="nav-title">Gestion</div>
              <a class="nav-link" href="index.php" data-nav="home"><span class="nav-icon icon-home"></span><span>Tableau de bord</span></a>
              <a class="nav-link" href="gestion-utilisateurs.php" data-nav="profile"><span class="nav-icon icon-profile"></span><span>Gestion des utilisateurs</span></a>
              <a class="nav-link" href="gestion-brainstormings.php" data-nav="community"><span class="nav-icon icon-community"></span><span>Gestion des brainstormings</span></a>
              <a class="nav-link" href="gestion-rendezvous.php" data-nav="subscription"><span class="nav-icon icon-card"></span><span>Gestion des rendez-vous</span></a>
              <a class="nav-link" href="gestion-accompagnements.php" data-nav="chatbot"><span class="nav-icon icon-chat"></span><span>Gestion des accompagnements</span></a>
              <a class="nav-link" href="gestion-evenements.php" data-nav="events"><span class="nav-icon icon-calendar"></span><span>Gestion des événements</span></a>
              <a class="nav-link" href="gestion-reclamations.php" data-nav="voice"><span class="nav-icon icon-mic"></span><span>Gestion des réclamations</span></a>
              <a class="nav-link" href="settings.php" data-nav="settings"><span class="nav-icon icon-settings"></span><span>Paramètres</span></a>
            </div>
          </div>
        </div>
      </aside>

      <main class="page">
        <div class="topbar">
          <div>
            <button class="mobile-toggle" data-nav-toggle aria-label="Open navigation">=</button>
            <h1 class="page-title">Gestion des événements</h1>
            <div class="page-subtitle">Créez, modifiez et suivez les inscriptions des événements SecondVoice.</div>
          </div>
          <div class="toolbar-actions">
            <a class="update-button" href="../frontoffice/index.php">Revenir</a>
            <button class="icon-button icon-moon" data-theme-toggle aria-label="Switch theme"></button>
          </div>
        </div>

        <div class="container admin-main">
          <div id="admin-feedback" class="notice" style="display:none"></div>

          <nav class="admin-tabs" role="tablist">
            <button class="tab active" type="button" data-target="#tab-events">Événements</button>
            <button class="tab" type="button" data-target="#tab-requests">Demandes</button>
            <button class="tab" type="button" data-target="#tab-add">Ajouter / Modifier</button>
          </nav>

          <div class="admin-grid">
            <section id="tab-events" class="panel tab-panel active">
              <h3>Liste des événements</h3>
              <div id="events-list">
                <?php if ($events === []): ?>
                  <div class="small">Aucun événement enregistré.</div>
                <?php endif; ?>

                <?php foreach ($events as $event): ?>
                  <?php
                    $eventId = (int) $event['id'];
                    $resources = $controller->getResourcesByEvent($eventId);
                    $registrants = $controller->getRegistrantsByEvent($eventId);
                    $materials = array_values(array_filter(array_map(static fn(array $row): ?string => ($row['type'] ?? '') === 'material' ? (string) $row['resource_name'] : null, $resources)));
                    $rules = array_values(array_filter(array_map(static fn(array $row): ?string => ($row['type'] ?? '') === 'rule' ? (string) $row['resource_name'] : null, $resources)));
                  ?>
                  <?php
                    $eventCreatedBy = (int) ($event['created_by'] ?? 0);
                    $isOwner = ($eventCreatedBy === $adminId);
                    // Récupérer le nom du créateur si disponible
                    $creatorName = $eventCreatedBy > 0 ? 'Utilisateur #' . $eventCreatedBy : 'Système';
                  ?>
                  <div class="event-card"
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
                       data-materials='<?= h(json_encode($materials, JSON_UNESCAPED_UNICODE)) ?>'
                       data-rules='<?= h(json_encode($rules, JSON_UNESCAPED_UNICODE)) ?>'
                       data-registrants='<?= h(json_encode($registrants, JSON_UNESCAPED_UNICODE)) ?>'>
                    <div class="row between">
                      <h4 class="evt-name"><?= h((string) ($event['name'] ?? '')) ?></h4>
                      <span class="status <?= h(strtolower((string) ($event['status'] ?? 'en cours'))) ?>"><?= h((string) ($event['status'] ?? 'en cours')) ?></span>
                    </div>
                    <p class="desc"><?= h((string) ($event['description'] ?? '')) ?></p>
                    <div class="meta"><?= h((string) ($event['start_date'] ?? '')) ?> — <?= h((string) ($event['end_date'] ?? '')) ?> • <?= h((string) ($event['location'] ?? '')) ?></div>
                    <div class="meta small">Places : <?= (int) ($event['current'] ?? 0) ?>/<?= (int) ($event['max'] ?? 0) ?></div>
                    <div class="small">Matériels : <?= h($materials !== [] ? implode(', ', $materials) : 'Aucun') ?></div>
                    <div class="small">Règles : <?= h($rules !== [] ? implode(', ', $rules) : 'Aucune') ?></div>
                    <div class="small" style="color: #666;">Créé par : <?= h($creatorName) ?></div>
                    <div class="actions">
                      <button class="btn view-registrants" type="button" data-id="<?= $eventId ?>">Voir les inscrits</button>
                      <?php if ($isOwner): ?>
                        <button class="btn modify" type="button" data-id="<?= $eventId ?>">Modifier</button>
                        <button class="btn delete" type="button" data-id="<?= $eventId ?>">Supprimer</button>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </section>

            <section id="tab-add" class="panel tab-panel">
              <h3>Ajouter / Modifier un événement</h3>
              <form id="admin-event-form" novalidate>
                <input type="hidden" id="evt-id" />
                <div class="form-row">
                  <label for="evt-name">Nom <span style="color: red; font-weight: bold;">*</span></label>
                  <input id="evt-name" type="text" class="field" />
                  <div class="error-message" id="evt-name-error"></div>
                </div>
                <div class="form-row">
                  <label for="evt-start">Date début <span style="color: red; font-weight: bold;">*</span></label>
                  <input id="evt-start" type="datetime-local" class="field" />
                  <div class="error-message" id="evt-start-error"></div>
                </div>
                <div class="form-row">
                  <label for="evt-end">Date fin <span style="color: red; font-weight: bold;">*</span></label>
                  <input id="evt-end" type="datetime-local" class="field" />
                  <div class="error-message" id="evt-end-error"></div>
                </div>
                <div class="form-row">
                  <label for="evt-deadline">Date limite <span style="color: red; font-weight: bold;">*</span></label>
                  <input id="evt-deadline" type="datetime-local" class="field" />
                  <div class="error-message" id="evt-deadline-error"></div>
                </div>
                <div class="form-row">
                  <label for="evt-location">Lieu <span style="color: red; font-weight: bold;">*</span></label>
                  <input id="evt-location" type="text" class="field" />
                  <div class="error-message" id="evt-location-error"></div>
                </div>
                <div class="form-row">
                  <label for="evt-desc">Description</label>
                  <textarea id="evt-desc" class="field"></textarea>
                </div>
                <div class="form-row">
                  <label for="evt-max">Nombre max</label>
                  <input id="evt-max" type="number" class="field" min="1" value="1" />
                </div>
                                                <div class="form-row actions">
                  <button id="admin-save" type="button" class="btn">Enregistrer</button>
                  <button id="admin-reset" type="button" class="btn outline">Réinitialiser</button>
                </div>
              </form>
            </section>

            <section id="tab-requests" class="panel tab-panel">
              <h3>Demandes de création d'événements</h3>
              <div id="requests-list">
                <?php if ($requests === []): ?>
                  <div class="small">Aucune demande de création en attente.</div>
                <?php endif; ?>

                <?php foreach ($requests as $request): ?>
                  <?php
                    $requestId = (int) $request['id'];
                    $resources = $controller->getResourcesByEvent($requestId);
                    $materials = array_values(array_filter(array_map(static fn(array $row): ?string => ($row['type'] ?? '') === 'material' ? (string) $row['resource_name'] : null, $resources)));
                    $rules = array_values(array_filter(array_map(static fn(array $row): ?string => ($row['type'] ?? '') === 'rule' ? (string) $row['resource_name'] : null, $resources)));
                    $requestCreatedBy = (int) ($request['created_by'] ?? 0);
                    $requestIsOwner = ($requestCreatedBy === $adminId);
                    $requestCreatorName = $requestCreatedBy > 0 ? 'Utilisateur #' . $requestCreatedBy : 'Système';
                  ?>
                  <div class="event-card"
                       data-id="<?= $requestId ?>"
                       data-name="<?= h((string) ($request['name'] ?? '')) ?>"
                       data-desc="<?= h((string) ($request['description'] ?? '')) ?>"
                       data-start="<?= h((string) ($request['start_date'] ?? '')) ?>"
                       data-end="<?= h((string) ($request['end_date'] ?? '')) ?>"
                       data-deadline="<?= h((string) ($request['deadline'] ?? '')) ?>"
                       data-location="<?= h((string) ($request['location'] ?? '')) ?>"
                       data-max="<?= (int) ($request['max'] ?? 0) ?>"
                       data-current="<?= (int) ($request['current'] ?? 0) ?>"
                       data-status="<?= h((string) ($request['status'] ?? 'en cours')) ?>"
                       data-materials='<?= h(json_encode($materials, JSON_UNESCAPED_UNICODE)) ?>'
                       data-rules='<?= h(json_encode($rules, JSON_UNESCAPED_UNICODE)) ?>'>
                    <div class="row between">
                      <h4 class="evt-name"><?= h((string) ($request['name'] ?? '')) ?></h4>
                      <span class="status <?= h(strtolower((string) ($request['status'] ?? 'en cours'))) ?>"><?= h((string) ($request['status'] ?? 'en cours')) ?></span>
                    </div>
                    <p class="desc"><?= h((string) ($request['description'] ?? '')) ?></p>
                    <div class="meta"><?= h((string) ($request['start_date'] ?? '')) ?> — <?= h((string) ($request['end_date'] ?? '')) ?> • <?= h((string) ($request['location'] ?? '')) ?></div>
                    <div class="meta small">Places : <?= (int) ($request['current'] ?? 0) ?>/<?= (int) ($request['max'] ?? 0) ?></div>
                    <div class="small">Matériels : <?= h($materials !== [] ? implode(', ', $materials) : 'Aucun') ?></div>
                    <div class="small">Règles : <?= h($rules !== [] ? implode(', ', $rules) : 'Aucune') ?></div>
                    <div class="small" style="color: #666;">Demandé par : <?= h($requestCreatorName) ?></div>
                    <div class="actions">
                      <button class="btn view-registrants" type="button" data-id="<?= $requestId ?>">Voir les inscrits</button>
                      <?php if ($requestIsOwner): ?>
                        <button class="btn modify" type="button" data-id="<?= $requestId ?>">Modifier</button>
                        <button class="btn delete" type="button" data-id="<?= $requestId ?>">Supprimer</button>
                      <?php endif; ?>
                      <button class="btn approve" type="button" data-id="<?= $requestId ?>">Valider</button>
                      <button class="btn reject" type="button" data-id="<?= $requestId ?>">Refuser</button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <!-- Section Demandes de modification -->
              <h3 style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--soft-border);">Demandes de modification d'événements</h3>
              <div id="modification-requests-list">
                <?php if ($modificationRequests === []): ?>
                  <div class="small">Aucune demande de modification en attente.</div>
                <?php endif; ?>

                <?php foreach ($modificationRequests as $modRequest): ?>
                  <div class="event-card modification-request"
                       data-request-id="<?= (int) $modRequest['request_id'] ?>"
                       data-event-id="<?= (int) $modRequest['event_id'] ?>">
                    <div class="row between">
                      <h4 class="evt-name"><?= h((string) ($modRequest['current_name'] ?? '')) ?></h4>
                      <span class="status pending">Demande de modification</span>
                    </div>
                    <div class="meta">Proposé par : <?= h((string) ($modRequest['user_prenom'] ?? '') . ' ' . (string) ($modRequest['user_nom'] ?? '')) ?> (<?= h((string) ($modRequest['user_email'] ?? '')) ?>)</div>
                    <div class="meta">Date demande : <?= h((string) ($modRequest['requested_at'] ?? '')) ?></div>
                    
                    <div style="margin: 15px 0; padding: 12px; background: rgba(255,255,255,0.05); border-radius: 8px;">
                      <div style="font-weight: 600; margin-bottom: 8px; color: var(--text);">Modifications proposées :</div>
                      <?php if (!empty($modRequest['new_name']) && $modRequest['new_name'] !== $modRequest['current_name']): ?>
                        <div class="small"><span style="color: #888;">Nom :</span> <?= h((string) $modRequest['current_name']) ?> → <strong><?= h((string) $modRequest['new_name']) ?></strong></div>
                      <?php endif; ?>
                      <?php if (!empty($modRequest['new_start_date']) && $modRequest['new_start_date'] !== $modRequest['current_start_date']): ?>
                        <div class="small"><span style="color: #888;">Début :</span> <?= h((string) $modRequest['current_start_date']) ?> → <strong><?= h((string) $modRequest['new_start_date']) ?></strong></div>
                      <?php endif; ?>
                      <?php if (!empty($modRequest['new_end_date']) && $modRequest['new_end_date'] !== $modRequest['current_end_date']): ?>
                        <div class="small"><span style="color: #888;">Fin :</span> <?= h((string) $modRequest['current_end_date']) ?> → <strong><?= h((string) $modRequest['new_end_date']) ?></strong></div>
                      <?php endif; ?>
                      <?php if (!empty($modRequest['new_location']) && $modRequest['new_location'] !== $modRequest['current_location']): ?>
                        <div class="small"><span style="color: #888;">Lieu :</span> <?= h((string) $modRequest['current_location']) ?> → <strong><?= h((string) $modRequest['new_location']) ?></strong></div>
                      <?php endif; ?>
                      <?php if (!empty($modRequest['new_max']) && $modRequest['new_max'] != $modRequest['current_max']): ?>
                        <div class="small"><span style="color: #888;">Capacité :</span> <?= (int) $modRequest['current_max'] ?> → <strong><?= (int) $modRequest['new_max'] ?></strong></div>
                      <?php endif; ?>
                    </div>
                    
                    <div class="actions">
                      <button class="btn approve-modification" type="button" data-request-id="<?= (int) $modRequest['request_id'] ?>">Approuver la modification</button>
                      <button class="btn reject-modification" type="button" data-request-id="<?= (int) $modRequest['request_id'] ?>">Refuser la modification</button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <!-- Section Demandes de suppression -->
              <h3 style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--soft-border);">Demandes de suppression d'événements</h3>
              <div id="deletion-requests-list">
                <?php if ($deletionRequests === []): ?>
                  <div class="small">Aucune demande de suppression en attente.</div>
                <?php endif; ?>

                <?php foreach ($deletionRequests as $deletionRequest): ?>
                  <div class="event-card deletion-request"
                       data-request-id="<?= (int) $deletionRequest['request_id'] ?>"
                       data-event-id="<?= (int) $deletionRequest['event_id'] ?>">
                    <div class="row between">
                      <h4 class="evt-name"><?= h((string) ($deletionRequest['event_name'] ?? '')) ?></h4>
                      <span class="status pending">Demande de suppression</span>
                    </div>
                    <div class="meta">Événement ID: <?= (int) $deletionRequest['event_id'] ?></div>
                    <div class="meta">Statut actuel: <?= h((string) ($deletionRequest['event_status'] ?? 'validé')) ?></div>
                    <div class="small" style="color: #666;">Demandé par : <?= h((string) ($deletionRequest['user_prenom'] ?? '') . ' ' . (string) ($deletionRequest['user_nom'] ?? '')) ?> (<?= h((string) ($deletionRequest['user_email'] ?? '')) ?>)</div>
                    <div class="small" style="color: #666;">Date de la demande : <?= h((string) ($deletionRequest['requested_at'] ?? '')) ?></div>
                    <div class="actions">
                      <button class="btn approve-deletion" type="button" data-request-id="<?= (int) $deletionRequest['request_id'] ?>">Approuver la suppression</button>
                      <button class="btn reject-deletion" type="button" data-request-id="<?= (int) $deletionRequest['request_id'] ?>">Refuser la suppression</button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </section>
          </div>
        </div>
      </main>
    </div>

    <div id="registrants-modal" class="modal" aria-hidden="true">
      <div class="modal-content">
        <button class="modal-close" type="button">×</button>
        <h3>Inscrits</h3>
        <div id="registrants-list"></div>
      </div>
    </div>

    <script src="assets/app.js"></script>
    <script src="assets/events-admin.js"></script>
  </body>
</html>
