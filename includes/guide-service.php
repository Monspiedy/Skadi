<?php

declare(strict_types=1);

class GuideException extends RuntimeException
{
}

function guideCategories(): array
{
    return [
        'general' => 'General',
        'walkthrough' => 'Walkthrough',
        'strategy' => 'Strategy',
        'build' => 'Build',
        'modding' => 'Modding',
        'other' => 'Other',
    ];
}

function safeGuideThumbnailUrl(?string $path): ?string
{
    if ($path === null || !isManagedGuideUploadPath($path)) {
        return null;
    }
    return baseUrl($path);
}

function loadPublishedGamesForGuides(PDO $connection): array
{
    return $connection->query("SELECT id, title FROM games WHERE status = 'published' ORDER BY title")->fetchAll();
}

function validateGuideInput(array $input): array
{
    $title = trim(is_string($input['title'] ?? null) ? $input['title'] : '');
    $summary = trim(is_string($input['summary'] ?? null) ? $input['summary'] : '');
    $body = trim(is_string($input['body'] ?? null) ? $input['body'] : '');
    $category = is_string($input['category'] ?? null) ? $input['category'] : 'general';
    $status = is_string($input['status'] ?? null) ? $input['status'] : 'draft';
    $errors = [];

    if (mb_strlen($title) < 3 || mb_strlen($title) > 180) {
        $errors['title'] = 'Title must be 3-180 characters.';
    }
    if (mb_strlen($summary) < 10 || mb_strlen($summary) > 500) {
        $errors['summary'] = 'Summary must be 10-500 characters.';
    }
    if (mb_strlen($body) < 20 || mb_strlen($body) > 50000) {
        $errors['body'] = 'Body must be 20-50,000 characters.';
    }
    if (!array_key_exists($category, guideCategories())) {
        $errors['category'] = 'Choose a valid category.';
    }
    if (!in_array($status, ['draft', 'published'], true)) {
        $errors['status'] = 'Choose draft or published.';
    }

    return compact('title', 'summary', 'body', 'category', 'status', 'errors');
}

function isSafeGuideUrl(string $url): bool
{
    if (mb_strlen($url) > 2000 || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true);
}

/**
 * Render the documented BBCode subset. Source HTML is escaped first and URL schemes
 * are allowlisted, so no user-controlled HTML is ever trusted by this renderer.
 */
function renderGuideBody(string $source): string
{
    $source = str_replace(["\r\n", "\r"], "\n", $source);
    $protected = [];
    $source = preg_replace_callback('/\[code\](.*?)\[\/code\]/is', function (array $match) use (&$protected): string {
        $key = '@@SKADI_BLOCK_' . count($protected) . '@@';
        $protected[$key] = '<pre><code>' . escape(trim($match[1], "\n")) . '</code></pre>';
        return "\n" . $key . "\n";
    }, $source) ?? $source;
    $source = preg_replace_callback('/\[url=([^\]\r\n]+)\](.*?)\[\/url\]/is', function (array $match) use (&$protected): string {
        $url = html_entity_decode(trim($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!isSafeGuideUrl($url)) {
            return $match[0];
        }
        $key = '@@SKADI_LINK_' . count($protected) . '@@';
        $protected[$key] = '<a href="' . escape($url) . '" target="_blank" rel="noopener noreferrer">'
            . escape($match[2]) . '</a>';
        return $key;
    }, $source) ?? $source;

    $escaped = escape($source);
    $escaped = preg_replace('/\[list\]/i', "\n[list]\n", $escaped) ?? $escaped;
    $escaped = preg_replace('/\[\/list\]/i', "\n[/list]\n", $escaped) ?? $escaped;
    $escaped = preg_replace('/\[\*\]/i', "\n[*]", $escaped) ?? $escaped;
    foreach ($protected as $key => $html) {
        $escaped = str_replace($key, $html, $escaped);
    }

    $lines = explode("\n", $escaped);
    $html = [];
    $paragraph = [];
    $listItems = [];
    $inList = false;
    $flushParagraph = static function () use (&$paragraph, &$html): void {
        if ($paragraph !== []) {
            $html[] = '<p>' . implode('<br>', $paragraph) . '</p>';
            $paragraph = [];
        }
    };
    $formatInline = static function (string $text): string {
        $text = preg_replace('/\[b\](.*?)\[\/b\]/is', '<strong>$1</strong>', $text) ?? $text;
        return preg_replace('/\[i\](.*?)\[\/i\]/is', '<em>$1</em>', $text) ?? $text;
    };

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (strcasecmp($trimmed, '[list]') === 0) {
            $flushParagraph();
            $inList = true;
            $listItems = [];
            continue;
        }
        if (strcasecmp($trimmed, '[/list]') === 0) {
            if ($inList && $listItems !== []) {
                $html[] = '<ul><li>' . implode('</li><li>', $listItems) . '</li></ul>';
            }
            $inList = false;
            $listItems = [];
            continue;
        }
        if ($inList && str_starts_with($trimmed, '[*]')) {
            $listItems[] = $formatInline(trim(substr($trimmed, 3)));
            continue;
        }
        if ($trimmed === '') {
            $flushParagraph();
            continue;
        }
        if (preg_match('/^\[h([1-3])\](.*?)\[\/h\1\]$/i', $trimmed, $heading) === 1) {
            $flushParagraph();
            $level = (int) $heading[1] + 1;
            $html[] = '<h' . $level . '>' . $formatInline($heading[2]) . '</h' . $level . '>';
            continue;
        }
        if (str_starts_with($trimmed, '<pre><code>')) {
            $flushParagraph();
            $html[] = $trimmed;
            continue;
        }
        $paragraph[] = $formatInline($trimmed);
    }
    $flushParagraph();
    if ($inList && $listItems !== []) {
        $html[] = '<ul><li>' . implode('</li><li>', $listItems) . '</li></ul>';
    }
    return implode("\n", $html);
}

