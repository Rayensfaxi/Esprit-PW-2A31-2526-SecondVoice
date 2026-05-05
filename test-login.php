<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/config.php';

$flashMessage = null;

function redirectToNextIfValid(): void
{
    $next = (string) ($_GET['next'] ?? '');
    if ($next === '') {
        return;
    }

    // Allow only in-app relative routes.
    if ($next[0] === '/' || str_starts_with($next, 'view/')) {
        header('Location: ' . $next);
        exit;
    }
}

function getFirstUserIdByRoles(array $roles, int $fallback = 1): int
{
    try {
        $db = Config::getConnexion();
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $sql = "SELECT id FROM utilisateurs WHERE LOWER(role) IN ($placeholders) ORDER BY id ASC LIMIT 1";
        $req = $db->prepare($sql);
        $req->execute(array_map('strtolower', $roles));
        $id = (int) ($req->fetchColumn() ?: 0);
        return $id > 0 ? $id : $fallback;
    } catch (Throwable $e) {
        return $fallback;
    }
}

if (isset($_GET['role'])) {
    $role = strtolower(trim((string) $_GET['role']));

    if ($role === 'user') {
        $_SESSION['user_id'] = getFirstUserIdByRoles(['user', 'citoyen'], 1);
        $_SESSION['role'] = 'user';
        $_SESSION['user_role'] = 'user';
        redirectToNextIfValid();
                header('Location: view/frontoffice/mes-accompagnements.php');
                exit;
    } elseif ($role === 'assistant' || $role === 'agent') {
        $_SESSION['user_id'] = getFirstUserIdByRoles(['assistant', 'agent'], 1);
        $_SESSION['role'] = 'assistant';
        $_SESSION['user_role'] = 'agent';
        redirectToNextIfValid();
                header('Location: view/frontoffice/assistant-accompagnements.php');
                exit;
    } elseif ($role === 'admin') {
        $_SESSION['user_id'] = getFirstUserIdByRoles(['admin'], 1);
        $_SESSION['role'] = 'admin';
        $_SESSION['user_role'] = 'admin';
        redirectToNextIfValid();
                header('Location: view/backoffice/gestion-accompagnements.php');
                exit;
    } elseif ($role === 'logout') {
        session_destroy();
                $flashMessage = "Session fermée avec succès.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SecondVoice | Choix du Rôle</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <style>
        :root {
            --bg: #0a0f1f;
            --panel: #121a31;
            --line: rgba(255,255,255,.1);
            --text: #f3f6ff;
            --muted: #a8b5d7;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Outfit", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 8% 0%, rgba(99, 91, 255, .28), transparent 24%),
                radial-gradient(circle at 92% 0%, rgba(76, 201, 240, .18), transparent 22%),
                linear-gradient(180deg, #080d1a, #0a0f1f);
            display: grid;
            place-items: center;
            padding: 24px;
        }
        .shell {
            width: min(980px, 100%);
            background: rgba(12, 18, 36, .75);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 26px;
            backdrop-filter: blur(3px);
        }
        .top h1 { margin: 0; font-size: 1.8rem; }
        .top p { margin: 8px 0 0; color: var(--muted); }
        .flash {
            margin-top: 16px;
            padding: 11px 13px;
            border-radius: 10px;
            background: rgba(49,208,170,.16);
            border: 1px solid rgba(49,208,170,.32);
            color: #8ef1d6;
            font-weight: 600;
        }
        .roles {
            margin-top: 20px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }
        .card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .emoji { font-size: 1.5rem; }
        .title { font-size: 1.05rem; font-weight: 700; }
        .desc { color: var(--muted); font-size: .92rem; min-height: 44px; }
        .btn {
            margin-top: auto;
            text-align: center;
            text-decoration: none;
            color: #fff;
            font-weight: 700;
            border-radius: 10px;
            padding: 10px 12px;
            border: 1px solid transparent;
        }
        .btn-user { background: linear-gradient(135deg, #21a0ff, #1565c0); }
        .btn-assistant { background: linear-gradient(135deg, #0f9c95, #0b726d); }
        .btn-admin { background: linear-gradient(135deg, #635bff, #3f38d4); }
        .footer {
            margin-top: 16px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            color: var(--muted);
            font-size: .88rem;
        }
        .logout {
            color: #ffb8b8;
            text-decoration: none;
            border: 1px solid rgba(255,107,107,.35);
            border-radius: 8px;
            padding: 6px 10px;
        }
        @media (max-width: 860px) {
            .roles { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <div class="top">
            <h1>Choisissez votre espace</h1>
            <p>Point d'entrée rapide pour tester l'application en mode Utilisateur, Assistant ou Administrateur.</p>
        </div>

        <?php if ($flashMessage): ?>
            <div class="flash"><?= htmlspecialchars($flashMessage) ?></div>
        <?php endif; ?>

        <div class="roles">
            <div class="card">
                <div class="emoji">👤</div>
                <div class="title">Utilisateur</div>
                <div class="desc">Soumettre une demande, suivre vos accompagnements et consulter les guides publiés.</div>
                <a class="btn btn-user" href="test-login.php?role=user">Entrer en tant qu'utilisateur</a>
            </div>

            <div class="card">
                <div class="emoji">🧭</div>
                <div class="title">Assistant</div>
                <div class="desc">Traiter les missions validées, créer et gérer les étapes d'accompagnement.</div>
                <a class="btn btn-assistant" href="test-login.php?role=assistant">Entrer en tant qu'assistant</a>
            </div>

            <div class="card">
                <div class="emoji">🛡️</div>
                <div class="title">Administrateur</div>
                <div class="desc">Valider les demandes, superviser les guides et piloter les espaces backoffice.</div>
                <a class="btn btn-admin" href="test-login.php?role=admin">Entrer en tant qu'administrateur</a>
            </div>
        </div>

        <div class="footer">
            <span>SecondVoice - Interface de sélection de rôle</span>
            <a class="logout" href="test-login.php?role=logout">Fermer la session active</a>
        </div>
    </div>
</body>
</html>
