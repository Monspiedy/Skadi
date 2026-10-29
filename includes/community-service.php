<?php

declare(strict_types=1);

class CommunityException extends RuntimeException
{
}

function discussionCategories(PDO $connection): array
{
    return $connection->query('SELECT id, slug, name FROM discussion_categories ORDER BY sort_order')->fetchAll();
}

function loadCommunityGames(PDO $connection): array
{
    return $connection->query("SELECT id, title FROM games WHERE status = 'published' ORDER BY title")->fetchAll();
}

function communityPositiveInt(mixed $value): ?int
{
    $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $number === false ? null : (int) $number;
}

function validateCommunityText(mixed $value, int $minimum, int $maximum, string $label): string
{
    $text = trim(is_string($value) ? $value : '');
    if (mb_strlen($text) < $minimum || mb_strlen($text) > $maximum
        || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $text) === 1) {
        throw new CommunityException("$label must be $minimum-$maximum readable characters.");
    }
    return $text;
}

function requirePublishedCommunityGame(PDO $connection, int $gameId): void
{
    $statement = $connection->prepare("SELECT 1 FROM games WHERE id = :id AND status = 'published'");
    $statement->execute(['id' => $gameId]);
    if (!$statement->fetchColumn()) {
        throw new CommunityException('Choose an active game.');
    }
}

