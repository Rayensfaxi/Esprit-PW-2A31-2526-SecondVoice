<?php
$content = file_get_contents("controller/GuideController.php");
$content = str_replace("'accepte'", "\"accepte\"", $content);
file_put_contents("controller/GuideController.php", $content);
?>
