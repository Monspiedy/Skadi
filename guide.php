<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

$guideId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$viewerId = isAuthenticated() && empty(currentUser()['must_change_password']) ? (int) currentUser()['id'] : null;
$guide = $guideId > 0 ? loadGuide(db(), $guideId, $viewerId) : null;
if ($guide === null) {
    http_response_code(404);
    $pageTitle = 'Guide not found';
    require SKADI_ROOT . '/includes/header.php';
    renderStatePanel('error', 'Guide not found', 'This guide is unavailable, hidden or deleted.');
    require SKADI_ROOT . '/includes/footer.php';
    exit;
}
$isAuthor = $viewerId !== null && $viewerId === (int) $guide['author_user_id'];
$comments = $guide['status'] === 'published' ? loadGuideComments(db(), $guideId) : [];
$pageTitle = $guide['title'];
require SKADI_ROOT . '/includes/header.php';
?>
<article class="guide-detail">
    <nav class="breadcrumb-nav" aria-label="Breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= escape(baseUrl('guides.php')) ?>">Guides</a></li>
            <li class="breadcrumb-item"><a href="<?= escape(baseUrl('guides.php?game=' . (int) $guide['game_id'])) ?>"><?= escape($guide['game_title']) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= escape($guide['title']) ?></li>
        </ol>
    </nav>
    <header class="guide-hero">
        <?php $thumbnailUrl = safeGuideThumbnailUrl($guide['thumbnail_path']); ?>
        <?php if ($thumbnailUrl !== null): ?><img src="<?= escape($thumbnailUrl) ?>" alt="" class="guide-detail-thumbnail"><?php endif; ?>
        <div>
            <p class="eyebrow"><?= escape($guide['game_title']) ?> · <?= escape(guideCategories()[$guide['category']]) ?></p>
            <h1><?= escape($guide['title']) ?></h1>
            <p><?= escape($guide['summary']) ?></p>
            <p>By <a href="<?= escape(baseUrl('profile.php?id=' . rawurlencode($guide['skadi_id']))) ?>"><?= escape($guide['display_name']) ?></a>
                <?php if (!empty($guide['is_owner'])): ?><span class="badge text-bg-info">Owner</span><?php endif; ?>
                <?php if ($guide['status'] !== 'published'): ?><span class="badge text-bg-warning"><?= escape(ucfirst($guide['status'])) ?></span><?php endif; ?>
            </p>
            <?php if ($isAuthor && $guide['status'] !== 'hidden' && $guide['status'] !== 'deleted'): ?>
                <div class="guide-actions">
                    <a class="btn btn-outline-secondary" href="<?= escape(baseUrl('guide-edit.php?id=' . $guideId)) ?>">Edit guide</a>
                    <form method="post" action="<?= escape(baseUrl('actions/guide-delete.php')) ?>" data-confirm-form="Delete this guide?">
                        <?= csrfInput() ?><input type="hidden" name="guide_id" value="<?= $guideId ?>">
                        <button class="btn btn-outline-danger" type="submit">Delete</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <div class="guide-body"><?= renderGuideBody($guide['body_source']) ?></div>

    <?php if ($guide['status'] === 'published'): ?>
        <section class="guide-reactions" aria-labelledby="reactions-heading">
            <h2 id="reactions-heading">Was this guide helpful?</h2>
            <p>Helpful score: <strong><?= (int) $guide['helpful_score'] ?></strong>
                (<?= (int) $guide['helpful_count'] ?> helpful, <?= (int) $guide['not_helpful_count'] ?> not helpful)</p>
            <?php if ($viewerId !== null): ?>
                <div class="guide-actions">
                    <form method="post" action="<?= escape(baseUrl('actions/guide-vote.php')) ?>">
                        <?= csrfInput() ?><input type="hidden" name="guide_id" value="<?= $guideId ?>"><input type="hidden" name="vote" value="1">
                        <button class="btn <?= (int) $guide['viewer_vote'] === 1 ? 'btn-primary' : 'btn-outline-secondary' ?>" type="submit">Helpful</button>
                    </form>
                    <form method="post" action="<?= escape(baseUrl('actions/guide-vote.php')) ?>">
                        <?= csrfInput() ?><input type="hidden" name="guide_id" value="<?= $guideId ?>"><input type="hidden" name="vote" value="-1">
                        <button class="btn <?= (int) $guide['viewer_vote'] === -1 ? 'btn-primary' : 'btn-outline-secondary' ?>" type="submit">Not helpful</button>
                    </form>
                    <form method="post" action="<?= escape(baseUrl('actions/guide-favorite.php')) ?>">
                        <?= csrfInput() ?><input type="hidden" name="guide_id" value="<?= $guideId ?>">
                        <button class="btn btn-outline-secondary" type="submit"><?= !empty($guide['is_favorite']) ? 'Remove favorite' : 'Add favorite' ?></button>
                    </form>
                </div>
            <?php else: ?>
                <p><a href="<?= escape(baseUrl('login.php')) ?>">Sign in</a> to vote, favorite or comment.</p>
            <?php endif; ?>
        </section>

        <section class="guide-comments" aria-labelledby="comments-heading">
            <h2 id="comments-heading">Comments</h2>
            <?php if ($viewerId !== null): ?>
                <form class="guide-comment-form" method="post" action="<?= escape(baseUrl('actions/guide-comment-add.php')) ?>">
                    <?= csrfInput() ?><input type="hidden" name="guide_id" value="<?= $guideId ?>">
                    <label for="guide-comment">Add a comment</label>
                    <textarea class="form-control" id="guide-comment" name="body" rows="3" maxlength="2000" required></textarea>
                    <button class="btn btn-primary" type="submit">Post comment</button>
                </form>
            <?php endif; ?>
            <div class="guide-comment-list">
                <?php foreach ($comments as $comment): ?>
                    <article class="guide-comment">
                        <header><strong><?= escape($comment['display_name']) ?></strong><small><?= escape(formatUtcDateTime($comment['created_at'])) ?></small></header>
                        <p><?= nl2br(escape($comment['body'])) ?></p>
                        <?php if ($viewerId === (int) $comment['author_user_id']): ?>
                            <form method="post" action="<?= escape(baseUrl('actions/guide-comment-delete.php')) ?>">
                                <?= csrfInput() ?><input type="hidden" name="comment_id" value="<?= (int) $comment['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete comment</button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
                <?php if ($comments === []): ?><p class="form-note">No comments yet.</p><?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</article>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
