<?php
require_once '../../../controller/RendezvousC.php';
require_once '../../../model/Rendezvous.php';

$rendezvousC = new RendezvousC();
$id_citoyen = 1; // Simulé pour l'exemple

if (isset($_POST['save_rdv'])) {
    $id = $_POST['id'] ?? null;
    $service = $_POST['service'] ?? '';
    $assistant = $_POST['assistant'] ?? '';
    $date_rdv = $_POST['date_rdv'] ?? '';
    $heure_rdv = $_POST['heure_rdv'] ?? '';
    $mode = $_POST['mode'] ?? '';
    $remarques = $_POST['remarques'] ?? '';

    if (!empty($service) && !empty($assistant) && !empty($date_rdv) && !empty($heure_rdv) && !empty($mode)) {
        $date_selectionnee = new DateTime($date_rdv);
        $aujourdhui = new DateTime();
        $aujourdhui->setTime(0, 0, 0);

        if ($date_selectionnee < $aujourdhui) {
            header("Location: HomeRendezvous.php?error=" . urlencode("La date du rendez-vous ne peut pas être dans le passé."));
            exit();
        } else {
            $heure_time = strtotime($heure_rdv);
            $start_time = strtotime('08:10');
            $end_time = strtotime('17:30');
            $word_count = !empty(trim($remarques)) ? preg_match_all('/\S+/', $remarques) : 0;

            if ($heure_time < $start_time || $heure_time > $end_time) {
                header("Location: HomeRendezvous.php?error=" . urlencode("L'heure doit être comprise entre 08:10 et 17:30."));
                exit();
            } elseif ($id && ($currentRdv = $rendezvousC->getRendezvousById($id)) && $currentRdv['statut'] == 'Annulé') {
                header("Location: HomeRendezvous.php?error=" . urlencode("Impossible de modifier un rendez-vous annulé."));
                exit();
            } elseif (empty(trim($remarques))) {
                header("Location: HomeRendezvous.php?error=" . urlencode("Le champ Remarques est obligatoire."));
                exit();
            } elseif ($word_count > 50) {
                header("Location: HomeRendezvous.php?error=" . urlencode("Les remarques ne doivent pas dépasser 50 mots."));
                exit();
            } else {
                $rendezvous = new Rendezvous(
                    $id,
                    $id_citoyen,
                    htmlspecialchars($service),
                    htmlspecialchars($assistant),
                    $date_selectionnee,
                    htmlspecialchars($heure_rdv),
                    htmlspecialchars($mode),
                    htmlspecialchars($remarques),
                    $id ? ($_POST['statut'] ?? 'En attente') : 'En attente'
                );

                if ($id) {
                    $rendezvousC->updateRendezvous($rendezvous, $id);
                    header("Location: HomeRendezvous.php?success=" . urlencode("Votre rendez-vous a été modifié avec succès."));
                    exit();
                } else {
                    try {
                        $rendezvousC->addRendezvous($rendezvous);
                        header("Location: HomeRendezvous.php?success=" . urlencode("Votre rendez-vous a été enregistré avec succès."));
                        exit();
                    } catch (Exception $e) {
                        header("Location: HomeRendezvous.php?error=" . urlencode("Erreur lors de l'enregistrement : " . $e->getMessage()));
                        exit();
                    }
                }
            }
        }
    } else {
        header("Location: HomeRendezvous.php?error=" . urlencode("Tous les champs obligatoires doivent être remplis."));
        exit();
    }
} else {
    header("Location: HomeRendezvous.php");
    exit();
}
?>
