<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

const IDENTITY_OCR_MAX_BYTES = 4194304;

function respondJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function failOcr(string $message, int $statusCode = 400): void
{
    respondJson([
        'ok' => false,
        'error' => $message
    ], $statusCode);
}

function resolveOcrTempDir(): string
{
    $baseCandidates = [
        getenv('TEMP') ?: '',
        getenv('TMP') ?: '',
        sys_get_temp_dir(),
        __DIR__ . '/../../storage'
    ];

    $dir = '';
    foreach ($baseCandidates as $baseDir) {
        $baseDir = rtrim((string) $baseDir, '\\/');
        if ($baseDir !== '' && is_dir($baseDir) && is_writable($baseDir)) {
            $dir = $baseDir . DIRECTORY_SEPARATOR . 'secondvoice-ocr';
            break;
        }
    }

    if ($dir === '') {
        throw new RuntimeException("Dossier OCR inaccessible.");
    }

    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("Dossier OCR inaccessible.");
    }

    if (!is_writable($dir)) {
        throw new RuntimeException("Dossier OCR non inscriptible.");
    }

    return $dir;
}

function validateAndStoreUpload(): string
{
    if (!isset($_FILES['identity_image']) || !is_array($_FILES['identity_image'])) {
        throw new InvalidArgumentException("Ajoutez une image de carte d'identite ou passeport.");
    }

    $file = $_FILES['identity_image'];
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        throw new InvalidArgumentException("Ajoutez une image de carte d'identite ou passeport.");
    }
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        throw new InvalidArgumentException('Image trop volumineuse (max 4 Mo).');
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Erreur pendant l'upload de l'image.");
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > IDENTITY_OCR_MAX_BYTES) {
        throw new InvalidArgumentException('Image trop volumineuse (max 4 Mo).');
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('Fichier upload invalide.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmpName);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($extensions[$mime])) {
        throw new InvalidArgumentException('Format refuse. Utilisez JPG, PNG ou WEBP.');
    }

    $targetPath = resolveOcrTempDir() . DIRECTORY_SEPARATOR . 'identity_' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($tmpName, $targetPath)) {
        throw new RuntimeException("Impossible de preparer l'image OCR.");
    }

    return $targetPath;
}

function validateAndStoreJsonImage(): string
{
    $rawBody = file_get_contents('php://input');
    if (is_string($rawBody)) {
        $rawBody = preg_replace('/^\xEF\xBB\xBF/', '', $rawBody) ?? $rawBody;
    }
    $payload = is_string($rawBody) ? json_decode($rawBody, true) : null;
    if (!is_array($payload)) {
        throw new InvalidArgumentException("Image OCR invalide.");
    }

    $imageData = trim((string) ($payload['image_data'] ?? ''));
    if ($imageData === '') {
        throw new InvalidArgumentException("Ajoutez une image de carte d'identite ou passeport.");
    }

    if (!preg_match('#^data:(image/(?:jpeg|png|webp));base64,([A-Za-z0-9+/=\r\n]+)$#', $imageData, $matches)) {
        throw new InvalidArgumentException('Format refuse. Utilisez JPG, PNG ou WEBP.');
    }

    $mime = (string) $matches[1];
    $binary = base64_decode(str_replace(["\r", "\n"], '', (string) $matches[2]), true);
    if ($binary === false) {
        throw new InvalidArgumentException("Image OCR invalide.");
    }

    $size = strlen($binary);
    if ($size <= 0 || $size > IDENTITY_OCR_MAX_BYTES) {
        throw new InvalidArgumentException('Image trop volumineuse (max 4 Mo).');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = (string) $finfo->buffer($binary);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($extensions[$mime]) || $detectedMime !== $mime) {
        throw new InvalidArgumentException('Format refuse. Utilisez JPG, PNG ou WEBP.');
    }

    $targetPath = resolveOcrTempDir() . DIRECTORY_SEPARATOR . 'identity_' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (file_put_contents($targetPath, $binary, LOCK_EX) === false) {
        throw new RuntimeException("Impossible de preparer l'image OCR.");
    }

    return $targetPath;
}

