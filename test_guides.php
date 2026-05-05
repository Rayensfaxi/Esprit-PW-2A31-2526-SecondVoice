<?php require 'config.php'; echo json_encode(Config::getConnexion()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)); ?>
