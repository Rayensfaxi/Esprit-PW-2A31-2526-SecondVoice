<?php
declare(strict_types=1);

session_start();

function generateCaptchaCode(int $length = 5): string
{
    $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $maxIndex = strlen($alphabet) - 1;
    $code = '';

    for ($i = 0; $i < $length; $i++) {
        $code .= $alphabet[random_int(0, $maxIndex)];
    }

    return $code;
}

$refresh = isset($_GET['refresh']) && (string) $_GET['refresh'] === '1';
$current = (string) ($_SESSION['client_login_image_captcha_code'] ?? '');

if ($refresh || $current === '' || strlen($current) !== 5) {
    $_SESSION['client_login_image_captcha_code'] = generateCaptchaCode(5);
}

$code = (string) ($_SESSION['client_login_image_captcha_code'] ?? '');
if ($code === '') {
    $code = generateCaptchaCode(5);
    $_SESSION['client_login_image_captcha_code'] = $code;
}

header('Content-Type: image/svg+xml; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$width = 280;
$height = 92;

$lineElements = '';
for ($i = 0; $i < 7; $i++) {
    $x1 = random_int(0, $width);
    $y1 = random_int(0, $height);
    $x2 = random_int(0, $width);
    $y2 = random_int(0, $height);
    $lineElements .= '<line x1="' . $x1 . '" y1="' . $y1 . '" x2="' . $x2 . '" y2="' . $y2 . '" stroke="rgba(37,99,235,0.25)" stroke-width="' . random_int(1, 2) . '" />';
}

$dotElements = '';
for ($i = 0; $i < 28; $i++) {
    $cx = random_int(0, $width);
    $cy = random_int(0, $height);
    $r = random_int(1, 2);
    $dotElements .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="rgba(15,23,42,0.20)" />';
}

$textElements = '';
$chars = str_split($code);
$baseX = 24;
foreach ($chars as $idx => $char) {
    $x = $baseX + ($idx * 46) + random_int(-2, 2);
    $y = 60 + random_int(-6, 6);
    $rotate = random_int(-16, 16);
    $textElements .= '<text x="' . $x . '" y="' . $y . '" transform="rotate(' . $rotate . ' ' . $x . ' ' . $y . ')" font-family="Verdana, Geneva, sans-serif" font-size="34" font-weight="700" fill="#0f172a">' . $char . '</text>';
}

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="Captcha image">';
echo '<defs>';
echo '<linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">';
echo '<stop offset="0%" stop-color="#eff6ff" />';
echo '<stop offset="100%" stop-color="#dbeafe" />';
echo '</linearGradient>';
echo '</defs>';
echo '<rect x="0" y="0" width="' . $width . '" height="' . $height . '" rx="12" fill="url(#bg)" />';
echo $lineElements;
echo $dotElements;
echo $textElements;
echo '</svg>';
