<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controller/GoalController.php';
require_once __DIR__ . '/../../controller/GuideController.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$role   = strtolower((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'user'));
$userId = (int) $_SESSION['user_id'];

// Normalize: 'agent' is treated as 'assistant'.
if ($role === 'agent') $role = 'assistant';
if (!in_array($role, ['user', 'assistant', 'admin'], true)) $role = 'user';

// === AJAX endpoint ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $msg  = trim((string)($body['message'] ?? ''));
    if ($msg === '') {
        echo json_encode(['ok' => false, 'error' => 'empty']);
        exit;
    }
    $reply = process_message($role, $userId, $msg);
    echo json_encode(array_merge(['ok' => true], $reply), JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── Intent processing ─────────────────────────────────────────────

/**
 * Normalize a string: lowercase + strip French accents + replace apostrophes with spaces.
 * Makes keyword matching tolerant of "où" vs "ou", "à" vs "a", "j'ai" vs "j ai".
 */
function normalize_text(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    return strtr($s, [
        'à'=>'a','á'=>'a','â'=>'a','ä'=>'a','ã'=>'a',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'ó'=>'o','ò'=>'o','ô'=>'o','ö'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'ç'=>'c','ñ'=>'n',
        "'" => ' ', '`' => ' ', '"' => ' ', "?" => ' ', "!" => ' ', "," => ' ', "." => ' ',
    ]);
}

/**
 * Returns true if any of the given keywords appears in the message (after normalization).
 * Single-word keywords require word boundaries; multi-word keywords use substring match.
 */
function kw_match(string $msg, array $keywords): bool {
    $m = ' ' . normalize_text($msg) . ' ';
    foreach ($keywords as $kw) {
        $k = normalize_text($kw);
        if ($k === '') continue;
        if (mb_strpos($k, ' ') !== false) {
            if (str_contains($m, ' ' . $k . ' ') || str_contains($m, $k)) return true;
        } else {
            if (str_contains($m, ' ' . $k . ' ')) return true;
        }
    }
    return false;
}

function process_message(string $role, int $userId, string $msg): array
{
    $m = ' ' . normalize_text($msg) . ' ';

    // Conversation niceties
    if (kw_match($msg, ['bonjour','salut','hello','hi','hey','coucou','bsr','bjr','bonsoir'])) {
        return [
            'message'     => "Bonjour ! 👋 Que puis-je faire pour vous aujourd'hui ?",
            'suggestions' => suggestions_for($role),
        ];
    }
    if (kw_match($msg, ['merci','thanks','thx','super','parfait'])) {
        return ['message' => "Avec plaisir ! 🌟 Autre chose ?"];
    }
    if (kw_match($msg, ['aide','help','aider','que sais tu','que peux tu','possibilites','options','que fais tu','capacites'])) {
        return [
            'message'     => "Voici ce que je peux faire pour vous :",
            'suggestions' => suggestions_for($role),
        ];
    }

    if ($role === 'user')      return process_user_intent($userId, $m, $msg);
    if ($role === 'assistant') return process_assistant_intent($userId, $m, $msg);
    if ($role === 'admin')     return process_admin_intent($userId, $m, $msg);

    return fallback($role, $msg);
}

function fallback(string $role, string $orig = ''): array
{
    $head = $orig !== ''
        ? "Hmm, je n'ai pas trouvé d'action pour « " . htmlspecialchars(mb_substr($orig, 0, 80)) . " ». Voici ce que je sais faire :"
        : "Je n'ai pas tout à fait compris. Voici ce que je sais faire :";
    return [
        'message'     => $head,
        'suggestions' => suggestions_for($role),
    ];
}

function suggestions_for(string $role): array
{
    if ($role === 'user') {
        return [
            ['icon' => '📊', 'label' => 'Mes demandes'],
            ['icon' => '➕', 'label' => 'Créer une demande'],
            ['icon' => '🎙️', 'label' => 'Echo Me'],
        ];
    }
    if ($role === 'assistant') {
        return [
            ['icon' => '🔴', 'label' => 'Mes urgences'],
            ['icon' => '🕐', 'label' => 'À évaluer'],
            ['icon' => '📈', 'label' => 'Mes stats'],
            ['icon' => '📝', 'label' => 'Modèle guide CV'],
            ['icon' => '🎤', 'label' => 'Modèle guide entretien'],
        ];
    }
    if ($role === 'admin') {
        return [
            ['icon' => '🛡', 'label' => "File d'attente"],
            ['icon' => '📊', 'label' => 'Stats du jour'],
            ['icon' => '⏰', 'label' => 'Demandes anciennes'],
            ['icon' => '🎓', 'label' => 'Top assistants'],
        ];
    }
    return [];
}

// ─── CITIZEN intents ───
function process_user_intent(int $uid, string $m, string $orig): array
{
    // 1) Specific goal status (extract the id first, before generic matches)
    if (preg_match('/#?\b(\d+)\b/u', $orig, $mm) &&
        kw_match($orig, ['statut','etat','ou en est','avancement','progres','progress','status','comment va','comment se passe'])) {
        $id   = (int) $mm[1];
        $ctrl = new GoalController();
        $goal = $ctrl->getGoalById($id);
        if (!$goal || (int)$goal['user_id'] !== $uid) {
            return ['message' => "Je ne trouve pas la demande #$id dans votre compte."];
        }
        $sl    = ['soumis'=>'🕐 Soumis','en_cours'=>'⚡ En cours','termine'=>'✅ Terminé','annule'=>'❌ Annulé'][$goal['status']] ?? $goal['status'];
        $admin = ['en_attente'=>'En attente','valide'=>'Validée','refuse'=>'Refusée'][$goal['admin_validation_status']] ?? '-';
        $asst  = ['en_attente'=>'En attente','accepte'=>'Acceptée','refuse'=>'Refusée'][$goal['assistant_validation_status']] ?? '-';
        return ['message' =>
            "**Demande #$id — " . htmlspecialchars($goal['title']) . "**\n" .
            "Statut : $sl\n" .
            "Admin : $admin · Assistant : $asst" .
            (!empty($goal['admin_comment']) ? "\n\n💬 Admin : « " . htmlspecialchars($goal['admin_comment']) . " »" : '') .
            (!empty($goal['assistant_comment']) ? "\n\n💬 Assistant : « " . htmlspecialchars($goal['assistant_comment']) . " »" : '')
        ];
    }

    // 2) List my goals
    if (kw_match($orig, [
        'mes demandes','mes accompagnements','demandes','accompagnements',
        'liste','lister','voir','afficher','montre','montrer','tableau',
        'quelles sont','combien j ai','combien de demandes','en cours','goals',
        'ce que j ai','j ai quoi'
    ])) {
        $ctrl  = new GoalController();
        $goals = $ctrl->getGoalsByUser($uid);
        $count = count($goals);
        if ($count === 0) {
            return [
                'message'     => "Vous n'avez encore aucune demande. Voulez-vous en créer une ?",
                'suggestions' => [['icon'=>'➕','label'=>'Créer une demande']],
            ];
        }
        $statusLbl = ['soumis'=>'🕐 Soumis','en_cours'=>'⚡ En cours','termine'=>'✅ Terminé','annule'=>'❌ Annulé'];
        $lines = [];
        foreach (array_slice($goals, 0, 5) as $g) {
            $sl = $statusLbl[$g['status']] ?? $g['status'];
            $lines[] = "• #{$g['id']} — " . htmlspecialchars($g['title']) . " — $sl";
        }
        $more = $count > 5 ? "\n\n_(et " . ($count - 5) . " autres dans Mes accompagnements)_" : '';
        return ['message' => "Vous avez **$count** demande(s) :\n\n" . implode("\n", $lines) . $more];
    }

    // 3) Create a new goal
    if (kw_match($orig, [
        'creer','nouvelle demande','nouvelle','soumettre','deposer','ajouter',
        'demarrer','commencer','start','new','faire une demande','postuler','demander','formulaire'
    ])) {
        return ['message' => "Je vous redirige vers le formulaire de création…", 'redirect' => 'service-accompagnement.php', 'redirect_label' => '➕ Ouvrir le formulaire'];
    }

    // 4) Echo Me redirect
    if (kw_match($orig, [
        'echo me','echome','dicter','dictee','vocal','parler','voix','voice',
        'reconnaissance vocale','speech','enregistrer ma voix'
    ])) {
        return ['message' => "Echo Me transforme votre voix en phrases claires 🎙️", 'redirect' => 'echome.php', 'redirect_label' => '🎙️ Ouvrir Echo Me'];
    }

    return fallback('user', $orig);
}

// ─── ASSISTANT intents ───
function process_assistant_intent(int $uid, string $m, string $orig): array
{
    if (kw_match($orig, ['urgent','urgence','urgences','signal','signale','rouge','alerte','priorite haute','important'])) {
        $db = Config::getConnexion();
        $sql = "SELECT g.id, g.title, g.urgency_level, g.urgency_updated_at, u.nom AS user_nom
                FROM goals g
                JOIN utilisateurs u ON g.user_id = u.id
                WHERE g.selected_assistant_id = :uid
                  AND g.urgency_level IS NOT NULL
                  AND g.status NOT IN ('termine', 'annule')
                ORDER BY FIELD(g.urgency_level, 'urgent', 'assistance', 'simple'),
                         g.urgency_updated_at DESC
                LIMIT 6";
        $r = $db->prepare($sql); $r->execute(['uid'=>$uid]);
        $rows = $r->fetchAll();
        if (empty($rows)) {
            return ['message' => "Aucun signal d'urgence en attente. Tout va bien ! 🌿"];
        }
        $emoji = ['simple'=>'🟢','assistance'=>'🟡','urgent'=>'🔴'];
        $lines = [];
        foreach ($rows as $r0) {
            $e = $emoji[$r0['urgency_level']] ?? '📡';
            $lines[] = "• $e #{$r0['id']} — " . htmlspecialchars($r0['title']) . " (" . htmlspecialchars($r0['user_nom']) . ")";
        }
        return ['message' => "**" . count($rows) . " signal(s) à traiter** :\n\n" . implode("\n", $lines),
                'redirect_label' => 'Voir mes missions',
                'redirect' => 'assistant-accompagnements.php'];
    }
    if (kw_match($orig, [
        'a evaluer','evaluer','a traiter','traiter','en attente','attente',
        'nouvelle mission','nouvelles missions','mes missions','missions','queue',
        'validation','a valider','valider','accepter','refuser','decider'
    ])) {
        $db = Config::getConnexion();
        $sql = "SELECT g.id, g.title, u.nom AS user_nom
                FROM goals g
                JOIN utilisateurs u ON g.user_id = u.id
                WHERE g.admin_validation_status = 'valide'
                  AND g.assistant_validation_status = 'en_attente'
                  AND (g.selected_assistant_id = :uid OR g.selected_assistant_id IS NULL)
                ORDER BY g.created_at ASC
                LIMIT 6";
        $r = $db->prepare($sql); $r->execute(['uid'=>$uid]);
        $rows = $r->fetchAll();
        if (empty($rows)) {
            return ['message' => "Aucune mission à évaluer pour le moment. ✨"];
        }
        $lines = [];
        foreach ($rows as $r0) {
            $lines[] = "• #{$r0['id']} — " . htmlspecialchars($r0['title']) . " (" . htmlspecialchars($r0['user_nom']) . ")";
        }
        return ['message' => "**" . count($rows) . " mission(s) à évaluer** :\n\n" . implode("\n", $lines),
                'redirect_label' => 'Aller au tableau',
                'redirect' => 'assistant-accompagnements.php'];
    }
    if (kw_match($orig, [
        'stats','statistique','statistiques','chiffres','combien','resum','resume',
        'performance','score','taux','completion','dashboard','tableau de bord',
        'mes chiffres','mes stats','rapport','bilan'
    ])) {
        $db = Config::getConnexion();
        $sql = "SELECT
                  SUM(CASE WHEN assistant_validation_status='accepte' THEN 1 ELSE 0 END) AS accepted,
                  SUM(CASE WHEN status='termine' THEN 1 ELSE 0 END)                       AS finished,
                  SUM(CASE WHEN status='en_cours' AND assistant_validation_status='accepte' THEN 1 ELSE 0 END) AS active,
                  SUM(CASE WHEN urgency_level IS NOT NULL AND status NOT IN ('termine','annule') THEN 1 ELSE 0 END) AS urgents
                FROM goals
                WHERE selected_assistant_id = :uid";
        $r = $db->prepare($sql); $r->execute(['uid'=>$uid]);
        $s = $r->fetch();
        $accepted = (int)($s['accepted'] ?? 0);
        $finished = (int)($s['finished'] ?? 0);
        $active   = (int)($s['active']   ?? 0);
        $urgents  = (int)($s['urgents']  ?? 0);
        $rate = $accepted > 0 ? round(($finished / $accepted) * 100) : 0;
        return ['message' =>
            "**Vos statistiques** 📈\n\n" .
            "• Acceptées : **$accepted**\n" .
            "• Terminées : **$finished**\n" .
            "• En cours : **$active**\n" .
            "• Signaux d'urgence en cours : **$urgents**\n" .
            "• Taux de complétion : **$rate %**"];
    }
    if (kw_match($orig, ['modele cv','template cv','guide cv','cv','curriculum','resume cv'])) {
        return ['message' =>
            "**Modèle de guide CV** (à personnaliser) 📄\n\n" .
            "**Étape 1 — Diagnostic du CV actuel**\n" .
            "Identifier 2-3 points forts et 2-3 points à améliorer (mise en page, contenu, mots-clés).\n\n" .
            "**Étape 2 — Réécriture des expériences**\n" .
            "Reformuler chaque poste avec : verbe d'action + mission + résultat chiffré.\n\n" .
            "**Étape 3 — Mise en valeur des compétences**\n" .
            "Sélectionner 6-8 compétences clés alignées avec le poste visé.\n\n" .
            "**Étape 4 — Mise en page & lisibilité**\n" .
            "Police lisible, hiérarchie visuelle claire, max 1 page (junior) ou 2 (senior).\n\n" .
            "**Étape 5 — Relecture finale**\n" .
            "Vérifier orthographe, cohérence des dates, format PDF nommé proprement."];
    }
    if (kw_match($orig, ['modele entretien','template entretien','guide entretien','entretien','interview','preparer entretien','simulation entretien'])) {
        return ['message' =>
            "**Modèle de guide Entretien** 🎤\n\n" .
            "**Étape 1 — Recherche sur l'entreprise**\n" .
            "Mission, valeurs, actualités, équipe. Préparer 2-3 questions à poser.\n\n" .
            "**Étape 2 — Méthode STAR**\n" .
            "Préparer 3 anecdotes pro structurées : Situation → Tâche → Action → Résultat.\n\n" .
            "**Étape 3 — Questions classiques**\n" .
            "Préparer : présentation 2 min, points forts/faibles, projet 5 ans, salaire.\n\n" .
            "**Étape 4 — Simulation**\n" .
            "Faire un mock interview oral (Echo Me peut aider à clarifier les réponses).\n\n" .
            "**Étape 5 — Logistique**\n" .
            "Tenue, trajet, documents à apporter, heure d'arrivée."];
    }
    if (kw_match($orig, ['modele lettre','template lettre','guide lettre','lettre','motivation','cover letter','lettre de motivation'])) {
        return ['message' =>
            "**Modèle de guide Lettre de motivation** ✉️\n\n" .
            "**Étape 1 — Décortiquer l'offre**\n" .
            "Lister les 3-4 attentes principales du recruteur.\n\n" .
            "**Étape 2 — Accroche personnelle**\n" .
            "Une phrase qui montre que vous avez compris l'entreprise et son besoin.\n\n" .
            "**Étape 3 — Démontrer la valeur**\n" .
            "Pour chaque attente, donner un exemple concret de votre parcours.\n\n" .
            "**Étape 4 — Conclusion proactive**\n" .
            "Proposer un échange, exprimer la motivation pour CETTE entreprise (pas générique).\n\n" .
            "**Étape 5 — Relecture & format**\n" .
            "Adapter le ton à la culture de l'entreprise. Max 1 page, PDF."];
    }
    return fallback('assistant', $orig);
}

// ─── ADMIN intents ───
function process_admin_intent(int $uid, string $m, string $orig): array
{
    $db = Config::getConnexion();

    if (kw_match($orig, [
        'file','file d attente','en attente','attente','a valider','valider','moder','moderation',
        'queue','pending','inbox','demandes a valider','demandes en attente','validation'
    ])) {
        $sql = "SELECT g.id, g.title, g.type, g.created_at, u.nom AS user_nom
                FROM goals g
                JOIN utilisateurs u ON g.user_id = u.id
                WHERE g.admin_validation_status = 'en_attente'
                ORDER BY g.created_at ASC
                LIMIT 6";
        $rows = $db->query($sql)->fetchAll();
        if (empty($rows)) {
            return ['message' => "✨ Aucune demande en attente de validation. Excellent !"];
        }
        $lines = [];
        foreach ($rows as $r0) {
            $age = round((time() - strtotime($r0['created_at'])) / 86400);
            $tag = $age >= 3 ? " ⚠️ {$age}j" : "";
            $lines[] = "• #{$r0['id']} — " . htmlspecialchars($r0['title']) . " (" . htmlspecialchars($r0['user_nom']) . ")$tag";
        }
        return ['message' => "**" . count($rows) . " demande(s) en attente de validation** :\n\n" . implode("\n", $lines),
                'redirect_label' => 'Aller à la modération',
                'redirect' => '../backoffice/gestion-accompagnements.php'];
    }
    if (kw_match($orig, [
        'stats du jour','aujourd hui','aujourd','today','chiffres','resum','resume',
        'dashboard','tableau de bord','kpi','journee','activite','stats'
    ])) {
        $sqlToday = "SELECT
                       SUM(CASE WHEN DATE(created_at)=CURDATE() THEN 1 ELSE 0 END)                                    AS submitted_today,
                       SUM(CASE WHEN DATE(updated_at)=CURDATE() AND admin_validation_status='valide' THEN 1 ELSE 0 END) AS validated_today,
                       SUM(CASE WHEN admin_validation_status='en_attente' THEN 1 ELSE 0 END)                          AS pending_total,
                       SUM(CASE WHEN status='termine' AND DATE(updated_at)=CURDATE() THEN 1 ELSE 0 END)               AS finished_today
                     FROM goals";
        $s = $db->query($sqlToday)->fetch();
        return ['message' =>
            "**Activité d'aujourd'hui** 📊\n\n" .
            "• Demandes soumises : **" . (int)$s['submitted_today'] . "**\n" .
            "• Demandes validées : **" . (int)$s['validated_today'] . "**\n" .
            "• Accompagnements terminés : **" . (int)$s['finished_today'] . "**\n" .
            "• File d'attente totale : **" . (int)$s['pending_total'] . "**"];
    }
    if (kw_match($orig, [
        'ancien','anciennes','vieille','vieilles','longtemps','depuis longtemps',
        'trois jours','3 jours','old','attente longue','retard','en retard','bloque','bloquees'
    ])) {
        $sql = "SELECT g.id, g.title, g.created_at, u.nom AS user_nom,
                       DATEDIFF(NOW(), g.created_at) AS days
                FROM goals g
                JOIN utilisateurs u ON g.user_id = u.id
                WHERE g.admin_validation_status = 'en_attente'
                  AND g.created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)
                ORDER BY g.created_at ASC
                LIMIT 6";
        $rows = $db->query($sql)->fetchAll();
        if (empty($rows)) {
            return ['message' => "Aucune demande n'attend depuis plus de 3 jours. 🌱"];
        }
        $lines = [];
        foreach ($rows as $r0) {
            $lines[] = "• ⚠️ #{$r0['id']} — " . htmlspecialchars($r0['title']) . " — **{$r0['days']}j** (" . htmlspecialchars($r0['user_nom']) . ")";
        }
        return ['message' => "**" . count($rows) . " demande(s) ouvertes depuis +3 jours** :\n\n" . implode("\n", $lines),
                'redirect_label' => 'Modérer maintenant',
                'redirect' => '../backoffice/gestion-accompagnements.php'];
    }
    if (kw_match($orig, [
        'top assistant','top assistants','meilleur assistant','meilleurs assistants',
        'classement','ranking','best','palmares','top','leaderboard','performance assistant'
    ])) {
        $sql = "SELECT u.id, u.nom, u.prenom,
                       COUNT(g.id) AS handled,
                       SUM(CASE WHEN g.status='termine' THEN 1 ELSE 0 END) AS finished
                FROM utilisateurs u
                LEFT JOIN goals g ON g.selected_assistant_id = u.id
                                  AND g.assistant_validation_status='accepte'
                WHERE LOWER(u.role) IN ('assistant','agent')
                GROUP BY u.id
                ORDER BY finished DESC, handled DESC
                LIMIT 5";
        $rows = $db->query($sql)->fetchAll();
        if (empty($rows)) {
            return ['message' => "Aucun assistant n'a encore d'accompagnement actif."];
        }
        $lines = [];
        $rank = 1;
        $medals = ['🥇','🥈','🥉','4️⃣','5️⃣'];
        foreach ($rows as $r0) {
            $name = trim(($r0['prenom'] ?? '') . ' ' . ($r0['nom'] ?? '')) ?: "Assistant #{$r0['id']}";
            $lines[] = $medals[$rank-1] . ' ' . htmlspecialchars($name) . " — **{$r0['finished']}** terminés sur {$r0['handled']} acceptés";
            $rank++;
        }
        return ['message' => "**Top assistants** 🎓\n\n" . implode("\n", $lines)];
    }
    if (kw_match($orig, [
        'par type','repartit','repartition','categorie','categories','category',
        'breakdown','volumes','distribution','types de demandes'
    ])) {
        $sql = "SELECT type, COUNT(*) AS n FROM goals GROUP BY type ORDER BY n DESC";
        $rows = $db->query($sql)->fetchAll();
        $labels = ['cv'=>'📄 CV','cover_letter'=>'✉️ Lettre','linkedin'=>'🔗 LinkedIn','interview'=>'🎤 Entretien','other'=>'📌 Autre'];
        $lines = [];
        foreach ($rows as $r0) {
            $lbl = $labels[$r0['type']] ?? $r0['type'];
            $lines[] = "• $lbl : **{$r0['n']}**";
        }
        return ['message' => "**Répartition par type**\n\n" . implode("\n", $lines)];
    }
    return fallback('admin', $orig);
}

