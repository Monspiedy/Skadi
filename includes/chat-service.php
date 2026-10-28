<?php

declare(strict_types=1);

const CHAT_MAX_BODY_LENGTH = 2000;
const CHAT_MAX_ATTACHMENT_BYTES = 5242880;
const CHAT_SEND_INTERVAL_SECONDS = 2;

class ChatException extends RuntimeException
{
    public function __construct(string $message, public readonly int $statusCode = 422)
    {
        parent::__construct($message);
    }
}

function chatJsonResponse(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function chatErrorResponse(int $statusCode, string $code, string $message): never
{
    chatJsonResponse($statusCode, [
        'ok' => false,
        'error' => ['code' => $code, 'message' => $message],
    ]);
}

function chatRequireUser(): array
{
    $user = currentUser();
    if ($user === null || !empty($user['must_change_password'])) {
        chatErrorResponse(401, 'authentication_required', 'Sign in to use chat.');
    }

    return $user;
}

function chatRequireMethod(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
        header('Allow: ' . $method);
        chatErrorResponse(405, 'method_not_allowed', 'Method not allowed.');
    }
}

function chatRequestData(): array
{
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : [];
    }

    return $_POST;
}

function chatRequireCsrf(array $data): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($data['csrf_token'] ?? null);
    if (!is_string($token) || !isValidCsrfToken($token)) {
        chatErrorResponse(403, 'invalid_csrf', 'The security token expired. Refresh and try again.');
    }
}

function chatPositiveId(mixed $value, string $label): int
{
    $filtered = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($filtered === false) {
        throw new ChatException('Choose a valid ' . $label . '.', 422);
    }

    return (int) $filtered;
}

function assertChatAvailable(PDO $connection, int $userId, int $otherUserId): array
{
    if ($userId === $otherUserId) {
        throw new ChatException('Choose another Skadi user.', 422);
    }
    if (isUserPairBlocked($connection, $userId, $otherUserId)
        || !areAcceptedFriends($connection, $userId, $otherUserId)) {
        throw new ChatException('Chat is available only between current unblocked friends.', 403);
    }

    $statement = $connection->prepare(
        'SELECT u.id, u.skadi_id, u.display_name, up.avatar_path '
        . 'FROM users u INNER JOIN user_profiles up ON up.user_id = u.id '
        . 'WHERE u.id = :user_id AND u.status = :status LIMIT 1'
    );
    $statement->execute(['user_id' => $otherUserId, 'status' => 'active']);
    $friend = $statement->fetch();
    if (!is_array($friend)) {
        throw new ChatException('This friend is no longer available.', 403);
    }

    return $friend;
}

function assertChatAvailableForUpdate(PDO $connection, int $userId, int $otherUserId): void
{
    [$lowId, $highId] = lockSocialUsers($connection, $userId, $otherUserId);
    $block = lockedPairBlock($connection, $lowId, $highId);
    $friendship = lockFriendship($connection, $lowId, $highId);
    if ($block !== null
        || $friendship === null || $friendship['status'] !== 'accepted') {
        throw new ChatException('Chat is available only between current unblocked friends.', 403);
    }
}

function findConversation(PDO $connection, int $userId, int $otherUserId): ?array
{
    [$lowId, $highId] = canonicalUserPair($userId, $otherUserId);
    $statement = $connection->prepare(
        'SELECT id, user_low_id, user_high_id, created_at, updated_at FROM conversations '
        . 'WHERE user_low_id = :low_id AND user_high_id = :high_id LIMIT 1'
    );
    $statement->execute(['low_id' => $lowId, 'high_id' => $highId]);
    $conversation = $statement->fetch();

    return is_array($conversation) ? $conversation : null;
}

