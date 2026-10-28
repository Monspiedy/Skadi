<?php

declare(strict_types=1);

class LibraryException extends RuntimeException
{
}

function parseLibraryFilters(array $query): array
{
    $warnings = [];
    $rawSearch = $query['q'] ?? '';
    $search = is_string($rawSearch) ? trim($rawSearch) : '';
    if (mb_strlen($search) > 80) {
        $search = '';
        $warnings[] = 'The title search was ignored because it exceeded 80 characters.';
    }

    $rawVisibility = $query['visibility'] ?? 'visible';
    $visibility = is_string($rawVisibility) ? $rawVisibility : 'visible';
    if (!in_array($visibility, ['all', 'visible', 'hidden'], true)) {
        $visibility = 'visible';
        $warnings[] = 'The visibility filter was not recognized and has been reset.';
    }

    return ['search' => $search, 'visibility' => $visibility, 'warnings' => $warnings];
}

function loadLibraryItems(PDO $connection, int $userId, array $filters): array
{
    $clauses = ['li.user_id = :user_id', 'li.removed_at IS NULL'];
    $parameters = ['user_id' => $userId];
    if ($filters['visibility'] === 'visible') {
        $clauses[] = 'li.hidden_at IS NULL';
    } elseif ($filters['visibility'] === 'hidden') {
        $clauses[] = 'li.hidden_at IS NOT NULL';
    }
    if ($filters['search'] !== '') {
        $clauses[] = 'g.title LIKE :title_search';
        $parameters['title_search'] = '%' . str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $filters['search']
        ) . '%';
    }

    $statement = $connection->prepare(
        "SELECT li.game_id, li.acquired_at, li.hidden_at, g.title, g.short_description,
                cover.storage_path AS cover_path, cover.alt_text AS cover_alt
         FROM library_items li
         INNER JOIN games g ON g.id = li.game_id
         LEFT JOIN game_media cover
            ON cover.game_id = g.id AND cover.media_type = 'cover' AND cover.sort_order = 0
         WHERE " . implode(' AND ', $clauses) . '
         ORDER BY g.title'
    );
    $statement->execute($parameters);

    return $statement->fetchAll();
}

function setLibraryItemHidden(PDO $connection, int $userId, int $gameId, bool $hidden): bool
{
    $statement = $connection->prepare(
        'UPDATE library_items
         SET hidden_at = ' . ($hidden ? 'UTC_TIMESTAMP()' : 'NULL') . '
         WHERE user_id = :user_id AND game_id = :game_id AND removed_at IS NULL'
    );
    $statement->execute(['user_id' => $userId, 'game_id' => $gameId]);

    return $statement->rowCount() === 1;
}

