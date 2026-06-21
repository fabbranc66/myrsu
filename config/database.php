<?php

declare(strict_types=1);

$hostName = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$isHosting = in_array($hostName, ['kr-solutions.it', 'www.kr-solutions.it'], true);

return [
    'host' => env_value('DB_HOST', $isHosting ? '31.11.39.231' : '127.0.0.1'),
    'database' => env_value('DB_DATABASE', $isHosting ? 'Sql1874742_5' : 'myrsu'),
    'username' => env_value('DB_USERNAME', $isHosting ? 'Sql1874742' : 'root'),
    'password' => env_value('DB_PASSWORD', $isHosting ? '@GenniH264rgnm' : ''),
    'charset' => env_value('DB_CHARSET', 'utf8mb4'),
];
