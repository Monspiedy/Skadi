<?php

declare(strict_types=1);

const MAX_FEATURED_GAMES = 5;
const MAX_GAME_SCREENSHOTS = 12;

function loadAdminGames(PDO $connection): array
{
    return $connection->query(
        "SELECT g.id, g.title, g.slug, g.base_price, g.status,
                COUNT(DISTINCT gg.genre_id) AS genre_count,
                COUNT(DISTINCT CASE WHEN gm.media_type = 'screenshot' THEN gm.id END) AS screenshot_count,
                MAX(CASE WHEN gm.media_type = 'cover' THEN gm.storage_path END) AS cover_path,
                fg.position AS featured_position
         FROM games g
         LEFT JOIN game_genres gg ON gg.game_id = g.id
         LEFT JOIN game_media gm ON gm.game_id = g.id
         LEFT JOIN featured_games fg ON fg.game_id = g.id
         GROUP BY g.id, g.title, g.slug, g.base_price, g.status, fg.position
         ORDER BY g.title"
    )->fetchAll();
}

function loadAdminGame(PDO $connection, int $gameId): ?array
{
    $statement = $connection->prepare('SELECT * FROM games WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $gameId]);
    $game = $statement->fetch();

    if (!is_array($game)) {
        return null;
    }

    $genreStatement = $connection->prepare('SELECT genre_id FROM game_genres WHERE game_id = :id');
    $genreStatement->execute(['id' => $gameId]);
    $game['genre_ids'] = array_map('intval', $genreStatement->fetchAll(PDO::FETCH_COLUMN));

    $mediaStatement = $connection->prepare(
        'SELECT id, media_type, storage_path, alt_text, sort_order
         FROM game_media WHERE game_id = :id ORDER BY media_type, sort_order'
    );
    $mediaStatement->execute(['id' => $gameId]);
    $game['cover'] = null;
    $game['screenshots'] = [];
    foreach ($mediaStatement->fetchAll() as $media) {
        if ($media['media_type'] === 'cover') {
            $game['cover'] = $media;
        } else {
            $game['screenshots'][] = $media;
        }
    }

    return $game;
}

function loadAllGenres(PDO $connection): array
{
    return $connection->query(
        'SELECT ge.id, ge.slug, ge.name, COUNT(gg.game_id) AS game_count
         FROM genres ge LEFT JOIN game_genres gg ON gg.genre_id = ge.id
         GROUP BY ge.id, ge.slug, ge.name ORDER BY ge.name'
    )->fetchAll();
}

function normalizeGameInput(array $input): array
{
    $genreIds = $input['genre_ids'] ?? [];
    if (!is_array($genreIds)) {
        $genreIds = [];
    }

    return [
        'title' => trim((string) ($input['title'] ?? '')),
        'slug' => strtolower(trim((string) ($input['slug'] ?? ''))),
        'short_description' => trim((string) ($input['short_description'] ?? '')),
        'description' => trim((string) ($input['description'] ?? '')),
        'developer' => trim((string) ($input['developer'] ?? '')),
        'publisher' => trim((string) ($input['publisher'] ?? '')),
        'release_date' => trim((string) ($input['release_date'] ?? '')),
        'base_price' => trim((string) ($input['base_price'] ?? '')),
        'system_requirements' => trim((string) ($input['system_requirements'] ?? '')),
        'status' => trim((string) ($input['status'] ?? '')),
        'genre_ids' => array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => is_scalar($id) ? (int) $id : 0, $genreIds),
            static fn (int $id): bool => $id > 0
        ))),
    ];
}

function gameInputErrors(PDO $connection, array $input, ?int $gameId = null): array
{
    $errors = [];
    $lengths = [
        'title' => ['Title', 1, 160],
        'short_description' => ['Short description', 1, 500],
        'description' => ['Description', 1, 20000],
        'developer' => ['Developer', 1, 160],
        'publisher' => ['Publisher', 1, 160],
        'system_requirements' => ['System requirements', 1, 10000],
    ];
    foreach ($lengths as $field => [$label, $minimum, $maximum]) {
        $error = lengthError($input[$field], $label, $minimum, $maximum);
        if ($error !== null) {
            $errors[$field] = $error;
        }
    }

    if (preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $input['slug']) !== 1 || mb_strlen($input['slug']) > 160) {
        $errors['slug'] = 'Slug must use 1-160 lowercase letters, numbers and single hyphens.';
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $input['release_date']);
    if ($date === false || $date->format('Y-m-d') !== $input['release_date']) {
        $errors['release_date'] = 'Release date must be a valid date.';
    }

    if (preg_match('/\A(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?\z/', $input['base_price']) !== 1) {
        $errors['base_price'] = 'Base price must be between 0.00 and 99,999,999.99 with at most two decimals.';
    }

    if (!in_array($input['status'], ['draft', 'published', 'hidden'], true)) {
        $errors['status'] = 'Choose a valid publication status.';
    }

    if ($input['genre_ids'] !== []) {
        $placeholders = implode(',', array_fill(0, count($input['genre_ids']), '?'));
        $statement = $connection->prepare('SELECT COUNT(*) FROM genres WHERE id IN (' . $placeholders . ')');
        foreach ($input['genre_ids'] as $index => $genreId) {
            $statement->bindValue($index + 1, $genreId, PDO::PARAM_INT);
        }
        $statement->execute();
        if ((int) $statement->fetchColumn() !== count($input['genre_ids'])) {
            $errors['genre_ids'] = 'One or more selected genres no longer exist.';
        }
    }

    if (!isset($errors['slug'])) {
        $statement = $connection->prepare(
            'SELECT id FROM games WHERE slug = :slug AND (:game_id IS NULL OR id <> :game_id_check) LIMIT 1'
        );
        $statement->bindValue(':slug', $input['slug']);
        $statement->bindValue(':game_id', $gameId, $gameId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':game_id_check', $gameId, $gameId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->execute();
        if ($statement->fetchColumn() !== false) {
            $errors['slug'] = 'Another game already uses this slug.';
        }
    }

    return $errors;
}

