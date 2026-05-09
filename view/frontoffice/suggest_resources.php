<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');

function resources_ai_respond_json(array $payload, int $statusCode = 200): void
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

function resource_ai_normalize(string $value): string
{
    $value = strtolower($value);
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    return $converted !== false ? strtolower($converted) : $value;
}

function resource_ai_category(string $text): string
{
    $categories = [
        'concert' => ['concert', 'musique', 'musical', 'festival', 'scene', 'chanteur', 'dj', 'spectacle'],
        'conference' => ['conference', 'seminaire', 'formation', 'atelier', 'workshop', 'debat', 'forum', 'business'],
        'hackathon' => ['hackathon', 'code', 'coding', 'programmation', 'tech', 'startup', 'innovation', 'ia', 'web'],
        'sport' => ['sport', 'match', 'tournoi', 'course', 'football', 'basket', 'tennis', 'fitness', 'marathon'],
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
    resources_ai_respond_json([
        'success' => false,
        'message' => 'Le nom de l evenement est necessaire pour suggerer des ressources IA.',
    ], 400);
}

$category = resource_ai_category(resource_ai_normalize($name . ' ' . $location));

$suggestions = [
    'concert' => [
        'title' => 'Ressources techniques du concert',
        'description' => 'Materiels et regles recommandes pour assurer une ambiance musicale fluide, securisee et professionnelle.',
        'materials' => [
            ['name' => 'Micros sans fil', 'quantity' => 4, 'description' => 'Prevoir des micros testes avant l ouverture.'],
            ['name' => 'Haut-parleurs', 'quantity' => 2, 'description' => 'Installer une sonorisation adaptee a la salle.'],
            ['name' => 'Eclairage scene', 'quantity' => 6, 'description' => 'Creer une ambiance visuelle confortable.'],
            ['name' => 'Barriere securite', 'quantity' => 8, 'description' => 'Organiser les zones public et scene.'],
        ],
        'rules' => [
            ['name' => 'Respect du volume sonore', 'description' => 'Maintenir un niveau sonore adapte au lieu.'],
            ['name' => 'Acces scene reserve', 'description' => 'Limiter l acces a l equipe autorisee.'],
            ['name' => 'Controle des entrees', 'description' => 'Verifier les inscriptions ou billets a l entree.'],
        ],
    ],
    'conference' => [
        'title' => 'Ressources de conference',
        'description' => 'Elements recommandes pour une conference claire, calme et bien organisee.',
        'materials' => [
            ['name' => 'Projecteur video', 'quantity' => 1, 'description' => 'Verifier la compatibilite avant les presentations.'],
            ['name' => 'WiFi haut debit', 'quantity' => 1, 'description' => 'Garantir une connexion stable aux intervenants.'],
            ['name' => 'Tables accueil', 'quantity' => 3, 'description' => 'Faciliter l orientation et l enregistrement.'],
            ['name' => 'Badges visiteurs', 'quantity' => 50, 'description' => 'Identifier facilement les participants.'],
        ],
        'rules' => [
            ['name' => 'Silence obligatoire', 'description' => 'Respecter les prises de parole et les presentations.'],
            ['name' => 'Telephones silencieux', 'description' => 'Mettre les appareils en mode silencieux.'],
            ['name' => 'Questions organisees', 'description' => 'Poser les questions pendant les temps prevus.'],
        ],
    ],
    'hackathon' => [
        'title' => 'Ressources hackathon',
        'description' => 'Configuration conseillee pour favoriser le developpement, la collaboration et le prototypage rapide.',
        'materials' => [
            ['name' => 'Ordinateurs equipes', 'quantity' => 10, 'description' => 'Prevoir des machines ou espaces de travail disponibles.'],
            ['name' => 'Internet stable', 'quantity' => 1, 'description' => 'Assurer une connexion fiable pendant tout l evenement.'],
            ['name' => 'Multiprises solides', 'quantity' => 8, 'description' => 'Alimenter les postes de travail en securite.'],
            ['name' => 'Tableaux idees', 'quantity' => 3, 'description' => 'Aider les equipes a structurer leurs solutions.'],
        ],
        'rules' => [
            ['name' => 'Acces 24h autorise', 'description' => 'Permettre aux equipes de travailler selon le planning defini.'],
            ['name' => 'Respect des equipes', 'description' => 'Favoriser l entraide et les echanges constructifs.'],
            ['name' => 'Prototype original', 'description' => 'Presenter une solution creee pendant le hackathon.'],
        ],
    ],
    'sport' => [
        'title' => 'Ressources sportives',
        'description' => 'Materiels et consignes utiles pour encadrer un evenement sportif dynamique et securise.',
        'materials' => [
            ['name' => 'Ballons officiels', 'quantity' => 4, 'description' => 'Prevoir du materiel adapte a l activite.'],
            ['name' => 'Trousse secours', 'quantity' => 2, 'description' => 'Disposer d un kit de premiers soins accessible.'],
            ['name' => 'Dossards equipes', 'quantity' => 30, 'description' => 'Identifier les participants rapidement.'],
            ['name' => 'Bouteilles eau', 'quantity' => 50, 'description' => 'Assurer l hydratation des participants.'],
        ],
        'rules' => [
            ['name' => 'Fair play obligatoire', 'description' => 'Respecter les adversaires, arbitres et organisateurs.'],
            ['name' => 'Echauffement requis', 'description' => 'Commencer par une preparation physique adaptee.'],
            ['name' => 'Consignes securite', 'description' => 'Suivre les instructions de l equipe encadrante.'],
        ],
    ],
    'general' => [
        'title' => 'Ressources recommandees',
        'description' => 'Suggestions generales pour organiser un evenement fluide, confortable et bien encadre.',
        'materials' => [
            ['name' => 'Tables accueil', 'quantity' => 2, 'description' => 'Installer un point d accueil clair.'],
            ['name' => 'Chaises invitees', 'quantity' => 30, 'description' => 'Prevoir assez de places assises.'],
            ['name' => 'Signaletique salle', 'quantity' => 5, 'description' => 'Orienter les participants facilement.'],
            ['name' => 'Kit organisation', 'quantity' => 1, 'description' => 'Regrouper badges, stylos et documents utiles.'],
        ],
        'rules' => [
            ['name' => 'Ponctualite demandee', 'description' => 'Respecter les horaires annonces.'],
            ['name' => 'Respect des lieux', 'description' => 'Garder l espace propre et organise.'],
            ['name' => 'Suivre les consignes', 'description' => 'Respecter les indications de l equipe organisatrice.'],
        ],
    ],
];

resources_ai_respond_json([
    'success' => true,
    'category' => $category,
    'resources_title' => $suggestions[$category]['title'],
    'resources_description' => $suggestions[$category]['description'],
    'materials' => $suggestions[$category]['materials'],
    'rules' => $suggestions[$category]['rules'],
]);
