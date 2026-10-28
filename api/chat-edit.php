<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

chatRequireMethod('POST');
$user = chatRequireUser();
$data = chatRequestData();
chatRequireCsrf($data);

try {
    $messageId = chatPositiveId($data['message_id'] ?? null, 'message');
    $body = normalizeChatBody($data['body'] ?? '');
    editChatMessage(db(), $messageId, (int) $user['id'], $body);
    chatJsonResponse(200, ['ok' => true]);
} catch (ChatException $exception) {
    chatErrorResponse($exception->statusCode, 'edit_not_allowed', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Chat edit failed: ' . $exception->getMessage());
    chatErrorResponse(500, 'server_error', 'The message could not be edited.');
}
