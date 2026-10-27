<?php

declare(strict_types=1);

return [
    'name' => 'Skadi',
    'environment' => getenv('SKADI_ENV') ?: 'development',
    'timezone' => 'Europe/Helsinki',
    'base_path' => '/Skadi',
    'session_name' => 'skadi_session',
    'session_idle_timeout' => 1800,
    'activity_update_interval' => 60,
];