function validateAndStoreImage(): string
{
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($contentType, 'application/json')) {
        return validateAndStoreJsonImage();
    }

    return validateAndStoreUpload();
}

function runCommand(string $command, int $timeoutSeconds = 30): array
{
    $output = [];
    $code = 1;
    @exec($command . ' 2>&1', $output, $code);
    $text = normalizeProcessText(implode("\n", $output));

    return [
        'code' => (int) $code,
        'stdout' => $text,
        'stderr' => ''
    ];
}

function normalizeProcessText(string $text): string
{
    if ($text === '' || mb_check_encoding($text, 'UTF-8')) {
        return $text;
    }

    foreach (['Windows-1252', 'CP850', 'ISO-8859-1'] as $encoding) {
        $converted = @mb_convert_encoding($text, 'UTF-8', $encoding);
        if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }
    }

    return $text;
}

function findTesseractBinary(): string
{
    $envPath = trim((string) (getenv('SECONDVOICE_TESSERACT_PATH') ?: ''));
    $candidates = [
        $envPath,
        'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
        'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe'
    ];

    $whereOutput = [];
    $whereCode = 1;
    @exec('where.exe tesseract 2>NUL', $whereOutput, $whereCode);
    if ($whereCode === 0) {
        foreach ($whereOutput as $line) {
            $candidates[] = trim((string) $line);
        }
    }

    foreach ($candidates as $candidate) {
        if ($candidate !== '' && is_file($candidate) && is_readable($candidate)) {
            return $candidate;
        }
    }

    return '';
}

function ocrWithTesseract(string $imagePath): string
{
    $binary = findTesseractBinary();
    if ($binary === '') {
        return '';
    }

    $command = escapeshellarg($binary) . ' ' . escapeshellarg($imagePath) . ' stdout --psm 6 -l fra+eng';
    $result = runCommand($command, 30);
    if ((int) $result['code'] !== 0) {
        return '';
    }

    return trim((string) $result['stdout']);
}

function ocrWithWindows(string $imagePath): string
{
    $script = __DIR__ . '/../../controller/windows-ocr.ps1';
    if (!is_file($script)) {
        return '';
    }

    $powershell = getenv('SystemRoot')
        ? rtrim((string) getenv('SystemRoot'), '\\/') . '\\System32\\WindowsPowerShell\\v1.0\\powershell.exe'
        : 'powershell.exe';
    if (!is_file($powershell)) {
        $powershell = 'powershell.exe';
    }

    $command = escapeshellarg($powershell)
        . ' -NoProfile -ExecutionPolicy Bypass -File '
        . escapeshellarg($script)
        . ' -ImagePath '
        . escapeshellarg($imagePath);
    $result = runCommand($command, 35);
    if ((int) $result['code'] !== 0) {
        return '';
    }

    return trim((string) $result['stdout']);
}

function ocrImageToText(string $imagePath): string
{
    $text = ocrWithTesseract($imagePath);
    if ($text !== '') {
        return $text;
    }

    $text = ocrWithWindows($imagePath);
    if ($text !== '') {
        return $text;
    }

    throw new RuntimeException('OCR echoue. Image floue, texte illisible ou moteur OCR indisponible.');
}

function normalizeMatchText(string $value): string
{
    $value = mb_strtoupper($value, 'UTF-8');
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($ascii) && $ascii !== '') {
        $value = $ascii;
    }
    $value = str_replace(['0', '1', '5'], ['O', 'I', 'S'], $value);
    return preg_replace('/[^A-Z0-9< ]+/', ' ', $value) ?? '';
}

