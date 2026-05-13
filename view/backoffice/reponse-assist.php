<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Récupération de l'ID réclamation
$id_reclamation = isset($_GET['reclamation']) ? (int)$_GET['reclamation'] : 
                 (isset($_POST['id_reclamation']) ? (int)$_POST['id_reclamation'] : 0);

if ($id_reclamation === 0) {
    header('Location: gestion-reclamations.php?error=no_reclamation');
    exit;
}

include_once '../../controller/reclamationcontroller.php';
include_once '../../controller/reponsecontroller.php';
require_once __DIR__ . '/../../model/reclamation.php';
require_once __DIR__ . '/../../model/reponse.php';

$reclamationController = new ReclamationController();
$reponseController = new ReponseController();

// ✅ RÉCUPÉRATION DES DONNÉES (AVANT l'IA)
$reclamation = $reclamationController->getReclamationById($id_reclamation);
$reponses = $reponseController->getReponsesByReclamation($id_reclamation);

// Vérifier que la réclamation existe
if (!$reclamation) {
    header('Location: gestion-reclamations.php?error=reclamation_not_found');
    exit;
}
$suggestedReply = '';
$summary = '';
if (!empty($reponses)) {
// ✅ RÉSUMÉ IA (APRÈS récupération des données)


    include_once '../../controller/aicontroller.php';
    $aiController = new AIController();
    $summary = $aiController->summarizeChat($reponses, $reclamation->getId_user());
    $lastMessage = end($reponses);
    if ($lastMessage && $lastMessage->getId_user() == $reclamation->getId_user()) {
        // Construire l'historique pour le contexte
        $conversation = "";
        foreach (array_slice($reponses, -5) as $msg) { // Derniers 5 messages
            $role = ($msg->getId_user() == $_SESSION['user_id']) ? 'Assistant' : 'Client';
            $conversation .= "$role: " . $msg->getContenu() . "\n";
        }
        
        $suggestedReply = $aiController->suggestReply(
            $conversation,
            $lastMessage->getContenu()
        );
    }
} else {
    $summary = "Aucune conversation pour le moment.";
}

// ✅ TRAITEMENT POST - AJOUT RÉPONSE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    
    if (!empty($message)) {
        $reponse = new Reponse();
        $reponse->setContenu($message);
        $reponse->setDate_reponse(date('Y-m-d H:i:s'));
        $reponse->setId_reclamation($id_reclamation);
        $reponse->setId_user($_SESSION['user_id']);
        
        $reponseController->addReponse($reponse);
        $reclamationController->changeStatut($id_reclamation, 'en_cours');

        
        
        header('Location: reponse-assist.php?reclamation=' . $id_reclamation);
        exit;
    }
}

