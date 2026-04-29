<?php
// client-reponse.php
// Page de chat pour les réponses aux réclamations
session_start();

// Messages statiques de test pour le prototype UI
/*$messages = [
    [
        'type' => 'assistant',
        'content' => 'Bonjour ! Je suis votre assistant SecondVoice. Je vois que vous avez déposé une réclamation concernant votre demande administrative. Comment puis-je vous aider ?',
        'time' => '10:30',
        'date' => 'Aujourd\'hui'
    ],
    [
        'type' => 'user',
        'content' => 'Bonjour, j\'attends toujours une réponse pour ma demande #1234 et cela fait maintenant 5 jours.',
        'time' => '10:32',
        'date' => 'Aujourd\'hui'
    ],
    [
        'type' => 'assistant',
        'content' => 'Je comprends votre impatience. Laissez-moi vérifier l\'état de votre demande immédiatement...',
        'time' => '10:33',
        'date' => 'Aujourd\'hui'
    ],
    [
        'type' => 'assistant',
        'content' => '✅ J\'ai trouvé votre dossier. Il est actuellement en cours de traitement par notre équipe. Le délai estimé est de 48 heures supplémentaires. Souhaitez-vous que je demande une priorisation ?',
        'time' => '10:34',
        'date' => 'Aujourd\'hui'
    ]
];*/

// L'input s'affiche car l'assistant a déjà répondu
$showInput = true;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Réponses | SecondVoice</title>
    <link
      rel="icon"
      type="image/png"
      sizes="32x32"
      href="../assets/media/favicon-32.png"
    />
    <link
      rel="icon"
      type="image/png"
      sizes="16x16"
      href="../assets/media/favicon-16.png"
    />
    <link rel="apple-touch-icon" href="../assets/media/apple-touch-icon.png" />
    <link rel="shortcut icon" href="../assets/media/favicon.png" />
    <script>
      const savedTheme = localStorage.getItem("theme");
      const initialTheme =
        savedTheme ||
        (window.matchMedia("(prefers-color-scheme: light)").matches
          ? "light"
          : "dark");
      document.documentElement.dataset.theme = initialTheme;
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="../assets/css/style.css" />
    <link rel="stylesheet" href="../assets/css/chat.css" />
</head>
<body>
    <div class="page-shell">
      <header class="site-header">
        <div class="container nav-inner">
          <a class="brand" href="index.php">
            <img
              class="brand-logo"
              src="../assets/media/secondvoice-logo.png"
              alt="SecondVoice logo"
            />
          </a>
          <button
            class="menu-toggle"
            type="button"
            data-menu-toggle
            aria-label="Ouvrir le menu"
          >
            <span class="icon-lines"></span>
          </button>
          <div class="nav" data-nav>
            <nav>
              <ul class="nav-links">
                <li><a href="index.php">Accueil</a></li>
                <li><a href="about.php">A propos</a></li>
                <li><a href="services.php">Services</a></li>
                <li><a href="blog.php">Blog</a></li>
                <li><a href="contact.php">Contact</a></li>
              </ul>
            </nav>
            <div class="header-actions">
              <button
                class="icon-btn theme-toggle"
                type="button"
                data-theme-toggle
                aria-label="Changer le theme"
              >
                <span class="theme-toggle-label" data-theme-label>Clair</span>
              </button>
              <div class="user-shell" data-user-shell>
                <a
                  class="icon-btn user-trigger"
                  href="login.php"
                  aria-label="Ouvrir la page de connexion"
                >
                  <span>Profil</span>
                </a>
              </div>
            </div>
          </div>
        </div>
      </header>

      <main class="chat-page">
        <!-- Watermark logo en arrière-plan -->
        <!-- Watermark avec le vrai logo SecondVoice -->
<div class="chat-watermark">
    <img src="../assets/media/secondvoice-logo.png" alt="SecondVoice" />
</div>

        <div class="container">
            <!-- Onglets de navigation -->
            <div class="tabs-menu">
                <a href="service-reclamations.php" class="tab-button">Nouvelle réclamation</a>
                <a href="service-reclamations.php" class="tab-button">Réclamations en cours</a>
                <a href="client-reponse.php" class="tab-button active">Réponses</a>
            </div>

            <!-- Container du chat -->
            <div class="chat-container">
                
                <!-- Zone des messages -->
                <div class="chat-messages" id="chatMessages">
                    <?php 
                    /*$currentDate = '';
                    foreach ($messages as $msg): 
                        // Afficher la date si elle change
                        if ($msg['date'] !== $currentDate):
                            $currentDate = $msg['date'];*/
                    ?>
                        <div class="chat-date-separator">
                            <span><?php// echo htmlspecialchars($currentDate); ?></span>
                        </div>
                    <?php //endif; ?>
                        
                        <div class="message message--<?php// echo $msg['type']; ?>">
                            <?php// if ($msg['type'] === 'assistant'): ?>
                                <div class="message__avatar">
                                    <div class="assistant-avatar">SV</div>
                                </div>
                            <?php //endif; ?>
                            
                            <div class="message__content-wrapper">
                                <div class="message__bubble">
                                    <div class="message__text">
                                        <?php //echo nl2br(htmlspecialchars($msg['content'])); ?>
                                    </div>
                                </div>
                                <span class="message__time"><?php// echo $msg['time']; ?></span>
                            <!--</div>-->
                            
                            <?php //if ($msg['type'] === 'user'): ?>
                                <div class="message__avatar">
                                    <div class="user-avatar">Vous</div>
                                </div>
                            <?php //endif; ?>
                        </div>
                    <?php //endforeach; ?>
                </div>

                <!-- Zone d'input conditionnelle -->
                <div class="chat-input-container <?php //echo $showInput ? 'is-visible' : 'is-hidden'; ?>" id="chatInputContainer">
                    <form class="chat-form" id="chatForm">
                        <div class="chat-input-wrapper">
                            <textarea 
                                class="chat-input" 
                                id="chatInput"
                                placeholder="Écrivez votre message..."
                                rows="1"
                            ></textarea>
                            <button type="submit" class="chat-submit" aria-label="Envoyer">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="22" y1="2" x2="11" y2="13"></line>
                                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                </svg>
                            </button>
                        </div>
                        <div class="chat-input-hint">
                            Appuyez sur <kbd>Entrée</kbd> pour envoyer, <kbd>Shift + Entrée</kbd> pour une nouvelle ligne
                        </div>
                    </form>
                </div>

            </div>
        </div>
      </main>

      <footer class="footer">
        <div class="container">
          <div class="footer-bottom">
            <span>&copy; 2026 SecondVoice. Tous droits réservés.</span>
            <div class="footer-links">
              <a href="services.php">Services</a>
              <a href="contact.php">Contact</a>
            </div>
          </div>
        </div>
      </footer>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/chat.js"></script>
</body>
</html>