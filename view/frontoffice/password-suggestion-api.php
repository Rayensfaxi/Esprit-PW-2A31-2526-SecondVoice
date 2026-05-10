<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'error' => 'Methode non autorisee.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function pickPasswordChar(string $chars): string
{
    return $chars[random_int(0, strlen($chars) - 1)];
}

function shufflePasswordChars(array $chars): array
{
    for ($i = count($chars) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
    }

    return $chars;
}

try {
    $lower = 'abcdefghjkmnpqrstuvwxyz';
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $digits = '23456789';
    $symbols = '!@#$%?*_-';
    $all = $lower . $upper . $digits . $symbols;
    $length = 16;

    $chars = [
        pickPasswordChar($lower),
        pickPasswordChar($upper),
        pickPasswordChar($digits),
        pickPasswordChar($symbols)
    ];

    while (count($chars) < $length) {
        $chars[] = pickPasswordChar($all);
    }

    $password = implode('', shufflePasswordChars($chars));

    echo json_encode([
        'ok' => true,
        'password' => $password,
        'length' => strlen($password)
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Generation impossible.'
    ], JSON_UNESCAPED_UNICODE);
}
