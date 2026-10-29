<?php

declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { renderErrorPage(405, 'Method not allowed', 'Posts require POST.'); }
requireValidCsrfToken($_POST['csrf_token'] ?? null);
$gameId = communityPositiveInt($_POST['game_id'] ?? null) ?? 0;
$body = is_string($_POST['body'] ?? null) ? $_POST['body'] : '';
$imagePath = null;
$upload = $_FILES['image'] ?? null;
if (is_array($upload) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $validated = validateUploadedImage($upload, 'community_image');
    if (empty($validated['valid'])) { addFlashMessage('error', $validated['error']); redirectTo('post-edit.php?game=' . $gameId); }
    $imagePath = storeValidatedImage($validated);
}
try { $postId = createCommunityPost(db(), (int) currentUser()['id'], $gameId, $body, $imagePath); addFlashMessage('success', 'Community post published.'); redirectTo('community-post.php?id=' . $postId); }
catch (Throwable $exception) { if ($imagePath !== null) { deleteManagedCommunityUpload($imagePath); } addFlashMessage('error', $exception instanceof CommunityException ? $exception->getMessage() : 'The post could not be published.'); redirectTo('post-edit.php?game=' . $gameId); }
