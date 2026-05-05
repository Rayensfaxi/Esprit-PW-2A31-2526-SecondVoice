<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../controller/ActivityLogger.php';

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function resolveFaceTemplateDir(): string
{
    $candidates = [
        __DIR__ . '/../../storage/security',
        __DIR__ . '/assets/media/security'
    ];

    foreach ($candidates as $dir) {
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
                continue;
            }
        }

        if (is_writable($dir)) {
            return $dir;
        }
    }

    throw new RuntimeException("Aucun dossier d'empreintes faciales accessible en ecriture.");
}

function getFaceTemplatePath(int $userId): string
{
    $dir = resolveFaceTemplateDir();
    return $dir . '/admin_face_' . $userId . '.json';
}

function parseSnapshotDataUrl(string $snapshot): string
{
    if (!preg_match('#^data:image/(png|jpeg);base64,(.+)$#', $snapshot, $matches)) {
        throw new InvalidArgumentException('Capture faciale invalide.');
    }

    $base64 = (string) ($matches[2] ?? '');
    $binary = base64_decode($base64, true);
    if ($binary === false) {
        throw new InvalidArgumentException('Capture faciale invalide.');
    }

    $size = strlen($binary);
    if ($size < 10000 || $size > 3 * 1024 * 1024) {
        throw new InvalidArgumentException('Capture faciale invalide.');
    }

    return $binary;
}

function buildDHash64FromJpegData(string $binary): string
{
    if (!function_exists('imagecreatefromstring') || !function_exists('imagecreatetruecolor')) {
        throw new RuntimeException("Extension GD manquante sur le serveur (activez GD dans PHP).");
    }

    $img = @imagecreatefromstring($binary);
    if ($img === false) {
        throw new InvalidArgumentException('Image faciale invalide.');
    }

    $srcW = imagesx($img);
    $srcH = imagesy($img);
    if ($srcW < 120 || $srcH < 120) {
        imagedestroy($img);
        throw new InvalidArgumentException('Image faciale trop petite.');
    }

    $work = imagecreatetruecolor(9, 8);
    imagecopyresampled($work, $img, 0, 0, 0, 0, 9, 8, $srcW, $srcH);

    $bits = '';
    for ($y = 0; $y < 8; $y++) {
        for ($x = 0; $x < 8; $x++) {
            $left = imagecolorat($work, $x, $y);
            $right = imagecolorat($work, $x + 1, $y);

            $lr = ($left >> 16) & 0xFF;
            $lg = ($left >> 8) & 0xFF;
            $lb = $left & 0xFF;
            $rr = ($right >> 16) & 0xFF;
            $rg = ($right >> 8) & 0xFF;
            $rb = $right & 0xFF;

            $leftGray = (int) round(($lr * 0.299) + ($lg * 0.587) + ($lb * 0.114));
            $rightGray = (int) round(($rr * 0.299) + ($rg * 0.587) + ($rb * 0.114));

            $bits .= $leftGray >= $rightGray ? '1' : '0';
        }
    }

    imagedestroy($work);
    imagedestroy($img);

    return $bits;
}

function resolveSnapshotHash(string $binary, string $clientHash): string
{
    $clientHash = trim($clientHash);
    if ($clientHash !== '' && preg_match('/^[01]{64}$/', $clientHash) === 1) {
        return $clientHash;
    }

    return buildDHash64FromJpegData($binary);
}

function hammingDistance64(string $a, string $b): int
{
    if (strlen($a) !== 64 || strlen($b) !== 64) {
        throw new InvalidArgumentException('Empreintes invalides.');
    }

    $distance = 0;
    for ($i = 0; $i < 64; $i++) {
        if ($a[$i] !== $b[$i]) {
            $distance++;
        }
    }
    return $distance;
}

function normalizeHashList(array $hashes): array
{
    $normalized = [];
    foreach ($hashes as $hash) {
        $value = trim((string) $hash);
        if (preg_match('/^[01]{64}$/', $value) !== 1) {
            continue;
        }
        if (!in_array($value, $normalized, true)) {
            $normalized[] = $value;
        }
    }

    return $normalized;
}

function loadTemplateHashes(int $userId): array
{
    $path = getFaceTemplatePath($userId);
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return [];
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return [];
    }

    $hashes = $json['hashes'] ?? null;
    if (!is_array($hashes)) {
        $legacyHash = trim((string) ($json['hash'] ?? ''));
        $hashes = $legacyHash !== '' ? [$legacyHash] : [];
    }

    return normalizeHashList($hashes);
}

