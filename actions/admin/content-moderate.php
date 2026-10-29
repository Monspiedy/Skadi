<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/config/bootstrap.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Moderation changes must be submitted from the administration form.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);
$contentId = communityPositiveInt($_POST['content_id'] ?? null);
$type = is_string($_POST['content_type'] ?? null) ? $_POST['content_type'] : '';
if ($contentId === null) {
    renderErrorPage(404, 'Content not found', 'The submitted content identifier is invalid.');
}
try {
    moderateAdminContent(
        db(),
        (int) currentUser()['id'],
        $type,
        $contentId,
        (string) ($_POST['status'] ?? ''),
        (string) ($_POST['reason'] ?? ''),
        ($_POST['is_pinned'] ?? '') === '1',
        ($_POST['is_locked'] ?? '') === '1'
    );
    addFlashMessage('success', 'Content moderation applied and audited.');
} catch (AdminException|CommunityException $exception) {
    addFlashMessage('warning', $exception->getMessage());
}
redirectTo('admin/moderation.php?type=' . rawurlencode($type));
