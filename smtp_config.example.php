<?php
declare(strict_types=1);

return [
    // Gmail exige la validation en 2 etapes + un mot de passe d'application.
    // N'utilisez pas le mot de passe normal du compte Gmail ici.
    'host' => 'smtp.gmail.com',
    'username' => 'votre-adresse-gmail-complete@gmail.com',
    'password' => 'mot-de-passe-application-google',
    'port' => 587,
    'encryption' => 'tls',
    'from_email' => 'votre-adresse-gmail-complete@gmail.com',
    'from_name' => 'SecondVoice',
    'debug' => 0,
];
