<?php

declare(strict_types=1);

return [
    'host' => env_value('DB_HOST', '127.0.0.1'),
    'database' => env_value('DB_DATABASE', 'myrsu'),
    'username' => env_value('DB_USERNAME', 'root'),
    'password' => env_value('DB_PASSWORD', ''),
    'charset' => env_value('DB_CHARSET', 'utf8mb4'),
];
