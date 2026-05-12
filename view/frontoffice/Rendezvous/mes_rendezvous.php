<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../controller/RendezvousC.php';

$rendezvousC = new RendezvousC();
$id_citoyen = 1; // Simule pour l'exemple

$search = $_GET['search'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$sortBy = $_GET['sort'] ?? 'date_desc';

$success = $_GET['success'] ?? '';
$liste = $rendezvousC->listRendezvousByCitoyen($id_citoyen, $search, $filterStatus, $sortBy);
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Mes Rendez-vous | SecondVoice</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/media/favicon-32.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/media/favicon-16.png" />
    <link rel="apple-touch-icon" href="../assets/media/apple-touch-icon.png" />
    <link rel="shortcut icon" href="../assets/media/favicon.png" />
    <script>
      const savedTheme = localStorage.getItem("theme");
      const initialTheme =
        savedTheme || (window.matchMedia("(prefers-color-scheme: light)").matches ? "light" : "dark");
      document.documentElement.dataset.theme = initialTheme;
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/style.css" />
    <style>
      .success-message {
        color: #27ae60;
        background: rgba(39, 174, 96, 0.1);
        padding: 10px;
        border-radius: 8px;
        margin-bottom: 16px;
        border-left: 5px solid #27ae60;
      }
      .rdv-hero-blank {
        height: 66px;
        border-radius: 0 0 40px 40px;
        background: linear-gradient(105deg, rgba(20, 12, 50, 0.98) 0%, rgba(16, 31, 58, 0.95) 100%);
        border: 1px solid var(--line);
        border-top: none;
      }
      .rdv-wrapper {
        margin-top: 28px;
        margin-bottom: 44px;
      }
      .rdv-filters {
        display: grid;
        grid-template-columns: minmax(280px, 1fr) auto auto;
        gap: 18px;
        align-items: center;
        padding: 30px;
        border-radius: 28px;
        border: 1px solid var(--line);
        background: var(--surface);
      }
      .rdv-input {
        width: 100%;
        height: 62px;
        border-radius: 22px;
        border: 1px solid var(--line);
        background: var(--input-bg);
        color: var(--text);
        padding: 0 20px;
        outline: none;
      }
      .rdv-search-wrap { position: relative; }
      .rdv-search-wrap .rdv-input { padding-left: 54px; }
      .rdv-search-icon {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        opacity: 0.55;
        display: inline-flex;
      }
      .rdv-list {
        margin-top: 26px;
        display: flex;
        flex-direction: column;
        gap: 18px;
      }
      .appointment-card {
        border: 1px solid var(--line);
        border-radius: 28px;
        background: var(--surface-strong);
        padding: 28px 30px;
      }
      .appointment-header {
        display: flex;
        justify-content: space-between;
        gap: 24px;
        flex-wrap: wrap;
      }
      .appointment-info h4 {
        margin: 12px 0 8px;
        font-size: 1.8rem;
        line-height: 1.1;
      }
      .appointment-info p {
        margin: 0;
        color: var(--muted);
      }
      .appointment-info p strong { color: var(--text); }
      .appointment-meta {
        text-align: right;
        min-width: 220px;
      }
      .appointment-meta .rdv-date {
        margin: 0;
        color: var(--primary);
        font-weight: 800;
        font-size: 2rem;
      }
      .appointment-meta .rdv-time {
        margin: 6px 0 0;
        font-weight: 700;
        font-size: 1.9rem;
      }
      .appointment-meta .rdv-mode {
        margin: 10px 0 0;
        color: var(--muted);
        font-size: 1.25rem;
      }
      .appointment-footer {
        margin-top: 22px;
        padding-top: 18px;
        border-top: 1px solid var(--line);
        display: flex;
        justify-content: space-between;
        gap: 16px;
        align-items: center;
        flex-wrap: wrap;
      }
      .appointment-footer p {
        margin: 0;
        color: var(--muted);
        font-style: italic;
      }
      .status-badge {
        display: inline-flex;
        padding: 9px 18px;
        border-radius: 999px;
        font-size: 1.05rem;
        font-weight: 800;
        border: 1px solid transparent;
      }
      .status-badge.confirme {
        background: rgba(49, 180, 122, 0.16);
        border-color: rgba(49, 180, 122, 0.3);
        color: #2fb57b;
      }
      .status-badge.en-attente {
        background: rgba(255, 184, 77, 0.16);
        border-color: rgba(255, 184, 77, 0.3);
        color: #f5a623;
      }
      .status-badge.annule {
        background: rgba(239, 68, 68, 0.16);
        border-color: rgba(239, 68, 68, 0.3);
        color: #ef4444;
      }
      @media (max-width: 980px) {
        .rdv-filters { grid-template-columns: 1fr; }
        .appointment-meta { text-align: left; }
      }
    </style>
  </head>
  <body>
    <div class="page-shell">
      <header class="site-header">
        <div class="container nav-inner">
          <a class="brand" href="../index.php"><img class="brand-logo" src="../assets/media/secondvoice-logo.png" alt="SecondVoice logo" /></a>
          <button class="menu-toggle" type="button" data-menu-toggle aria-label="Ouvrir le menu">
            <span class="icon-lines"></span>
          </button>
          <div class="nav" data-nav>
            <nav>
              <ul class="nav-links">
                <li><a href="../index.php">Accueil</a></li>
                <li><a href="../about.php">A propos</a></li>
                <li><a href="../services.php">Services</a></li>
                <li><a class="is-active" href="mes_rendezvous.php">Mes Rendez-vous</a></li>
                <li><a href="../blog.php">Blog</a></li>
                <li><a href="../contact.php">Contact</a></li>
              </ul>
            </nav>
            <div class="header-actions">
              <button class="icon-btn theme-toggle" type="button" data-theme-toggle aria-label="Changer le theme">
                <span class="theme-toggle-label" data-theme-label>Clair</span>
              </button>
              <div class="user-shell" data-user-shell>
                <a class="icon-btn user-trigger" href="../login.php" aria-label="Ouvrir la page de connexion"><span>Profil</span></a>
              </div>
            </div>
          </div>
        </div>
      </header>

      <main>
        <section class="page-hero">
          <div class="container">
            <div class="rdv-hero-blank"></div>
          </div>
        </section>

        <div class="container">
          <div class="rdv-wrapper fade-up">
            <?php if ($success): ?>
              <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php include 'rendezvousList.php'; ?>

            <div style="margin-top: 2rem; display: flex; justify-content: center;">
              <a href="HomeRendezvous.php" class="btn btn-primary">Prendre un nouveau rendez-vous</a>
            </div>
          </div>
        </div>
      </main>

      <footer class="site-footer">
        <div class="container">
          <div class="footer-bottom">
            <p>&copy; 2026 SecondVoice. Tous droits reserves.</p>
          </div>
        </div>
      </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/main.js"></script>
    <script src="rendezvous.js?v=<?php echo time(); ?>"></script>
  </body>
</html>

