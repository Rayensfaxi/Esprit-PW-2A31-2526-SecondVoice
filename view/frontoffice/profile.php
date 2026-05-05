<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../controller/UtilisateurController.php';
require_once __DIR__ . '/../../controller/ActivityLogger.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function getInitials(array $user): string
{
    $nom = (string) ($user['nom'] ?? '');
    $prenom = (string) ($user['prenom'] ?? '');
    $initials = strtoupper(substr(trim($nom), 0, 1) . substr(trim($prenom), 0, 1));
    return $initials !== '' ? $initials : 'SV';
}

function findUserPhotoFile(int $userId, string $photoDir): ?string
{
    $pattern = $photoDir . DIRECTORY_SEPARATOR . 'user_' . $userId . '.*';
    $files = glob($pattern) ?: [];
    return $files[0] ?? null;
}

function deleteUserPhotoFiles(int $userId, string $photoDir): void
{
    $pattern = $photoDir . DIRECTORY_SEPARATOR . 'user_' . $userId . '.*';
    $files = glob($pattern) ?: [];
    foreach ($files as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

function getAdminFaceTemplatePaths(int $userId): array
{
    $safeId = max(1, $userId);
    return [
        __DIR__ . '/../../storage/security/admin_face_' . $safeId . '.json',
        __DIR__ . '/assets/media/security/admin_face_' . $safeId . '.json'
    ];
}

function resetAdminFaceTemplates(int $userId): int
{
    $deleted = 0;
    foreach (getAdminFaceTemplatePaths($userId) as $path) {
        if (is_file($path) && @unlink($path)) {
            $deleted++;
        }
    }
    return $deleted;
}

function formatEventDate(string $isoDate): string
{
    try {
        return (new DateTime($isoDate))->format('d/m/Y');
    } catch (Throwable $exception) {
        return '-';
    }
}

function formatEventTime(string $isoDate): string
{
    try {
        return (new DateTime($isoDate))->format('H:i');
    } catch (Throwable $exception) {
        return '-';
    }
}

function formatTimeAgo(string $isoDate): string
{
    try {
        $date = new DateTime($isoDate);
        $now = new DateTime();
        $diff = max(0, $now->getTimestamp() - $date->getTimestamp());

        if ($diff < 60) {
            return "a l'instant";
        }
        if ($diff < 3600) {
            $minutes = (int) floor($diff / 60);
            return 'il y a ' . $minutes . ' min';
        }
        if ($diff < 86400) {
            $hours = (int) floor($diff / 3600);
            return 'il y a ' . $hours . ' h';
        }
        if ($diff < 172800) {
            return "hier";
        }
        $days = (int) floor($diff / 86400);
        return 'il y a ' . $days . ' jours';
    } catch (Throwable $exception) {
        return '-';
    }
}

function frontofficeBaseUrl(): string
{
    $forcedPublicBase = '';
    if (class_exists('Config') && method_exists('Config', 'getPublicBaseUrl')) {
        $forcedPublicBase = trim((string) Config::getPublicBaseUrl());
    } else {
        $forcedPublicBase = trim((string) (getenv('SECONDVOICE_PUBLIC_BASE_URL') ?: ''));
    }
    if ($forcedPublicBase !== '') {
        return rtrim($forcedPublicBase, '/');
    }

    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
    $dir = rtrim(dirname($script), '/');
    return $scheme . '://' . $host . $dir;
}

$controller = new UtilisateurController();
$userId = (int) $_SESSION['user_id'];
$user = $controller->getUserById($userId);

if (!$user) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

$photoDir = __DIR__ . '/assets/media/profile-users';
$photoWebDir = 'assets/media/profile-users';
if (!is_dir($photoDir)) {
    mkdir($photoDir, 0775, true);
}

$feedback = '';
$feedbackType = '';

if (isset($_GET['status']) && $_GET['status'] === 'registered') {
    $feedback = 'Compte cree avec succes.';
    $feedbackType = 'success';
}
if (isset($_GET['status']) && $_GET['status'] === 'logged_in') {
    $feedback = 'Connexion reussie.';
    $feedbackType = 'success';
}
if (isset($_GET['status']) && $_GET['status'] === 'forbidden') {
    $feedback = 'Acces refuse: seuls un administrateur ou un agent peuvent acceder au dashboard.';
    $feedbackType = 'error';
}
if (isset($_GET['status']) && $_GET['status'] === 'face_reset_done') {
    $feedback = "Empreinte faciale admin reinitialisee. Un nouvel enrolement sera demande a la prochaine connexion.";
    $feedbackType = 'success';
}
if (isset($_GET['status']) && $_GET['status'] === 'face_reset_forbidden') {
    $feedback = "Action reservee a l'administrateur.";
    $feedbackType = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strtolower(trim((string) ($_POST['action'] ?? 'update')));
    $currentRole = strtolower((string) ($user['role'] ?? 'client'));

    if ($action === 'logout') {
        session_unset();
        session_destroy();
        header('Location: login.php?status=logged_out');
        exit;
    }

    if ($action === 'reset_face_template') {
        try {
            if ($currentRole !== 'admin') {
                header('Location: profile.php?status=face_reset_forbidden');
                exit;
            }

            resetAdminFaceTemplates($userId);
            unset($_SESSION['admin_face_verified_at']);

            // Force immediate re-enrollment flow by converting active admin session
            // into a pending admin verification session.
            $pendingAdmin = [
                'id' => $userId,
                'role' => (string) ($user['role'] ?? 'admin'),
                'nom' => (string) ($user['nom'] ?? ''),
                'prenom' => (string) ($user['prenom'] ?? ''),
                'email' => (string) ($user['email'] ?? '')
            ];

            unset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['user_nom'], $_SESSION['user_prenom'], $_SESSION['user_email']);
            $_SESSION['pending_admin_user'] = $pendingAdmin;

            ActivityLogger::log($userId, 'Securite', "Reinitialisation de l'empreinte faciale admin.");
            header('Location: admin-face-verify.php');
            exit;
        } catch (Throwable $exception) {
            $feedback = $exception->getMessage();
            $feedbackType = 'error';
        }
    } else {
        $nom = trim((string) ($_POST['nom'] ?? ''));
        $prenom = trim((string) ($_POST['prenom'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $telephone = trim((string) ($_POST['telephone'] ?? ''));
        $password = trim((string) ($_POST['mot_de_passe'] ?? ''));
        $removePhoto = ((string) ($_POST['remove_photo'] ?? '0')) === '1';

        try {
            $oldUser = $user;
            $newPhotoTmp = null;
            $newPhotoExtension = null;

            if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('Erreur pendant l\'upload de la photo.');
                }

                $maxSize = 4 * 1024 * 1024;
                if ((int) $_FILES['photo']['size'] > $maxSize) {
                    throw new RuntimeException('Image trop volumineuse (max 4 Mo).');
                }

                $tmpPath = (string) ($_FILES['photo']['tmp_name'] ?? '');
                if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
                    throw new RuntimeException('Fichier upload invalide.');
                }

                $mime = (string) (finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmpPath) ?: '');
                $allowed = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp'
                ];

                if (!isset($allowed[$mime])) {
                    throw new RuntimeException('Format image non supporte. Utilisez JPG, PNG ou WEBP.');
                }

                $newPhotoTmp = $tmpPath;
                $newPhotoExtension = $allowed[$mime];
            }

            $controller->updateUser(
                $userId,
                $nom,
                $prenom,
                $email,
                $telephone,
                (string) ($user['role'] ?? 'client'),
                $password !== '' ? $password : null
            );

            if ($removePhoto) {
                deleteUserPhotoFiles($userId, $photoDir);
            }

            if ($newPhotoTmp !== null && $newPhotoExtension !== null) {
                deleteUserPhotoFiles($userId, $photoDir);
                $targetPath = $photoDir . DIRECTORY_SEPARATOR . 'user_' . $userId . '.' . $newPhotoExtension;
                if (!move_uploaded_file($newPhotoTmp, $targetPath)) {
                    throw new RuntimeException('Impossible d\'enregistrer la photo de profil.');
                }
            }

            $user = $controller->getUserById($userId);
            if (!$user) {
                throw new RuntimeException('Utilisateur introuvable apres mise a jour.');
            }

            $_SESSION['user_role'] = (string) ($user['role'] ?? 'client');
            $_SESSION['user_nom'] = (string) ($user['nom'] ?? '');
            $_SESSION['user_prenom'] = (string) ($user['prenom'] ?? '');
            $_SESSION['user_email'] = (string) ($user['email'] ?? '');

            $profileChanged = (
                (string) ($oldUser['nom'] ?? '') !== (string) ($user['nom'] ?? '') ||
                (string) ($oldUser['prenom'] ?? '') !== (string) ($user['prenom'] ?? '') ||
                (string) ($oldUser['email'] ?? '') !== (string) ($user['email'] ?? '') ||
                (string) ($oldUser['telephone'] ?? '') !== (string) ($user['telephone'] ?? '') ||
                $password !== ''
            );

            if ($profileChanged) {
                ActivityLogger::log($userId, 'Profil', 'Mise a jour du profil utilisateur.');
            }

            if ($newPhotoTmp !== null && $newPhotoExtension !== null) {
                ActivityLogger::log($userId, 'Photo', "Ajout d'une photo de profil.");
            } elseif ($removePhoto) {
                ActivityLogger::log($userId, 'Photo', "Suppression de la photo de profil.");
            }

            $feedback = 'Profil mis a jour avec succes.';
            $feedbackType = 'success';
        } catch (Throwable $exception) {
            $feedback = $exception->getMessage();
            $feedbackType = 'error';

            $user['nom'] = $nom;
            $user['prenom'] = $prenom;
            $user['email'] = $email;
            $user['telephone'] = $telephone;
        }
    }
}

