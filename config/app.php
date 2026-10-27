<?php

declare(strict_types=1);

return [
    'name' => 'Skadi',
    'environment' => getenv('SKADI_ENV') ?: 'development',
    'timezone' => 'Europe/Helsinki',
    'base_path' => '/Skadi',
    'session_name' => 'skadi_session',
];
