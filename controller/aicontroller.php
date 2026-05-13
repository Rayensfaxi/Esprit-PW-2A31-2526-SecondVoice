<?php

class AIController 
{
    private $apiKey;
    private $apiUrl = 'https://api.groq.com/openai/v1/chat/completions';
    private $model = 'llama-3.1-8b-instant'; // ou 'mixtral-8x7b-32768', 'gemma-7b-it'
    
    public function __construct($apiKey = null)
{
    $this->apiKey = $apiKey ?? Config::getApiKey();
}
    
    // ==================== JUSTIFICATION ====================
    
    public function generateJustification($description, $statut, $ancienStatut = null, $chatHistory = '')
{
    $contexte = $ancienStatut 
        ? "Passage de '$ancienStatut' à '$statut'."
        : "Statut : '$statut'.";
    
    $prompt = "Justification du traitement (1-2 phrases) :
    
RÉCLAMATION : $description
$contexte";

    // Ajouter le chat s'il existe
    if (!empty($chatHistory)) {
        $prompt .= "\n\nCONVERSATION :\n$chatHistory";
    }
    
    $prompt .= "\n\nExpliquer brièvement la décision prise :";
    
    return $this->callAPI($prompt);
}
    
    // ==================== RÉSUMÉ CHAT ====================
    
    public function summarizeChat($reponses, $clientId)
{
    if (empty($reponses)) {
        return "Aucune conversation pour le moment.";
    }
    
    $conversation = "";
    foreach ($reponses as $reponse) {
        $role = ($reponse->getId_user() == $clientId) ? 'Client' : 'Assistant';
        $conversation .= "$role: " . $reponse->getContenu() . "\n";
    }
    
    $prompt = "Résume cette conversation support client en 3-4 phrases clés.

CONVERSATION :
$conversation

POINTS À IDENTIFIER :
1. Problème principal du client
2. Réponses/actions de l'assistant
3. État actuel (résolu/en cours/bloqué)
4. Prochaine étape recommandée

Résumé :";
    
    return $this->callAPI($prompt);
}
    
    // ==================== SUGGESTION RÉPONSE ====================
    
    public function suggestReply($conversation, $lastMessage)
    {
        $prompt = "Tu es un assistant clientèle empathique et professionnel.
Propose une réponse à ce message client.

HISTORIQUE :
$conversation

DERNIER MESSAGE CLIENT :
$lastMessage

Règles :
- 2-3 phrases maximum
- Ton empathique mais professionnel
- Propose une solution concrète

Réponse proposée :";
        
        return $this->callAPI($prompt);
    }
    
    // ==================== APPEL API GROQ ====================
    
    private function callAPI($prompt)
    {
        $ch = curl_init($this->apiUrl);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'Tu es un assistant administratif intelligent et professionnel.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => 500,
                'temperature' => 0.3
            ])
        ]);
        
        $result = curl_exec($ch);
        
        if (curl_errno($ch)) {
            curl_close($ch);
            return 'Erreur de connexion: ' . curl_error($ch);
        }
        
        curl_close($ch);
        
        $data = json_decode($result, true);
        
        if (isset($data['choices'][0]['message']['content'])) {
            return trim($data['choices'][0]['message']['content']);
        }
        
        if (isset($data['error']['message'])) {
            return 'Erreur API: ' . $data['error']['message'];
        }
        
        return 'Erreur de génération';
    }
}
?>