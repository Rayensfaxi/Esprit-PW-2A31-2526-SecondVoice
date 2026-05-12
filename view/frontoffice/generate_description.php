<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input') ?: '', true);
$input = is_array($input) ? $input : [];

$name = trim((string) ($input['name'] ?? ''));
$location = trim((string) ($input['location'] ?? ''));

if ($name === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Le titre de l evenement est obligatoire.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$description = 'Participez a "' . $name . '"';
if ($location !== '') {
    $description .= ' a ' . $location;
}
$description .= '. Cet evenement est organise pour favoriser les echanges, le partage d idees et la collaboration.';

echo json_encode([
    'success' => true,
    'description' => $description,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