function removeLibraryAccess(PDO $connection, int $userId, int $gameId): bool
{
    $connection->beginTransaction();
    try {
        $ownership = $connection->prepare(
            'SELECT id FROM library_items
             WHERE user_id = :user_id AND game_id = :game_id AND removed_at IS NULL
             FOR UPDATE'
        );
        $ownership->execute(['user_id' => $userId, 'game_id' => $gameId]);
        if ($ownership->fetchColumn() === false) {
            $connection->rollBack();
            return false;
        }

        $remove = $connection->prepare(
            'UPDATE library_items
             SET removed_at = UTC_TIMESTAMP(), hidden_at = NULL
             WHERE user_id = :user_id AND game_id = :game_id AND removed_at IS NULL'
        );
        $remove->execute(['user_id' => $userId, 'game_id' => $gameId]);

        // Defensive cleanup keeps access state coherent without touching immutable order or wallet history.
        foreach (['cart_items', 'wishlist_items'] as $table) {
            $cleanup = $connection->prepare(
                "DELETE FROM $table WHERE user_id = :user_id AND game_id = :game_id"
            );
            $cleanup->execute(['user_id' => $userId, 'game_id' => $gameId]);
        }

        $connection->commit();
        return $remove->rowCount() === 1;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

function addGameToWishlist(PDO $connection, int $userId, int $gameId): void
{
    $connection->beginTransaction();
    try {
        $user = $connection->prepare('SELECT id FROM users WHERE id = :id AND status = \'active\' FOR UPDATE');
        $user->execute(['id' => $userId]);
        if ($user->fetchColumn() === false) {
            throw new LibraryException('Your account is not available.');
        }

        $game = $connection->prepare('SELECT id FROM games WHERE id = :id AND status = \'published\' FOR UPDATE');
        $game->execute(['id' => $gameId]);
        if ($game->fetchColumn() === false) {
            throw new LibraryException('That game is not available in the store.');
        }

        $owned = $connection->prepare(
            'SELECT id FROM library_items
             WHERE user_id = :user_id AND game_id = :game_id AND removed_at IS NULL FOR UPDATE'
        );
        $owned->execute(['user_id' => $userId, 'game_id' => $gameId]);
        if ($owned->fetchColumn() !== false) {
            throw new LibraryException('Owned games cannot be added to your wishlist.');
        }

        $existing = $connection->prepare(
            'SELECT game_id FROM wishlist_items WHERE user_id = :user_id AND game_id = :game_id FOR UPDATE'
        );
        $existing->execute(['user_id' => $userId, 'game_id' => $gameId]);
        if ($existing->fetchColumn() !== false) {
            throw new LibraryException('That game is already on your wishlist.');
        }

        $insert = $connection->prepare(
            'INSERT INTO wishlist_items (user_id, game_id) VALUES (:user_id, :game_id)'
        );
        $insert->execute(['user_id' => $userId, 'game_id' => $gameId]);
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

function removeGameFromWishlist(PDO $connection, int $userId, int $gameId): bool
{
    $statement = $connection->prepare(
        'DELETE FROM wishlist_items WHERE user_id = :user_id AND game_id = :game_id'
    );
    $statement->execute(['user_id' => $userId, 'game_id' => $gameId]);

    return $statement->rowCount() === 1;
}

function loadWishlist(PDO $connection, int $userId): array
{
    $statement = $connection->prepare(
        "SELECT wi.game_id, wi.created_at, g.title, g.short_description, g.base_price,
                cover.storage_path AS cover_path, cover.alt_text AS cover_alt
         FROM wishlist_items wi
         INNER JOIN games g ON g.id = wi.game_id AND g.status = 'published'
         LEFT JOIN library_items li
            ON li.user_id = wi.user_id AND li.game_id = wi.game_id AND li.removed_at IS NULL
         LEFT JOIN game_media cover
            ON cover.game_id = g.id AND cover.media_type = 'cover' AND cover.sort_order = 0
         WHERE wi.user_id = :user_id AND li.id IS NULL
         ORDER BY wi.created_at DESC, g.title"
    );
    $statement->execute(['user_id' => $userId]);
    $items = $statement->fetchAll();
    $prices = loadCurrentPriceRows($connection, array_map('intval', array_column($items, 'game_id')));
    foreach ($items as &$item) {
        $item = array_merge($item, $prices[(int) $item['game_id']] ?? [
            'current_price' => $item['base_price'], 'discount_percent' => 0,
        ]);
    }
    unset($item);

    return $items;
}

function moveWishlistGameToCart(PDO $connection, int $userId, int $gameId): void
{
    $connection->beginTransaction();
    try {
        $user = $connection->prepare('SELECT id FROM users WHERE id = :id AND status = \'active\' FOR UPDATE');
        $user->execute(['id' => $userId]);
        if ($user->fetchColumn() === false) {
            throw new LibraryException('Your account is not available.');
        }

        $wishlist = $connection->prepare(
            'SELECT game_id FROM wishlist_items WHERE user_id = :user_id AND game_id = :game_id FOR UPDATE'
        );
        $wishlist->execute(['user_id' => $userId, 'game_id' => $gameId]);
        if ($wishlist->fetchColumn() === false) {
            throw new LibraryException('That game is not on your wishlist.');
        }

        $saleId = loadActiveSaleId($connection, true);
        $prices = loadPriceRows($connection, [$gameId], $saleId, true);
        $game = $prices[$gameId] ?? null;
        if ($game === null || $game['status'] !== 'published') {
            throw new LibraryException('That game is not available in the store.');
        }

        $owned = $connection->prepare(
            'SELECT id FROM library_items
             WHERE user_id = :user_id AND game_id = :game_id AND removed_at IS NULL FOR UPDATE'
        );
        $owned->execute(['user_id' => $userId, 'game_id' => $gameId]);
        if ($owned->fetchColumn() !== false) {
            throw new LibraryException('You already own this game.');
        }

        $cart = $connection->prepare(
            'SELECT game_id FROM cart_items WHERE user_id = :user_id AND game_id = :game_id FOR UPDATE'
        );
        $cart->execute(['user_id' => $userId, 'game_id' => $gameId]);
        if ($cart->fetchColumn() === false) {
            $insert = $connection->prepare(
                'INSERT INTO cart_items (user_id, game_id, price_when_added)
                 VALUES (:user_id, :game_id, :price)'
            );
            $insert->execute([
                'user_id' => $userId,
                'game_id' => $gameId,
                'price' => $game['current_price'],
            ]);
        }

        $delete = $connection->prepare(
            'DELETE FROM wishlist_items WHERE user_id = :user_id AND game_id = :game_id'
        );
        $delete->execute(['user_id' => $userId, 'game_id' => $gameId]);
        if ($delete->rowCount() !== 1) {
            throw new LibraryException('The wishlist changed before the game could be moved.');
        }
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

function userCurrentlyOwnsGame(PDO $connection, int $userId, int $gameId): bool
{
    $statement = $connection->prepare(
        'SELECT 1 FROM library_items
         WHERE user_id = :user_id AND game_id = :game_id AND removed_at IS NULL'
    );
    $statement->execute(['user_id' => $userId, 'game_id' => $gameId]);

    return $statement->fetchColumn() !== false;
}

function loadGameReviews(PDO $connection, int $gameId): array
{
    $statement = $connection->prepare(
        "SELECT r.id, r.user_id, r.rating, r.body, r.created_at, r.updated_at,
                u.display_name
         FROM reviews r
         INNER JOIN users u ON u.id = r.user_id
         WHERE r.game_id = :game_id AND r.status = 'active'
         ORDER BY r.updated_at DESC, r.id DESC"
    );
    $statement->execute(['game_id' => $gameId]);

    return $statement->fetchAll();
}

function loadUserReview(PDO $connection, int $userId, int $gameId): ?array
{
    $statement = $connection->prepare(
        'SELECT id, rating, body, status, created_at, updated_at
         FROM reviews WHERE user_id = :user_id AND game_id = :game_id LIMIT 1'
    );
    $statement->execute(['user_id' => $userId, 'game_id' => $gameId]);
    $review = $statement->fetch();

    return is_array($review) ? $review : null;
}

function saveGameReview(PDO $connection, int $userId, int $gameId, int $rating, string $body): string
{
    $body = trim($body);
    if ($rating < 1 || $rating > 10) {
        throw new LibraryException('Rating must be a whole number from 1 to 10.');
    }
    if (mb_strlen($body) > 4000) {
        throw new LibraryException('Review text must be 4,000 characters or fewer.');
    }

    $connection->beginTransaction();
    try {
        $ownership = $connection->prepare(
            'SELECT id FROM library_items
             WHERE user_id = :user_id AND game_id = :game_id AND removed_at IS NULL FOR UPDATE'
        );
        $ownership->execute(['user_id' => $userId, 'game_id' => $gameId]);
        if ($ownership->fetchColumn() === false) {
            throw new LibraryException('Only current owners can create or edit a review for this game.');
        }

        $existing = $connection->prepare(
            'SELECT id, status, moderated_by_user_id
             FROM reviews WHERE user_id = :user_id AND game_id = :game_id FOR UPDATE'
        );
        $existing->execute(['user_id' => $userId, 'game_id' => $gameId]);
        $review = $existing->fetch();
        if ($review === false) {
            $insert = $connection->prepare(
                "INSERT INTO reviews (user_id, game_id, rating, body, status)
                 VALUES (:user_id, :game_id, :rating, :body, 'active')"
            );
            $insert->execute([
                'user_id' => $userId, 'game_id' => $gameId, 'rating' => $rating,
                'body' => $body === '' ? null : $body,
            ]);
            $result = 'created';
        } elseif ($review['status'] === 'hidden'
            || ($review['status'] === 'deleted' && $review['moderated_by_user_id'] !== null)) {
            throw new LibraryException('This review was moderated and cannot be edited or republished.');
        } else {
            $update = $connection->prepare(
                "UPDATE reviews
                 SET rating = :rating, body = :body, status = 'active', deleted_at = NULL,
                     moderated_by_user_id = NULL, moderation_reason = NULL
                 WHERE id = :id"
            );
            $update->execute([
                'rating' => $rating, 'body' => $body === '' ? null : $body, 'id' => (int) $review['id'],
            ]);
            $result = $review['status'] === 'deleted' ? 'created' : 'updated';
        }

        $connection->commit();
        return $result;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

function deleteGameReview(PDO $connection, int $userId, int $gameId): bool
{
    $statement = $connection->prepare(
        "UPDATE reviews SET status = 'deleted', deleted_at = UTC_TIMESTAMP()
         WHERE user_id = :user_id AND game_id = :game_id AND status <> 'deleted'"
    );
    $statement->execute(['user_id' => $userId, 'game_id' => $gameId]);

    return $statement->rowCount() === 1;
}

function formatUtcDateTime(string $value): string
{
    $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
    return $date->setTimezone(new DateTimeZone((string) appConfig('timezone')))->format('M j, Y H:i');
}
