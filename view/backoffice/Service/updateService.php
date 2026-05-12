<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../controller/ServiceHttpController.php';

(new ServiceHttpController())->handleUpdate($_POST);

