<?php

declare(strict_types=1);

const CATALOG_PAGE_SIZE = 6;
const CATALOG_SUGGESTION_LIMIT = 5;
const CATALOG_SUGGESTION_MIN_LENGTH = 2;

function catalogSortOptions(): array
{
    return [
        'title' => 'Title',
        'price' => 'Price: low to high',
        'rating' => 'Rating',
        'newest' => 'Newest',
        'popularity' => 'Popularity',
    ];
}

function catalogPriceOptions(): array
{
    return [
        'all' => 'Any price',
        'free' => 'Free',
        'under-15' => 'Under €15',
        '15-40' => '€15 to €40',
        'over-40' => 'Over €40',
    ];
}

function loadCatalogGenres(PDO $connection): array
{
    $statement = $connection->query(
        'SELECT ge.slug, ge.name, COUNT(g.id) AS game_count
         FROM genres ge
         LEFT JOIN game_genres gg ON gg.genre_id = ge.id
         LEFT JOIN games g ON g.id = gg.game_id AND g.status = \'published\'
         GROUP BY ge.id, ge.slug, ge.name
         HAVING game_count > 0
         ORDER BY ge.name'
    );

    return $statement->fetchAll();
}

function escapeCatalogLikeValue(string $value): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
}

function normalizeCatalogSuggestionQuery(mixed $rawQuery): array
{
    if (!is_string($rawQuery) || !mb_check_encoding($rawQuery, 'UTF-8')) {
        return ['valid' => false, 'query' => '', 'error' => 'The search query must be valid text.'];
    }
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $rawQuery) === 1) {
        return ['valid' => false, 'query' => '', 'error' => 'The search query contains unsupported characters.'];
    }

    $query = preg_replace('/\s+/u', ' ', trim($rawQuery));
    if (!is_string($query)) {
        return ['valid' => false, 'query' => '', 'error' => 'The search query must be valid text.'];
    }

    if (mb_strlen($query) > 80) {
        return ['valid' => false, 'query' => '', 'error' => 'The search query must be 80 characters or fewer.'];
    }

    return ['valid' => true, 'query' => $query, 'error' => null];
}

function loadCatalogSuggestions(PDO $connection, string $query): array
{
    if (mb_strlen($query) < CATALOG_SUGGESTION_MIN_LENGTH) {
        return [];
    }

    $statement = $connection->prepare(
        "SELECT id, title FROM games
         WHERE status = 'published' AND title LIKE :title_search ESCAPE '\\\\'
         ORDER BY title ASC
         LIMIT " . CATALOG_SUGGESTION_LIMIT
    );
    $statement->execute(['title_search' => '%' . escapeCatalogLikeValue($query) . '%']);

    return array_map(static fn (array $game): array => [
        'id' => (int) $game['id'],
        'title' => (string) $game['title'],
    ], $statement->fetchAll());
}

function parseCatalogFilters(array $query, array $genres): array
{
    $warnings = [];
    $genreSlugs = array_column($genres, 'slug');

    $rawSearch = $query['q'] ?? '';
    $search = is_string($rawSearch) ? trim($rawSearch) : '';
    if (mb_strlen($search) > 80) {
        $search = '';
        $warnings[] = 'The title search was ignored because it exceeded 80 characters.';
    }

    $rawGenre = $query['genre'] ?? '';
    $genre = is_string($rawGenre) ? $rawGenre : '';
    if ($genre !== '' && !in_array($genre, $genreSlugs, true)) {
        $genre = '';
        $warnings[] = 'The selected genre was not recognized and has been reset.';
    }

    $rawPrice = $query['price'] ?? 'all';
    $price = is_string($rawPrice) ? $rawPrice : 'all';
    if (!array_key_exists($price, catalogPriceOptions())) {
        $price = 'all';
        $warnings[] = 'The selected price filter was not recognized and has been reset.';
    }

    $rawSort = $query['sort'] ?? 'popularity';
    $sort = is_string($rawSort) ? $rawSort : 'popularity';
    if (!array_key_exists($sort, catalogSortOptions())) {
        $sort = 'popularity';
        $warnings[] = 'The selected sort order was not recognized and has been reset.';
    }

    $rawPage = $query['page'] ?? '1';
    $page = is_string($rawPage) || is_int($rawPage) ? (string) $rawPage : '';
    if ($page === '' || !ctype_digit($page) || (int) $page < 1) {
        $page = '1';
        $warnings[] = 'The page number was invalid and has been reset.';
    }

    return [
        'search' => $search,
        'genre' => $genre,
        'price' => $price,
        'sort' => $sort,
        'page' => min((int) $page, 1000000),
        'warnings' => $warnings,
    ];
}

