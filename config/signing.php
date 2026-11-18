<?php

declare(strict_types=1);

return [
    'secret' => env_value('MYRSU_SIGNING_SECRET') ?? throw new RuntimeException('Configurazione firma documenti incompleta: impostare MYRSU_SIGNING_SECRET.'),
];
