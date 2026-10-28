<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

chatRequireMethod('GET');
$user = chatRequireUser();

try {
    $conversations = loadChatConversations(db(), (int) $user['id']);
    chatJsonResponse(200, ['ok' => true, 'conversations' => $conversations]);
} catch (Throwable $exception) {
    error_log('Chat conversation list failed: ' . $exception->getMessage());
    chatErrorResponse(500, 'server_error', 'The conversation list could not be loaded.');
}