// ✅ TRAITEMENT GET - SUPPRESSION RÉPONSE
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['delete_reponse'])) {
    $id_reponse = (int)$_GET['delete_reponse'];
    
    $reponse = $reponseController->getReponseById($id_reponse);
    
    if ($reponse && $reponse->getId_user() == $_SESSION['user_id']) {
        $reponseController->deleteReponse($id_reponse);
    }
    
    header('Location: reponse-assist.php?reclamation=' . $id_reclamation);
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Répondre | SecondVoice</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/media/favicon-32.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/media/favicon-16.png" />
    <link rel="apple-touch-icon" href="../assets/media/apple-touch-icon.png" />
    <link rel="shortcut icon" href="../assets/media/favicon.png" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/style.css" />
    <style>
        /* ============================================
           LAYOUT AVEC SIDEBAR
           ============================================ */
        
        .page.with-sidebar {
            margin-left: 260px;
            width: calc(100% - 260px);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            max-width: none;
        }
        
        /* ============================================
           CHAT PAGE
           ============================================ */
        
        .chat-page {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 24px;
            position: relative;
            width: 100%;
            align-items: stretch;
            justify-content: flex-start;
        }
        
        .chat-watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 400px;
            opacity: 0.03;
            pointer-events: none;
            z-index: 0;
        }
        
        .chat-watermark img {
            width: 100%;
            height: auto;
            filter: grayscale(100%);
        }
        
        .tabs-menu {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            padding: 4px;
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border);
            width: fit-content;
            position: relative;
            z-index: 1;
        }
        
        .tab-button {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .tab-button:hover {
            color: var(--text-primary);
        }
        
        .tab-button.active {
            background: var(--accent);
            color: white;
        }
        
        /* ============================================
           CHAT CONTAINER
           ============================================ */
        
        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border);
            overflow: hidden;
            width: 100%;
            min-height: 0; /* ← CORRIGÉ pour scroll */
        }
        
        .chat-header {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
            flex-shrink: 0; /* ← Ne pas rétrécir */
        }
        
        .chat-header h3 {
            margin: 0;
            font-size: 16px;
            color: var(--text);
        }
        
        .chat-header p {
            margin: 4px 0 0;
            font-size: 13px;
            color: var(--muted);
        }
        
        /* ============================================
           RÉSUMÉ IA - COLLAPSIBLE
           ============================================ */
        
        .ai-summary-box {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border: 1px solid var(--border);
            border-radius: 12px;
            margin: 12px 24px 0;
            overflow: hidden;
            transition: all 0.3s ease;
            flex-shrink: 0; /* ← Ne pas rétrécir */
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
            display: none; /* ← Caché par défaut */
        }
        
        .ai-summary-content.active {
            display: block;
        }
        
        .ai-summary-content p {
            margin: 0;
        }
        
        /* ============================================
           MESSAGES - BULLES INVERSEES
           Assistant (vous) → DROITE
           Client → GAUCHE
           ============================================ */
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            min-height: 0; /* ← CORRIGÉ pour scroll */
        }
        
        .message {
            display: flex;
            align-items: flex-end;
            gap: 12px;
            max-width: 75%;
        }
        
        /* ASSISTANT (vous) → A DROITE */
        .message--assistant {
            align-self: flex-end;
            flex-direction: row-reverse;
        }
        
        .message--assistant .message__bubble {
            background: var(--primary);
            color: white;
            border-radius: 16px 16px 4px 16px;
        }
        
        .message--assistant .message__time {
            text-align: right;
        }
        
        /* CLIENT → A GAUCHE */
        .message--user {
            align-self: flex-start;
            flex-direction: row;
        }
        
        .message--user .message__bubble {
            background: var(--surface);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 16px 16px 16px 4px;
        }
        
        .message--user .message__time {
            text-align: left;
        }
        
        /* BULLE */
        .message__content {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .message__bubble {
            padding: 12px 16px;
            line-height: 1.5;
            font-size: 14px;
            word-wrap: break-word;
        }
        
        .message__time {
            font-size: 11px;
            color: var(--muted);
            padding: 0 4px;
        }
        
        /* AVATAR */
        .message__avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            flex-shrink: 0;
        }
        
        .message--assistant .message__avatar {
            background: var(--primary);
            color: white;
        }
        
        .message--user .message__avatar {
            background: var(--surface);
            color: var(--text);
            border: 1px solid var(--border);
        }
        
        /* ============================================
           INPUT
           ============================================ */
        
        .chat-input-container {
            padding: 16px 24px;
            border-top: 1px solid var(--border);
            background: var(--surface);
            width: 100%;
            flex-shrink: 0; /* ← Ne pas rétrécir */
        }
        
        .chat-form {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .chat-input-wrapper {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            width: 100%;
        }
        
        .chat-input {
            flex: 1;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 12px;
            padding: 12px 16px;
            color: var(--text);
            resize: none;
            min-height: 48px;
            max-height: 120px;
            font-family: inherit;
            font-size: 14px;
        }
        
        .chat-input:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .chat-submit {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--primary);
            color: white;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            flex-shrink: 0;
        }
        
        .chat-submit:hover {
            transform: scale(1.05);
        }
        
        .chat-submit svg {
            width: 20px;
            height: 20px;
        }
        
        .chat-hint {
            font-size: 12px;
            color: var(--muted);
            text-align: center;
        }
        
        .chat-hint kbd {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 2px 6px;
            font-size: 11px;
        }
        
        /* ============================================
           EMPTY STATE
           ============================================ */
        
        .empty-chat {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
        }
        
        .empty-chat-icon {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        /* ============================================
           RESPONSIVE
           ============================================ */
        
        @media (max-width: 760px) {
            .page.with-sidebar {
                margin-left: 0;
                width: 100%;
            }
            
            .chat-container {
                max-height: calc(100vh - 200px);
            }
            
            .message {
                max-width: 85%;
            }
        }
                /* ===== SUGGESTION IA ===== */
                .ai-suggestion-box {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%);
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .ai-suggestion-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            cursor: pointer;
            background: var(--surface);
            border-bottom: 1px solid transparent;
            transition: all 0.2s;
        }
        
        .ai-suggestion-header:hover {
            background: var(--surface-2);
        }
        
        .ai-suggestion-header.active {
            border-bottom-color: var(--border);
        }
        
        .ai-suggestion-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            color: #10b981;
            margin: 0;
        }
        
        .ai-suggestion-toggle {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 4px;
            border-radius: 6px;
            transition: all 0.2s;
            font-size: 12px;
        }
        
        .ai-suggestion-content {
            padding: 16px;
            font-size: 13px;
            line-height: 1.6;
            color: var(--text);
            display: none;
        }
        
        .ai-suggestion-content.active {
            display: block;
        }
        
        .btn-use-suggestion {
            margin-top: 12px;
            background: #10b981;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
        }
        
        .btn-use-suggestion:hover {
            background: #059669;
            transform: scale(1.02);
        }
    </style>
