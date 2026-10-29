<?php

declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php'; requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { renderErrorPage(405, 'Method not allowed', 'Comments require POST.'); }
requireValidCsrfToken($_POST['csrf_token'] ?? null);
$postId = communityPositiveInt($_POST['post_id'] ?? null) ?? 0;
try { addCommunityComment(db(), (int) currentUser()['id'], $postId, is_string($_POST['body'] ?? null) ? $_POST['body'] : ''); addFlashMessage('success', 'Comment posted.'); } catch (CommunityException $exception) { addFlashMessage('error', $exception->getMessage()); }
redirectTo('community-post.php?id=' . $postId);
