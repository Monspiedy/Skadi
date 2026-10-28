<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

chatRequireMethod('POST');
$user = chatRequireUser();
$data = $_POST;
chatRequireCsrf($data);

try {
    $otherUserId = chatPositiveId($data['user_id'] ?? null, 'friend');
    $body = normalizeChatBody($data['body'] ?? '');
    $attachment = validateChatAttachment(isset($_FILES['attachment']) && is_array($_FILES['attachment'])
        ? $_FILES['attachment'] : null);
    $messageId = sendChatMessage(db(), (int) $user['id'], $otherUserId, $body, $attachment);
    chatJsonResponse(201, ['ok' => true, 'message_id' => $messageId]);
} catch (ChatException $exception) {
    $code = $exception->statusCode === 429 ? 'rate_limited' : 'invalid_message';
    chatErrorResponse($exception->statusCode, $code, $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Chat send failed: ' . $exception->getMessage());
    chatErrorResponse(500, 'server_error', 'The message could not be sent.');
}
