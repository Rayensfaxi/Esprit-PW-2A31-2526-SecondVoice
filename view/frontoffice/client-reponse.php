<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// ✅ ID depuis l'URL (GET)
$id_reclamation = isset($_GET['reclamation']) ? (int)$_GET['reclamation'] : 0;

include_once '../../controller/reclamationcontroller.php';
include_once '../../controller/reponsecontroller.php';
include_once '../../controller/aicontroller.php'; // ← AJOUTÉ
require_once __DIR__ . '/../../model/reclamation.php';
require_once __DIR__ . '/../../model/reponse.php';

$reclamationController = new ReclamationController();
$reponseController = new ReponseController();

// Vérifier que la réclamation appartient à l'utilisateur
$reclamation = $reclamationController->getReclamationById($id_reclamation);

if (!$reclamation || $reclamation->getId_user() != $_SESSION['user_id']) {
    header('Location: client-reclamations.php');
    exit;
}

// ✅ RÉSUMÉ IA
$reponses = $reponseController->getReponsesByReclamation($id_reclamation);
$summary = '';
if (!empty($reponses)) {
    $aiController = new AIController();
    $summary = $aiController->summarizeChat($reponses, $_SESSION['user_id']);
} else {
    $summary = "Aucune conversation pour le moment.";
}

// ✅ TRAITEMENT POST - Message du client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    
    if (!empty($message)) {
        $reponse = new Reponse();
        $reponse->setContenu($message);
        $reponse->setDate_reponse(date('Y-m-d H:i:s'));
        $reponse->setId_reclamation($id_reclamation);
        $reponse->setId_user($_SESSION['user_id']);
        
        $reponseController->addReponse($reponse);
        
        header('Location: client-reponse.php?reclamation=' . $id_reclamation);
        exit;
    }
}