function loadCommunityCenter(PDO $connection, ?int $gameId, int $page = 1, int $perSection = 4): array
{
    if ($perSection < 1) {
        throw new InvalidArgumentException('Community page size must be positive.');
    }
    $requestedPage = max(1, $page);
    $gameWhere = $gameId === null ? '' : ' AND game.id = :game_id';
    $parameters = $gameId === null ? [] : ['game_id' => $gameId];

    $countQueries = [
        'topics' => "SELECT COUNT(*) FROM discussion_topics dt JOIN games game ON game.id = dt.game_id
            WHERE dt.status = 'active' AND game.status = 'published'$gameWhere",
        'guides' => "SELECT COUNT(*) FROM guides g JOIN games game ON game.id = g.game_id
            WHERE g.status = 'published' AND game.status = 'published'$gameWhere",
        'screenshots' => "SELECT COUNT(*) FROM game_media gm JOIN games game ON game.id = gm.game_id
            WHERE gm.media_type = 'screenshot' AND game.status = 'published'$gameWhere",
        'posts' => "SELECT COUNT(*) FROM community_posts cp JOIN games game ON game.id = cp.game_id
            WHERE cp.status = 'active' AND game.status = 'published'$gameWhere",
    ];
    $totals = [];
    foreach ($countQueries as $contentType => $sql) {
        $count = $connection->prepare($sql);
        $count->execute($parameters);
        $totals[$contentType] = (int) $count->fetchColumn();
    }
    $sectionPages = array_map(
        static fn (int $total): int => max(1, (int) ceil($total / $perSection)),
        $totals
    );
    $pages = max($sectionPages);
    $page = min($requestedPage, $pages);
    $offset = ($page - 1) * $perSection;

    $topics = $connection->prepare(
        "SELECT dt.id, dt.game_id, dt.title, dt.is_pinned, dt.is_locked, dt.updated_at,
                game.title AS game_title, dc.name AS category_name, u.display_name, u.skadi_id,
                COUNT(dr.id) AS reply_count
         FROM discussion_topics dt JOIN games game ON game.id = dt.game_id
         JOIN discussion_categories dc ON dc.id = dt.category_id JOIN users u ON u.id = dt.author_user_id
         LEFT JOIN discussion_replies dr ON dr.topic_id = dt.id AND dr.status = 'active'
         WHERE dt.status = 'active' AND game.status = 'published'$gameWhere
         GROUP BY dt.id, game.title, dc.name, u.display_name, u.skadi_id
         ORDER BY dt.is_pinned DESC, reply_count DESC, dt.updated_at DESC LIMIT :limit OFFSET :offset"
    );
    foreach ($parameters as $name => $value) {
        $topics->bindValue(':' . $name, $value, PDO::PARAM_INT);
    }
    $topics->bindValue(':limit', $perSection, PDO::PARAM_INT);
    $topics->bindValue(':offset', $offset, PDO::PARAM_INT);
    $topics->execute();

    $posts = $connection->prepare(
        "SELECT cp.id, cp.game_id, cp.body, cp.image_path, cp.created_at, game.title AS game_title,
                u.display_name, u.skadi_id, COUNT(DISTINCT cpl.user_id) AS like_count,
                COUNT(DISTINCT cc.id) AS comment_count
         FROM community_posts cp JOIN games game ON game.id = cp.game_id JOIN users u ON u.id = cp.author_user_id
         LEFT JOIN community_post_likes cpl ON cpl.post_id = cp.id
         LEFT JOIN community_comments cc ON cc.post_id = cp.id AND cc.status = 'active'
         WHERE cp.status = 'active' AND game.status = 'published'$gameWhere
         GROUP BY cp.id, game.title, u.display_name, u.skadi_id
         ORDER BY cp.created_at DESC, cp.id DESC LIMIT :limit OFFSET :offset"
    );
    foreach ($parameters as $name => $value) {
        $posts->bindValue(':' . $name, $value, PDO::PARAM_INT);
    }
    $posts->bindValue(':limit', $perSection, PDO::PARAM_INT);
    $posts->bindValue(':offset', $offset, PDO::PARAM_INT);
    $posts->execute();

    $screenshots = $connection->prepare(
        "SELECT gm.id, gm.game_id, gm.storage_path, gm.alt_text, game.title AS game_title
         FROM game_media gm JOIN games game ON game.id = gm.game_id
         WHERE gm.media_type = 'screenshot' AND game.status = 'published'$gameWhere
         ORDER BY gm.created_at DESC, gm.id DESC LIMIT :limit OFFSET :offset"
    );
    foreach ($parameters as $name => $value) {
        $screenshots->bindValue(':' . $name, $value, PDO::PARAM_INT);
    }
    $screenshots->bindValue(':limit', $perSection, PDO::PARAM_INT);
    $screenshots->bindValue(':offset', $offset, PDO::PARAM_INT);
    $screenshots->execute();

    $guidePages = $sectionPages['guides'];
    $guides = $page > $guidePages
        ? ['items' => [], 'total' => $totals['guides'], 'page' => $page, 'pages' => $guidePages]
        : loadGuideList($connection, ['sort' => 'popular', 'page' => $page, 'game_id' => $gameId], $perSection);
    return [
        'topics' => $topics->fetchAll(),
        'guides' => $guides,
        'posts' => $posts->fetchAll(),
        'screenshots' => $screenshots->fetchAll(),
        'page' => $page,
        'pages' => $pages,
        'totals' => $totals,
    ];
}

function parseDiscussionFilters(array $query, ?int $fixedGameId = null): array
{
    $search = trim(is_string($query['search'] ?? null) ? $query['search'] : '');
    if (mb_strlen($search) > 100) {
        $search = mb_substr($search, 0, 100);
    }
    return [
        'game_id' => $fixedGameId ?? communityPositiveInt($query['game'] ?? null),
        'category_id' => communityPositiveInt($query['category'] ?? null),
        'search' => $search,
        'page' => communityPositiveInt($query['page'] ?? 1) ?? 1,
    ];
}

