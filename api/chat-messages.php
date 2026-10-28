<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

chatRequireMethod('POST');
$user = chatRequireUser();
$data = chatRequestData();
chatRequireCsrf($data);

try {
    $otherUserId = chatPositiveId($data['user_id'] ?? null, 'friend');
    $friend = assertChatAvailable(db(), (int) $user['id'], $otherUserId);
    $result = loadChatMessages(db(), (int) $user['id'], $otherUserId);
    chatJsonResponse(200, [
        'ok' => true,
        'friend' => [
            'user_id' => (int) $friend['id'],
            'skadi_id' => (string) $friend['skadi_id'],
            'display_name' => (string) $friend['display_name'],
        ],
        'conversation_id' => $result['conversation_id'],
        'messages' => $result['messages'],
    ]);
} catch (ChatException $exception) {
    chatErrorResponse($exception->statusCode, 'chat_unavailable', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Chat messages failed: ' . $exception->getMessage());
    chatErrorResponse(500, 'server_error', 'Messages could not be loaded.');
}