// ─── Page rendering ─────────────────────────────────────────────────

$roleLabel = ['user'=>'Citoyen', 'assistant'=>'Assistant', 'admin'=>'Admin'][$role];
$roleEmoji = ['user'=>'👤', 'assistant'=>'🎓', 'admin'=>'🛡'][$role];
$initialSuggestions = suggestions_for($role);

// Greeting
$greetings = [
    'user'      => "Bonjour ! Je suis ChatBot 🤖. Demandez-moi de suivre vos demandes, en créer une nouvelle, ou de vous orienter vers Echo Me.",
    'assistant' => "Bonjour Assistant ! Je peux vous montrer vos urgences, vos missions à évaluer, vos stats, ou des modèles de guides (CV, lettre, entretien).",
    'admin'     => "Bonjour Admin ! Je peux vous donner la file d'attente, les stats du jour, les demandes anciennes (>3j) ou le classement des assistants.",
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="theme-color" content="#5046e5" />
  <title>ChatBot (<?= htmlspecialchars($roleLabel) ?>) | SecondVoice</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="icon" type="image/png" sizes="32x32" href="assets/media/favicon-32.png" />
  <style>
    :root {
      --bg: #f0f4ff; --surface: #ffffff; --surface2: #f7f9ff; --border: #e2e8f8;
      --text: #0f1629; --muted: #6b7a9f; --accent: #5046e5; --accent-soft: #eeeeff;
      --shadow: 0 4px 24px rgba(80,70,229,.08); --shadow-lg: 0 16px 48px rgba(80,70,229,.18);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
      background: radial-gradient(1200px 600px at 50% -10%, rgba(80,70,229,.18), transparent 60%), var(--bg);
      color: var(--text); min-height: 100vh;
    }
    .topbar {
      background: var(--surface); border-bottom: 1px solid var(--border);
      padding: 12px 20px; display: flex; align-items: center; justify-content: space-between;
      position: sticky; top: 0; z-index: 50;
    }
    .brand { display: flex; align-items: center; gap: 10px; font-weight: 800; color: var(--accent); text-decoration: none; }
    .brand-mark {
      width: 32px; height: 32px; border-radius: 9px;
      background: linear-gradient(135deg, #5046e5, #3d34d1); color: #fff;
      display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: .78rem;
    }
    .role-pill {
      display: inline-flex; align-items: center; gap: 5px;
      background: var(--accent-soft); color: var(--accent);
      font-size: .75rem; font-weight: 800; padding: 4px 12px; border-radius: 100px;
    }
    .wrap { max-width: 760px; margin: 0 auto; padding: 24px 16px 100px; }
    h1 {
      font-size: 1.7rem; font-weight: 800; margin-bottom: 4px;
      background: linear-gradient(135deg, #0f1629, #5046e5); -webkit-background-clip: text; background-clip: text; color: transparent;
    }
    .lead { color: var(--muted); font-size: .92rem; margin-bottom: 20px; line-height: 1.5; }

    .chat-shell {
      background: var(--surface); border: 1px solid var(--border); border-radius: 22px;
      box-shadow: var(--shadow-lg); overflow: hidden;
      display: flex; flex-direction: column;
      height: calc(100vh - 220px);
      min-height: 480px;
    }
    .chat-header {
      display: flex; align-items: center; gap: 12px;
      padding: 14px 18px;
      background: linear-gradient(135deg, #5046e5, #3d34d1); color: #fff;
    }
    .bot-avatar {
      width: 40px; height: 40px; border-radius: 50%;
      background: rgba(255,255,255,.15); display: flex; align-items: center; justify-content: center;
      font-size: 1.3rem;
    }
    .bot-name { font-weight: 800; }
    .bot-sub { font-size: .72rem; opacity: .88; margin-top: 2px; }

    .chat-body {
      flex: 1; overflow-y: auto; padding: 20px;
      display: flex; flex-direction: column; gap: 12px;
      background: var(--surface2);
    }
    .bubble {
      max-width: 86%; padding: 11px 16px; border-radius: 18px;
      font-size: .94rem; line-height: 1.55; word-wrap: break-word; white-space: pre-wrap;
      animation: bIn .3s cubic-bezier(.2,.9,.3,1.05);
    }
    @keyframes bIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
    .bubble.bot {
      align-self: flex-start; background: var(--surface); color: var(--text);
      border: 1px solid var(--border); border-bottom-left-radius: 6px;
      box-shadow: 0 2px 8px rgba(15,22,41,.04);
    }
    .bubble.user {
      align-self: flex-end; background: linear-gradient(135deg, #5046e5, #3d34d1); color: #fff;
      border-bottom-right-radius: 6px; box-shadow: 0 4px 12px rgba(80,70,229,.32);
    }
    .bubble strong { font-weight: 800; }
    .bubble em     { color: var(--muted); font-style: italic; }
    .typing {
      align-self: flex-start; display: inline-flex; gap: 4px;
      padding: 14px 18px; background: var(--surface);
      border: 1px solid var(--border); border-radius: 18px; border-bottom-left-radius: 6px;
    }
    .typing span { width: 7px; height: 7px; background: var(--accent); border-radius: 50%; opacity: .5; animation: td 1.1s ease-in-out infinite; }
    .typing span:nth-child(2) { animation-delay: .15s; }
    .typing span:nth-child(3) { animation-delay: .3s; }
    @keyframes td { 0%,100% { transform: translateY(0); opacity: .35; } 50% { transform: translateY(-4px); opacity: 1; } }

    .quick-replies {
      display: flex; flex-wrap: wrap; gap: 6px;
      align-self: flex-start; max-width: 86%;
      margin-top: 4px;
    }
    .qr-chip {
      display: inline-flex; align-items: center; gap: 4px;
      padding: 7px 12px; font-family: inherit; font-size: .82rem; font-weight: 700;
      background: var(--surface); color: var(--accent);
      border: 1.5px solid #d6d2ff; border-radius: 100px;
      cursor: pointer; transition: all .15s;
    }
    .qr-chip:hover { background: var(--accent); color: #fff; transform: translateY(-1px); }

    .reply-action {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 8px 14px; margin-top: 8px;
      background: var(--accent); color: #fff;
      font-family: inherit; font-size: .82rem; font-weight: 700;
      border: none; border-radius: 10px; cursor: pointer;
      text-decoration: none;
      transition: filter .15s, transform .12s;
    }
    .reply-action:hover { filter: brightness(1.08); transform: translateY(-1px); }

    .input-bar {
      padding: 12px 14px;
      background: var(--surface); border-top: 1px solid var(--border);
      display: flex; gap: 8px; align-items: flex-end;
    }
    .input-bar textarea {
      flex: 1; min-height: 44px; max-height: 120px;
      padding: 11px 14px; border: 1.5px solid var(--border); border-radius: 14px;
      font-family: inherit; font-size: .95rem; color: var(--text); background: var(--surface2);
      resize: none; outline: none; transition: border-color .15s, box-shadow .15s;
    }
    .input-bar textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(80,70,229,.12); }
    .send-btn {
      padding: 0 22px; height: 44px;
      background: linear-gradient(135deg, #5046e5, #3d34d1); color: #fff;
      border: none; border-radius: 12px; font-family: inherit; font-weight: 800; font-size: .9rem;
      cursor: pointer; box-shadow: 0 6px 16px rgba(80,70,229,.32);
      transition: filter .15s, transform .12s;
      flex-shrink: 0;
    }
    .send-btn:hover { filter: brightness(1.08); transform: translateY(-1px); }
    .send-btn:disabled { opacity: .55; cursor: wait; transform: none; }

    .vd-mic-wrap { flex-shrink: 0; }
  </style>
</head>
<body>

<header class="topbar">
  <a href="<?= $role === 'admin' ? '../backoffice/gestion-accompagnements.php' : 'mes-accompagnements.php' ?>" class="brand">
    <span class="brand-mark">SV</span>
    SecondVoice
  </a>
  <span class="role-pill"><?= $roleEmoji ?> <?= htmlspecialchars($roleLabel) ?></span>
</header>

<main class="wrap">
  <h1>🤖 ChatBot</h1>
  <p class="lead">Tapez une commande ou choisissez une suggestion. Je peux suivre vos données, vous orienter, et exécuter des tâches courantes.</p>

  <div class="chat-shell">
    <div class="chat-header">
      <div class="bot-avatar">🤖</div>
      <div>
        <div class="bot-name">ChatBot</div>
        <div class="bot-sub">Mode <?= htmlspecialchars($roleLabel) ?> · tapez ou cliquez</div>
      </div>
    </div>

    <div class="chat-body" id="chatBody"></div>

    <div class="input-bar">
      <textarea id="msgInput" placeholder="Écrivez votre demande… (ex: « mes urgences », « stats du jour », « modèle guide CV »)" rows="1"></textarea>
      <div class="vd-mic-wrap">
        <button type="button" class="vd-btn" id="vd-msgInput">🎙️</button>
      </div>
      <button type="button" class="send-btn" id="sendBtn">Envoyer</button>
    </div>
  </div>
</main>

<?php require __DIR__ . '/../partials/voice-dictate.php'; ?>

<script>
(function () {
  const ROLE_GREETING = <?= json_encode($greetings[$role], JSON_UNESCAPED_UNICODE) ?>;
  const INITIAL_SUGGESTIONS = <?= json_encode($initialSuggestions, JSON_UNESCAPED_UNICODE) ?>;

  const chatBody = document.getElementById('chatBody');
  const msgInput = document.getElementById('msgInput');
  const sendBtn  = document.getElementById('sendBtn');
  const vdBtn    = document.getElementById('vd-msgInput');

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }
  // Light markdown (bold + line breaks already preserved by white-space:pre-wrap)
  function md(s) {
    return escapeHtml(s)
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/_([^_]+)_/g, '<em>$1</em>');
  }

  function scrollEnd() { chatBody.scrollTop = chatBody.scrollHeight; }

  function addBubble(text, who) {
    const d = document.createElement('div');
    d.className = 'bubble ' + who;
    d.innerHTML = md(text);
    chatBody.appendChild(d);
    scrollEnd();
    return d;
  }

  function addQuickReplies(suggestions) {
    if (!suggestions || !suggestions.length) return;
    const row = document.createElement('div');
    row.className = 'quick-replies';
    suggestions.forEach(s => {
      const c = document.createElement('button');
      c.type = 'button';
      c.className = 'qr-chip';
      c.textContent = (s.icon ? s.icon + ' ' : '') + s.label;
      c.addEventListener('click', () => sendMessage(s.label));
      row.appendChild(c);
    });
    chatBody.appendChild(row);
    scrollEnd();
  }

  function addRedirectAction(label, url, message) {
    const a = document.createElement('a');
    a.href = url;
    a.className = 'reply-action';
    a.textContent = label || 'Ouvrir';
    a.style.alignSelf = 'flex-start';
    chatBody.appendChild(a);
    scrollEnd();
  }

  function showTyping() {
    const t = document.createElement('div');
    t.className = 'typing'; t.id = 'typing';
    t.innerHTML = '<span></span><span></span><span></span>';
    chatBody.appendChild(t); scrollEnd();
  }
  function hideTyping() { const t = document.getElementById('typing'); if (t) t.remove(); }

  async function sendMessage(text) {
    const v = (text || msgInput.value || '').trim();
    if (!v) return;
    addBubble(v, 'user');
    msgInput.value = '';
    sendBtn.disabled = true;

    showTyping();
    try {
      const r = await fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: v }),
      });
      const j = await r.json();
      hideTyping();

      if (!j.ok) {
        addBubble("Désolé, je n'ai pas pu traiter ce message.", 'bot');
        return;
      }
      if (j.message) addBubble(j.message, 'bot');
      if (j.suggestions) addQuickReplies(j.suggestions);
      if (j.redirect) {
        // If the bot says "redirect", present a button rather than auto-navigate.
        addRedirectAction(j.redirect_label || '➡️ Ouvrir', j.redirect, j.message);
      }
    } catch (e) {
      hideTyping();
      addBubble("Erreur réseau : " + e.message, 'bot');
    } finally {
      sendBtn.disabled = false;
      msgInput.focus();
    }
  }

  sendBtn.addEventListener('click', () => sendMessage());
  msgInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });

  // Voice dictation on the message field
  if (window.VoiceDictate && vdBtn) {
    VoiceDictate.attachButton(vdBtn, 'msgInput', { polishUrl: 'echome.php' });
  }

  // Initial greeting + suggestions
  setTimeout(() => {
    showTyping();
    setTimeout(() => {
      hideTyping();
      addBubble(ROLE_GREETING, 'bot');
      addQuickReplies(INITIAL_SUGGESTIONS);
      msgInput.focus();
    }, 600);
  }, 300);
})();
</script>

<?php require __DIR__ . '/../partials/role-switcher.php'; ?>
</body>
</html>