function findOrCreateConversation(PDO $connection, int $userId, int $otherUserId): array
{
    [$lowId, $highId] = canonicalUserPair($userId, $otherUserId);
    $statement = $connection->prepare(
        'INSERT INTO conversations (user_low_id, user_high_id) VALUES (:low_id, :high_id) '
        . 'ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
    );
    $statement->execute(['low_id' => $lowId, 'high_id' => $highId]);

    $conversation = findConversation($connection, $userId, $otherUserId);
    if ($conversation === null) {
        throw new ChatException('The conversation could not be opened.', 500);
    }

    return $conversation;
}

function loadChatConversations(PDO $connection, int $userId): array
{
    $statement = $connection->prepare(
        "SELECT u.id AS other_user_id, u.skadi_id, u.display_name, up.avatar_path,
            c.id AS conversation_id, c.updated_at,
            (SELECT m.body FROM messages m
             LEFT JOIN message_user_states own_state ON own_state.message_id = m.id AND own_state.user_id = :snippet_user_id
             WHERE m.conversation_id = c.id AND own_state.hidden_at IS NULL
             ORDER BY m.id DESC LIMIT 1) AS last_body,
            (SELECT COUNT(*) FROM messages unread_message
             LEFT JOIN message_user_states unread_state
               ON unread_state.message_id = unread_message.id AND unread_state.user_id = :unread_user_id
             WHERE unread_message.conversation_id = c.id
               AND unread_message.sender_user_id <> :sender_user_id
               AND unread_state.read_at IS NULL AND unread_state.hidden_at IS NULL) AS unread_count
         FROM friendships f
         INNER JOIN users u ON u.id = CASE WHEN f.user_low_id = :select_user_id THEN f.user_high_id ELSE f.user_low_id END
         INNER JOIN user_profiles up ON up.user_id = u.id
         LEFT JOIN conversations c ON c.user_low_id = f.user_low_id AND c.user_high_id = f.user_high_id
         LEFT JOIN user_blocks ub ON ub.user_low_id = f.user_low_id AND ub.user_high_id = f.user_high_id
         WHERE (f.user_low_id = :low_user_id OR f.user_high_id = :high_user_id)
           AND f.status = 'accepted' AND ub.id IS NULL AND u.status = 'active'
         ORDER BY (c.updated_at IS NULL), c.updated_at DESC, u.display_name, u.id"
    );
    $statement->execute([
        'snippet_user_id' => $userId,
        'unread_user_id' => $userId,
        'sender_user_id' => $userId,
        'select_user_id' => $userId,
        'low_user_id' => $userId,
        'high_user_id' => $userId,
    ]);

    return array_map(static function (array $row): array {
        $body = is_string($row['last_body']) ? trim($row['last_body']) : '';
        return [
            'user_id' => (int) $row['other_user_id'],
            'skadi_id' => (string) $row['skadi_id'],
            'display_name' => (string) $row['display_name'],
            'avatar_path' => is_string($row['avatar_path']) ? $row['avatar_path'] : null,
            'conversation_id' => $row['conversation_id'] !== null ? (int) $row['conversation_id'] : null,
            'updated_at' => $row['updated_at'],
            'last_message' => $body === '' ? null : mb_strimwidth($body, 0, 80, '…'),
            'unread_count' => (int) $row['unread_count'],
        ];
    }, $statement->fetchAll());
}

