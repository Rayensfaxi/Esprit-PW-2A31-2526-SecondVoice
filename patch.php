<?php
$content = file_get_contents("controller/GuideController.php");
// Strip bad generated text starting from the first "public function getAllGuides"
$pos = strpos($content, "public function getAllGuides");
if ($pos !== false) $content = substr($content, 0, $pos);
// Ensure it ends cleanly
$content = rtrim($content) . "\n\n";

$newMethods = <<<EOT
    public function getAllGuides() {
        \$db = Config::getConnexion();
        \$sql = "SELECT g.*, go.description as goal_desc, u.nom, u.prenom FROM guides g JOIN goals go ON g.goal_id = go.id JOIN utilisateurs u ON go.user_id = u.id ORDER BY g.created_at DESC";
        try {
            \$req = \$db->prepare(\$sql);
            \$req->execute();
            return \$req->fetchAll();
        } catch (Exception \$e) {
            die("Error:" . \$e->getMessage());
        }
    }

    public function getGuidesByAssistant(\$assistant_id) {
        \$db = Config::getConnexion();
        \$sql = "SELECT g.*, go.description as goal_desc, u.nom, u.prenom FROM guides g JOIN goals go ON g.goal_id = go.id JOIN utilisateurs u ON go.user_id = u.id WHERE go.selected_assistant_id = :aid AND go.status = 'accepte' ORDER BY g.created_at DESC";
        try {
            \$req = \$db->prepare(\$sql);
            \$req->execute(["aid" => \$assistant_id]);
            return \$req->fetchAll();
        } catch (Exception \$e) {
            die("Error:" . \$e->getMessage());
        }
    }

    public function getGuidesByUser(\$user_id) {
        \$db = Config::getConnexion();
        \$sql = "SELECT g.*, go.description as goal_desc, u.nom, u.prenom as assistant_prenom FROM guides g JOIN goals go ON g.goal_id = go.id LEFT JOIN utilisateurs u ON go.selected_assistant_id = u.id WHERE go.user_id = :uid ORDER BY g.created_at DESC";
        try {
            \$req = \$db->prepare(\$sql);
            \$req->execute(["uid" => \$user_id]);
            return \$req->fetchAll();
        } catch (Exception \$e) {
            die("Error:" . \$e->getMessage());
        }
    }
}
EOT;

file_put_contents("controller/GuideController.php", $content . $newMethods);
?>
