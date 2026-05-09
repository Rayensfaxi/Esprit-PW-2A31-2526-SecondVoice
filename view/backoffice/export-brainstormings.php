<?php
declare(strict_types=1);

require_once __DIR__ . '/../../controller/ExportController.php';

$exportController = new ExportController();
$exportController->exportBrainstormingsToExcel();