function catalogWhere(array $filters): array
{
    $clauses = ["g.status = 'published'"];
    $parameters = [];

    if ($filters['search'] !== '') {
        $clauses[] = 'g.title LIKE :title_search';
        $parameters['title_search'] = '%' . escapeCatalogLikeValue($filters['search']) . '%';
    }

    if ($filters['genre'] !== '') {
        $clauses[] = 'EXISTS (
            SELECT 1
            FROM game_genres filter_gg
            INNER JOIN genres filter_ge ON filter_ge.id = filter_gg.genre_id
            WHERE filter_gg.game_id = g.id AND filter_ge.slug = :genre_slug
        )';
        $parameters['genre_slug'] = $filters['genre'];
    }

    $priceClause = match ($filters['price']) {
        'free' => 'g.base_price = 0',
        'under-15' => 'g.base_price > 0 AND g.base_price < 15',
        '15-40' => 'g.base_price >= 15 AND g.base_price <= 40',
        'over-40' => 'g.base_price > 40',
        default => '',
    };

    if ($priceClause !== '') {
        $clauses[] = $priceClause;
    }

    return ['sql' => implode(' AND ', $clauses), 'parameters' => $parameters];
}

function catalogSortExpression(string $sort): string
{
    // Only these fixed SQL fragments can reach ORDER BY; request values never do.
    return match ($sort) {
        'title' => 'g.title ASC',
        'price' => 'g.base_price ASC, g.title ASC',
        'rating' => 'COALESCE(review_stats.average_rating, 0) DESC, review_stats.review_count DESC, g.title ASC',
        'newest' => 'g.release_date DESC, g.title ASC',
        default => 'COALESCE(owner_stats.owner_count, 0) DESC, g.title ASC',
    };
}

function bindCatalogParameters(PDOStatement $statement, array $parameters): void
{
    foreach ($parameters as $name => $value) {
        $statement->bindValue(':' . $name, $value, PDO::PARAM_STR);
    }
}

function countCatalogGames(PDO $connection, array $filters): int
{
    $where = catalogWhere($filters);
    $statement = $connection->prepare('SELECT COUNT(*) FROM games g WHERE ' . $where['sql']);
    bindCatalogParameters($statement, $where['parameters']);
    $statement->execute();

    return (int) $statement->fetchColumn();
}

