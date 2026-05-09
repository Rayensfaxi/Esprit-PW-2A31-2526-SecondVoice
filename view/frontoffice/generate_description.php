<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');

function ai_respond_json(array $payload, int $statusCode = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$input = json_decode(file_get_contents('php://input') ?: '', true);
$input = is_array($input) ? $input : $_POST;

$name = trim((string) ($input['name'] ?? ''));
$location = trim((string) ($input['location'] ?? ''));
$theme = trim((string) ($input['theme'] ?? ''));

function ai_normalize(string $value): string
{
    $value = strtolower($value);
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    return $converted !== false ? strtolower($converted) : $value;
}

function ai_pick_category(string $text): string
{
    $categories = [
        'concert' => ['concert', 'musique', 'musical', 'festival', 'scene', 'chanteur', 'dj', 'spectacle'],
        'conference' => ['conference', 'seminaire', 'formation', 'atelier', 'workshop', 'debat', 'forum', 'business'],
        'hackathon' => ['hackathon', 'code', 'coding', 'programmation', 'tech', 'startup', 'innovation', 'ia', 'web'],
        'sport' => ['sport', 'match', 'tournoi', 'course', 'football', 'basket', 'tennis', 'fitness', 'marathon'],
        'solidarite' => ['solidarite', 'association', 'benevole', 'don', 'collecte', 'social', 'caritatif', 'aide'],
        'culture' => ['culture', 'art', 'exposition', 'theatre', 'cinema', 'lecture', 'poesie', 'livre'],
    ];

    foreach ($categories as $category => $keywords) {
        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return $category;
            }
        }
    }

    return 'general';
}

if ($name === '') {
    ai_respond_json([
        'success' => false,
        'message' => 'Ajoutez d abord le titre de l evenement pour generer une description IA.',
    ], 400);
}

$category = ai_pick_category(ai_normalize($name . ' ' . $location . ' ' . $theme));
$place = $location !== '' ? ' a ' . $location : '';
$eventName = $name;

$templates = [
    'concert' => [
        "$eventName est une experience musicale immersive$place, concue pour reunir le public autour d une ambiance vibrante et memorable.",
        "Au programme, des performances soigneusement preparees, une atmosphere conviviale et des moments forts qui valorisent la scene artistique.",
        "Cet evenement invite les participants a vivre une soiree rythmee, elegante et accessible, avec une organisation pensee pour le confort et la securite de tous.",
    ],
    'conference' => [
        "$eventName est une rencontre professionnelle$place dediee au partage d idees, d expertises et de retours d experience.",
        "Les participants pourront echanger avec des intervenants qualifies, decouvrir des perspectives utiles et enrichir leur reseau dans un cadre structure.",
        "Cette conference met l accent sur la qualite des contenus, la clarte des interventions et une organisation fluide pour favoriser l apprentissage.",
    ],
    'hackathon' => [
        "$eventName est un rendez-vous technologique$place axe sur la creation, l innovation et la collaboration en equipe.",
        "Les participants seront invites a imaginer des solutions concretes, prototyper rapidement leurs idees et relever des defis stimulants.",
        "L evenement favorise l entraide, la creativite et l esprit startup dans un environnement dynamique adapte aux projets numeriques.",
    ],
    'sport' => [
        "$eventName est un evenement sportif$place pense pour rassembler les participants autour du depassement de soi et du fair-play.",
        "La journee mettra en avant l energie collective, la competition saine et une organisation claire pour accompagner chaque participant.",
        "Que ce soit pour performer, encourager ou partager un moment actif, cet evenement promet une experience engageante et conviviale.",
    ],
    'solidarite' => [
        "$eventName est une initiative solidaire$place qui vise a mobiliser les participants autour d une cause utile et humaine.",
        "L evenement encourage l engagement, la cooperation et les actions concretes au service de la communaute.",
        "Chaque contribution compte et participe a construire un moment porteur de sens, organise avec attention et bienveillance.",
    ],
    'culture' => [
        "$eventName est un evenement culturel$place qui met en valeur la decouverte, l expression artistique et le dialogue.",
        "Les participants pourront profiter d un programme riche, accessible et concu pour stimuler la curiosite.",
        "Cette rencontre propose une experience soignee, ouverte et inspirante autour de la creativite et du partage.",
    ],
    'general' => [
        "$eventName est un evenement organise avec soin$place pour offrir aux participants une experience claire, utile et conviviale.",
        "Le programme met en avant l echange, la participation active et une ambiance accueillante adaptee a differents profils.",
        "Cette rencontre a ete pensee pour creer de la valeur, faciliter les interactions et garantir un deroulement fluide du debut a la fin.",
    ],
];

ai_respond_json([
    'success' => true,
    'category' => $category,
    'description' => implode(' ', $templates[$category]),
]);
