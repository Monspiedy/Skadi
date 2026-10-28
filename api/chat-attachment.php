<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

chatRequireMethod('GET');
$user = chatRequireUser();

try {
    $attachmentId = chatPositiveId($_GET['id'] ?? null, 'attachment');
    $attachment = loadAuthorizedAttachment(db(), $attachmentId, (int) $user['id']);
    $storageName = (string) $attachment['storage_name'];
    if (preg_match('/\A[a-f0-9]{32}\.[a-z0-9]+\z/', $storageName) !== 1) {
        throw new ChatException('The attachment is not available.', 404);
    }
    $path = chatAttachmentDirectory() . DIRECTORY_SEPARATOR . $storageName;
    if (!is_file($path)) {
        throw new ChatException('The attachment is not available.', 404);
    }

    $downloadName = str_replace(["\r", "\n", '"'], '', (string) $attachment['original_name']);
    $disposition = str_starts_with((string) $attachment['mime_type'], 'image/') ? 'inline' : 'attachment';
    header('Content-Type: ' . $attachment['mime_type']);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: ' . $disposition . '; filename="attachment"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
    header('X-Content-Type-Options: nosniff');
    header('Content-Security-Policy: default-src \'none\'; sandbox');
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
} catch (ChatException $exception) {
    chatErrorResponse($exception->statusCode, 'attachment_unavailable', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Chat attachment failed: ' . $exception->getMessage());
    chatErrorResponse(500, 'server_error', 'The attachment could not be downloaded.');
}
