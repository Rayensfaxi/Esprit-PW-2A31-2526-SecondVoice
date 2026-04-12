<?php
require_once '../../controller/RendezvousC.php';

$rendezvousC = new RendezvousC();
$id_citoyen = 1; // Simulé pour l'exemple

$error = "";
$success = "";
$rdvToEdit = null;

// Chargement pour modification
if (isset($_GET['edit'])) {
    $rdvToEdit = $rendezvousC->getRendezvousById($_GET['edit']);
}

// Traitement de l'ajout ou modification
if (isset($_POST['save_rdv'])) {
    $id = $_POST['id'] ?? null;
    $service = $_POST['service'] ?? '';
    $assistant = $_POST['assistant'] ?? '';
    $date_rdv = $_POST['date_rdv'] ?? '';
    $heure_rdv = $_POST['heure_rdv'] ?? '';
    $mode = $_POST['mode'] ?? '';
    $remarques = $_POST['remarques'] ?? '';

    if (!empty($service) && !empty($assistant) && !empty($date_rdv) && !empty($heure_rdv) && !empty($mode)) {
        // Contrôle de saisie PHP
        $date_selectionnee = new DateTime($date_rdv);
        $aujourdhui = new DateTime();
        $aujourdhui->setTime(0, 0, 0);

        if ($date_selectionnee < $aujourdhui) {
            $error = "La date du rendez-vous ne peut pas être dans le passé.";
        } else {
            // Validation Heure (8:10 à 17:30)
            $heure_time = strtotime($heure_rdv);
            $start_time = strtotime('08:10');
            $end_time = strtotime('17:30');
            
            // Validation Remarques (Max 50 mots)
            $word_count = !empty(trim($remarques)) ? preg_match_all('/\S+/', $remarques) : 0;

            if ($heure_time < $start_time || $heure_time > $end_time) {
                $error = "L'heure doit être comprise entre 08:10 et 17:30.";
            } elseif ($id && ($currentRdv = $rendezvousC->getRendezvousById($id)) && $currentRdv['statut'] == 'Annulé') {
                $error = "Impossible de modifier un rendez-vous annulé.";
            } elseif (empty(trim($remarques))) {
                $error = "Le champ Remarques est obligatoire.";
            } elseif ($word_count > 50) {
                $error = "Les remarques ne doivent pas dépasser 50 mots (actuellement : $word_count mots).";
            } else {
                $rendezvous = new Rendezvous(
                    $id,
                    $id_citoyen,
                    htmlspecialchars($service),
                    htmlspecialchars($assistant),
                    $date_selectionnee,
                    htmlspecialchars($heure_rdv),
                    htmlspecialchars($mode),
                    htmlspecialchars($remarques),
                    $id ? ($_POST['statut'] ?? 'En attente') : 'En attente'
                );

                if ($id) {
                    $rendezvousC->updateRendezvous($rendezvous, $id);
                    $success = "Votre rendez-vous a été modifié avec succès.";
                    header("Location: service-prise-rendezvous.php?success=" . urlencode($success));
                    exit();
                } else {
                    try {
                        $rendezvousC->addRendezvous($rendezvous);
                        $success = "Votre rendez-vous a été enregistré avec succès.";
                        header("Location: service-prise-rendezvous.php?success=" . urlencode($success));
                        exit();
                    } catch (Exception $e) {
                        $error = "Erreur lors de l'enregistrement : " . $e->getMessage();
                    }
                }
            }
        }
    } else {
        $error = "Tous les champs obligatoires doivent être remplis.";
    }
}

// Traitement de l'annulation
if (isset($_GET['cancel'])) {
    $rendezvousC->updateStatut($_GET['cancel'], 'Annulé');
    header('Location: service-prise-rendezvous.php?success=Rendez-vous annulé');
    exit;
}

// Traitement de la suppression
if (isset($_GET['delete'])) {
    $rendezvousC->deleteRendezvous($_GET['delete']);
    header('Location: service-prise-rendezvous.php?success=Rendez-vous supprimé');
    exit;
}

if (isset($_GET['success'])) $success = htmlspecialchars($_GET['success']);