$recentActivities = ActivityLogger::getRecent($userId, 5);
$lastActivity = $recentActivities[0] ?? null;

$currentPhotoPath = findUserPhotoFile($userId, $photoDir);
$currentPhotoUrl = '';
if ($currentPhotoPath) {
    $currentPhotoUrl = $photoWebDir . '/' . basename($currentPhotoPath) . '?v=' . (string) @filemtime($currentPhotoPath);
}
$initials = getInitials($user);
$mobileOpenUrl = frontofficeBaseUrl() . '/index.php';
$qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . rawurlencode($mobileOpenUrl);
$mobileHost = strtolower((string) (parse_url($mobileOpenUrl, PHP_URL_HOST) ?? ''));
$mobileUrlIsLocal = in_array($mobileHost, ['localhost', '127.0.0.1', '::1'], true);
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Profil | SecondVoice</title>
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
                <li><a href="services.php">Services</a></li>
                <li><a href="blog.php">Blog</a></li>
                <li><a href="contact.php">Contact</a></li>
              </ul>
            </nav>
            <div class="header-actions">
              <button class="icon-btn theme-toggle" type="button" data-theme-toggle aria-label="Changer le theme">
                <span class="theme-toggle-label" data-theme-label>Clair</span>
              </button>
              <a class="btn btn-secondary" href="index.php">Accueil</a>
              <form method="post" action="profile.php" style="display:inline;">
                <input type="hidden" name="action" value="logout" />
                <button class="btn btn-primary" type="submit">Deconnexion</button>
              </form>
            </div>
          </div>
        </div>
      </header>

      <main>
        <section class="page-hero">
          <div class="container">
            <div class="page-hero-card fade-up">
              <div class="breadcrumbs"><span>Accueil</span><span>/</span><span>Profil</span></div>
              <h1>Mon profil utilisateur</h1>
              <p>Modifiez vos informations personnelles et votre photo de profil.</p>
            </div>
          </div>
        </section>

        <section class="section">
          <div class="container profile-grid">
            <article class="contact-card fade-up">
              <h3>Informations personnelles</h3>
              <p>Les modifications sont enregistrees en base de donnees.</p>

              <form id="profile-form" class="profile-form" method="post" action="profile.php" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="action" value="update" />
                <input type="hidden" name="remove_photo" id="remove-photo-flag" value="0" />

                <div class="profile-photo-row">
                  <div
                    class="profile-photo-preview<?= $currentPhotoUrl !== '' ? ' has-image' : '' ?>"
                    id="profile-photo-preview"
                    <?php if ($currentPhotoUrl !== ''): ?>style="background-image: url('<?= h($currentPhotoUrl) ?>');"<?php endif; ?>
                  >
                    <span id="profile-photo-initials"><?= h($initials) ?></span>
                  </div>
                  <div class="profile-photo-actions">
                    <label class="btn btn-secondary" for="profile-photo-input">Ajouter/Changer photo</label>
                    <button class="btn btn-secondary" id="profile-photo-clear" type="button">Supprimer photo</button>
                    <p class="profile-help">Formats: JPG/PNG/WEBP, max 4 Mo.</p>
                  </div>
                </div>

                <input id="profile-photo-input" class="profile-photo-input" type="file" name="photo" accept="image/jpeg,image/png,image/webp" />

                <div class="input-row">
                  <input class="field" type="text" name="nom" value="<?= h($user['nom'] ?? '') ?>" placeholder="Nom" />
                  <input class="field" type="text" name="prenom" value="<?= h($user['prenom'] ?? '') ?>" placeholder="Prenom" />
                </div>

                <div class="input-row">
                  <input class="field" type="text" name="email" value="<?= h($user['email'] ?? '') ?>" placeholder="Email" />
                  <input class="field" type="text" name="telephone" value="<?= h($user['telephone'] ?? '') ?>" placeholder="Telephone" />
                </div>

                <input class="field" type="password" name="mot_de_passe" placeholder="Nouveau mot de passe (optionnel)" />

                <p id="profile-feedback" class="profile-feedback <?= $feedbackType === 'error' ? 'error' : ($feedbackType === 'success' ? 'success' : '') ?>"><?= h($feedback) ?></p>
                <button class="btn btn-primary" type="submit">Enregistrer le profil</button>
              </form>
            </article>

            <aside class="sidebar">
              <div class="sidebar-card fade-up">
                <div class="profile-photo-preview<?= $currentPhotoUrl !== '' ? ' has-image' : '' ?>"
                     <?php if ($currentPhotoUrl !== ''): ?>style="background-image: url('<?= h($currentPhotoUrl) ?>'); margin-bottom: 14px;"<?php else: ?>style="margin-bottom: 14px;"<?php endif; ?>
                >
                  <span><?= h($initials) ?></span>
                </div>
                <h3><?= h(($user['nom'] ?? '') . ' ' . ($user['prenom'] ?? '')) ?></h3>
                <p><?= h($user['email'] ?? '') ?></p>
                <p class="profile-help">Role: <?= h(ucfirst((string) ($user['role'] ?? 'client'))) ?></p>
                <p class="profile-help">Statut: <?= h(ucfirst((string) ($user['statut_compte'] ?? 'actif'))) ?></p>
                <?php if (strtolower((string) ($user['role'] ?? 'client')) === 'admin'): ?>
                  <form method="post" action="profile.php" style="margin-top: 12px;">
                    <input type="hidden" name="action" value="reset_face_template" />
                    <button
                      class="btn btn-secondary"
                      type="submit"
                      onclick="return confirm('Reinitialiser l empreinte faciale admin ? Un nouvel enrolement sera demande a la prochaine connexion.');"
                    >
                      Reinitialiser empreinte faciale
                    </button>
                    <p class="profile-help" style="margin-top:8px;">Cette action force un nouvel enrôlement facial a la prochaine connexion admin.</p>
                  </form>
                <?php endif; ?>
              </div>

              <div class="sidebar-card fade-up">
                <h3>Derniere activite</h3>
                <?php if ($lastActivity): ?>
                  <p class="profile-help">Type : <?= h((string) ($lastActivity['type'] ?? '-')) ?></p>
                  <p class="profile-help">Date : <?= h(formatEventDate((string) ($lastActivity['at'] ?? ''))) ?></p>
                  <p class="profile-help">Heure : <?= h(formatEventTime((string) ($lastActivity['at'] ?? ''))) ?></p>
                  <p class="profile-help">Detail : <?= h((string) ($lastActivity['detail'] ?? '-')) ?></p>
                <?php else: ?>
                  <p class="profile-help">Aucune activite enregistree pour le moment.</p>
                <?php endif; ?>
              </div>

              <div class="sidebar-card fade-up">
                <h3>Activite recente</h3>
                <?php if (count($recentActivities) === 0): ?>
                  <p class="profile-help">Aucune activite recente.</p>
                <?php else: ?>
                  <ul class="footer-list">
                    <?php foreach ($recentActivities as $activity): ?>
                      <li>
                        <?= h((string) ($activity['detail'] ?? 'Activite')) ?>
                        <span class="profile-help"> - <?= h(formatTimeAgo((string) ($activity['at'] ?? ''))) ?></span>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </div>

              <div class="sidebar-card fade-up">
                <h3>Liens rapides</h3>
                <ul class="footer-list">
                  <li><a href="index.php">Accueil</a></li>
                  <li><a href="services.php">Services</a></li>
                  <li><a href="contact.php">Support</a></li>
                  <?php
                    $role = strtolower((string) ($user['role'] ?? 'client'));
                    if ($role === 'admin'):
                  ?>
                  <li><a href="../backoffice/index.php">Dashboard</a></li>
                  <?php elseif ($role === 'agent'): ?>
                  <li><a href="../backoffice/gestion-accompagnements.php">Dashboard</a></li>
                  <?php endif; ?>
                </ul>
              </div>

              <div class="sidebar-card fade-up">
                <h3>Acces mobile</h3>
                <p class="profile-help">Scannez ce QR code pour ouvrir le site sur votre telephone.</p>
                <?php if ($mobileUrlIsLocal): ?>
                  <p class="profile-help" style="color:#d64b6a;">
                    URL locale detectee (<?= h($mobileHost) ?>). Configurez `SECONDVOICE_PUBLIC_BASE_URL` avec l'IP LAN de votre PC pour ouvrir depuis telephone.
                  </p>
                <?php endif; ?>
                <img
                  src="<?= h($qrCodeUrl) ?>"
                  alt="QR code vers le site SecondVoice"
                  style="width: 100%; max-width: 220px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.15); margin: 10px 0;"
                />
                <p class="profile-help" style="word-break: break-all;"><?= h($mobileOpenUrl) ?></p>
              </div>
            </aside>
          </div>
        </section>
      </main>
    </div>

    <script>
      (function () {
        const form = document.getElementById("profile-form");
        const feedback = document.getElementById("profile-feedback");
        const photoInput = document.getElementById("profile-photo-input");
        const photoClear = document.getElementById("profile-photo-clear");
        const photoPreview = document.getElementById("profile-photo-preview");
        const photoInitials = document.getElementById("profile-photo-initials");
        const removeFlag = document.getElementById("remove-photo-flag");
        if (!form) return;

        function showError(message) {
          feedback.textContent = message;
          feedback.classList.add("error");
          feedback.classList.remove("success");
        }

        function clearError() {
          feedback.classList.remove("error");
        }

        function validateImage(file) {
          const allowed = ["image/jpeg", "image/png", "image/webp"];
          const maxSize = 4 * 1024 * 1024;

          if (!allowed.includes(file.type)) {
            return "Format image non supporte. Utilisez JPG, PNG ou WEBP.";
          }

          if (file.size > maxSize) {
            return "Image trop volumineuse (max 4 Mo).";
          }

          return "";
        }

        if (photoInput) {
          photoInput.addEventListener("change", function () {
            const file = photoInput.files && photoInput.files[0];
            if (!file) return;

            const imageError = validateImage(file);
            if (imageError) {
              photoInput.value = "";
              showError(imageError);
              return;
            }

            const reader = new FileReader();
            reader.onload = function () {
              photoPreview.style.backgroundImage = `url('${String(reader.result || "")}')`;
              photoPreview.classList.add("has-image");
              if (photoInitials) {
                photoInitials.textContent = "";
              }
              removeFlag.value = "0";
              clearError();
            };
            reader.readAsDataURL(file);
          });
        }

        if (photoClear) {
          photoClear.addEventListener("click", function () {
            if (photoInput) {
              photoInput.value = "";
            }
            photoPreview.style.backgroundImage = "";
            photoPreview.classList.remove("has-image");
            removeFlag.value = "1";
          });
        }

        form.addEventListener("submit", function (event) {
          const nom = (form.nom.value || "").trim();
          const prenom = (form.prenom.value || "").trim();
          const email = (form.email.value || "").trim();
          const telephone = (form.telephone.value || "").trim().replace(/\s+/g, "");
          const password = form.mot_de_passe.value || "";

          const namePattern = /^[A-Za-z�-��-��-�\s'-]{2,60}$/;
          const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
          const phonePattern = /^\+?[0-9]{8,15}$/;

          if (!namePattern.test(nom)) {
            event.preventDefault();
            showError("Le nom doit contenir 2 a 60 caracteres alphabetiques.");
            return;
          }

          if (!namePattern.test(prenom)) {
            event.preventDefault();
            showError("Le prenom doit contenir 2 a 60 caracteres alphabetiques.");
            return;
          }

          if (!emailPattern.test(email)) {
            event.preventDefault();
            showError("Adresse e-mail invalide.");
            return;
          }

          if (!phonePattern.test(telephone)) {
            event.preventDefault();
            showError("Le telephone doit contenir entre 8 et 15 chiffres.");
            return;
          }

          if (password.length > 0 && password.length < 6) {
            event.preventDefault();
            showError("Le nouveau mot de passe doit contenir au moins 6 caracteres.");
            return;
          }

          const file = photoInput && photoInput.files ? photoInput.files[0] : null;
          if (file) {
            const imageError = validateImage(file);
            if (imageError) {
              event.preventDefault();
              showError(imageError);
              return;
            }
          }

          clearError();
        });
      })();
    </script>
    <script src="assets/js/main.js"></script>
  </body>
</html>