function cleanNameCandidate(string $value): string
{
    $value = str_replace('<', ' ', $value);
    $value = preg_replace(
        '/\b(NOM|NOMS|PRENOM|PRENOMS|SURNAME|SURNAMES|GIVEN|NAMES|FIRST|LAST|FAMILY|FORENAMES|DATE|BIRTH|DOB|NAISSANCE|NATIONALITE|NATIONALITY|SEXE|SEX|PASSEPORT|PASSPORT|CARTE|IDENTITE|IDENTITY|REPUBLIC|REPUBLIQUE|FRANCE|FRANCAIS|FRANCAISE|FRENCH|EUROPEENNE|EUROPEAN|UNION|LIBERTE|EGALITE|FRATERNITE|ETAT|STATE|TUNISIE|TUNISIA|TUNISIENNE|NUMERO|NUMBER|EXPIRY|EXPIRE|DELIVRANCE|SIGNATURE)\b/i',
        ' ',
        $value
    ) ?? $value;
    $value = preg_replace('/[^\p{L}\' -]/u', ' ', $value) ?? $value;
    $value = preg_replace('/\s+/', ' ', trim($value, " -'")) ?? '';

    if ($value === '' || mb_strlen($value, 'UTF-8') < 2 || preg_match('/\d/', $value)) {
        return '';
    }
    if (count(preg_split('/\s+/', $value) ?: []) > 4) {
        return '';
    }

    return mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
}

function mrzNameToText(string $value): string
{
    return cleanNameCandidate(str_replace('<', ' ', $value));
}

function extractFromMrz(array $lines): array
{
    foreach ($lines as $line) {
        $mrz = strtoupper(preg_replace('/[^A-Z0-9<]/', '', $line) ?? '');
        if (preg_match('/^P<[A-Z0-9<]{3}([A-Z<]{2,})<<([A-Z<]{2,})/', $mrz, $matches)) {
            return [
                'nom' => mrzNameToText($matches[1]),
                'prenom' => mrzNameToText($matches[2])
            ];
        }
        if (preg_match('/^([A-Z][A-Z<]{2,})<<([A-Z][A-Z<]{2,})/', $mrz, $matches)) {
            return [
                'nom' => mrzNameToText($matches[1]),
                'prenom' => mrzNameToText($matches[2])
            ];
        }
    }

    return ['nom' => '', 'prenom' => ''];
}

function valueAfterLabel(array $normalizedLines, int $index, string $pattern): string
{
    $line = $normalizedLines[$index] ?? '';
    if (preg_match($pattern, $line, $matches)) {
        $candidate = cleanNameCandidate((string) ($matches[1] ?? ''));
        if ($candidate !== '') {
            return $candidate;
        }
    }

    for ($offset = 1; $offset <= 2; $offset++) {
        $candidate = cleanNameCandidate((string) ($normalizedLines[$index + $offset] ?? ''));
        if ($candidate !== '') {
            return $candidate;
        }
    }

    return '';
}

function hasNameLabel(string $line, string $type): bool
{
    if ($type === 'nom') {
        return preg_match('/\b(NOM|NOMS|SURNAME|SURNAMES|FAMILY NAME|LAST NAME|NOM DE FAMILLE)\b/', $line) === 1;
    }

    return preg_match('/\b(PRENOM|PRENOMS|GIVEN NAME|GIVEN NAMES|FIRST NAME|FORENAMES)\b/', $line) === 1;
}

function extractFromJoinedText(array $normalizedLines): array
{
    $joined = preg_replace('/\s+/', ' ', implode(' ', $normalizedLines)) ?? '';
    $nom = '';
    $prenom = '';

    if (preg_match('/\b(?:NOM DE FAMILLE|NOM|NOMS|SURNAME|SURNAMES|FAMILY NAME|LAST NAME)\b\s+([A-Z <\'-]{2,}?)(?=\s+\b(?:PRENOM|PRENOMS|GIVEN NAME|GIVEN NAMES|FIRST NAME|FORENAMES|DATE|NAISSANCE|BIRTH|DOB|SEXE|SEX|NATIONALITE|NATIONALITY|NUMERO|NUMBER|CIN|ID)\b|$)/', $joined, $matches)) {
        $nom = cleanNameCandidate((string) ($matches[1] ?? ''));
    }

    if (preg_match('/\b(?:PRENOM|PRENOMS|GIVEN NAME|GIVEN NAMES|FIRST NAME|FORENAMES)\b\s+([A-Z <\'-]{2,}?)(?=\s+\b(?:NOM|NOMS|SURNAME|SURNAMES|FAMILY NAME|LAST NAME|DATE|NAISSANCE|BIRTH|DOB|SEXE|SEX|NATIONALITE|NATIONALITY|NUMERO|NUMBER|CIN|ID)\b|$)/', $joined, $matches)) {
        $prenom = cleanNameCandidate((string) ($matches[1] ?? ''));
    }

    return [
        'nom' => $nom,
        'prenom' => $prenom
    ];
}

