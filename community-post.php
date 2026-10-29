<?php

declare(strict_types=1);
require __DIR__ . '/config/bootstrap.php';
$postId = communityPositiveInt($_GET['id'] ?? null) ?? 0;
$viewerId = isAuthenticated() ? (int) currentUser()['id'] : null;
$post = $postId ? loadCommunityPost(db(), $postId, $viewerId) : null;
if (!$post) { renderErrorPage(404, 'Post not found', 'This post is unavailable, hidden or deleted.'); }
$comments = loadCommunityComments(db(), $postId);
$pageTitle = 'Community post';
require SKADI_ROOT . '/includes/header.php';
?>
<section class="community-page"><article class="post-card post-detail"><p class="eyebrow"><a href="<?= escape(baseUrl('game-community.php?id=' . (int) $post['game_id'])) ?>"><?= escape($post['game_title']) ?></a></p><h1>Post by <a href="<?= escape(baseUrl('profile.php?id=' . rawurlencode($post['skadi_id']))) ?>"><?= escape($post['display_name']) ?></a></h1><?php $imageUrl = safeCommunityImageUrl($post['image_path']); ?><?php if ($imageUrl): ?><img src="<?= escape($imageUrl) ?>" alt="Community image by <?= escape($post['display_name']) ?>"><?php endif; ?><p><?= nl2br(escape($post['body'])) ?></p><p><?= (int) $post['like_count'] ?> likes · <?= (int) $post['comment_count'] ?> comments</p><?php if ($viewerId): ?><form method="post" action="<?= escape(baseUrl('actions/post-like.php')) ?>"><?= csrfInput() ?><input type="hidden" name="post_id" value="<?= $postId ?>"><button class="btn btn-outline-secondary" type="submit"><?= $post['viewer_liked'] ? 'Unlike' : 'Like' ?></button></form><?php endif; ?><?php renderReportForm('post', $postId, 'community-post.php?id=' . $postId); ?></article>
<section class="reply-list"><h2>Comments</h2><?php foreach ($comments as $comment): ?><article class="reply-card" id="comment-<?= (int) $comment['id'] ?>"><header><a href="<?= escape(baseUrl('profile.php?id=' . rawurlencode($comment['skadi_id']))) ?>"><?= escape($comment['display_name']) ?></a><small><?= escape($comment['created_at']) ?></small></header><p><?= nl2br(escape($comment['body'])) ?></p><?php renderReportForm('community_comment', (int) $comment['id'], 'community-post.php?id=' . $postId . '#comment-' . (int) $comment['id']); ?></article><?php endforeach; ?><?php if ($comments === []): ?><p>No comments yet.</p><?php endif; ?><?php if ($viewerId): ?><form class="reply-form" method="post" action="<?= escape(baseUrl('actions/post-comment.php')) ?>"><?= csrfInput() ?><input type="hidden" name="post_id" value="<?= $postId ?>"><label>Add comment<textarea class="form-control" name="body" maxlength="2000" rows="4" required></textarea></label><button class="btn btn-primary" type="submit">Comment</button></form><?php endif; ?></section></section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
