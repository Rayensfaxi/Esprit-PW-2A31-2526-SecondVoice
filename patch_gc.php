<?php
$c = file_get_contents("controller/GoalController.php");
$c = preg_replace("/\}\s*public function getAcceptedGoalsForAssistant/is", "\n    public function getAcceptedGoalsForAssistant", $c);
$c = str_replace("\x27\x27accepte\x27\x27", "\x27accepte\x27", $c);
file_put_contents("controller/GoalController.php", $c);
?>