function loadCatalogGames(PDO $connection, array $filters, int $page, ?int $viewerUserId = null): array
{
    $where = catalogWhere($filters);
    $offset = ($page - 1) * CATALOG_PAGE_SIZE;
    $viewerUserId = $viewerUserId ?? 0;
    $sql = 'SELECT g.id, g.slug, g.title, g.short_description, g.release_date, g.base_price,
                   cover.storage_path AS cover_path, cover.alt_text AS cover_alt,
                   COALESCE(review_stats.review_count, 0) AS review_count,
                   review_stats.average_rating,
                   COALESCE(owner_stats.owner_count, 0) AS owner_count,
                   CASE WHEN viewer_library.user_id IS NULL THEN 0 ELSE 1 END AS is_owned,
                   CASE WHEN viewer_cart.user_id IS NULL THEN 0 ELSE 1 END AS is_in_cart,
                   CASE WHEN viewer_wishlist.user_id IS NULL THEN 0 ELSE 1 END AS is_wishlisted
            FROM games g
            LEFT JOIN game_media cover
                ON cover.game_id = g.id AND cover.media_type = \'cover\' AND cover.sort_order = 0
            LEFT JOIN (
                SELECT game_id, COUNT(*) AS review_count, ROUND(AVG(rating), 1) AS average_rating
                FROM reviews WHERE status = \'active\' GROUP BY game_id
            ) review_stats ON review_stats.game_id = g.id
            LEFT JOIN (
                SELECT game_id, COUNT(*) AS owner_count
                FROM library_items WHERE removed_at IS NULL GROUP BY game_id
            ) owner_stats ON owner_stats.game_id = g.id
            LEFT JOIN library_items viewer_library
                ON viewer_library.game_id = g.id AND viewer_library.user_id = :library_user_id
                AND viewer_library.removed_at IS NULL
            LEFT JOIN cart_items viewer_cart
                ON viewer_cart.game_id = g.id AND viewer_cart.user_id = :cart_user_id
            LEFT JOIN wishlist_items viewer_wishlist
                ON viewer_wishlist.game_id = g.id AND viewer_wishlist.user_id = :wishlist_user_id
            WHERE ' . $where['sql'] . '
            ORDER BY ' . catalogSortExpression($filters['sort']) . '
            LIMIT :page_size OFFSET :page_offset';

    $statement = $connection->prepare($sql);
    bindCatalogParameters($statement, $where['parameters']);
    $statement->bindValue(':library_user_id', $viewerUserId, PDO::PARAM_INT);
    $statement->bindValue(':cart_user_id', $viewerUserId, PDO::PARAM_INT);
    $statement->bindValue(':wishlist_user_id', $viewerUserId, PDO::PARAM_INT);
    $statement->bindValue(':page_size', CATALOG_PAGE_SIZE, PDO::PARAM_INT);
    $statement->bindValue(':page_offset', $offset, PDO::PARAM_INT);
    $statement->execute();
    $games = $statement->fetchAll();

    return attachGenresToGames($connection, $games);
}

function loadFeaturedGames(PDO $connection, ?int $viewerUserId = null): array
{
    $statement = $connection->prepare(
        "SELECT g.id, g.slug, g.title, g.short_description, g.release_date, g.base_price,
                cover.storage_path AS cover_path, cover.alt_text AS cover_alt,
                COALESCE(review_stats.review_count, 0) AS review_count,
                review_stats.average_rating, COALESCE(owner_stats.owner_count, 0) AS owner_count,
                CASE WHEN viewer_library.user_id IS NULL THEN 0 ELSE 1 END AS is_owned,
                CASE WHEN viewer_cart.user_id IS NULL THEN 0 ELSE 1 END AS is_in_cart,
                CASE WHEN viewer_wishlist.user_id IS NULL THEN 0 ELSE 1 END AS is_wishlisted
         FROM featured_games fg
         INNER JOIN games g ON g.id = fg.game_id AND g.status = 'published'
         LEFT JOIN game_media cover
             ON cover.game_id = g.id AND cover.media_type = 'cover' AND cover.sort_order = 0
         LEFT JOIN (
             SELECT game_id, COUNT(*) AS review_count, ROUND(AVG(rating), 1) AS average_rating
             FROM reviews WHERE status = 'active' GROUP BY game_id
         ) review_stats ON review_stats.game_id = g.id
         LEFT JOIN (
             SELECT game_id, COUNT(*) AS owner_count
             FROM library_items WHERE removed_at IS NULL GROUP BY game_id
         ) owner_stats ON owner_stats.game_id = g.id
         LEFT JOIN library_items viewer_library
             ON viewer_library.game_id = g.id AND viewer_library.user_id = :library_user_id
             AND viewer_library.removed_at IS NULL
         LEFT JOIN cart_items viewer_cart
             ON viewer_cart.game_id = g.id AND viewer_cart.user_id = :cart_user_id
         LEFT JOIN wishlist_items viewer_wishlist
             ON viewer_wishlist.game_id = g.id AND viewer_wishlist.user_id = :wishlist_user_id
         ORDER BY fg.position
         LIMIT 5"
    );
    $viewerUserId = $viewerUserId ?? 0;
    $statement->bindValue(':library_user_id', $viewerUserId, PDO::PARAM_INT);
    $statement->bindValue(':cart_user_id', $viewerUserId, PDO::PARAM_INT);
    $statement->bindValue(':wishlist_user_id', $viewerUserId, PDO::PARAM_INT);
    $statement->execute();

    return attachGenresToGames($connection, $statement->fetchAll());
}