function parseGuideFilters(array $query): array
{
    $sort = is_string($query['sort'] ?? null) ? $query['sort'] : 'newest';
    if (!in_array($sort, ['newest', 'popular', 'helpful'], true)) {
        $sort = 'newest';
    }
    $page = filter_var($query['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
    $gameId = filter_var($query['game'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    return ['sort' => $sort, 'page' => $page, 'game_id' => $gameId];
}

function loadGuideList(PDO $connection, array $filters, int $perPage = 6): array
{
    $where = "g.status = 'published' AND game.status = 'published'";
    $parameters = [];
    if ($filters['game_id'] !== null) {
        $where .= ' AND g.game_id = :game_id';
        $parameters['game_id'] = $filters['game_id'];
    }
    $count = $connection->prepare("SELECT COUNT(*) FROM guides g JOIN games game ON game.id = g.game_id WHERE $where");
    $count->execute($parameters);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($filters['page'], $pages);
    $offset = ($page - 1) * $perPage;
    $orders = [
        'newest' => 'g.published_at DESC, g.id DESC',
        'popular' => 'popularity_count DESC, g.published_at DESC, g.id DESC',
        'helpful' => 'helpful_score DESC, helpful_count DESC, g.published_at DESC, g.id DESC',
    ];
    $sql = "SELECT g.id, g.game_id, g.title, g.summary, g.category, g.thumbnail_path, g.published_at,
                   game.title AS game_title, cover.storage_path AS cover_path, cover.alt_text AS cover_alt,
                   u.skadi_id, u.display_name,
                   EXISTS(SELECT 1 FROM library_items li WHERE li.user_id = g.author_user_id
                       AND li.game_id = g.game_id AND li.removed_at IS NULL) AS is_owner,
                   COALESCE(v.helpful_count, 0) AS helpful_count,
                   COALESCE(v.not_helpful_count, 0) AS not_helpful_count,
                   COALESCE(v.helpful_score, 0) AS helpful_score,
                   COALESCE(f.favorite_count, 0) AS favorite_count,
                   COALESCE(c.comment_count, 0) AS comment_count,
                   COALESCE(v.vote_count, 0) + COALESCE(f.favorite_count, 0) + COALESCE(c.comment_count, 0) AS popularity_count
            FROM guides g JOIN games game ON game.id = g.game_id
            JOIN users u ON u.id = g.author_user_id
            LEFT JOIN game_media cover
                ON cover.game_id = game.id AND cover.media_type = 'cover' AND cover.sort_order = 0
            LEFT JOIN (SELECT guide_id, SUM(vote_value = 1) helpful_count,
                SUM(vote_value = -1) not_helpful_count, SUM(vote_value) helpful_score, COUNT(*) vote_count
                FROM guide_votes GROUP BY guide_id) v ON v.guide_id = g.id
            LEFT JOIN (SELECT guide_id, COUNT(*) favorite_count FROM guide_favorites GROUP BY guide_id) f ON f.guide_id = g.id
            LEFT JOIN (SELECT guide_id, COUNT(*) comment_count FROM guide_comments WHERE status = 'active' GROUP BY guide_id) c ON c.guide_id = g.id
            WHERE $where ORDER BY {$orders[$filters['sort']]} LIMIT :limit OFFSET :offset";
    $statement = $connection->prepare($sql);
    foreach ($parameters as $name => $value) {
        $statement->bindValue(':' . $name, $value, PDO::PARAM_INT);
    }
    $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();
    return ['items' => $statement->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
}

function loadGuide(PDO $connection, int $guideId, ?int $viewerId = null): ?array
{
    $statement = $connection->prepare(
        "SELECT g.*, game.title AS game_title, game.status AS game_status, u.skadi_id, u.display_name,
                EXISTS(SELECT 1 FROM library_items li WHERE li.user_id = g.author_user_id
                    AND li.game_id = g.game_id AND li.removed_at IS NULL) AS is_owner,
                COALESCE(SUM(gv.vote_value = 1), 0) AS helpful_count,
                COALESCE(SUM(gv.vote_value = -1), 0) AS not_helpful_count,
                COALESCE(SUM(gv.vote_value), 0) AS helpful_score,
                MAX(CASE WHEN gv.user_id = :viewer_vote THEN gv.vote_value END) AS viewer_vote,
                EXISTS(SELECT 1 FROM guide_favorites gf WHERE gf.guide_id = g.id AND gf.user_id = :viewer_favorite) AS is_favorite
         FROM guides g JOIN games game ON game.id = g.game_id JOIN users u ON u.id = g.author_user_id
         LEFT JOIN guide_votes gv ON gv.guide_id = g.id
         WHERE g.id = :guide_id GROUP BY g.id, game.title, game.status, u.skadi_id, u.display_name"
    );
    $statement->execute(['viewer_vote' => $viewerId ?? 0, 'viewer_favorite' => $viewerId ?? 0, 'guide_id' => $guideId]);
    $guide = $statement->fetch();
    if (!$guide) {
        return null;
    }
    $isAuthor = $viewerId !== null && (int) $guide['author_user_id'] === $viewerId;
    if (($guide['status'] !== 'published' || $guide['game_status'] !== 'published') && !$isAuthor) {
        return null;
    }
    if (in_array($guide['status'], ['hidden', 'deleted'], true) && !$isAuthor) {
        return null;
    }
    return $guide;
}

function loadGuideComments(PDO $connection, int $guideId): array
{
    $statement = $connection->prepare(
        "SELECT gc.id, gc.author_user_id, gc.body, gc.created_at, gc.updated_at, u.skadi_id, u.display_name
         FROM guide_comments gc JOIN users u ON u.id = gc.author_user_id
         WHERE gc.guide_id = :guide_id AND gc.status = 'active' ORDER BY gc.created_at, gc.id"
    );
    $statement->execute(['guide_id' => $guideId]);
    return $statement->fetchAll();
}

function loadAuthorGuides(PDO $connection, int $userId): array
{
    $statement = $connection->prepare(
        "SELECT g.id, g.title, g.status, g.updated_at, game.title AS game_title
         FROM guides g JOIN games game ON game.id = g.game_id WHERE g.author_user_id = :user_id
         AND g.status <> 'deleted' ORDER BY g.updated_at DESC, g.id DESC"
    );
    $statement->execute(['user_id' => $userId]);
    return $statement->fetchAll();
}

function saveGuide(PDO $connection, int $userId, int $gameId, ?int $guideId, array $data, ?string $thumbnailPath): int
{
    $game = $connection->prepare("SELECT id FROM games WHERE id = :id AND status = 'published'");
    $game->execute(['id' => $gameId]);
    if (!$game->fetchColumn()) {
        throw new GuideException('Choose an active game.');
    }
    if ($guideId === null) {
        $statement = $connection->prepare(
            "INSERT INTO guides (game_id, author_user_id, title, summary, category, thumbnail_path, body_source, status, published_at)
             VALUES (:game_id, :user_id, :title, :summary, :category, :thumbnail, :body, :status,
                CASE WHEN :published_status = 'published' THEN UTC_TIMESTAMP() ELSE NULL END)"
        );
        $statement->execute(['game_id' => $gameId, 'user_id' => $userId, 'title' => $data['title'],
            'summary' => $data['summary'], 'category' => $data['category'], 'thumbnail' => $thumbnailPath,
            'body' => $data['body'], 'status' => $data['status'], 'published_status' => $data['status']]);
        return (int) $connection->lastInsertId();
    }
    $existing = $connection->prepare('SELECT author_user_id, status FROM guides WHERE id = :id FOR UPDATE');
    $existing->execute(['id' => $guideId]);
    $row = $existing->fetch();
    if (!$row || (int) $row['author_user_id'] !== $userId || in_array($row['status'], ['hidden', 'deleted'], true)) {
        throw new GuideException('This guide cannot be edited.');
    }
    $thumbnailSql = $thumbnailPath !== null ? ', thumbnail_path = :thumbnail' : '';
    $statement = $connection->prepare(
        "UPDATE guides SET game_id = :game_id, title = :title, summary = :summary, category = :category,
            body_source = :body, status = :status,
            published_at = CASE WHEN :published_status = 'published' THEN COALESCE(published_at, UTC_TIMESTAMP()) ELSE NULL END
            $thumbnailSql WHERE id = :id"
    );
    $values = ['game_id' => $gameId, 'title' => $data['title'], 'summary' => $data['summary'],
        'category' => $data['category'], 'body' => $data['body'], 'status' => $data['status'],
        'published_status' => $data['status'], 'id' => $guideId];
    if ($thumbnailPath !== null) {
        $values['thumbnail'] = $thumbnailPath;
    }
    $statement->execute($values);
    return $guideId;
}

function deleteGuide(PDO $connection, int $userId, int $guideId): ?string
{
    $connection->beginTransaction();
    try {
        $select = $connection->prepare('SELECT author_user_id, status, thumbnail_path FROM guides WHERE id = :id FOR UPDATE');
        $select->execute(['id' => $guideId]);
        $guide = $select->fetch();
        if (!$guide || (int) $guide['author_user_id'] !== $userId || $guide['status'] === 'deleted') {
            throw new GuideException('This guide cannot be deleted.');
        }
        $update = $connection->prepare(
            "UPDATE guides SET status = 'deleted', deleted_at = UTC_TIMESTAMP(), thumbnail_path = NULL WHERE id = :id"
        );
        $update->execute(['id' => $guideId]);
        $connection->commit();
        return $guide['thumbnail_path'];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

function setGuideVote(PDO $connection, int $userId, int $guideId, int $vote): void
{
    if (!in_array($vote, [-1, 1], true)) {
        throw new GuideException('Choose helpful or not helpful.');
    }
    requirePublishedGuide($connection, $guideId);
    $statement = $connection->prepare(
        'INSERT INTO guide_votes (guide_id, user_id, vote_value) VALUES (:guide_id, :user_id, :vote)
         ON DUPLICATE KEY UPDATE vote_value = VALUES(vote_value), updated_at = CURRENT_TIMESTAMP'
    );
    $statement->execute(['guide_id' => $guideId, 'user_id' => $userId, 'vote' => $vote]);
}

function toggleGuideFavorite(PDO $connection, int $userId, int $guideId): bool
{
    $connection->beginTransaction();
    try {
        $guide = $connection->prepare(
            "SELECT g.id FROM guides g JOIN games game ON game.id = g.game_id
             WHERE g.id = :id AND g.status = 'published' AND game.status = 'published' FOR UPDATE"
        );
        $guide->execute(['id' => $guideId]);
        if (!$guide->fetchColumn()) {
            throw new GuideException('The guide is not available.');
        }
        $existing = $connection->prepare('SELECT 1 FROM guide_favorites WHERE guide_id = :guide_id AND user_id = :user_id');
        $existing->execute(['guide_id' => $guideId, 'user_id' => $userId]);
        if ($existing->fetchColumn()) {
            $delete = $connection->prepare('DELETE FROM guide_favorites WHERE guide_id = :guide_id AND user_id = :user_id');
            $delete->execute(['guide_id' => $guideId, 'user_id' => $userId]);
            $connection->commit();
            return false;
        }
        $insert = $connection->prepare('INSERT INTO guide_favorites (guide_id, user_id) VALUES (:guide_id, :user_id)');
        $insert->execute(['guide_id' => $guideId, 'user_id' => $userId]);
        $connection->commit();
        return true;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

function addGuideComment(PDO $connection, int $userId, int $guideId, string $body): void
{
    $body = trim($body);
    if (mb_strlen($body) < 2 || mb_strlen($body) > 2000 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $body) === 1) {
        throw new GuideException('Comment must be 2-2,000 readable characters.');
    }
    requirePublishedGuide($connection, $guideId);
    $statement = $connection->prepare('INSERT INTO guide_comments (guide_id, author_user_id, body) VALUES (:guide_id, :user_id, :body)');
    $statement->execute(['guide_id' => $guideId, 'user_id' => $userId, 'body' => $body]);
}

function deleteGuideComment(PDO $connection, int $userId, int $commentId): int
{
    $statement = $connection->prepare(
        "UPDATE guide_comments SET status = 'deleted', deleted_at = UTC_TIMESTAMP()
         WHERE id = :id AND author_user_id = :user_id AND status = 'active'"
    );
    $statement->execute(['id' => $commentId, 'user_id' => $userId]);
    if ($statement->rowCount() !== 1) {
        throw new GuideException('This comment cannot be deleted.');
    }
    $lookup = $connection->prepare('SELECT guide_id FROM guide_comments WHERE id = :id');
    $lookup->execute(['id' => $commentId]);
    return (int) $lookup->fetchColumn();
}

function requirePublishedGuide(PDO $connection, int $guideId): void
{
    $statement = $connection->prepare(
        "SELECT 1 FROM guides g JOIN games game ON game.id = g.game_id
         WHERE g.id = :id AND g.status = 'published' AND game.status = 'published'"
    );
    $statement->execute(['id' => $guideId]);
    if (!$statement->fetchColumn()) {
        throw new GuideException('The guide is not available.');
    }
}