function saveTemplateHashes(int $userId, array $hashes): void
{
    $hashes = normalizeHashList($hashes);
    if ($hashes === []) {
        throw new InvalidArgumentException('Empreinte invalide.');
    }

    $payload = [
        'algo' => 'dhash64',
        'hashes' => $hashes,
        'hash' => $hashes[0],
        'updated_at' => date('c')
    ];

    $path = getFaceTemplatePath($userId);
    $written = file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    if ($written === false) {
        throw new RuntimeException("Impossible d'enregistrer l'empreinte faciale.");
    }
}

$pending = $_SESSION['pending_admin_user'] ?? null;
if (!is_array($pending) || (int) ($pending['id'] ?? 0) <= 0) {
    header('Location: login.php?status=auth_required');
    exit;
}

$pendingRole = strtolower((string) ($pending['role'] ?? ''));
if ($pendingRole !== 'admin') {
    unset($_SESSION['pending_admin_user']);
    header('Location: login.php?status=forbidden');
    exit;
}

$adminId = (int) $pending['id'];
$feedback = '';
$feedbackType = '';
$templateHashes = loadTemplateHashes($adminId);
$hasEnrolledFace = count($templateHashes) > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strtolower(trim((string) ($_POST['action'] ?? 'verify')));

    if ($action === 'cancel') {
        unset($_SESSION['pending_admin_user']);
        header('Location: login.php?status=logged_out');
        exit;
    }

    try {
        $snapshot = trim((string) ($_POST['face_snapshot'] ?? ''));
        $clientHash = (string) ($_POST['face_hash'] ?? '');
        $binary = parseSnapshotDataUrl($snapshot);
        $currentHash = resolveSnapshotHash($binary, $clientHash);

        if ($action === 'enroll') {
            $templateHashes[] = $currentHash;
            $templateHashes = normalizeHashList($templateHashes);
            $templateHashes = array_slice($templateHashes, -3);
            saveTemplateHashes($adminId, $templateHashes);

            $savedHashes = loadTemplateHashes($adminId);
            if ($savedHashes === []) {
                throw new RuntimeException("Enregistrement visage echoue. Verifiez les permissions du dossier storage/security.");
            }
            $hasEnrolledFace = true;
            $templateHashes = $savedHashes;
            $count = count($templateHashes);
            $feedback = $count < 3
                ? "Capture enregistree ($count/3). Ajoutez encore " . (3 - $count) . " capture(s) pour une reconnaissance plus stable."
                : 'Visage admin enregistre avec succes (3 captures). Vous pouvez verifier.';
            $feedbackType = 'success';
        } elseif ($action === 'verify') {
            if (!$hasEnrolledFace || $templateHashes === []) {
                throw new RuntimeException("Aucun visage admin enregistre. Cliquez d'abord sur Enregistrer mon visage.");
            }

            $distance = null;
            foreach ($templateHashes as $templateHash) {
                $d = hammingDistance64($templateHash, $currentHash);
                if ($distance === null || $d < $distance) {
                    $distance = $d;
                }
            }
            if ($distance === null) {
                throw new RuntimeException('Empreintes invalides.');
            }

            // Seuil plus tolérant pour accepter de légères variations caméra/lumière/position.
            $maxDistance = 22;
            if ($distance > $maxDistance) {
                throw new RuntimeException('Visage non reconnu. Verification echouee.');
            }

            $_SESSION['user_id'] = (int) $pending['id'];
            $_SESSION['user_role'] = (string) ($pending['role'] ?? 'admin');
            $_SESSION['user_nom'] = (string) ($pending['nom'] ?? '');
            $_SESSION['user_prenom'] = (string) ($pending['prenom'] ?? '');
            $_SESSION['user_email'] = (string) ($pending['email'] ?? '');
            $_SESSION['admin_face_verified_at'] = date('c');
            unset($_SESSION['pending_admin_user']);

            ActivityLogger::log(
                (int) $_SESSION['user_id'],
                'Connexion',
                'Connexion admin validee par reconnaissance faciale (matching biometrique).'
            );

            header('Location: ../backoffice/index.php');
            exit;
        } else {
            throw new InvalidArgumentException('Action invalide.');
        }
    } catch (Throwable $exception) {
        $feedback = $exception->getMessage();
        $feedbackType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Verification faciale admin | SecondVoice</title>
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
    <link rel="stylesheet" href="assets/css/auth.css" />
    <style>
      .face-video-wrap {
        margin-top: 14px;
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 16px;
        padding: 12px;
        background: rgba(10, 12, 36, 0.55);
      }
      .face-video {
        width: 100%;
        border-radius: 12px;
        display: block;
      }
      .face-note {
        margin-top: 10px;
        color: #a9b0c4;
        font-size: 0.95rem;
      }
      .auth-feedback.success {
        color: #87e7a0;
      }
    </style>
  </head>
  <body class="auth-screen">
    <main class="auth-stage">
      <div class="auth-theme-row">
        <button class="icon-btn auth-theme-toggle" type="button" data-theme-toggle aria-label="Changer le theme">
          <span class="theme-glyph" data-theme-glyph aria-hidden="true">â˜¾</span>
        </button>
      </div>

      <a class="auth-brand" href="index.php">
        <img src="assets/media/secondvoice-logo.png" alt="SecondVoice logo" />
      </a>

      <section class="user-panel">
        <div class="user-panel-head">
          <div class="user-panel-intro">
            <div class="user-avatar">SV</div>
            <div>
              <p class="user-panel-title">Verification admin</p>
              <p class="user-modal-copy">
                <?= $hasEnrolledFace ? ('Profil facial actif (' . count($templateHashes) . '/3 captures). Verifiez votre identite.') : 'Aucun visage enregistre. Faites un enrolement initial.' ?>
              </p>
            </div>
          </div>
          <form method="post" action="admin-face-verify.php">
            <input type="hidden" name="action" value="cancel" />
            <button class="icon-btn user-close" type="submit" aria-label="Annuler">X</button>
          </form>
        </div>

        <section class="auth-panel is-active">
          <h3 class="auth-title">Reconnaissance faciale</h3>
          <p class="auth-helper">Autorisez la camera, centrez votre visage, puis capturez.</p>

          <div class="face-video-wrap">
            <video id="face-video" class="face-video" autoplay playsinline muted></video>
            <p id="face-note" class="face-note">Initialisation de la camera...</p>
          </div>

          <form class="auth-form" id="face-verify-form" method="post" action="admin-face-verify.php" data-has-enrolled="<?= $hasEnrolledFace ? '1' : '0' ?>">
            <input type="hidden" name="action" id="face-action" value="<?= $hasEnrolledFace ? 'verify' : 'enroll' ?>" />
            <input type="hidden" name="face_snapshot" id="face-snapshot" value="" />
            <input type="hidden" name="face_hash" id="face-hash" value="" />
            <canvas id="face-canvas" style="display:none;"></canvas>

            <p id="face-feedback" class="auth-feedback <?= $feedbackType === 'error' ? 'error' : ($feedbackType === 'success' ? 'success' : '') ?>"><?= h($feedback) ?></p>

            <div style="display:flex; gap:12px; flex-wrap:wrap;">
              <button class="btn btn-secondary" id="face-enroll-btn" type="button"><?= $hasEnrolledFace ? 'Ajouter une capture visage' : 'Enregistrer mon visage' ?></button>
              <button class="btn btn-primary" id="face-verify-btn" type="button" <?= $hasEnrolledFace ? '' : 'disabled' ?>>Verifier mon visage</button>
              <button class="btn btn-secondary" id="face-retry-btn" type="button">Relancer camera</button>
            </div>
          </form>
        </section>
      </section>
    </main>

    <script>
      (function () {
        const root = document.documentElement;
        const themeToggle = document.querySelector("[data-theme-toggle]");
        const themeGlyph = document.querySelector("[data-theme-glyph]");

        function applyTheme(theme) {
          root.dataset.theme = theme;
          if (themeToggle) {
            themeToggle.setAttribute("aria-label", theme === "light" ? "Activer le mode sombre" : "Activer le mode clair");
          }
          if (themeGlyph) {
            themeGlyph.textContent = theme === "light" ? "â˜€" : "â˜¾";
          }
        }

        applyTheme(root.dataset.theme || "dark");
        if (themeToggle) {
          themeToggle.addEventListener("click", function () {
            const nextTheme = root.dataset.theme === "light" ? "dark" : "light";
            localStorage.setItem("theme", nextTheme);
            applyTheme(nextTheme);
          });
        }

        const form = document.getElementById("face-verify-form");
        const actionField = document.getElementById("face-action");
        const video = document.getElementById("face-video");
        const canvas = document.getElementById("face-canvas");
        const snapshotField = document.getElementById("face-snapshot");
        const faceHashField = document.getElementById("face-hash");
        const feedback = document.getElementById("face-feedback");
        const note = document.getElementById("face-note");
        const enrollBtn = document.getElementById("face-enroll-btn");
        const verifyBtn = document.getElementById("face-verify-btn");
        const retryBtn = document.getElementById("face-retry-btn");
        if (!form || !actionField || !video || !canvas || !snapshotField || !faceHashField || !feedback || !note || !verifyBtn || !retryBtn) return;

        let stream = null;
        let hasEnrolledFace = form.dataset.hasEnrolled === "1";

        function setError(message) {
          feedback.textContent = message;
          feedback.classList.add("error");
        }

        function clearError() {
          feedback.textContent = "";
          feedback.classList.remove("error");
        }

        async function startCamera() {
          note.textContent = "Demande d'acces camera...";

          if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            note.textContent = "Camera non supportee sur ce navigateur.";
            setError("Votre navigateur ne supporte pas la camera.");
            return;
          }

          try {
            if (stream) {
              stream.getTracks().forEach((t) => t.stop());
              stream = null;
            }
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" }, audio: false });
            video.srcObject = stream;
            note.textContent = "Camera active. Centrez votre visage puis capturez.";
          } catch (error) {
            note.textContent = "Impossible d'acceder a la camera.";
            setError("Acces camera refuse ou indisponible.");
          }
        }

        function captureToDataUrl() {
          if (!stream) {
            throw new Error("CAMERA_NOT_READY");
          }

          const width = video.videoWidth || 0;
          const height = video.videoHeight || 0;
          if (width < 240 || height < 240) {
            throw new Error("LOW_RESOLUTION");
          }

          canvas.width = width;
          canvas.height = height;
          const ctx = canvas.getContext("2d");
          if (!ctx) {
            throw new Error("CANVAS_ERROR");
          }
          ctx.drawImage(video, 0, 0, width, height);
          return canvas.toDataURL("image/jpeg", 0.92);
        }

        function buildClientDHash64(sourceCanvas) {
          const work = document.createElement("canvas");
          work.width = 9;
          work.height = 8;
          const wctx = work.getContext("2d", { willReadFrequently: true });
          if (!wctx) {
            throw new Error("CANVAS_ERROR");
          }

          wctx.drawImage(sourceCanvas, 0, 0, 9, 8);
          const pixels = wctx.getImageData(0, 0, 9, 8).data;
          let bits = "";

          for (let y = 0; y < 8; y++) {
            for (let x = 0; x < 8; x++) {
              const iL = (y * 9 + x) * 4;
              const iR = (y * 9 + (x + 1)) * 4;

              const lGray = Math.round(pixels[iL] * 0.299 + pixels[iL + 1] * 0.587 + pixels[iL + 2] * 0.114);
              const rGray = Math.round(pixels[iR] * 0.299 + pixels[iR + 1] * 0.587 + pixels[iR + 2] * 0.114);
              bits += lGray >= rGray ? "1" : "0";
            }
          }

          return bits;
        }

        function submitWithAction(action) {
          clearError();
          if (action === "verify" && !hasEnrolledFace) {
            setError("Enregistrez d'abord votre visage.");
            return;
          }
          try {
            const dataUrl = captureToDataUrl();
            const hash = buildClientDHash64(canvas);
            actionField.value = action;
            snapshotField.value = dataUrl;
            faceHashField.value = hash;
            form.submit();
          } catch (error) {
            if (error && error.message === "CAMERA_NOT_READY") {
              setError("Camera non initialisee.");
            } else if (error && error.message === "LOW_RESOLUTION") {
              setError("Image camera insuffisante. Reessayez.");
            } else {
              setError("Erreur de capture image.");
            }
          }
        }

        if (enrollBtn) {
          enrollBtn.addEventListener("click", function () {
            submitWithAction("enroll");
          });
        }

        verifyBtn.addEventListener("click", function () {
          submitWithAction("verify");
        });

        if (!hasEnrolledFace) {
          verifyBtn.disabled = true;
        }

        retryBtn.addEventListener("click", function () {
          startCamera();
        });

        window.addEventListener("beforeunload", function () {
          if (stream) {
            stream.getTracks().forEach((t) => t.stop());
          }
        });

        startCamera();
      })();
    </script>
  </body>
</html>
