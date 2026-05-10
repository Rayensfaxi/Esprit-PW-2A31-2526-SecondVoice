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

function rememberLatestFaceHash(int $userId, array $hashes, string $currentHash): array
{
    $hashes = normalizeHashList($hashes);
    $currentHash = trim($currentHash);
    if (preg_match('/^[01]{64}$/', $currentHash) !== 1) {
        throw new InvalidArgumentException('Empreinte invalide.');
    }

    $hashes = array_values(array_filter($hashes, static function (string $hash) use ($currentHash): bool {
        return $hash !== $currentHash;
    }));
    $hashes[] = $currentHash;
    $hashes = array_slice($hashes, -3);
    saveTemplateHashes($userId, $hashes);

    return loadTemplateHashes($userId);
}

function getFaceEnrollCode(): string
{
    if (class_exists('Config') && method_exists('Config', 'getFaceEnrollCode')) {
        return Config::getFaceEnrollCode();
    }

    $env = getenv('SECONDVOICE_FACE_ENROLL_CODE');
    return $env !== false ? trim($env) : '';
}

function normalizeEnrollCode(string $code): string
{
    $code = strtoupper(trim($code));
    $code = str_replace(["\xE2\x80\x90", "\xE2\x80\x91", "\xE2\x80\x92", "\xE2\x80\x93", "\xE2\x80\x94", "\xE2\x88\x92"], '-', $code);
    return preg_replace('/[^A-Z0-9]/', '', $code) ?? '';
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
$faceEnrollCode = getFaceEnrollCode();
$enrollAllowedFromProfile = !empty($pending['allow_face_enroll']);
$enrollRequiresCode = !$enrollAllowedFromProfile;
$allowFaceEnroll = $enrollAllowedFromProfile || (!$hasEnrolledFace && $faceEnrollCode !== '');

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
        $faceDetectorMethod = strtolower(trim((string) ($_POST['face_detector'] ?? '')));
        if (!in_array($faceDetectorMethod, ['native', 'heuristic'], true)) {
            throw new RuntimeException('Detection visage invalide. Centrez votre visage puis reessayez.');
        }

        $facePresent = (string) ($_POST['face_present'] ?? '') === '1';
        if (!$facePresent) {
            throw new RuntimeException('Aucun visage detecte. Centrez votre visage puis reessayez.');
        }

        $binary = parseSnapshotDataUrl($snapshot);
        $currentHash = resolveSnapshotHash($binary, $clientHash);

        if ($action === 'enroll') {
            if (!$allowFaceEnroll) {
                throw new RuntimeException("Enregistrement visage refuse pendant le login.");
            }
            if ($enrollRequiresCode) {
                $submittedEnrollCode = trim((string) ($_POST['face_enroll_code'] ?? ''));
                if ($faceEnrollCode === '' || !hash_equals(normalizeEnrollCode($faceEnrollCode), normalizeEnrollCode($submittedEnrollCode))) {
                    throw new RuntimeException("Code d'enrolement visage invalide.");
                }
            }

            $savedHashes = rememberLatestFaceHash($adminId, $templateHashes, $currentHash);
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
                throw new RuntimeException("Aucun visage admin enregistre. L'enregistrement doit etre lance depuis le profil admin.");
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
            $maxDistance = 32;
            if ($distance > $maxDistance) {
                throw new RuntimeException('Visage non reconnu. Verification echouee. Reessayez avec le visage bien centre et le meme eclairage.');
            }

            $templateHashes = rememberLatestFaceHash($adminId, $templateHashes, $currentHash);
            if ($templateHashes === []) {
                throw new RuntimeException("Enregistrement visage echoue. Verifiez les permissions du dossier storage/security.");
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
                'Connexion admin validee par reconnaissance faciale. Capture du login enregistree.'
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
    <link rel="stylesheet" href="assets/css/style.css?v=<?= h((string) @filemtime(__DIR__ . '/assets/css/style.css')) ?>" />
    <link rel="stylesheet" href="assets/css/auth.css?v=<?= h((string) @filemtime(__DIR__ . '/assets/css/auth.css')) ?>" />
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
          <span class="theme-glyph theme-icon-moon" data-theme-glyph aria-hidden="true"></span>
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
                <?php if ($hasEnrolledFace): ?>
                  <?= h('Profil facial actif (' . count($templateHashes) . '/3 captures). Chaque login valide met a jour la capture.') ?>
                <?php elseif ($allowFaceEnroll): ?>
                  Initialisation visage admin autorisee avec code d'enrolement.
                <?php else: ?>
                  Aucun visage admin enregistre. Enrolement bloque pendant le login.
                <?php endif; ?>
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

          <form class="auth-form" id="face-verify-form" method="post" action="admin-face-verify.php" data-has-enrolled="<?= $hasEnrolledFace ? '1' : '0' ?>" data-allow-enroll="<?= $allowFaceEnroll ? '1' : '0' ?>">
            <input type="hidden" name="action" id="face-action" value="verify" />
            <input type="hidden" name="face_snapshot" id="face-snapshot" value="" />
            <input type="hidden" name="face_hash" id="face-hash" value="" />
            <input type="hidden" name="face_present" id="face-present" value="0" />
            <input type="hidden" name="face_detector" id="face-detector" value="0" />
            <canvas id="face-canvas" style="display:none;"></canvas>

            <?php if ($allowFaceEnroll && $enrollRequiresCode): ?>
              <input class="field" type="password" name="face_enroll_code" placeholder="Code d'enrolement admin" autocomplete="off" />
            <?php endif; ?>

            <p id="face-feedback" class="auth-feedback <?= $feedbackType === 'error' ? 'error' : ($feedbackType === 'success' ? 'success' : '') ?>"><?= h($feedback) ?></p>

            <div style="display:flex; gap:12px; flex-wrap:wrap;">
              <?php if ($allowFaceEnroll): ?>
                <button class="btn btn-secondary" id="face-enroll-btn" type="button"><?= $hasEnrolledFace ? 'Reinitialiser mon visage' : 'Initialiser visage admin' ?></button>
              <?php endif; ?>
              <button class="btn btn-primary" id="face-verify-btn" type="button" <?= $hasEnrolledFace ? '' : 'disabled' ?>>Se connecter avec mon visage</button>
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
            themeGlyph.classList.toggle("theme-icon-moon", theme === "light");
            themeGlyph.classList.toggle("theme-icon-sun", theme !== "light");
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
        const facePresentField = document.getElementById("face-present");
        const faceDetectorField = document.getElementById("face-detector");
        const feedback = document.getElementById("face-feedback");
        const note = document.getElementById("face-note");
        const enrollBtn = document.getElementById("face-enroll-btn");
        const verifyBtn = document.getElementById("face-verify-btn");
        const retryBtn = document.getElementById("face-retry-btn");
        if (!form || !actionField || !video || !canvas || !snapshotField || !faceHashField || !facePresentField || !faceDetectorField || !feedback || !note || !verifyBtn || !retryBtn) return;

        let stream = null;
        let hasEnrolledFace = form.dataset.hasEnrolled === "1";
        const allowFaceEnroll = form.dataset.allowEnroll === "1";

        function setError(message) {
          feedback.textContent = message;
          feedback.classList.add("error");
        }

        function clearError() {
          feedback.textContent = "";
          feedback.classList.remove("error");
        }

        function setFaceActionsDisabled(disabled) {
          verifyBtn.disabled = disabled || !hasEnrolledFace;
          if (enrollBtn) {
            enrollBtn.disabled = disabled;
          }
        }

        async function startCamera() {
          note.textContent = "Demande d'acces camera...";
          faceDetectorField.value = "0";
          facePresentField.value = "0";

          if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            note.textContent = "Camera non supportee sur ce navigateur.";
            setError("Votre navigateur ne supporte pas la camera.");
            setFaceActionsDisabled(true);
            return;
          }

          try {
            if (stream) {
              stream.getTracks().forEach((t) => t.stop());
              stream = null;
            }
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" }, audio: false });
            video.srcObject = stream;
            setFaceActionsDisabled(false);
            note.textContent = "Camera active. Centrez votre visage puis capturez.";
          } catch (error) {
            note.textContent = "Impossible d'acceder a la camera.";
            setError("Acces camera refuse ou indisponible.");
            setFaceActionsDisabled(true);
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

        function hasUsableFaceBox(face, width, height) {
          const box = face && face.boundingBox;
          if (!box) {
            return false;
          }

          const boxWidth = Number(box.width || 0);
          const boxHeight = Number(box.height || 0);
          const centerX = Number(box.x || 0) + boxWidth / 2;
          const centerY = Number(box.y || 0) + boxHeight / 2;
          return boxWidth >= width * 0.14
            && boxHeight >= height * 0.14
            && boxWidth <= width * 0.9
            && boxHeight <= height * 0.9
            && centerX >= width * 0.16
            && centerX <= width * 0.84
            && centerY >= height * 0.12
            && centerY <= height * 0.88;
        }

        function hasSkinTone(r, g, b) {
          const max = Math.max(r, g, b);
          const min = Math.min(r, g, b);
          const y = r * 0.299 + g * 0.587 + b * 0.114;
          const cb = 128 - r * 0.168736 - g * 0.331264 + b * 0.5;
          const cr = 128 + r * 0.5 - g * 0.418688 - b * 0.081312;

          return r > 45
            && g > 35
            && b > 18
            && max - min > 14
            && y > 45
            && y < 238
            && cb >= 75
            && cb <= 138
            && cr >= 132
            && cr <= 182
            && r >= g * 0.82
            && r > b * 1.02;
        }

        function detectFaceHeuristic(sourceCanvas) {
          const size = 96;
          const crop = Math.min(sourceCanvas.width, sourceCanvas.height);
          const sx = Math.max(0, Math.round((sourceCanvas.width - crop) / 2));
          const sy = Math.max(0, Math.round((sourceCanvas.height - crop) / 2));
          const work = document.createElement("canvas");
          work.width = size;
          work.height = size;
          const wctx = work.getContext("2d", { willReadFrequently: true });
          if (!wctx) {
            throw new Error("CANVAS_ERROR");
          }

          wctx.drawImage(sourceCanvas, sx, sy, crop, crop, 0, 0, size, size);
          const pixels = wctx.getImageData(0, 0, size, size).data;
          let headArea = 0;
          let skin = 0;
          let outsideArea = 0;
          let outsideSkin = 0;
          let darkFeatures = 0;
          let detail = 0;
          let detailSamples = 0;
          let minX = size;
          let minY = size;
          let maxX = -1;
          let maxY = -1;

          for (let y = 0; y < size; y++) {
            for (let x = 0; x < size; x++) {
              const index = (y * size + x) * 4;
              const r = pixels[index];
              const g = pixels[index + 1];
              const b = pixels[index + 2];
              const gray = r * 0.299 + g * 0.587 + b * 0.114;
              const inHeadZone = Math.pow((x - 48) / 37, 2) + Math.pow((y - 48) / 43, 2) <= 1;
              const skinTone = hasSkinTone(r, g, b);

              if (inHeadZone) {
                headArea++;
                if (skinTone) {
                  skin++;
                  minX = Math.min(minX, x);
                  minY = Math.min(minY, y);
                  maxX = Math.max(maxX, x);
                  maxY = Math.max(maxY, y);
                }

                const inFeatureBand = x >= 22 && x <= 74 && ((y >= 28 && y <= 56) || (y >= 56 && y <= 76));
                if (inFeatureBand && gray < 98 && (r + g + b) < 330) {
                  darkFeatures++;
                }

                if (x > 0) {
                  const left = index - 4;
                  const leftGray = pixels[left] * 0.299 + pixels[left + 1] * 0.587 + pixels[left + 2] * 0.114;
                  detail += Math.abs(gray - leftGray);
                  detailSamples++;
                }
                if (y > 0) {
                  const up = index - size * 4;
                  const upGray = pixels[up] * 0.299 + pixels[up + 1] * 0.587 + pixels[up + 2] * 0.114;
                  detail += Math.abs(gray - upGray);
                  detailSamples++;
                }
              } else {
                outsideArea++;
                if (skinTone) {
                  outsideSkin++;
                }
              }
            }
          }

          const skinRatio = headArea > 0 ? skin / headArea : 0;
          const outsideSkinRatio = outsideArea > 0 ? outsideSkin / outsideArea : 0;
          const darkFeatureRatio = headArea > 0 ? darkFeatures / headArea : 0;
          const averageDetail = detailSamples > 0 ? detail / detailSamples : 0;
          const bboxWidth = maxX >= minX ? maxX - minX + 1 : 0;
          const bboxHeight = maxY >= minY ? maxY - minY + 1 : 0;
          const centerX = maxX >= minX ? (minX + maxX) / 2 : 0;
          const centerY = maxY >= minY ? (minY + maxY) / 2 : 0;

          const detected = skinRatio >= 0.07
            && skinRatio <= 0.58
            && outsideSkinRatio <= Math.max(0.1, skinRatio * 1.25)
            && bboxWidth >= 18
            && bboxHeight >= 20
            && centerX >= 27
            && centerX <= 69
            && centerY >= 24
            && centerY <= 76
            && darkFeatureRatio >= 0.008
            && darkFeatureRatio <= 0.34
            && averageDetail >= 3;

          return { detected, method: "heuristic" };
        }

        async function detectFacePresence(sourceCanvas) {
          const heuristicResult = detectFaceHeuristic(sourceCanvas);

          if ("FaceDetector" in window) {
            try {
              const detector = new FaceDetector({ fastMode: true, maxDetectedFaces: 1 });
              const faces = await detector.detect(sourceCanvas);
              const nativeDetected = faces.some((face) => hasUsableFaceBox(face, sourceCanvas.width, sourceCanvas.height));
              return { detected: nativeDetected && heuristicResult.detected, method: "native" };
            } catch (error) {
              return heuristicResult;
            }
          }

          return heuristicResult;
        }

        async function submitWithAction(action) {
          clearError();
          if (action === "enroll" && !allowFaceEnroll) {
            setError("Enregistrement visage refuse pendant le login.");
            return;
          }
          if (action === "verify" && !hasEnrolledFace) {
            setError("Aucun visage admin enregistre.");
            return;
          }
          try {
            const dataUrl = captureToDataUrl();
            note.textContent = "Detection du visage...";
            const faceDetection = await detectFacePresence(canvas);
            faceDetectorField.value = faceDetection.method;
            if (!faceDetection.detected) {
              facePresentField.value = "0";
              note.textContent = "Aucun visage detecte. Centrez votre visage puis capturez.";
              setError("Aucun visage detecte. Le login est bloque.");
              return;
            }

            const hash = buildClientDHash64(canvas);
            actionField.value = action;
            snapshotField.value = dataUrl;
            faceHashField.value = hash;
            facePresentField.value = "1";
            faceDetectorField.value = faceDetection.method;
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