function saveAdminGame(PDO $connection, array $input, ?int $gameId = null): int
{
    $connection->beginTransaction();
    try {
        $priceParts = explode('.', $input['base_price'], 2);
        $normalizedPrice = $priceParts[0] . '.' . str_pad($priceParts[1] ?? '', 2, '0');
        $parameters = [
            'slug' => $input['slug'],
            'title' => $input['title'],
            'short_description' => $input['short_description'],
            'description' => $input['description'],
            'developer' => $input['developer'],
            'publisher' => $input['publisher'],
            'release_date' => $input['release_date'],
            'base_price' => $normalizedPrice,
            'system_requirements' => $input['system_requirements'],
            'status' => $input['status'],
        ];

        if ($gameId === null) {
            $statement = $connection->prepare(
                'INSERT INTO games
                    (slug, title, short_description, description, developer, publisher,
                     release_date, base_price, system_requirements, status)
                 VALUES
                    (:slug, :title, :short_description, :description, :developer, :publisher,
                     :release_date, :base_price, :system_requirements, :status)'
            );
            $statement->execute($parameters);
            $gameId = (int) $connection->lastInsertId();
        } else {
            $parameters['id'] = $gameId;
            $statement = $connection->prepare(
                'UPDATE games SET slug = :slug, title = :title, short_description = :short_description,
                    description = :description, developer = :developer, publisher = :publisher,
                    release_date = :release_date, base_price = :base_price,
                    system_requirements = :system_requirements, status = :status
                 WHERE id = :id'
            );
            $statement->execute($parameters);
            if ($statement->rowCount() === 0 && loadAdminGame($connection, $gameId) === null) {
                throw new RuntimeException('The game no longer exists.');
            }
        }

        $deleteGenres = $connection->prepare('DELETE FROM game_genres WHERE game_id = :game_id');
        $deleteGenres->execute(['game_id' => $gameId]);
        $insertGenre = $connection->prepare(
            'INSERT INTO game_genres (game_id, genre_id) VALUES (:game_id, :genre_id)'
        );
        foreach ($input['genre_ids'] as $genreId) {
            $insertGenre->execute(['game_id' => $gameId, 'genre_id' => $genreId]);
        }

        $connection->commit();
        return $gameId;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

function gameDeletionDependencies(PDO $connection, int $gameId): array
{
    $tables = [
        'weekly_discounts' => 'weekend sale records',
        'cart_items' => 'cart entries',
        'order_items' => 'order history items',
        'library_items' => 'library ownership records',
        'wishlist_items' => 'wishlist entries',
        'reviews' => 'reviews',
        'guides' => 'guides',
        'discussion_topics' => 'discussion topics',
        'community_posts' => 'community posts',
    ];
    $dependencies = [];
    foreach ($tables as $table => $label) {
        $statement = $connection->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE game_id = :game_id');
        $statement->execute(['game_id' => $gameId]);
        $count = (int) $statement->fetchColumn();
        if ($count > 0) {
            $dependencies[$label] = $count;
        }
    }

    return $dependencies;
}

function deleteAdminGame(PDO $connection, int $gameId): array
{
    $dependencies = gameDeletionDependencies($connection, $gameId);
    if ($dependencies !== []) {
        return ['deleted' => false, 'dependencies' => $dependencies, 'media_paths' => []];
    }

    $mediaStatement = $connection->prepare('SELECT storage_path FROM game_media WHERE game_id = :game_id');
    $mediaStatement->execute(['game_id' => $gameId]);
    $mediaPaths = $mediaStatement->fetchAll(PDO::FETCH_COLUMN);

    $statement = $connection->prepare('DELETE FROM games WHERE id = :id');
    $statement->execute(['id' => $gameId]);

    return [
        'deleted' => $statement->rowCount() === 1,
        'dependencies' => [],
        'media_paths' => $mediaPaths,
    ];
}

function normalizeGenreInput(array $input): array
{
    return [
        'name' => trim((string) ($input['name'] ?? '')),
        'slug' => strtolower(trim((string) ($input['slug'] ?? ''))),
    ];
}

function genreInputErrors(PDO $connection, array $input, ?int $genreId = null): array
{
    $errors = [];
    $nameError = lengthError($input['name'], 'Genre name', 1, 80);
    if ($nameError !== null || preg_match('/[\p{C}]/u', $input['name']) === 1) {
        $errors['name'] = $nameError ?? 'Genre name contains an unsupported control character.';
    }
    if (preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $input['slug']) !== 1 || mb_strlen($input['slug']) > 80) {
        $errors['slug'] = 'Slug must use 1-80 lowercase letters, numbers and single hyphens.';
    }

    if ($errors === []) {
        $statement = $connection->prepare(
            'SELECT id, name, slug FROM genres
             WHERE (LOWER(name) = LOWER(:name) OR slug = :slug)
               AND (:genre_id IS NULL OR id <> :genre_id_check) LIMIT 1'
        );
        $statement->bindValue(':name', $input['name']);
        $statement->bindValue(':slug', $input['slug']);
        $statement->bindValue(':genre_id', $genreId, $genreId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':genre_id_check', $genreId, $genreId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->execute();
        if ($statement->fetch() !== false) {
            $errors['name'] = 'A genre with this name or slug already exists.';
            $errors['slug'] = 'A genre with this name or slug already exists.';
        }
    }

    return $errors;
}

function saveGenre(PDO $connection, array $input, ?int $genreId = null): int
{
    if ($genreId === null) {
        $statement = $connection->prepare('INSERT INTO genres (name, slug) VALUES (:name, :slug)');
        $statement->execute($input);
        return (int) $connection->lastInsertId();
    }

    $statement = $connection->prepare('UPDATE genres SET name = :name, slug = :slug WHERE id = :id');
    $statement->execute($input + ['id' => $genreId]);
    return $genreId;
}

function deleteGenre(PDO $connection, int $genreId): array
{
    $countStatement = $connection->prepare('SELECT COUNT(*) FROM game_genres WHERE genre_id = :id');
    $countStatement->execute(['id' => $genreId]);
    $gameCount = (int) $countStatement->fetchColumn();
    if ($gameCount > 0) {
        return ['deleted' => false, 'game_count' => $gameCount];
    }

    $statement = $connection->prepare('DELETE FROM genres WHERE id = :id');
    $statement->execute(['id' => $genreId]);
    return ['deleted' => $statement->rowCount() === 1, 'game_count' => 0];
}

function loadFeaturedAdminGames(PDO $connection): array
{
    return $connection->query(
        "SELECT g.id, g.title, g.status, fg.position
         FROM games g LEFT JOIN featured_games fg ON fg.game_id = g.id
         WHERE g.status = 'published' OR fg.game_id IS NOT NULL
         ORDER BY COALESCE(fg.position, 99), g.title"
    )->fetchAll();
}

function normalizeFeaturedSelection(mixed $selection): array
{
    if (!is_array($selection)) {
        return [];
    }

    return array_values(array_filter(
        array_map(static fn (mixed $id): int => is_scalar($id) ? (int) $id : 0, $selection),
        static fn (int $id): bool => $id > 0
    ));
}

function featuredSelectionError(PDO $connection, array $gameIds): ?string
{
    if (count($gameIds) > MAX_FEATURED_GAMES) {
        return 'Select no more than five featured games.';
    }
    if (count($gameIds) !== count(array_unique($gameIds))) {
        return 'A game can appear only once in the featured list.';
    }
    if ($gameIds === []) {
        return null;
    }

    $placeholders = implode(',', array_fill(0, count($gameIds), '?'));
    $statement = $connection->prepare(
        "SELECT COUNT(*) FROM games WHERE status = 'published' AND id IN ($placeholders)"
    );
    foreach ($gameIds as $index => $gameId) {
        $statement->bindValue($index + 1, $gameId, PDO::PARAM_INT);
    }
    $statement->execute();

    return (int) $statement->fetchColumn() === count($gameIds)
        ? null
        : 'Every featured game must exist and be published.';
}

function saveFeaturedSelection(PDO $connection, array $gameIds, int $adminUserId): void
{
    $error = featuredSelectionError($connection, $gameIds);
    if ($error !== null) {
        throw new InvalidArgumentException($error);
    }

    $connection->beginTransaction();
    try {
        $connection->exec('DELETE FROM featured_games');
        $statement = $connection->prepare(
            'INSERT INTO featured_games (game_id, position, featured_by_user_id)
             VALUES (:game_id, :position, :admin_user_id)'
        );
        foreach ($gameIds as $index => $gameId) {
            $statement->execute([
                'game_id' => $gameId,
                'position' => $index + 1,
                'admin_user_id' => $adminUserId,
            ]);
        }
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}
