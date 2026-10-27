<?php

declare(strict_types=1);

function addFlashMessage(string $type, string $message): void
{
    $allowedTypes = ['success', 'info', 'warning', 'error'];
    $safeType = in_array($type, $allowedTypes, true) ? $type : 'info';

    $_SESSION['flash_messages'][] = [
        'type' => $safeType,
        'message' => $message,
    ];
}

function consumeFlashMessages(): array
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);

    return is_array($messages) ? $messages : [];
}
