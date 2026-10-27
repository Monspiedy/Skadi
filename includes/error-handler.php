<?php

declare(strict_types=1);

function initializeErrorHandling(array $appConfig): void
{
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', SKADI_ROOT . '/storage/logs/php-error.log');

    set_error_handler(static function (
        int $severity,
        string $message,
        string $file,
        int $line
    ): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    set_exception_handler(static function (Throwable $exception) use ($appConfig): void {
        error_log(sprintf(
            '[%s] Unhandled %s: %s in %s:%d%s%s',
            $appConfig['environment'],
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            PHP_EOL,
            $exception->getTraceAsString()
        ));

        renderErrorPage(
            500,
            'Something went wrong',
            'The application could not complete this request. Please try again later.'
        );
    });

    register_shutdown_function(static function (): void {
        $error = error_get_last();
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];

        if ($error === null || !in_array($error['type'], $fatalTypes, true)) {
            return;
        }

        error_log(sprintf(
            'Fatal error: %s in %s:%d',
            $error['message'],
            $error['file'],
            $error['line']
        ));

        if (!headers_sent()) {
            http_response_code(500);
        }
    });
}

function renderErrorPage(int $statusCode, string $heading, string $message): never
{
    http_response_code($statusCode);
    $pageTitle = $statusCode . ' - ' . $heading;

    require SKADI_ROOT . '/includes/header.php';
    ?>
    <section class="status-page" aria-labelledby="error-heading">
        <p class="status-code"><?= escape((string) $statusCode) ?></p>
        <h1 id="error-heading"><?= escape($heading) ?></h1>
        <p><?= escape($message) ?></p>
        <a class="button-link" href="<?= escape(baseUrl()) ?>">Return to the home page</a>
    </section>
    <?php
    require SKADI_ROOT . '/includes/footer.php';
    exit;
}