// Récupérer les réponses à jour
$reponses = $reponseController->getReponsesByReclamation($id_reclamation);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Mes Réponses | SecondVoice</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/media/favicon-32.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/media/favicon-16.png" />
    <link rel="apple-touch-icon" href="../assets/media/apple-touch-icon.png" />
    <link rel="shortcut icon" href="../assets/media/favicon.png" />
    <script>
        const savedTheme = localStorage.getItem("theme");
        const initialTheme = savedTheme || (window.matchMedia("(prefers-color-scheme: light)").matches ? "light" : "dark");
        document.documentElement.dataset.theme = initialTheme;
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/style.css" />
    <link rel="stylesheet" href="../assets/css/chat.css" />
    <style>
        /* Styles spécifiques client - bulles inversées par rapport à assistant */
        .message--user {
            align-self: flex-end;
            flex-direction: row-reverse;
        }
        
        .message--user .message__bubble {
            background: var(--primary);
            color: white;
            border-radius: 16px 16px 4px 16px;
        }
        
        .message--assistant {
            align-self: flex-start;
            flex-direction: row;
        }
        
        .message--assistant .message__bubble {
            background: var(--surface);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 16px 16px 16px 4px;
        }

        /* ===== HEADER AMÉLIORÉ ===== */
        .chat-page .container {
            padding-top: 20px;
        }

        .back-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 50%;
            color: var(--text);
            text-decoration: none;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }
        
        .back-btn:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
            transform: translateX(-2px);
        }
        
        .back-btn svg {
            width: 20px;
            height: 20px;
        }

        .back-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            margin: 0;
        }

        .back-meta {
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 2px;
        }

        /* ===== INFO RÉCLAMATION ===== */
        .reclamation-info {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 20px;
        }

        .reclamation-info__label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--primary);
            margin-bottom: 8px;
        }

        .reclamation-info__text {
            font-size: 15px;
            line-height: 1.5;
            color: var(--text);
            margin: 0;
        }

        /* ===== RÉSUMÉ IA COLLAPSIBLE ===== */
        .ai-summary-box {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .ai-summary-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            cursor: pointer;
            background: var(--surface);
            border-bottom: 1px solid transparent;
            transition: all 0.2s;
        }
        
        .ai-summary-header:hover {
            background: var(--surface-2);
        }
        
        .ai-summary-header.active {
            border-bottom-color: var(--border);
        }
        
        .ai-summary-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            color: var(--primary);
            margin: 0;
        }
        
        .ai-summary-toggle {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 4px;
            border-radius: 6px;
            transition: all 0.2s;
            font-size: 12px;
        }
        
        .ai-summary-toggle:hover {
            background: var(--surface);
            color: var(--text);
        }
        
        .ai-summary-content {
            padding: 16px;
            font-size: 13px;
            line-height: 1.6;
            color: var(--text);
            display: none;
        }
        
        .ai-summary-content.active {
            display: block;
        }
        
        .ai-summary-content p {
            margin: 0;
        }

        /* ===== CHAT MESSAGES ===== */
        .chat-messages {
            display: flex;
            flex-direction: column;
            gap: 16px;
            padding: 8px 4px;
            min-height: 200px;
            max-height: 500px;
            overflow-y: auto;
        }

        .message {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            max-width: 85%;
            animation: fadeInUp 0.3s ease;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message__avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .message--user .message__avatar {
            background: var(--primary);
            color: white;
        }

        .message--assistant .message__avatar {
            background: var(--surface-2);
            color: var(--text-secondary);
            border: 1px solid var(--border);
        }

        .message__content {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .message__bubble {
            padding: 12px 16px;
            font-size: 14px;
            line-height: 1.5;
            word-wrap: break-word;
        }

        .message__time {
            font-size: 11px;
            color: var(--text-secondary);
            padding: 0 4px;
        }

        .message--user .message__time {
            text-align: right;
        }

        /* Empty state */
        .empty-chat {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .empty-chat-icon {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        /* Input */
        .chat-input-container {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }
        
        /* ===== CONTAINER ÉLARGI ===== */
        .chat-page .container {
            max-width: 900px;
            width: 100%;
            margin: 0 auto;
            padding: 20px 16px;
        }

        /* Sur grand écran, on peut élargir encore */
        @media (min-width: 1400px) {
            .chat-page .container {
                max-width: 1100px;
            }
        }

        /* ===== CHAT CONTAINER PLEINE LARGEUR ===== */
        .chat-container {
            width: 100%;
            display: flex;
            flex-direction: column;
        }

        /* Les messages prennent toute la largeur disponible */
        .chat-messages {
            width: 100%;
        }

        .message {
            max-width: 75%;
        }

        /* Input pleine largeur */
        .chat-form {
            width: 100%;
        }

        .chat-input-wrapper {
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <!-- Header -->
        <header class="site-header">
            <div class="container nav-inner">
                <a class="brand" href="index.php">
                    <img class="brand-logo" src="../assets/media/secondvoice-logo.png" alt="SecondVoice logo" />
                </a>
                <div class="header-actions">
                    <a class="icon-btn user-trigger" href="profile.php">
                        <span><?= substr($_SESSION['user_prenom'] ?? 'U', 0, 1) ?></span>
                    </a>
                </div>
            </div>
        </header>

        <main class="chat-page">
            <div class="chat-watermark">
                <img src="../assets/media/secondvoice-logo.png" alt="SecondVoice" />
            </div>

            <div class="container">
                <div class="chat-container">
                    <!-- Barre de retour -->
                    <div class="back-bar">
                        <a href="client-reclamations.php" class="back-btn" aria-label="Retour">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M19 12H5M12 19l-7-7 7-7"/>
                            </svg>
                        </a>
                        <div>
                            <h2 class="back-title">Réclamation #<?= $id_reclamation ?></h2>
                            <div class="back-meta">
                                <?= date('d/m/Y', strtotime($reclamation->getDate_creation())) ?> · 
                                <span style="color: var(--primary); font-weight: 600;">
                                    <?= htmlspecialchars($reclamation->getStatut()) ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Info réclamation -->
                    <div class="reclamation-info">
                        <div class="reclamation-info__label">Sujet</div>
                        <p class="reclamation-info__text">
                            <?= nl2br(htmlspecialchars($reclamation->getDescription())) ?>
                        </p>
                    </div>

                    <!-- Résumé IA Collapsible -->
                    <div class="ai-summary-box">
                        <div class="ai-summary-header" onclick="toggleSummary(this)">
                            <h4 class="ai-summary-title">
                                <span>🤖</span>
                                <span>Résumé IA</span>
                            </h4>
                            <button class="ai-summary-toggle" type="button" aria-label="Afficher/Masquer">
                                <span class="toggle-icon">▼</span>
                            </button>
                        </div>
                        <div class="ai-summary-content">
                            <p><?= nl2br(htmlspecialchars($summary)) ?></p>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="chat-messages" id="chatMessages">
                        <?php if (empty($reponses)): ?>
                            <div class="empty-chat">
                                <div class="empty-chat-icon">💬</div>
                                <p>Aucune réponse pour le moment.<br>Écrivez votre premier message ci-dessous.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($reponses as $reponse): 
                                $isClient = ($reponse->getId_user() == $_SESSION['user_id']);
                                $type = $isClient ? 'user' : 'assistant';
                                $avatar = $isClient ? 'V' : 'SV';
                                $name = $isClient ? 'Vous' : 'SecondVoice';
                            ?>
                                <div class="message message--<?= $type ?>">
                                    <div class="message__avatar" title="<?= $name ?>"><?= $avatar ?></div>
                                    <div class="message__content">
                                        <div class="message__bubble">
                                            <?= nl2br(htmlspecialchars($reponse->getContenu())) ?>
                                        </div>
                                        <span class="message__time">
                                            <?= date('d/m/Y H:i', strtotime($reponse->getDate_reponse())) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Input -->
                    <div class="chat-input-container">
                        <form class="chat-form" method="POST" action="">
                            <div class="chat-input-wrapper">
                                <textarea class="chat-input" name="message" placeholder="Écrivez votre message..." rows="1" required></textarea>
                                <button type="submit" class="chat-submit" aria-label="Envoyer">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="22" y1="2" x2="11" y2="13"></line>
                                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                    </svg>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        // Toggle résumé IA
        function toggleSummary(header) {
            const content = header.nextElementSibling;
            const icon = header.querySelector('.toggle-icon');
            
            header.classList.toggle('active');
            content.classList.toggle('active');
            
            if (content.classList.contains('active')) {
                icon.textContent = '▲';
            } else {
                icon.textContent = '▼';
            }
        }
        
        const textarea = document.querySelector('.chat-input');
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
        
        textarea.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.closest('form').submit();
            }
        });
        
        const messagesContainer = document.getElementById('chatMessages');
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    </script>
</body>
</html>