function isDocumentNoiseLine(string $line): bool
{
    if ($line === '' || str_contains($line, '<<')) {
        return true;
    }

    return preg_match('/\b(REPUBLIQUE|REPUBLIC|FRANCE|FRANCAIS|FRANCAISE|FRENCH|EUROPEENNE|EUROPEAN|UNION|LIBERTE|EGALITE|FRATERNITE|ETAT|STATE|TUNISIE|TUNISIA|TUNISIENNE|CARTE|IDENTITE|IDENTITY|PASSEPORT|PASSPORT|NATIONAL|NATIONALE|MINISTERE|MINISTRY|GOUVERNEMENT|GOVERNMENT|DATE|NAISSANCE|BIRTH|LIEU|PLACE|SEXE|SEX|DELIVRANCE|DELIVERED|EXPIR|SIGNATURE|NUMERO|NUMBER|CIN|ID|TYPE|CODE|AUTORITE|AUTHORITY)\b/', $line) === 1;
}

function extractNameTokenCandidates(array $normalizedLines): array
{
    $text = preg_replace('/\s+/', ' ', implode(' ', $normalizedLines)) ?? '';
    $text = preg_replace(
        '/\b(REPUBLIQUE|REPUBLIC|FRANCE|FRANCAIS|FRANCAISE|FRENCH|EUROPEENNE|EUROPEAN|UNION|LIBERTE|EGALITE|FRATERNITE|ETAT|STATE|TUNISIE|TUNISIA|TUNISIENNE|CARTE|IDENTITE|IDENTITY|PASSEPORT|PASSPORT|NATIONAL|NATIONALE|MINISTERE|MINISTRY|GOUVERNEMENT|GOVERNMENT|DATE|NAISSANCE|BIRTH|LIEU|PLACE|SEXE|SEX|DELIVRANCE|DELIVERED|EXPIRY|EXPIRE|EXPIRATION|SIGNATURE|NUMERO|NUMBER|CIN|ID|TYPE|CODE|AUTORITE|AUTHORITY|NOM|NOMS|PRENOM|PRENOMS|SURNAME|SURNAMES|GIVEN|FIRST|LAST|FAMILY|NAME|NAMES)\b/',
        ' ',
        $text
    ) ?? $text;
    $text = preg_replace('/\b(DE|DU|DES|D|LA|LE|LES|EL|AL|THE|OF|AND|ET)\b/', ' ', $text) ?? $text;
    $text = preg_replace('/\d+/', ' ', $text) ?? $text;
    $text = preg_replace('/[^A-Z \'-]/', ' ', $text) ?? $text;

    $candidates = [];
    foreach (preg_split('/\s+/', trim($text)) ?: [] as $token) {
        $token = trim((string) $token, " -'");
        if ($token === '' || strlen($token) < 2 || strlen($token) > 30) {
            continue;
        }
        if (!preg_match('/^[A-Z][A-Z\'-]*$/', $token)) {
            continue;
        }
        $candidate = cleanNameCandidate($token);
        if ($candidate !== '' && !in_array($candidate, $candidates, true)) {
            $candidates[] = $candidate;
        }
    }

    return $candidates;
}