function loadChatMessages(PDO $connection, int $userId, int $otherUserId): array
{
    assertChatAvailable($connection, $userId, $otherUserId);
    $conversation = findConversation($connection, $userId, $otherUserId);
    if ($conversation === null) {
        return ['conversation_id' => null, 'messages' => []];
    }

    $markRead = $connection->prepare(
        'UPDATE message_user_states mus INNER JOIN messages m ON m.id = mus.message_id '
        . 'SET mus.read_at = UTC_TIMESTAMP() WHERE m.conversation_id = :conversation_id '
        . 'AND mus.user_id = :user_id AND m.sender_user_id <> :sender_id '
        . 'AND mus.read_at IS NULL AND mus.hidden_at IS NULL'
    );
    $markRead->execute([
        'conversation_id' => $conversation['id'],
        'user_id' => $userId,
        'sender_id' => $userId,
    ]);

    $statement = $connection->prepare(
        "SELECT m.id, m.sender_user_id, m.body, m.created_at, m.edited_at,
            ma.id AS attachment_id, ma.original_name, ma.mime_type, ma.extension, ma.size_bytes
         FROM messages m
         LEFT JOIN message_user_states mus ON mus.message_id = m.id AND mus.user_id = :user_id
         LEFT JOIN message_attachments ma ON ma.message_id = m.id
         WHERE m.conversation_id = :conversation_id AND mus.hidden_at IS NULL
         ORDER BY m.id DESC LIMIT 100"
    );
    $statement->execute(['user_id' => $userId, 'conversation_id' => $conversation['id']]);
    $messages = array_reverse($statement->fetchAll());

    return [
        'conversation_id' => (int) $conversation['id'],
        'messages' => array_map(static function (array $row) use ($userId): array {
            $attachment = null;
            if ($row['attachment_id'] !== null) {
                $attachment = [
                    'id' => (int) $row['attachment_id'],
                    'name' => (string) $row['original_name'],
                    'mime_type' => (string) $row['mime_type'],
                    'size_bytes' => (int) $row['size_bytes'],
                    'is_image' => str_starts_with((string) $row['mime_type'], 'image/'),
                    'url' => baseUrl('api/chat-attachment.php?id=' . (int) $row['attachment_id']),
                ];
            }
            return [
                'id' => (int) $row['id'],
                'sender_user_id' => (int) $row['sender_user_id'],
                'is_own' => (int) $row['sender_user_id'] === $userId,
                'body' => is_string($row['body']) ? $row['body'] : '',
                'created_at' => (string) $row['created_at'],
                'edited' => $row['edited_at'] !== null,
                'attachment' => $attachment,
            ];
        }, $messages),
    ];
}

function normalizeChatBody(mixed $body): string
{
    $body = trim(str_replace("\0", '', (string) $body));
    if (mb_strlen($body) > CHAT_MAX_BODY_LENGTH) {
        throw new ChatException('Messages must be at most ' . CHAT_MAX_BODY_LENGTH . ' characters.');
    }
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $body) === 1) {
        throw new ChatException('Messages can contain only readable text.');
    }

    return $body;
}

function validateChatAttachment(?array $file): ?array
{
    if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new ChatException('The attachment upload did not complete.');
    }
    $size = (int) ($file['size'] ?? 0);
    $temporaryPath = (string) ($file['tmp_name'] ?? '');
    if ($size < 1 || $size > CHAT_MAX_ATTACHMENT_BYTES || !is_uploaded_file($temporaryPath)) {
        throw new ChatException('Attachments must be non-empty and no larger than 5 MB.');
    }

    $originalName = basename((string) ($file['name'] ?? 'attachment'));
    if (strlen($originalName) > 255) {
        throw new ChatException('The attachment filename is too long.');
    }
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
    $allowed = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'webp' => 'image/webp', 'pdf' => 'application/pdf', 'txt' => 'text/plain',
    ];
    if (!isset($allowed[$extension]) || $mime !== $allowed[$extension]) {
        throw new ChatException('Choose a JPG, PNG, WebP, PDF or TXT file whose content matches its extension.');
    }
    if (str_starts_with($mime, 'image/') && @getimagesize($temporaryPath) === false) {
        throw new ChatException('The uploaded image is not readable.');
    }

    return [
        'temporary_path' => $temporaryPath,
        'original_name' => $originalName,
        'extension' => $extension,
        'mime_type' => $mime,
        'size_bytes' => $size,
        'storage_name' => bin2hex(random_bytes(16)) . '.' . $extension,
    ];
}

function chatAttachmentDirectory(): string
{
    return SKADI_ROOT . '/storage/chat-attachments';
}

