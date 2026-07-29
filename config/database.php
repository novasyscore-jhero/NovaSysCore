<?php

return [

    'host' => $_ENV['DB_HOST'] ?? 'localhost',

    'database' => $_ENV['DB_DATABASE'] ?? '',

    'username' => $_ENV['DB_USERNAME'] ?? 'root',

    'password' => $_ENV['DB_PASSWORD'] ?? '',

];
