<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

renderErrorPage(403, 'Access denied', 'You do not have permission to view this page.');
