<?php

declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php'; requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { renderErrorPage(405, 'Method not allowed', 'Likes require POST.'); }
requireValidCsrfToken($_POST['csrf_token'] ?? null);
$postId = communityPositiveInt($_POST['post_id'] ?? null) ?? 0;
try { $liked = toggleCommunityPostLike(db(), (int) currentUser()['id'], $postId); addFlashMessage('success', $liked ? 'Post liked.' : 'Like removed.'); } catch (CommunityException $exception) { addFlashMessage('error', $exception->getMessage()); }
redirectTo('community-post.php?id=' . $postId);