function extractFromLikelyNameLines(array $normalizedLines, string $currentNom, string $currentPrenom): array
{
    $candidates = [];
    foreach ($normalizedLines as $line) {
        $line = preg_replace('/\s+/', ' ', trim($line)) ?? '';
        if ($line === '' || isDocumentNoiseLine($line) || hasNameLabel($line, 'nom') || hasNameLabel($line, 'prenom')) {
            continue;
        }
        if (preg_match('/\d/', $line) || preg_match('/[^A-Z \'-]/', $line)) {
            continue;
        }

        $candidate = cleanNameCandidate($line);
        if ($candidate === '' || mb_strlen($candidate, 'UTF-8') > 40) {
            continue;
        }
        if (!in_array($candidate, $candidates, true)) {
            $candidates[] = $candidate;
        }
    }

    foreach (extractNameTokenCandidates($normalizedLines) as $candidate) {
        if (!in_array($candidate, $candidates, true)) {
            $candidates[] = $candidate;
        }
    }

    $nom = $currentNom;
    $prenom = $currentPrenom;
    foreach ($candidates as $candidate) {
        if ($nom === '') {
            $nom = $candidate;
            continue;
        }
        if ($prenom === '' && strcasecmp($candidate, $nom) !== 0) {
            $prenom = $candidate;
            break;
        }
    }

    return [
        'nom' => $nom,
        'prenom' => $prenom
    ];
}

function extractIdentityNames(string $text): array
{
    $rawLines = preg_split('/\R+/', $text) ?: [];
    $lines = [];
    foreach ($rawLines as $line) {
        $line = trim((string) $line);
        if ($line !== '') {
            $lines[] = $line;
        }
    }

    if ($lines === []) {
        throw new RuntimeException('OCR echoue. Aucun texte detecte.');
    }

    $mrz = extractFromMrz($lines);
    $nom = $mrz['nom'];
    $prenom = $mrz['prenom'];
    $normalizedLines = array_map('normalizeMatchText', $lines);

    foreach ($normalizedLines as $index => $line) {
        if ($nom === '' && hasNameLabel($line, 'nom')) {
            $nom = valueAfterLabel($normalizedLines, $index, '/\b(?:NOM DE FAMILLE|NOM|NOMS|SURNAME|SURNAMES|LAST NAME|FAMILY NAME)\b\s*([A-Z <\'-]{2,}?)(?=\s+\b(?:PRENOM|PRENOMS|GIVEN NAMES|GIVEN NAME|FIRST NAME|FORENAMES)\b|$)/');
        }
        if ($prenom === '' && hasNameLabel($line, 'prenom')) {
            $prenom = valueAfterLabel($normalizedLines, $index, '/\b(?:PRENOM|PRENOMS|GIVEN NAMES|GIVEN NAME|FIRST NAME|FORENAMES)\b\s*([A-Z <\'-]{2,}?)(?=\s+\b(?:NOM|NOMS|SURNAME|LAST NAME|FAMILY NAME|DATE|BIRTH|DOB|NAISSANCE|NATIONALITE|NATIONALITY|SEXE|SEX)\b|$)/');
        }
    }

    if ($nom === '' || $prenom === '') {
        $joined = extractFromJoinedText($normalizedLines);
        $nom = $nom !== '' ? $nom : $joined['nom'];
        $prenom = $prenom !== '' ? $prenom : $joined['prenom'];
    }

    if ($nom === '' || $prenom === '') {
        $likely = extractFromLikelyNameLines($normalizedLines, $nom, $prenom);
        $nom = $likely['nom'];
        $prenom = $likely['prenom'];
    }

    if ($nom === '' || $prenom === '') {
        throw new RuntimeException('OCR echoue. Nom ou prenom introuvable.');
    }

    return [
        'nom' => $nom,
        'prenom' => $prenom
    ];
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    failOcr('Methode non autorisee.', 405);
}

$storedPath = '';
$responseStatus = 200;
$responsePayload = [];
try {
    $storedPath = validateAndStoreImage();
    $text = ocrImageToText($storedPath);
    $identity = extractIdentityNames($text);

    $responsePayload = [
        'ok' => true,
        'nom' => $identity['nom'],
        'prenom' => $identity['prenom']
    ];
} catch (InvalidArgumentException $exception) {
    $responseStatus = 400;
    $responsePayload = [
        'ok' => false,
        'error' => $exception->getMessage()
    ];
} catch (Throwable $exception) {
    $responseStatus = 422;
    $responsePayload = [
        'ok' => false,
        'error' => $exception->getMessage()
    ];
} finally {
    if ($storedPath !== '' && is_file($storedPath)) {
        @unlink($storedPath);
    }
}

respondJson($responsePayload, $responseStatus);
