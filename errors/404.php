<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

renderErrorPage(404, 'Page not found', 'The page you requested does not exist.');