</head>
<body data-page="voice">
    <div class="overlay" data-overlay></div>
    
    <div class="shell">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-panel">
                <div class="brand-row">
                    <a class="brand" href="index.php">
                        <img class="brand-logo" src="../assets/media/secondvoice-logo.png" alt="SecondVoice logo" />
                    </a>
                </div>
                <div class="sidebar-scroll">
                    <div class="nav-section">
                        <div class="nav-title">Gestion</div>
                        <a class="nav-link" href="index.php" data-nav="home">
                            <span class="nav-icon icon-home"></span>
                            <span>Tableau de bord</span>
                        </a>
                        <a class="nav-link" href="gestion-idees.php" data-nav="community">
                            <span class="nav-icon icon-community"></span>
                            <span>Gestion des idées</span>
                        </a>
                        <a class="nav-link" href="gestion-rendezvous.php" data-nav="subscription">
                            <span class="nav-icon icon-card"></span>
                            <span>Gestion des rendez-vous</span>
                        </a>
                        <a class="nav-link" href="../accompagnements/gestion-accompagnements.php" data-nav="chatbot">
                            <span class="nav-icon icon-chat"></span>
                            <span>Gestion des guides</span>
                        </a>
                        <a class="nav-link" href="gestion-evenements.php" data-nav="images">
                            <span class="nav-icon icon-image"></span>
                            <span>Gestion des événements</span>
                        </a>
                        <a class="nav-link active" href="gestion-reclamations.php" data-nav="voice">
                            <span class="nav-icon icon-mic"></span>
                            <span>Gestion des réclamations</span>
                        </a>
                        <a class="nav-link" href="settings.php" data-nav="settings">
                            <span class="nav-icon icon-settings"></span>
                            <span>Paramètres</span>
                        </a>
                    </div>
                </div>
            </div>
        </aside>

        <!-- PAGE PRINCIPALE -->
        <main class="page with-sidebar">
            <!-- TOPBAR -->
            <div class="topbar">
                <div>
                    <button class="mobile-toggle" data-nav-toggle aria-label="Open navigation">=</button>
                    <h1 class="page-title">Répondre à la réclamation</h1>
                    <div class="page-subtitle">Chat avec le client</div>
                </div>
                <div class="toolbar-actions">
                    <a class="update-button" href="gestion-reclamations.php">Retour</a>
                    <button class="icon-button icon-moon" data-theme-toggle aria-label="Switch theme"></button>
                    <div class="profile-menu-wrap" data-profile-wrap>
                        <button class="profile-trigger" data-profile-toggle aria-label="Open profile menu">
                            <img class="topbar-avatar" src="../assets/media/profile-avatar.svg" alt="Profile" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- CHAT -->
            <div class="chat-page">
                <div class="chat-watermark">
                    <img src="../assets/media/secondvoice-logo.png" alt="SecondVoice" />
                </div>

                <div class="tabs-menu">
                    <a href="gestion-reclamations.php" class="tab-button">Liste</a>
                    <a href="reponse-assist.php?reclamation=<?= $id_reclamation ?>" class="tab-button active">Répondre</a>
                </div>

                <div class="chat-container">
                    <!-- Header avec info réclamation -->
                    <div class="chat-header">
                        <h3>Réclamation #<?= $id_reclamation ?></h3>
                        <p><?= htmlspecialchars(substr($reclamation->getDescription(), 0, 100)) ?><?= strlen($reclamation->getDescription()) > 100 ? '...' : '' ?></p>
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
                                        <!-- Suggestion IA de réponse -->
                                        <?php if (!empty($suggestedReply)): ?>
                    <!--<div class="ai-suggestion-box">
                        <div class="ai-suggestion-header" onclick="toggleSuggestion(this)">
                            <h4 class="ai-suggestion-title">
                                <span>💡</span>
                                <span>Réponse suggérée</span>
                            </h4>
                            <button class="ai-suggestion-toggle" type="button">
                                <span class="toggle-icon">▼</span>
                            </button>
                        </div>
                        
                    </div>-->
                    <?php endif; ?>
                    <!-- Messages -->
                    <div class="chat-messages" id="chatMessages">
                        <?php if (empty($reponses)): ?>
                            <div class="empty-chat">
                                <div class="empty-chat-icon">💬</div>
                                <p>Aucune réponse pour le moment. Soyez le premier à répondre !</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($reponses as $reponse): 
                                $type = ($reponse->getId_user() == $_SESSION['user_id']) ? 'assistant' : 'user';
                                $avatar = $type === 'assistant' ? 'SV' : 'CL';
                                $canEdit = ($reponse->getId_user() == $_SESSION['user_id']);
                            ?>
                                <div class="message message--<?= $type ?>">
                                    <div class="message__content">
                                        <div class="message__bubble">
                                            <?= nl2br(htmlspecialchars($reponse->getContenu())) ?>
                                        </div>
                                        <span class="message__time">
                                            <?= date('H:i', strtotime($reponse->getDate_reponse())) ?>
                                            
                                            <?php if ($canEdit): ?>
                                                <span class="message__actions">
                                                    <a href="edit-reponse.php?id=<?= $reponse->getId_reponse() ?>&reclamation=<?= $id_reclamation ?>">✏️ Modifier</a>
                                                    <a href="reponse-assist.php?reclamation=<?= $id_reclamation ?>&delete_reponse=<?= $reponse->getId_reponse() ?>" 
                                                       style="margin-left: 10px; color: #dc2626; font-size: 11px; text-decoration: none;"
                                                       onclick="return confirm('Supprimer cette réponse ?')">🗑️ Supprimer</a>
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="message__avatar"><?= $avatar ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Input -->
                    <div class="chat-input-container">
                        <form class="chat-form" method="POST" action="">
                            <input type="hidden" name="id_reclamation" value="<?= $id_reclamation ?>">
                            
                            <div class="chat-input-wrapper">
                            <button type="button" class="btn-use-suggestion" onclick="useSuggestion('<?= htmlspecialchars(addslashes($suggestedReply), ENT_QUOTES) ?>')">
                            🤖 
                            </button>
                                <textarea class="chat-input" name="message" placeholder="Écrivez votre réponse..." rows="1" required></textarea>
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

    <script src="../assets/app.js"></script>
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
        
        // Auto-resize textarea
        const textarea = document.querySelector('.chat-input');
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
        
        // Envoyer avec Entrée
        textarea.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.closest('form').submit();
            }
        });
        
        // Scroll to bottom des messages
        const messagesContainer = document.getElementById('chatMessages');
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                // Toggle suggestion
                function toggleSuggestion(header) {
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
        
        // Utiliser la suggestion dans le textarea
        function useSuggestion(text) {
            const textarea = document.querySelector('.chat-input');
            textarea.value = text;
            textarea.focus();
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
        }
    </script>
</body>
</html>