function loadDiscussionTopics(PDO $connection, array $filters, int $perPage = 10): array
{
    $where = "dt.status = 'active' AND game.status = 'published'";
    $parameters = [];
    if ($filters['game_id'] !== null) {
        $where .= ' AND dt.game_id = :game_id';
        $parameters['game_id'] = $filters['game_id'];
    }
    if ($filters['category_id'] !== null) {
        $where .= ' AND dt.category_id = :category_id';
        $parameters['category_id'] = $filters['category_id'];
    }
    if ($filters['search'] !== '') {
        $where .= " AND (dt.title LIKE :search_title ESCAPE '=' OR dt.body LIKE :search_body ESCAPE '=')";
        $escaped = str_replace(['=', '%', '_'], ['==', '=%', '=_'], $filters['search']);
        $parameters['search_title'] = '%' . $escaped . '%';
        $parameters['search_body'] = '%' . $escaped . '%';
    }
    $count = $connection->prepare("SELECT COUNT(*) FROM discussion_topics dt JOIN games game ON game.id = dt.game_id WHERE $where");
    $count->execute($parameters);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min((int) $filters['page'], $pages);
    $sql = "SELECT dt.id, dt.game_id, dt.title, dt.is_pinned, dt.is_locked, dt.created_at, dt.updated_at,
                   game.title AS game_title, dc.name AS category_name, u.display_name, u.skadi_id,
                   COUNT(dr.id) AS reply_count
            FROM discussion_topics dt JOIN games game ON game.id = dt.game_id
            JOIN discussion_categories dc ON dc.id = dt.category_id JOIN users u ON u.id = dt.author_user_id
            LEFT JOIN discussion_replies dr ON dr.topic_id = dt.id AND dr.status = 'active'
            WHERE $where GROUP BY dt.id, game.title, dc.name, u.display_name, u.skadi_id
            ORDER BY dt.is_pinned DESC, dt.updated_at DESC, dt.id DESC LIMIT :limit OFFSET :offset";
    $statement = $connection->prepare($sql);
    foreach ($parameters as $name => $value) {
        $statement->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
    $statement->execute();
    return ['items' => $statement->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
}

function saveDiscussionTopic(PDO $connection, int $userId, ?int $topicId, array $input): int
{
    $gameId = communityPositiveInt($input['game_id'] ?? null) ?? 0;
    $categoryId = communityPositiveInt($input['category_id'] ?? null) ?? 0;
    $title = validateCommunityText($input['title'] ?? null, 3, 180, 'Title');
    $body = validateCommunityText($input['body'] ?? null, 5, 30000, 'Topic body');
    requirePublishedCommunityGame($connection, $gameId);
    $category = $connection->prepare('SELECT 1 FROM discussion_categories WHERE id = :id');
    $category->execute(['id' => $categoryId]);
    if (!$category->fetchColumn()) {
        throw new CommunityException('Choose a valid discussion category.');
    }
    if ($topicId === null) {
        $insert = $connection->prepare(
            'INSERT INTO discussion_topics (game_id, category_id, author_user_id, title, body)
             VALUES (:game_id, :category_id, :user_id, :title, :body)'
        );
        $insert->execute(['game_id' => $gameId, 'category_id' => $categoryId, 'user_id' => $userId,
            'title' => $title, 'body' => $body]);
        return (int) $connection->lastInsertId();
    }
    $owned = $connection->prepare(
        "SELECT 1 FROM discussion_topics WHERE id = :id AND author_user_id = :user AND status = 'active'"
    );
    $owned->execute(['id' => $topicId, 'user' => $userId]);
    if (!$owned->fetchColumn()) {
        throw new CommunityException('This topic cannot be edited.');
    }
    $update = $connection->prepare(
        "UPDATE discussion_topics SET game_id = :gameId, category_id = :categoryId, title = :title, body = :body
         WHERE id = :topicId AND author_user_id = :userId AND status = 'active'"
    );
    $update->execute(compact('gameId', 'categoryId', 'title', 'body', 'topicId', 'userId'));
    return $topicId;
}

function loadDiscussionTopic(PDO $connection, int $topicId): ?array
{
    $statement = $connection->prepare(
        "SELECT dt.*, game.title AS game_title, dc.name AS category_name, u.display_name, u.skadi_id
         FROM discussion_topics dt JOIN games game ON game.id = dt.game_id
         JOIN discussion_categories dc ON dc.id = dt.category_id JOIN users u ON u.id = dt.author_user_id
         WHERE dt.id = :id AND dt.status = 'active' AND game.status = 'published'"
    );
    $statement->execute(['id' => $topicId]);
    return $statement->fetch() ?: null;
}

function loadDiscussionReplies(PDO $connection, int $topicId, int $page, int $perPage = 12): array
{
    $count = $connection->prepare("SELECT COUNT(*) FROM discussion_replies WHERE topic_id = :id AND status = 'active'");
    $count->execute(['id' => $topicId]);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, $page), $pages);
    $statement = $connection->prepare(
        "SELECT dr.id, dr.author_user_id, dr.body, dr.created_at, dr.updated_at, u.display_name, u.skadi_id
         FROM discussion_replies dr JOIN users u ON u.id = dr.author_user_id
         WHERE dr.topic_id = :id AND dr.status = 'active' ORDER BY dr.created_at, dr.id LIMIT :limit OFFSET :offset"
    );
    $statement->bindValue(':id', $topicId, PDO::PARAM_INT);
    $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
    $statement->execute();
    return ['items' => $statement->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
}

function addDiscussionReply(PDO $connection, int $userId, int $topicId, string $body): void
{
    $body = validateCommunityText($body, 2, 10000, 'Reply');
    $connection->beginTransaction();
    try {
        $topic = $connection->prepare(
            "SELECT dt.is_locked FROM discussion_topics dt JOIN games game ON game.id = dt.game_id
             WHERE dt.id = :id AND dt.status = 'active' AND game.status = 'published' FOR UPDATE"
        );
        $topic->execute(['id' => $topicId]);
        $locked = $topic->fetchColumn();
        if ($locked === false) {
            throw new CommunityException('The topic is unavailable.');
        }
        if ((int) $locked === 1) {
            throw new CommunityException('This topic is locked and no longer accepts replies.');
        }
        $insert = $connection->prepare('INSERT INTO discussion_replies (topic_id, author_user_id, body) VALUES (:topic, :user, :body)');
        $insert->execute(['topic' => $topicId, 'user' => $userId, 'body' => $body]);
        $touch = $connection->prepare('UPDATE discussion_topics SET updated_at = UTC_TIMESTAMP() WHERE id = :id');
        $touch->execute(['id' => $topicId]);
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

function editDiscussionReply(PDO $connection, int $userId, int $replyId, string $body): int
{
    $body = validateCommunityText($body, 2, 10000, 'Reply');
    $owned = $connection->prepare(
        "SELECT dr.topic_id FROM discussion_replies dr JOIN discussion_topics dt ON dt.id = dr.topic_id
         WHERE dr.id = :id AND dr.author_user_id = :user AND dr.status = 'active' AND dt.status = 'active'"
    );
    $owned->execute(['id' => $replyId, 'user' => $userId]);
    $topicId = $owned->fetchColumn();
    if ($topicId === false) {
        throw new CommunityException('This reply cannot be edited.');
    }
    $statement = $connection->prepare(
        "UPDATE discussion_replies dr JOIN discussion_topics dt ON dt.id = dr.topic_id
         SET dr.body = :body WHERE dr.id = :id AND dr.author_user_id = :user
         AND dr.status = 'active' AND dt.status = 'active'"
    );
    $statement->execute(['body' => $body, 'id' => $replyId, 'user' => $userId]);
    return (int) $topicId;
}

function requireCommunityAdmin(PDO $connection, int $userId): void
{
    $statement = $connection->prepare(
        "SELECT 1 FROM users u JOIN roles r ON r.id = u.role_id
         WHERE u.id = :id AND u.status = 'active' AND r.name = 'admin'"
    );
    $statement->execute(['id' => $userId]);
    if (!$statement->fetchColumn()) {
        throw new CommunityException('Administrator permission is required.');
    }
}

/** Phase 14 can expose this operation without weakening the Phase 13 service boundary. */
function moderateDiscussionTopic(
    PDO $connection,
    int $adminUserId,
    int $topicId,
    string $status,
    bool $isPinned,
    bool $isLocked,
    string $reason
): void {
    requireCommunityAdmin($connection, $adminUserId);
    if (!in_array($status, ['active', 'hidden', 'deleted'], true)) {
        throw new CommunityException('Choose a valid topic status.');
    }
    $reason = validateCommunityText($reason, 3, 500, 'Moderation reason');
    $statement = $connection->prepare(
        "UPDATE discussion_topics SET status = :status, is_pinned = :pinned, is_locked = :locked,
            moderated_by_user_id = :admin, moderation_reason = :reason,
            deleted_at = CASE WHEN :deleted_status = 'deleted' THEN UTC_TIMESTAMP() ELSE NULL END
         WHERE id = :id AND status <> 'deleted'"
    );
    $statement->execute(['status' => $status, 'pinned' => (int) $isPinned, 'locked' => (int) $isLocked,
        'admin' => $adminUserId, 'reason' => $reason, 'deleted_status' => $status, 'id' => $topicId]);
    if ($statement->rowCount() !== 1) {
        throw new CommunityException('The topic could not be moderated.');
    }
}

function moderateDiscussionReply(
    PDO $connection,
    int $adminUserId,
    int $replyId,
    string $status,
    string $reason
): void {
    requireCommunityAdmin($connection, $adminUserId);
    if (!in_array($status, ['active', 'hidden', 'deleted'], true)) {
        throw new CommunityException('Choose a valid reply status.');
    }
    $reason = validateCommunityText($reason, 3, 500, 'Moderation reason');
    $statement = $connection->prepare(
        "UPDATE discussion_replies SET status = :status, moderated_by_user_id = :admin,
            moderation_reason = :reason,
            deleted_at = CASE WHEN :deleted_status = 'deleted' THEN UTC_TIMESTAMP() ELSE NULL END
         WHERE id = :id AND status <> 'deleted'"
    );
    $statement->execute(['status' => $status, 'admin' => $adminUserId, 'reason' => $reason,
        'deleted_status' => $status, 'id' => $replyId]);
    if ($statement->rowCount() !== 1) {
        throw new CommunityException('The reply could not be moderated.');
    }
}

function safeCommunityImageUrl(?string $path): ?string
{
    return is_string($path) && isManagedCommunityUploadPath($path) ? baseUrl($path) : null;
}

function createCommunityPost(PDO $connection, int $userId, int $gameId, string $body, ?string $imagePath): int
{
    $body = trim($body);
    if ($body === '' && $imagePath === null) {
        throw new CommunityException('Add text or a safe image.');
    }
    if ($body !== '') {
        $body = validateCommunityText($body, 1, 5000, 'Post');
    }
    requirePublishedCommunityGame($connection, $gameId);
    if ($imagePath !== null && !isManagedCommunityUploadPath($imagePath)) {
        throw new CommunityException('The post image is invalid.');
    }
    $statement = $connection->prepare(
        'INSERT INTO community_posts (game_id, author_user_id, body, image_path) VALUES (:game, :user, :body, :image)'
    );
    $statement->execute(['game' => $gameId, 'user' => $userId, 'body' => $body, 'image' => $imagePath]);
    return (int) $connection->lastInsertId();
}

function loadCommunityPosts(PDO $connection, ?int $gameId, int $page, ?int $viewerId, int $perPage = 8): array
{
    $where = "cp.status = 'active' AND game.status = 'published'";
    $parameters = ['viewer' => $viewerId ?? 0];
    if ($gameId !== null) {
        $where .= ' AND cp.game_id = :game';
        $parameters['game'] = $gameId;
    }
    $count = $connection->prepare("SELECT COUNT(*) FROM community_posts cp JOIN games game ON game.id = cp.game_id WHERE $where");
    $countParameters = $parameters;
    unset($countParameters['viewer']);
    $count->execute($countParameters);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, $page), $pages);
    $statement = $connection->prepare(
        "SELECT cp.id, cp.game_id, cp.author_user_id, cp.body, cp.image_path, cp.created_at, cp.updated_at,
                game.title AS game_title, u.display_name, u.skadi_id,
                COUNT(DISTINCT cpl.user_id) AS like_count, COUNT(DISTINCT cc.id) AS comment_count,
                MAX(cpl.user_id = :viewer) AS viewer_liked
         FROM community_posts cp JOIN games game ON game.id = cp.game_id JOIN users u ON u.id = cp.author_user_id
         LEFT JOIN community_post_likes cpl ON cpl.post_id = cp.id
         LEFT JOIN community_comments cc ON cc.post_id = cp.id AND cc.status = 'active'
         WHERE $where GROUP BY cp.id, game.title, u.display_name, u.skadi_id
         ORDER BY cp.created_at DESC, cp.id DESC LIMIT :limit OFFSET :offset"
    );
    foreach ($parameters as $name => $value) {
        $statement->bindValue(':' . $name, $value, PDO::PARAM_INT);
    }
    $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
    $statement->execute();
    return ['items' => $statement->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
}

function loadCommunityPost(PDO $connection, int $postId, ?int $viewerId): ?array
{
    $statement = $connection->prepare(
        "SELECT cp.id, cp.game_id, cp.author_user_id, cp.body, cp.image_path, cp.created_at, cp.updated_at,
                game.title AS game_title, u.display_name, u.skadi_id,
                COUNT(DISTINCT cpl.user_id) AS like_count, COUNT(DISTINCT cc.id) AS comment_count,
                MAX(cpl.user_id = :viewer) AS viewer_liked
         FROM community_posts cp JOIN games game ON game.id = cp.game_id JOIN users u ON u.id = cp.author_user_id
         LEFT JOIN community_post_likes cpl ON cpl.post_id = cp.id
         LEFT JOIN community_comments cc ON cc.post_id = cp.id AND cc.status = 'active'
         WHERE cp.id = :id AND cp.status = 'active' AND game.status = 'published'
         GROUP BY cp.id, game.title, u.display_name, u.skadi_id"
    );
    $statement->execute(['viewer' => $viewerId ?? 0, 'id' => $postId]);
    return $statement->fetch() ?: null;
}

function loadCommunityComments(PDO $connection, int $postId): array
{
    $statement = $connection->prepare(
        "SELECT cc.id, cc.author_user_id, cc.body, cc.created_at, cc.updated_at, u.display_name, u.skadi_id
         FROM community_comments cc JOIN users u ON u.id = cc.author_user_id
         WHERE cc.post_id = :post AND cc.status = 'active' ORDER BY cc.created_at, cc.id"
    );
    $statement->execute(['post' => $postId]);
    return $statement->fetchAll();
}

function toggleCommunityPostLike(PDO $connection, int $userId, int $postId): bool
{
    $connection->beginTransaction();
    try {
        $post = $connection->prepare(
            "SELECT cp.id FROM community_posts cp JOIN games game ON game.id = cp.game_id
             WHERE cp.id = :id AND cp.status = 'active' AND game.status = 'published' FOR UPDATE"
        );
        $post->execute(['id' => $postId]);
        if (!$post->fetchColumn()) {
            throw new CommunityException('The post is unavailable.');
        }
        $existing = $connection->prepare('SELECT 1 FROM community_post_likes WHERE post_id = :post AND user_id = :user');
        $existing->execute(['post' => $postId, 'user' => $userId]);
        if ($existing->fetchColumn()) {
            $delete = $connection->prepare('DELETE FROM community_post_likes WHERE post_id = :post AND user_id = :user');
            $delete->execute(['post' => $postId, 'user' => $userId]);
            $connection->commit();
            return false;
        }
        $insert = $connection->prepare('INSERT INTO community_post_likes (post_id, user_id) VALUES (:post, :user)');
        $insert->execute(['post' => $postId, 'user' => $userId]);
        $connection->commit();
        return true;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

function addCommunityComment(PDO $connection, int $userId, int $postId, string $body): void
{
    $body = validateCommunityText($body, 2, 2000, 'Comment');
    $post = $connection->prepare(
        "SELECT 1 FROM community_posts cp JOIN games game ON game.id = cp.game_id
         WHERE cp.id = :id AND cp.status = 'active' AND game.status = 'published'"
    );
    $post->execute(['id' => $postId]);
    if (!$post->fetchColumn()) {
        throw new CommunityException('The post is unavailable.');
    }
    $statement = $connection->prepare('INSERT INTO community_comments (post_id, author_user_id, body) VALUES (:post, :user, :body)');
    $statement->execute(['post' => $postId, 'user' => $userId, 'body' => $body]);
}

function reportableContentTypes(): array
{
    return [
        'review' => ['column' => 'review_id', 'table' => 'reviews', 'public_status' => 'active'],
        'guide' => ['column' => 'guide_id', 'table' => 'guides', 'public_status' => 'published'],
        'guide_comment' => ['column' => 'guide_comment_id', 'table' => 'guide_comments', 'public_status' => 'active'],
        'topic' => ['column' => 'discussion_topic_id', 'table' => 'discussion_topics', 'public_status' => 'active'],
        'reply' => ['column' => 'discussion_reply_id', 'table' => 'discussion_replies', 'public_status' => 'active'],
        'post' => ['column' => 'community_post_id', 'table' => 'community_posts', 'public_status' => 'active'],
        'community_comment' => ['column' => 'community_comment_id', 'table' => 'community_comments', 'public_status' => 'active'],
    ];
}

function submitContentReport(PDO $connection, int $userId, string $type, int $targetId, string $reason, string $details): void
{
    $types = reportableContentTypes();
    if (!isset($types[$type])) {
        throw new CommunityException('Choose reportable content.');
    }
    if (!in_array($reason, ['spam', 'harassment', 'inappropriate', 'misinformation', 'other'], true)) {
        throw new CommunityException('Choose a valid report reason.');
    }
    $details = trim($details);
    if (mb_strlen($details) > 1000 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $details) === 1) {
        throw new CommunityException('Report details must be at most 1,000 readable characters.');
    }
    $target = $types[$type];
    $exists = $connection->prepare("SELECT 1 FROM {$target['table']} WHERE id = :id AND status = :status");
    $exists->execute(['id' => $targetId, 'status' => $target['public_status']]);
    if (!$exists->fetchColumn()) {
        throw new CommunityException('The reported content is unavailable.');
    }
    $column = $target['column'];
    $statement = $connection->prepare(
        "INSERT INTO content_reports (reporter_user_id, $column, reason, details)
         VALUES (:user, :target, :reason, :details)"
    );
    try {
        $statement->execute(['user' => $userId, 'target' => $targetId, 'reason' => $reason,
            'details' => $details === '' ? null : $details]);
    } catch (PDOException $exception) {
        if ((string) $exception->getCode() === '23000') {
            throw new CommunityException('You have already reported this content.');
        }
        throw $exception;
    }
}

function renderReportForm(string $type, int $targetId, string $returnTo): void
{
    if (!isAuthenticated() || !empty(currentUser()['must_change_password'])) {
        return;
    }
    ?>
    <details class="report-panel">
        <summary>Report</summary>
        <form method="post" action="<?= escape(baseUrl('actions/content-report.php')) ?>">
            <?= csrfInput() ?>
            <input type="hidden" name="target_type" value="<?= escape($type) ?>">
            <input type="hidden" name="target_id" value="<?= $targetId ?>">
            <input type="hidden" name="return_to" value="<?= escape($returnTo) ?>">
            <label>Reason
                <select class="form-select" name="reason" required>
                    <option value="spam">Spam</option><option value="harassment">Harassment</option>
                    <option value="inappropriate">Inappropriate</option><option value="misinformation">Misinformation</option>
                    <option value="other">Other</option>
                </select>
            </label>
            <label>Details (optional)<textarea class="form-control" name="details" maxlength="1000" rows="2"></textarea></label>
            <button class="btn btn-sm btn-outline-danger" type="submit">Submit report</button>
        </form>
    </details>
    <?php
}
