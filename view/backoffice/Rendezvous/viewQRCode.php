<?php
require_once '../../../controller/RendezvousC.php';

if (!isset($_GET['id'])) {
    die("ID de rendez-vous manquant.");
}

$rendezvousC = new RendezvousC();
$rdv = $rendezvousC->getRendezvousById($_GET['id']);

if (!$rdv) {
    die("Rendez-vous introuvable.");
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Rendez-vous #<?php echo $rdv->getId(); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <style>
        :root {
            --bg: #070b16;
            --panel: #111728;
            --text: #f5f7fb;
            --muted: #96a0b8;
            --purple: #635bff;
        }
        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg);
            color: var(--text);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            background-color: var(--panel);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            max-width: 400px;
            width: 100%;
        }
        h1 {
            font-size: 1.5rem;
            margin-bottom: 20px;
            text-align: center;
            color: var(--purple);
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .info-label {
            color: var(--muted);
            font-weight: 500;
        }
        .info-value {
            font-weight: 600;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
        }
        .status-confirmé { background: rgba(49, 208, 170, 0.2); color: #31d0aa; }
        .status-en-attente { background: rgba(255, 184, 77, 0.2); color: #ffb84d; }
        .status-annulé { background: rgba(255, 107, 107, 0.2); color: #ff6b6b; }
        .footer {
            margin-top: 30px;
            text-align: center;
        }
        .btn {
            background: var(--purple);
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            transition: opacity 0.2s;
        }
        .btn:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Détails du Rendez-vous</h1>
        
        <div class="info-row">
            <span class="info-label">ID</span>
            <span class="info-value">#<?php echo $rdv->getId(); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Citoyen</span>
            <span class="info-value">ID #<?php echo $rdv->getIdCitoyen(); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Service</span>
            <span class="info-value"><?php echo $rdv->getService(); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Assistant</span>
            <span class="info-value"><?php echo $rdv->getAssistant(); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Date</span>
            <span class="info-value"><?php echo $rdv->getDateRdv()->format('d/m/Y'); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Heure</span>
            <span class="info-value"><?php echo $rdv->getHeureRdv(); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Statut</span>
            <span class="info-value">
                <span class="status status-<?php echo strtolower(str_replace(' ', '-', $rdv->getStatut())); ?>">
                    <?php echo $rdv->getStatut(); ?>
                </span>
            </span>
        </div>
        
        <?php if ($rdv->getRemarques()): ?>
        <div style="margin-top: 20px;">
            <span class="info-label" style="display: block; margin-bottom: 8px;">Remarques</span>
            <div style="background: rgba(255,255,255,0.05); padding: 12px; border-radius: 10px; font-size: 0.9rem;">
                <?php echo nl2br(htmlspecialchars($rdv->getRemarques())); ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="footer">
            <a href="HomeRendezvous.php" class="btn">Retour au Dashboard</a>
        </div>
    </div>
</body>
</html>
