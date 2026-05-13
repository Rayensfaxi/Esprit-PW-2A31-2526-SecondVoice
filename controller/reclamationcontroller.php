<?php
include_once(__DIR__ . '/../config.php');
include(__DIR__ . '/../model/reclamation.php');

class ReclamationController 
{
    public function addReclamation(Reclamation $reclamation)
    {
        $sql = "INSERT INTO reclamation (description, date_creation, statut, id_user) 
                VALUES (:description, :date_creation, :statut, :id_user)";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute([
                'description' => $reclamation->getDescription(),
                'date_creation' => $reclamation->getDate_creation(),
                'statut' => $reclamation->getStatut(),
                'id_user' => $reclamation->getId_user(),
            ]);
            //return $db->lastInsertId();
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            return false;
        }
    }

    /*public function listReclamations($ord)
    {
        $sql = "SELECT * FROM reclamation ORDER BY :ord";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['ord'=>$ord]);
            $reclamationsData = $query->fetchAll();

            $reclamations = [];
            foreach ($reclamationsData as $row) { 
                $reclamation = new Reclamation(
                    $row['description'], 
                    $row['date_creation'], 
                    $row['statut'], 
                    $row['id_user']
                );
                $reclamation->setId_reclamation($row['id_reclamation']);
                $reclamations[] = $reclamation;
            }
            return $reclamations;
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            return [];
        }
    }*/
    public function listReclamations($ordre = 'date_desc', $search = '')
    {
        $db = Config::getConnexion();
        
        // ORDER BY avec switch (compatible PHP 7)
        switch ($ordre) {
            case 'date_asc':
                $orderBy = 'r.date_creation ASC';
                break;
            case 'statut':
                $orderBy = "FIELD(r.statut, 'en_cours', 'en_attente', 'resolu', 'rejete'), r.date_creation DESC";
                break;
            case 'id_asc':
                $orderBy = 'r.id_reclamation ASC';
                break;
            case 'id_desc':
                $orderBy = 'r.id_reclamation DESC';
                break;
            // ===== NOUVEAUX CAS POUR PRIORITÉ =====
            case 'priorite_desc':
                $orderBy = "FIELD(COALESCE(p.niveau, 'moyenne'), 'critique', 'haute', 'moyenne', 'faible'), COALESCE(p.score, 50) DESC, r.date_creation DESC";
                break;
            case 'priorite_asc':
                $orderBy = "FIELD(COALESCE(p.niveau, 'moyenne'), 'faible', 'moyenne', 'haute', 'critique'), COALESCE(p.score, 50) ASC, r.date_creation DESC";
                break;
            case 'date_desc':
            default:
                $orderBy = 'r.date_creation DESC';
                break;
        }
        
        // WHERE et params
        $where = '';
        $params = [];
        
        if (!empty($search)) {
            if (is_numeric($search)) {
                $where = "WHERE r.id_reclamation = :id OR u.nom LIKE :search";
                $params['id'] = (int)$search;
                $params['search'] = '%' . $search . '%';
            } else {
                $where = "WHERE u.nom LIKE :search OR r.description LIKE :search";
                $params['search'] = '%' . $search . '%';
            }
        }
        
        // ===== AJOUT DU LEFT JOIN priorite_reclamation =====
        $sql = "SELECT r.*, u.nom, p.niveau, p.score, p.raison_ia
                FROM reclamation r
                LEFT JOIN utilisateur u ON r.id_user = u.id
                LEFT JOIN priorite_reclamation p ON r.id_reclamation = p.id_reclamation
                $where
                ORDER BY $orderBy";
        
        try {
            $query = $db->prepare($sql);
            $query->execute($params);
            $rows = $query->fetchAll();
            
            $reclamations = [];
            foreach ($rows as $row) {
                $reclamation = new Reclamation(
                    $row['description'],
                    $row['date_creation'],
                    $row['statut'],
                    $row['id_user']
                );
                $reclamation->setId_reclamation($row['id_reclamation']);
                $reclamation->client_nom = $row['nom'] ?? 'Inconnu';
                
                // ===== AJOUT DES DONNÉES DE PRIORITÉ =====
                $reclamation->priorite_niveau = $row['niveau'] ?? 'moyenne';
                $reclamation->priorite_score = $row['score'] ?? 50;
                $reclamation->priorite_raison = $row['raison_ia'] ?? '';
                
                $reclamations[] = $reclamation;
            }
            return $reclamations;
            
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            return [];
        }
    }

    public function getReclamationById($id)
    {
        $sql = "SELECT * FROM reclamation WHERE id_reclamation = :id";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['id' => $id]);
            $row = $query->fetch();
            
            if ($row) {
                $reclamation = new Reclamation(
                    $row['description'], 
                    $row['date_creation'], 
                    $row['statut'], 
                    $row['id_user']
                );
                $reclamation->setId_reclamation($row['id_reclamation']);
                return $reclamation;
            }
            return null;
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            return null;
        }
    }

    public function deleteReclamation($id)
    {
        $sql = "DELETE FROM reclamation WHERE id_reclamation = :id";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['id' => $id]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function updateReclamation($id, $description, $statut, $id_user)
    {
        $sql = "UPDATE reclamation 
                SET description = :description,
                    statut = :statut,
                    id_user = :id_user
                WHERE id_reclamation = :id";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute([
                'id' => $id,
                'description' => $description,
                'statut' => $statut,
                'id_user' => $id_user
            ]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function changeStatut($id, $nouveauStatut)
    {
        $sql = "UPDATE reclamation SET statut = :statut WHERE id_reclamation = :id";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute([
                'id' => $id,
                'statut' => $nouveauStatut
            ]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    public function getReclamationsByUser($id_user)
    {
        $db = Config::getConnexion();
        
        try {
            // 1. Récupérer en_cours
            $sql1 = "SELECT * FROM reclamation 
                     WHERE id_user = :id_user AND statut = 'en_cours' 
                     ORDER BY date_creation DESC";
            $query1 = $db->prepare($sql1);
            $query1->execute(['id_user' => $id_user]);
            $enCours = $query1->fetchAll();
            
            // 2. Récupérer en_attente
            $sql2 = "SELECT * FROM reclamation 
                     WHERE id_user = :id_user AND statut = 'en_attente' 
                     ORDER BY date_creation DESC";
            $query2 = $db->prepare($sql2);
            $query2->execute(['id_user' => $id_user]);
            $enAttente = $query2->fetchAll();
            
            // 3. Fusionner
            $toutes = array_merge($enCours, $enAttente);
            
            // 4. Convertir en objets
            $reclamations = [];
            foreach ($toutes as $row) {
                $reclamation = new Reclamation(
                    $row['description'],
                    $row['date_creation'],
                    $row['statut'],
                    $row['id_user']
                );
                $reclamation->setId_reclamation($row['id_reclamation']);
                $reclamations[] = $reclamation;
            }
            
            return $reclamations;
            
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            return [];
        }
    }
}
?>