function sendChatMessage(PDO $connection, int $senderId, int $otherUserId, string $body, ?array $attachment): int
{
    assertChatAvailable($connection, $senderId, $otherUserId);
    if ($body === '' && $attachment === null) {
        throw new ChatException('Write a message or choose one attachment.');
    }

    $storedPath = null;
    if ($attachment !== null) {
        $storedPath = chatAttachmentDirectory() . DIRECTORY_SEPARATOR . $attachment['storage_name'];
        if (!move_uploaded_file($attachment['temporary_path'], $storedPath)) {
            throw new ChatException('The attachment could not be stored.', 500);
        }
    }

    $connection->beginTransaction();
    try {
        // Use the social service's canonical locks so blocking/removal and sending cannot cross in flight.
        assertChatAvailableForUpdate($connection, $senderId, $otherUserId);
        $rate = $connection->prepare(
            'SELECT 1 FROM messages WHERE sender_user_id = :sender_id '
            . 'AND created_at > CURRENT_TIMESTAMP - INTERVAL ' . CHAT_SEND_INTERVAL_SECONDS . ' SECOND LIMIT 1'
        );
        $rate->execute(['sender_id' => $senderId]);
        if ($rate->fetchColumn() !== false) {
            throw new ChatException('Please wait a moment before sending another message.', 429);
        }
        $conversation = findOrCreateConversation($connection, $senderId, $otherUserId);
        $message = $connection->prepare(
            'INSERT INTO messages (conversation_id, sender_user_id, body) VALUES (:conversation_id, :sender_id, :body)'
        );
        $message->execute([
            'conversation_id' => $conversation['id'],
            'sender_id' => $senderId,
            'body' => $body === '' ? null : $body,
        ]);
        $messageId = (int) $connection->lastInsertId();
        $states = $connection->prepare(
            'INSERT INTO message_user_states (message_id, user_id, read_at) '
            . 'VALUES (:message_id, :sender_id, UTC_TIMESTAMP()), (:message_id_again, :recipient_id, NULL)'
        );
        $states->execute([
            'message_id' => $messageId,
            'sender_id' => $senderId,
            'message_id_again' => $messageId,
            'recipient_id' => $otherUserId,
        ]);
        if ($attachment !== null) {
            $insertAttachment = $connection->prepare(
                'INSERT INTO message_attachments '
                . '(message_id, original_name, storage_name, mime_type, extension, size_bytes) '
                . 'VALUES (:message_id, :original_name, :storage_name, :mime_type, :extension, :size_bytes)'
            );
            $insertAttachment->execute([
                'message_id' => $messageId,
                'original_name' => $attachment['original_name'],
                'storage_name' => $attachment['storage_name'],
                'mime_type' => $attachment['mime_type'],
                'extension' => $attachment['extension'],
                'size_bytes' => $attachment['size_bytes'],
            ]);
        }
        $touch = $connection->prepare('UPDATE conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $touch->execute(['id' => $conversation['id']]);
        $connection->commit();
        return $messageId;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        if ($storedPath !== null && is_file($storedPath)) {
            @unlink($storedPath);
        }
        throw $exception;
    }
}

function loadMessageContext(
    PDO $connection,
    int $messageId,
    int $userId,
    bool $ownerOnly,
    bool $forUpdate = false
): array
{
    $statement = $connection->prepare(
        'SELECT m.id, m.sender_user_id, m.conversation_id, c.user_low_id, c.user_high_id '
        . 'FROM messages m INNER JOIN conversations c ON c.id = m.conversation_id '
        . 'WHERE m.id = :message_id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '')
    );
    $statement->execute(['message_id' => $messageId]);
    $message = $statement->fetch();
    if (!is_array($message)
        || !in_array($userId, [(int) $message['user_low_id'], (int) $message['user_high_id']], true)) {
        throw new ChatException('This message is not part of your conversation.', 403);
    }
    if ($ownerOnly && (int) $message['sender_user_id'] !== $userId) {
        throw new ChatException('You cannot change this message.', 403);
    }
    $otherUserId = (int) $message['user_low_id'] === $userId
        ? (int) $message['user_high_id'] : (int) $message['user_low_id'];
    $message['other_user_id'] = $otherUserId;

    return $message;
}

function editChatMessage(PDO $connection, int $messageId, int $userId, string $body): void
{
    if ($body === '') {
        throw new ChatException('An edited message cannot be empty.');
    }
    $context = loadMessageContext($connection, $messageId, $userId, true);
    $connection->beginTransaction();
    try {
        assertChatAvailableForUpdate($connection, $userId, (int) $context['other_user_id']);
        loadMessageContext($connection, $messageId, $userId, true, true);
        $statement = $connection->prepare(
            'UPDATE messages SET body = :body, edited_at = UTC_TIMESTAMP() '
            . 'WHERE id = :message_id AND sender_user_id = :user_id'
        );
        $statement->execute(['body' => $body, 'message_id' => $messageId, 'user_id' => $userId]);
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

function hideChatMessage(PDO $connection, int $messageId, int $userId): void
{
    $context = loadMessageContext($connection, $messageId, $userId, false);
    $connection->beginTransaction();
    try {
        assertChatAvailableForUpdate($connection, $userId, (int) $context['other_user_id']);
        loadMessageContext($connection, $messageId, $userId, false, true);
        $hide = $connection->prepare(
            'INSERT INTO message_user_states (message_id, user_id, read_at, hidden_at) '
            . 'VALUES (:message_id, :user_id, UTC_TIMESTAMP(), UTC_TIMESTAMP()) '
            . 'ON DUPLICATE KEY UPDATE read_at = COALESCE(read_at, UTC_TIMESTAMP()), hidden_at = UTC_TIMESTAMP()'
        );
        $hide->execute(['message_id' => $messageId, 'user_id' => $userId]);
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

function deleteChatMessageForEveryone(PDO $connection, int $messageId, int $userId): void
{
    $context = loadMessageContext($connection, $messageId, $userId, true);
    $storageName = false;
    $connection->beginTransaction();
    try {
        assertChatAvailableForUpdate($connection, $userId, (int) $context['other_user_id']);
        loadMessageContext($connection, $messageId, $userId, true, true);
        $attachment = $connection->prepare(
            'SELECT storage_name FROM message_attachments WHERE message_id = :message_id LIMIT 1 FOR UPDATE'
        );
        $attachment->execute(['message_id' => $messageId]);
        $storageName = $attachment->fetchColumn();
        $delete = $connection->prepare('DELETE FROM messages WHERE id = :message_id AND sender_user_id = :user_id');
        $delete->execute(['message_id' => $messageId, 'user_id' => $userId]);
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
    if (is_string($storageName) && preg_match('/\A[a-f0-9]{32}\.[a-z0-9]+\z/', $storageName) === 1) {
        $path = chatAttachmentDirectory() . DIRECTORY_SEPARATOR . $storageName;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function loadAuthorizedAttachment(PDO $connection, int $attachmentId, int $userId): array
{
    $statement = $connection->prepare(
        'SELECT ma.original_name, ma.storage_name, ma.mime_type, ma.size_bytes, '
        . 'c.user_low_id, c.user_high_id, mus.hidden_at '
        . 'FROM message_attachments ma INNER JOIN messages m ON m.id = ma.message_id '
        . 'INNER JOIN conversations c ON c.id = m.conversation_id '
        . 'LEFT JOIN message_user_states mus ON mus.message_id = m.id AND mus.user_id = :state_user_id '
        . 'WHERE ma.id = :attachment_id LIMIT 1'
    );
    $statement->execute(['state_user_id' => $userId, 'attachment_id' => $attachmentId]);
    $attachment = $statement->fetch();
    if (!is_array($attachment)
        || !in_array($userId, [(int) $attachment['user_low_id'], (int) $attachment['user_high_id']], true)
        || $attachment['hidden_at'] !== null) {
        throw new ChatException('The attachment is not available.', 404);
    }
    $otherUserId = (int) $attachment['user_low_id'] === $userId
        ? (int) $attachment['user_high_id'] : (int) $attachment['user_low_id'];
    assertChatAvailable($connection, $userId, $otherUserId);

    return $attachment;
}
