<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

renderErrorPage(500, 'Something went wrong', 'The application could not complete this request. Please try again later.');
