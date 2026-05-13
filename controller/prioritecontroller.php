<?php
include_once(__DIR__ . '/../config.php');
include_once(__DIR__ . '/aicontroller.php');

class PrioriteController 
{
    private $aiController;
    
    public function __construct()
    {
        $this->aiController = new AIController();
    }
    
    /**
     * Analyse une réclamation avec l'IA et retourne la priorité
     */
    public function analyserPriorite($id_reclamation, $description, $statut)
    {
        $prompt = "Analyse cette réclamation administrative et attribue une priorité.
        
RÈGLES :
- CRITIQUE (90-100) : Urgence vitale, sécurité, service essentiel coupé, menace légale, danger
- HAUTE (70-89) : Problème important, retard significatif, client très mécontent, impact travail
- MOYENNE (40-69) : Problème standard, question administrative, demande d'information
- FAIBLE (0-39) : Suggestion, amélioration mineure, question générale

RÉCLAMATION :
Description : $description
Statut : $statut

Réponds UNIQUEMENT en JSON strict :
{\"niveau\":\"critique|haute|moyenne|faible\",\"score\":75,\"raison\":\"explication courte\"}";

        $response = $this->aiController->callAPI($prompt, 200, 0.2);
        
        // Extraction JSON
        $data = $this->extraireJson($response);
        
        if (!$data || !isset($data['niveau'])) {
            return $this->prioriteParDefaut($description);
        }
        
        return [
            'niveau' => $data['niveau'],
            'score' => min(100, max(0, (int)($data['score'] ?? 50))),
            'raison' => $data['raison'] ?? 'Analyse IA'
        ];
    }
    
    /**
     * Sauvegarde la priorité en base (table à créer)
     */
    public function sauvegarderPriorite($id_reclamation, $analyse)
    {
        $db = Config::getConnexion();
        
        try {
            // Vérifier si existe déjà
            $check = $db->prepare("SELECT id FROM priorite_reclamation WHERE id_reclamation = ?");
            $check->execute([$id_reclamation]);
            
            if ($check->fetch()) {
                // Update
                $sql = "UPDATE priorite_reclamation 
                        SET niveau = ?, score = ?, raison_ia = ?, date_analyse = NOW() 
                        WHERE id_reclamation = ?";
                $stmt = $db->prepare($sql);
                return $stmt->execute([
                    $analyse['niveau'], 
                    $analyse['score'], 
                    $analyse['raison'], 
                    $id_reclamation
                ]);
            } else {
                // Insert
                $sql = "INSERT INTO priorite_reclamation 
                        (id_reclamation, niveau, score, raison_ia, date_analyse) 
                        VALUES (?, ?, ?, ?, NOW())";
                $stmt = $db->prepare($sql);
                return $stmt->execute([
                    $id_reclamation,
                    $analyse['niveau'],
                    $analyse['score'],
                    $analyse['raison']
                ]);
            }
        } catch (Exception $e) {
            error_log("Erreur priorité: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Récupère la priorité d'une réclamation
     */
    public function getPriorite($id_reclamation)
    {
        $db = Config::getConnexion();
        
        try {
            $sql = "SELECT * FROM priorite_reclamation WHERE id_reclamation = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$id_reclamation]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Extrait le JSON de la réponse IA
     */
    private function extraireJson($texte)
    {
        // Cherche JSON entre accolades
        if (preg_match('/\{.*\}/s', $texte, $matches)) {
            return json_decode($matches[0], true);
        }
        return json_decode($texte, true);
    }
    
    /**
     * Priorité par défaut si l'IA échoue
     */
    private function prioriteParDefaut($description)
    {
        $desc = strtolower($description);
        $urgent = ['urgent', 'critique', 'immédiat', 'danger', 'coupé', 'plainte'];
        $important = ['problème', 'retard', 'erreur', 'impossible', 'bloqué'];
        
        foreach ($urgent as $mot) {
            if (strpos($desc, $mot) !== false) {
                return ['niveau' => 'haute', 'score' => 80, 'raison' => 'Mot-clé détecté: ' . $mot];
            }
        }
        foreach ($important as $mot) {
            if (strpos($desc, $mot) !== false) {
                return ['niveau' => 'moyenne', 'score' => 55, 'raison' => 'Mot-clé détecté: ' . $mot];
            }
        }
        
        return ['niveau' => 'moyenne', 'score' => 50, 'raison' => 'Analyse par défaut'];
    }
}