$liste = $rendezvousC->listRendezvousByCitoyen($id_citoyen);
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Prise de rendez-vous | SecondVoice</title>
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
    <link
      href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="assets/css/style.css" />
    <style>
        .error-message { color: #e74c3c; background: rgba(231, 76, 60, 0.1); padding: 10px; border-radius: 5px; margin-bottom: 20px; border-left: 5px solid #e74c3c; }
        .success-message { color: #27ae60; background: rgba(39, 174, 96, 0.1); padding: 10px; border-radius: 5px; margin-bottom: 20px; border-left: 5px solid #27ae60; }
        .js-error { display: none; color: #e74c3c; font-size: 0.85rem; margin-top: 5px; }
        .field.invalid { border-color: #e74c3c; }
    </style>
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
                <a class="icon-btn user-trigger" href="login.html" aria-label="Ouvrir la page de connexion"><span>Profil</span></a>
              </div>
            </div>
          </div>
        </div>
      </header>

      <main>
        <section class="page-hero">
          <div class="container">
            <div class="page-hero-card fade-up">
              <div class="breadcrumbs"><span>Accueil</span><span>/</span><span>Services</span><span>/</span><span>Prise de rendez-vous</span></div>
              <h1>Planifiez votre rendez-vous en quelques clics.</h1>
              <p>Choisissez votre service, votre assistant et l'horaire qui vous convient le mieux.</p>
            </div>
          </div>
        </section>

        <section class="section">
          <div class="container service-layout">
            <div class="post-content fade-up">
              <div class="post-hero">
                <div class="post-hero-content">
                  <div class="tag">Formulaire de réservation</div>
                  <h2><?php echo $rdvToEdit ? 'Modifier mon rendez-vous' : 'Détails du rendez-vous'; ?></h2>
                  <p><?php echo $rdvToEdit ? 'Modifiez les informations ci-dessous.' : 'Merci de remplir les informations ci-dessous pour confirmer votre demande.'; ?></p>
                </div>
              </div>
              
              <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
              <?php endif; ?>
              <?php if ($success): ?>
                <div class="success-message"><?php echo $success; ?></div>
              <?php endif; ?>

                <form id="rdvForm" method="POST" action="service-prise-rendezvous.php" class="auth-form" style="max-width: 100%; margin-top: 2.5rem;" novalidate>
                  <?php if ($rdvToEdit): ?>
                    <input type="hidden" name="id" value="<?php echo $rdvToEdit['id']; ?>">
                    <input type="hidden" name="statut" value="<?php echo $rdvToEdit['statut']; ?>">
                  <?php endif; ?>
                  <div class="input-row" style="display: flex; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 2rem;">
                    <div style="flex: 1; min-width: 250px;">
                      <label style="display: block; margin-bottom: 0.75rem; font-weight: 600; color: var(--text); opacity: 0.9;">Service souhaité</label>
                      <select id="service" name="service" class="field" style="width: 100%;">
                        <option value="" disabled <?php echo !$rdvToEdit ? 'selected' : ''; ?>>Choisir un service...</option>
                        <?php 
                        $services = ["Accompagnement administratif", "Suivi de dossier", "Gestion de réclamation", "Support technique"];
                        foreach($services as $s) {
                            $selected = ($rdvToEdit && $rdvToEdit['service'] == $s) ? 'selected' : '';
                            echo "<option value=\"$s\" $selected>$s</option>";
                        }
                        ?>
                      </select>
                  <div id="service-error" class="js-error">Veuillez choisir un service.</div>
                </div>
                <div style="flex: 1; min-width: 250px;">
                  <label style="display: block; margin-bottom: 0.75rem; font-weight: 600; color: var(--text); opacity: 0.9;">Assistant préféré</label>
                  <select id="assistant" name="assistant" class="field" style="width: 100%;">
                        <option value="" disabled <?php echo !$rdvToEdit ? 'selected' : ''; ?>>Choisir un assistant...</option>
                        <?php 
                        $assistants = [
                            "Amira Selmi" => "Amira Selmi (Administration)",
                            "Nour Kammoun" => "Nour Kammoun (Social)",
                            "Hichem Ben Ali" => "Hichem Ben Ali (Technique)"
                        ];
                        foreach($assistants as $val => $label) {
                            $selected = ($rdvToEdit && $rdvToEdit['assistant'] == $val) ? 'selected' : '';
                            echo "<option value=\"$val\" $selected>$label</option>";
                        }
                        ?>
                      </select>
                      <div id="assistant-error" class="js-error">Veuillez choisir un assistant.</div>
                    </div>
                  </div>

                  <div class="input-row" style="display: flex; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 2rem;">
                    <div style="flex: 1; min-width: 250px;">
                      <label style="display: block; margin-bottom: 0.75rem; font-weight: 600; color: var(--text); opacity: 0.9;">Date du rendez-vous</label>
                      <input id="date_rdv" name="date_rdv" class="field" type="date" style="width: 100%;" value="<?php echo $rdvToEdit ? $rdvToEdit['date_rdv'] : ''; ?>" />
                      <div id="date-error" class="js-error">Veuillez choisir une date valide.</div>
                    </div>
                    <div style="flex: 1; min-width: 250px;">
                      <label style="display: block; margin-bottom: 0.75rem; font-weight: 600; color: var(--text); opacity: 0.9;">Heure du rendez-vous</label>
                      <input id="heure_rdv" name="heure_rdv" class="field" type="time" style="width: 100%;" value="<?php echo $rdvToEdit ? $rdvToEdit['heure_rdv'] : ''; ?>" />
                      <div id="heure-error" class="js-error">Veuillez choisir une heure.</div>
                    </div>
                  </div>

                <div style="margin-bottom: 2rem;">
                  <label style="display: block; margin-bottom: 0.75rem; font-weight: 600; color: var(--text); opacity: 0.9;">Mode du rendez-vous</label>
                  <div style="display: flex; gap: 3rem; flex-wrap: wrap;">
                    <label class="check-row"><input type="radio" name="mode" value="Présentiel" <?php echo (!$rdvToEdit || $rdvToEdit['mode'] == 'Présentiel') ? 'checked' : ''; ?> /> Présentiel</label>
                    <label class="check-row"><input type="radio" name="mode" value="En ligne" <?php echo ($rdvToEdit && $rdvToEdit['mode'] == 'En ligne') ? 'checked' : ''; ?> /> En ligne (Visioconférence)</label>
                  </div>
                </div>

                <div style="margin-bottom: 2.5rem;">
                  <label style="display: block; margin-bottom: 0.75rem; font-weight: 600; color: var(--text); opacity: 0.9;">Remarques ou précisions (Obligatoire)</label>
                  <textarea name="remarques" id="remarques" class="field" placeholder="Expliquez brièvement l'objet de votre rendez-vous..." style="width: 100%; min-height: 140px; resize: vertical;"><?php echo $rdvToEdit ? $rdvToEdit['remarques'] : ''; ?></textarea>
                  <div id="remarques-error" class="js-error">Ce champ est obligatoire et ne doit pas dépasser 50 mots.</div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                  <?php if ($rdvToEdit): ?>
                    <a href="service-prise-rendezvous.php" class="btn btn-secondary">Annuler l'édition</a>
                  <?php else: ?>
                    <button class="btn btn-secondary" type="reset">Réinitialiser</button>
                  <?php endif; ?>
                  <button class="btn btn-primary" type="submit" name="save_rdv"><?php echo $rdvToEdit ? 'Mettre à jour' : 'Confirmer le rendez-vous'; ?></button>
                </div>
              </form>

              <!-- Section Mes rendez-vous -->
              <div style="margin-top: 5rem;">
                <div class="post-hero">
                  <div class="post-hero-content">
                    <div class="tag">Mon Suivi</div>
                    <h2>Mes rendez-vous</h2>
                    <p>Consultez, modifiez ou annulez vos rendez-vous en cours.</p>
                  </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 1.5rem; margin-top: 3rem;">
                  <?php if (empty($liste)): ?>
                    <p>Vous n'avez pas encore de rendez-vous.</p>
                  <?php else: ?>
                    <?php foreach ($liste as $rdv): ?>
                      <div class="appointment-card">
                        <div class="appointment-header">
                          <div class="appointment-info">
                            <span class="status-badge <?php echo strtolower(str_replace(' ', '-', $rdv['statut'])); ?>">
                                <?php echo $rdv['statut']; ?>
                            </span>
                            <h4><?php echo $rdv['service']; ?></h4>
                            <p style="margin: 0; color: var(--muted); font-size: 0.95rem;">Assistant : <strong><?php echo $rdv['assistant']; ?></strong></p>
                          </div>
                          <div class="appointment-meta">
                            <p style="margin: 0; font-weight: 700; font-size: 1.1rem; color: var(--primary);"><?php echo date('d M Y', strtotime($rdv['date_rdv'])); ?></p>
                            <p style="margin: 0; font-weight: 600;"><?php echo $rdv['heure_rdv']; ?></p>
                            <p style="margin: 0.25rem 0 0; color: var(--muted); font-size: 0.85rem;">Mode : <?php echo $rdv['mode']; ?></p>
                          </div>
                        </div>
                        <div class="appointment-footer">
                          <p style="font-size: 0.85rem; color: var(--muted); font-style: italic; margin: 0;">
                            <?php 
                            if ($rdv['statut'] == 'Confirmé') echo 'Ce rendez-vous est déjà confirmé.';
                            elseif ($rdv['statut'] == 'Annulé') echo 'Ce rendez-vous a été annulé.';
                            else echo 'Rendez-vous modifiable.'; 
                            ?>
                          </p>
                          <div class="appointment-actions">
                             <?php if ($rdv['statut'] == 'En attente'): ?>
                                <a href="service-prise-rendezvous.php?edit=<?php echo $rdv['id']; ?>#rdvForm" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">Reprogrammer</a>
                                <a href="service-prise-rendezvous.php?cancel=<?php echo $rdv['id']; ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem; color: #e74c3c;" onclick="return confirm('Voulez-vous vraiment annuler ce rendez-vous ?')">Annuler</a>
                             <?php endif; ?>
                             <?php if ($rdv['statut'] == 'Annulé'): ?>
                                <a href="service-prise-rendezvous.php?delete=<?php echo $rdv['id']; ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem; color: #e74c3c;" onclick="return confirm('Supprimer définitivement cet historique ?')">Supprimer</a>
                             <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </section>
      </main>

      <footer class="site-footer">
        <div class="container">
          <div class="footer-bottom">
            <p>&copy; 2026 SecondVoice. Tous droits réservés.</p>
          </div>
        </div>
      </footer>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        // Fonction pour valider un champ individuellement
        function validateField(fieldId) {
            const field = document.getElementById(fieldId);
            const errorElement = document.getElementById(fieldId + '-error') || document.getElementById(fieldId.replace('_rdv', '') + '-error');
            
            if (!field || !errorElement) return;

            let isFieldValid = true;
            let errorMessage = "";

            if (fieldId === 'service' || fieldId === 'assistant') {
                if (!field.value || field.value === "") {
                    isFieldValid = false;
                    errorMessage = "Veuillez faire un choix.";
                }
            } else if (fieldId === 'date_rdv') {
                if (!field.value) {
                    isFieldValid = false;
                    errorMessage = "Veuillez choisir une date.";
                } else {
                    const selectedDate = new Date(field.value);
                    const today = new Date();
                    today.setHours(0,0,0,0);
                    if (selectedDate < today) {
                        isFieldValid = false;
                        errorMessage = "La date ne peut pas être dans le passé.";
                    }
                }
            } else if (fieldId === 'heure_rdv') {
                if (!field.value) {
                    isFieldValid = false;
                    errorMessage = "Veuillez choisir une heure.";
                } else {
                    const hParts = field.value.split(':');
                    const h = parseInt(hParts[0]);
                    const m = parseInt(hParts[1]);
                    const timeInMinutes = h * 60 + m;
                    const minTime = 8 * 60 + 10;
                    const maxTime = 17 * 60 + 30;
                    if (timeInMinutes < minTime || timeInMinutes > maxTime) {
                        isFieldValid = false;
                        errorMessage = "L'heure doit être comprise entre 08:10 et 17:30.";
                    }
                }
            } else if (fieldId === 'remarques') {
                const words = field.value.trim().match(/\S+/g) || [];
                const wordCount = words.length;
                if (field.value.trim() === "") {
                    isFieldValid = false;
                    errorMessage = "Ce champ est obligatoire.";
                } else if (wordCount > 50) {
                    isFieldValid = false;
                    errorMessage = "Les remarques ne doivent pas dépasser 50 mots (actuellement : " + wordCount + " mots).";
                }
            }

            if (isFieldValid) {
                errorElement.style.display = 'none';
                field.classList.remove('invalid');
            } else {
                errorElement.textContent = errorMessage;
                errorElement.style.display = 'block';
                field.classList.add('invalid');
            }
            return isFieldValid;
        }

        // Ajouter des écouteurs d'événements pour la validation en temps réel
        ['service', 'assistant', 'date_rdv', 'heure_rdv', 'remarques'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', () => validateField(id));
                el.addEventListener('change', () => validateField(id));
            }
        });

        document.getElementById('rdvForm').addEventListener('submit', function(e) {
            let isValid = true;
            
            ['service', 'assistant', 'date_rdv', 'heure_rdv', 'remarques'].forEach(id => {
                if (!validateField(id)) isValid = false;
            });

            if (!isValid) {
                e.preventDefault();
                document.querySelector('.field.invalid').scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    </script>
  </body>
</html>
