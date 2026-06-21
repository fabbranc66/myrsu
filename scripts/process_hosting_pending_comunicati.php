<?php

declare(strict_types=1);

use App\Core\Application;

require __DIR__ . '/../app/Core/helpers.php';
require __DIR__ . '/../vendor/autoload.php';

$app = new Application(dirname(__DIR__));
$result = $app->pendingComunicatoQueue->process();
foreach ($result['items'] as $item) {
    echo $item['status'] . ' ' . $item['id'] . PHP_EOL;
}
