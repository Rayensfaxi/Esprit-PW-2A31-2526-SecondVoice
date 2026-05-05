<?php
require_once '../../controller/ExportController.php';

$exportController = new ExportController();
$exportController->exportBrainstormingsToExcel();
?>