function loadHomeStorefrontGames(PDO $connection, ?int $viewerUserId = null): array
{
    // One bounded query supplies every home shelf, including viewer state without per-card lookups.
    $statement = $connection->prepare(
        "SELECT g.id, g.slug, g.title, g.short_description, g.release_date, g.base_price,
                cover.storage_path AS cover_path, cover.alt_text AS cover_alt,
                fg.position AS featured_position,
                COALESCE(review_stats.review_count, 0) AS review_count,
                review_stats.average_rating, COALESCE(owner_stats.owner_count, 0) AS owner_count,
                CASE WHEN viewer_library.user_id IS NULL THEN 0 ELSE 1 END AS is_owned,
                CASE WHEN viewer_cart.user_id IS NULL THEN 0 ELSE 1 END AS is_in_cart,
                CASE WHEN viewer_wishlist.user_id IS NULL THEN 0 ELSE 1 END AS is_wishlisted
         FROM games g
         LEFT JOIN featured_games fg ON fg.game_id = g.id
         LEFT JOIN game_media cover
             ON cover.game_id = g.id AND cover.media_type = 'cover' AND cover.sort_order = 0
         LEFT JOIN (
             SELECT game_id, COUNT(*) AS review_count, ROUND(AVG(rating), 1) AS average_rating
             FROM reviews WHERE status = 'active' GROUP BY game_id
         ) review_stats ON review_stats.game_id = g.id
         LEFT JOIN (
             SELECT game_id, COUNT(*) AS owner_count
             FROM library_items WHERE removed_at IS NULL GROUP BY game_id
         ) owner_stats ON owner_stats.game_id = g.id
         LEFT JOIN library_items viewer_library
             ON viewer_library.game_id = g.id AND viewer_library.user_id = :library_user_id
             AND viewer_library.removed_at IS NULL
         LEFT JOIN cart_items viewer_cart
             ON viewer_cart.game_id = g.id AND viewer_cart.user_id = :cart_user_id
         LEFT JOIN wishlist_items viewer_wishlist
             ON viewer_wishlist.game_id = g.id AND viewer_wishlist.user_id = :wishlist_user_id
         WHERE g.status = 'published'
         ORDER BY CASE WHEN fg.position IS NULL THEN 1 ELSE 0 END, fg.position, g.title
         LIMIT 24"
    );
    $viewerUserId = $viewerUserId ?? 0;
    $statement->bindValue(':library_user_id', $viewerUserId, PDO::PARAM_INT);
    $statement->bindValue(':cart_user_id', $viewerUserId, PDO::PARAM_INT);
    $statement->bindValue(':wishlist_user_id', $viewerUserId, PDO::PARAM_INT);
    $statement->execute();

    return attachGenresToGames($connection, $statement->fetchAll());
}

function takeUniqueStorefrontGames(array $games, array &$usedGameIds, int $limit): array
{
    $selected = [];

    foreach ($games as $game) {
        $gameId = (int) $game['id'];
        if (isset($usedGameIds[$gameId])) {
            continue;
        }

        $usedGameIds[$gameId] = true;
        $selected[] = $game;
        if (count($selected) === $limit) {
            break;
        }
    }

    return $selected;
}

