<?php require 'config.php'; print_r(Config::getConnexion()->query('DESCRIBE guides')->fetchAll()); ?>