function attachGenresToGames(PDO $connection, array $games): array
{
    if ($games === []) {
        return [];
    }

    $gameIds = array_values(array_unique(array_map(
        static fn (array $game): int => (int) $game['id'],
        $games
    )));
    $placeholders = implode(',', array_fill(0, count($gameIds), '?'));
    $statement = $connection->prepare(
        'SELECT gg.game_id, ge.slug, ge.name
         FROM game_genres gg
         INNER JOIN genres ge ON ge.id = gg.genre_id
         WHERE gg.game_id IN (' . $placeholders . ')
         ORDER BY ge.name'
    );

    foreach ($gameIds as $index => $gameId) {
        $statement->bindValue($index + 1, $gameId, PDO::PARAM_INT);
    }
    $statement->execute();

    $genresByGame = [];
    foreach ($statement->fetchAll() as $genre) {
        $genresByGame[(int) $genre['game_id']][] = [
            'slug' => $genre['slug'],
            'name' => $genre['name'],
        ];
    }

    foreach ($games as &$game) {
        $game['genres'] = $genresByGame[(int) $game['id']] ?? [];
    }
    unset($game);

    return $games;
}

function loadGameDetail(PDO $connection, int $gameId): ?array
{
    $statement = $connection->prepare(
        "SELECT g.id, g.slug, g.title, g.short_description, g.description, g.developer,
                g.publisher, g.release_date, g.base_price, g.system_requirements,
                COALESCE(review_stats.review_count, 0) AS review_count,
                review_stats.average_rating, COALESCE(owner_stats.owner_count, 0) AS owner_count
         FROM games g
         LEFT JOIN (
             SELECT game_id, COUNT(*) AS review_count, ROUND(AVG(rating), 1) AS average_rating
             FROM reviews WHERE status = 'active' GROUP BY game_id
         ) review_stats ON review_stats.game_id = g.id
         LEFT JOIN (
             SELECT game_id, COUNT(*) AS owner_count
             FROM library_items WHERE removed_at IS NULL GROUP BY game_id
         ) owner_stats ON owner_stats.game_id = g.id
         WHERE g.id = :game_id AND g.status = 'published'
         LIMIT 1"
    );
    $statement->bindValue(':game_id', $gameId, PDO::PARAM_INT);
    $statement->execute();
    $game = $statement->fetch();

    if ($game === false) {
        return null;
    }

    $game = attachGenresToGames($connection, [$game])[0];
    $mediaStatement = $connection->prepare(
        'SELECT media_type, storage_path, alt_text, sort_order
         FROM game_media
         WHERE game_id = :game_id
         ORDER BY media_type, sort_order'
    );
    $mediaStatement->bindValue(':game_id', $gameId, PDO::PARAM_INT);
    $mediaStatement->execute();
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

function catalogQueryUrl(array $filters, int $page, array $overrides = []): string
{
    $parameters = [
        'q' => $filters['search'],
        'genre' => $filters['genre'],
        'price' => $filters['price'],
        'sort' => $filters['sort'],
        'page' => $page,
    ];
    $parameters = array_merge($parameters, $overrides);
    $parameters = array_filter(
        $parameters,
        static fn (mixed $value, string $key): bool => $value !== '' && !($key === 'page' && $value === 1),
        ARRAY_FILTER_USE_BOTH
    );

    return baseUrl('store.php') . ($parameters === [] ? '' : '?' . http_build_query($parameters));
}

function gamePriceLabel(string $basePrice): string
{
    if ((float) $basePrice === 0.0) {
        return 'Free';
    }

    return '€' . number_format((float) $basePrice, 2, '.', '');
}

function gameRatingLabel(array $game): string
{
    if ((int) $game['review_count'] === 0 || $game['average_rating'] === null) {
        return 'Not rated yet';
    }

    return number_format((float) $game['average_rating'], 1) . '/10 from '
        . (int) $game['review_count'] . ((int) $game['review_count'] === 1 ? ' review' : ' reviews');
}

function safeGameMediaUrl(?string $storagePath): ?string
{
    if ($storagePath === null || str_contains($storagePath, '..')) {
        return null;
    }

    if (preg_match('#^(assets/images|uploads/(?:covers|screenshots))/[a-zA-Z0-9/_-]+\.(?:jpe?g|png|webp)$#', $storagePath) !== 1) {
        return null;
    }

    return baseUrl($storagePath);
}
