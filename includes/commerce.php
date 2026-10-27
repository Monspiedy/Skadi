<?php

declare(strict_types=1);

final class CommerceException extends RuntimeException
{
}

function moneyToCents(string $amount): int
{
    if (preg_match('/\A([0-9]+)(?:\.([0-9]{1,2}))?\z/', $amount, $matches) !== 1) {
        throw new InvalidArgumentException('Invalid decimal money value.');
    }

    return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
}

function centsToMoney(int $cents): string
{
    if ($cents < 0) {
        throw new InvalidArgumentException('A displayed price cannot be negative.');
    }

    return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}

function commercePriceLabel(string $amount): string
{
    return moneyToCents($amount) === 0 ? 'Free' : '€' . centsToMoney(moneyToCents($amount));
}

function discountedPriceCents(int $basePriceCents, int $discountPercent): int
{
    if ($basePriceCents < 0 || $discountPercent < 0 || $discountPercent > 75) {
        throw new InvalidArgumentException('Invalid price calculation input.');
    }

    // Adding 50 before integer division gives normal half-up rounding to the nearest cent.
    return intdiv(($basePriceCents * (100 - $discountPercent)) + 50, 100);
}

function loadActiveSaleId(PDO $connection, bool $forUpdate = false): ?int
{
    $sql = "SELECT id FROM weekly_sales
            WHERE starts_at <= UTC_TIMESTAMP() AND ends_at > UTC_TIMESTAMP() AND status = 'active'
            ORDER BY generation_number DESC, id DESC LIMIT 1";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }
    $saleId = $connection->query($sql)->fetchColumn();

    return $saleId === false ? null : (int) $saleId;
}

function loadPriceRows(PDO $connection, array $gameIds, ?int $saleId, bool $forUpdate = false): array
{
    $gameIds = array_values(array_unique(array_map('intval', $gameIds)));
    if ($gameIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($gameIds), '?'));
    $sql = "SELECT id, title, base_price, status FROM games WHERE id IN ($placeholders) ORDER BY id";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }
    $statement = $connection->prepare($sql);
    $statement->execute($gameIds);
    $rows = [];
    foreach ($statement->fetchAll() as $game) {
        $game['discount_percent'] = 0;
        $rows[(int) $game['id']] = $game;
    }

    if ($saleId !== null && $rows !== []) {
        $discountSql = "SELECT game_id, discount_percent FROM weekly_discounts
                        WHERE sale_id = ? AND game_id IN ($placeholders) ORDER BY game_id";
        if ($forUpdate) {
            $discountSql .= ' FOR UPDATE';
        }
        $discountStatement = $connection->prepare($discountSql);
        $discountStatement->execute(array_merge([$saleId], $gameIds));
        foreach ($discountStatement->fetchAll() as $discount) {
            $gameId = (int) $discount['game_id'];
            if (isset($rows[$gameId])) {
                $rows[$gameId]['discount_percent'] = (int) $discount['discount_percent'];
            }
        }
    }

    foreach ($rows as &$row) {
        $baseCents = moneyToCents((string) $row['base_price']);
        $row['current_price'] = centsToMoney(discountedPriceCents($baseCents, (int) $row['discount_percent']));
    }
    unset($row);

    return $rows;
}

function loadCurrentPriceRows(PDO $connection, array $gameIds): array
{
    return loadPriceRows($connection, $gameIds, loadActiveSaleId($connection), false);
}

function addGameToCart(PDO $connection, int $userId, int $gameId): void
{
    $connection->beginTransaction();
    try {
        $userStatement = $connection->prepare('SELECT id FROM users WHERE id = :id AND status = \'active\' FOR UPDATE');
        $userStatement->execute(['id' => $userId]);
        if ($userStatement->fetchColumn() === false) {
            throw new CommerceException('Your account is not available for purchases.');
        }

        $saleId = loadActiveSaleId($connection, true);
        $prices = loadPriceRows($connection, [$gameId], $saleId, true);
        $game = $prices[$gameId] ?? null;
        if ($game === null || $game['status'] !== 'published') {
            throw new CommerceException('That game is not available in the store.');
        }

        $ownedStatement = $connection->prepare(
            'SELECT id FROM library_items WHERE user_id = :user_id AND game_id = :game_id FOR UPDATE'
        );
        $ownedStatement->execute(['user_id' => $userId, 'game_id' => $gameId]);
        if ($ownedStatement->fetchColumn() !== false) {
            throw new CommerceException('You already own this game.');
        }

        $cartStatement = $connection->prepare(
            'SELECT game_id FROM cart_items WHERE user_id = :user_id AND game_id = :game_id FOR UPDATE'
        );
        $cartStatement->execute(['user_id' => $userId, 'game_id' => $gameId]);
        if ($cartStatement->fetchColumn() !== false) {
            throw new CommerceException('That game is already in your cart.');
        }

        $insert = $connection->prepare(
            'INSERT INTO cart_items (user_id, game_id, price_when_added) VALUES (:user_id, :game_id, :price)'
        );
        $insert->execute([
            'user_id' => $userId,
            'game_id' => $gameId,
            'price' => $game['current_price'],
        ]);
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

function removeGameFromCart(PDO $connection, int $userId, int $gameId): bool
{
    $statement = $connection->prepare('DELETE FROM cart_items WHERE user_id = :user_id AND game_id = :game_id');
    $statement->execute(['user_id' => $userId, 'game_id' => $gameId]);

    return $statement->rowCount() === 1;
}

function loadCart(PDO $connection, int $userId): array
{
    $statement = $connection->prepare(
        "SELECT ci.game_id, ci.price_when_added, ci.added_at, g.title, g.status,
                gm.storage_path AS cover_path, gm.alt_text AS cover_alt
         FROM cart_items ci
         INNER JOIN games g ON g.id = ci.game_id
         LEFT JOIN game_media gm ON gm.game_id = g.id AND gm.media_type = 'cover' AND gm.sort_order = 0
         WHERE ci.user_id = :user_id ORDER BY ci.added_at, ci.game_id"
    );
    $statement->execute(['user_id' => $userId]);
    $items = $statement->fetchAll();
    $prices = loadCurrentPriceRows($connection, array_column($items, 'game_id'));
    $totalCents = 0;

    foreach ($items as &$item) {
        $gameId = (int) $item['game_id'];
        $price = $prices[$gameId] ?? null;
        $item['base_price'] = $price['base_price'] ?? '0.00';
        $item['discount_percent'] = (int) ($price['discount_percent'] ?? 0);
        $item['current_price'] = (string) ($price['current_price'] ?? '0.00');
        $item['price_changed'] = moneyToCents((string) $item['price_when_added']) !== moneyToCents($item['current_price']);
        $totalCents += moneyToCents($item['current_price']);
    }
    unset($item);

    return ['items' => $items, 'total' => centsToMoney($totalCents)];
}

function loadWalletBalance(PDO $connection, int $userId): string
{
    $statement = $connection->prepare('SELECT wallet_balance FROM users WHERE id = :id');
    $statement->execute(['id' => $userId]);
    $balance = $statement->fetchColumn();

    return $balance === false ? '0.00' : (string) $balance;
}

function generateOrderNumber(): string
{
    return 'SKD-' . strtoupper(bin2hex(random_bytes(8)));
}

function checkoutCart(PDO $connection, int $userId): array
{
    $connection->beginTransaction();
    try {
        // Lock the wallet owner first so repeated or concurrent submissions serialize consistently.
        $userStatement = $connection->prepare(
            'SELECT id, wallet_balance FROM users WHERE id = :id AND status = \'active\' FOR UPDATE'
        );
        $userStatement->execute(['id' => $userId]);
        $user = $userStatement->fetch();
        if (!is_array($user)) {
            throw new CommerceException('Your account is not available for checkout.');
        }

        $cartStatement = $connection->prepare(
            'SELECT game_id, price_when_added FROM cart_items WHERE user_id = :user_id ORDER BY game_id FOR UPDATE'
        );
        $cartStatement->execute(['user_id' => $userId]);
        $cartRows = $cartStatement->fetchAll();
        if ($cartRows === []) {
            throw new CommerceException('Your cart is empty.');
        }

        $gameIds = array_map('intval', array_column($cartRows, 'game_id'));
        $saleId = loadActiveSaleId($connection, true);
        $prices = loadPriceRows($connection, $gameIds, $saleId, true);
        if (count($prices) !== count($gameIds)) {
            throw new CommerceException('A game in your cart is no longer available.');
        }

        $placeholders = implode(',', array_fill(0, count($gameIds), '?'));
        $ownedStatement = $connection->prepare(
            "SELECT game_id FROM library_items WHERE user_id = ? AND game_id IN ($placeholders) ORDER BY game_id FOR UPDATE"
        );
        $ownedStatement->execute(array_merge([$userId], $gameIds));
        if ($ownedStatement->fetchColumn() !== false) {
            throw new CommerceException('Your cart contains a game that you already own.');
        }

        $subtotalCents = 0;
        $totalCents = 0;
        foreach ($gameIds as $gameId) {
            $game = $prices[$gameId];
            if ($game['status'] !== 'published') {
                throw new CommerceException('A game in your cart is no longer published.');
            }
            $subtotalCents += moneyToCents((string) $game['base_price']);
            $totalCents += moneyToCents((string) $game['current_price']);
        }
        $balanceCents = moneyToCents((string) $user['wallet_balance']);
        if ($balanceCents < $totalCents) {
            throw new CommerceException('Your wallet balance is too low for this purchase.');
        }

        $orderNumber = generateOrderNumber();
        $orderInsert = $connection->prepare(
            "INSERT INTO orders (order_number, user_id, status, subtotal, discount_total, total, completed_at)
             VALUES (:number, :user_id, 'completed', :subtotal, :discount_total, :total, UTC_TIMESTAMP())"
        );
        $orderInsert->execute([
            'number' => $orderNumber,
            'user_id' => $userId,
            'subtotal' => centsToMoney($subtotalCents),
            'discount_total' => centsToMoney($subtotalCents - $totalCents),
            'total' => centsToMoney($totalCents),
        ]);
        $orderId = (int) $connection->lastInsertId();

        $itemInsert = $connection->prepare(
            'INSERT INTO order_items (order_id, game_id, game_title, base_price, discount_percent, paid_price)
             VALUES (:order_id, :game_id, :title, :base_price, :discount_percent, :paid_price)'
        );
        $libraryInsert = $connection->prepare(
            'INSERT INTO library_items (user_id, game_id, acquired_order_item_id) VALUES (:user_id, :game_id, :order_item_id)'
        );
        foreach ($gameIds as $gameId) {
            $game = $prices[$gameId];
            $itemInsert->execute([
                'order_id' => $orderId,
                'game_id' => $gameId,
                'title' => $game['title'],
                'base_price' => $game['base_price'],
                'discount_percent' => $game['discount_percent'],
                'paid_price' => $game['current_price'],
            ]);
            $libraryInsert->execute([
                'user_id' => $userId,
                'game_id' => $gameId,
                'order_item_id' => (int) $connection->lastInsertId(),
            ]);
        }

        if ($totalCents > 0) {
            $newBalanceCents = $balanceCents - $totalCents;
            $balanceUpdate = $connection->prepare(
                'UPDATE users SET wallet_balance = :balance WHERE id = :id AND wallet_balance >= :total'
            );
            $balanceUpdate->execute([
                'balance' => centsToMoney($newBalanceCents),
                'id' => $userId,
                'total' => centsToMoney($totalCents),
            ]);
            if ($balanceUpdate->rowCount() !== 1) {
                throw new CommerceException('Your wallet balance changed before checkout completed.');
            }

            $ledgerInsert = $connection->prepare(
                "INSERT INTO wallet_transactions
                    (user_id, transaction_type, amount, balance_after, order_id, reason)
                 VALUES (:user_id, 'purchase', :amount, :balance_after, :order_id, :reason)"
            );
            $ledgerInsert->execute([
                'user_id' => $userId,
                'amount' => '-' . centsToMoney($totalCents),
                'balance_after' => centsToMoney($newBalanceCents),
                'order_id' => $orderId,
                'reason' => 'Purchase ' . $orderNumber,
            ]);
        }

        $clearCart = $connection->prepare('DELETE FROM cart_items WHERE user_id = :user_id');
        $clearCart->execute(['user_id' => $userId]);
        if ($clearCart->rowCount() !== count($gameIds)) {
            throw new CommerceException('The cart changed before checkout completed.');
        }

        $connection->commit();
        return ['order_id' => $orderId, 'order_number' => $orderNumber, 'total' => centsToMoney($totalCents)];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

function loadWalletTransactions(PDO $connection, int $userId, int $limit = 50): array
{
    $limit = max(1, min(100, $limit));
    $statement = $connection->prepare(
        "SELECT wt.transaction_type, wt.amount, wt.balance_after, wt.reason, wt.created_at,
                o.order_number
         FROM wallet_transactions wt LEFT JOIN orders o ON o.id = wt.order_id
         WHERE wt.user_id = :user_id ORDER BY wt.created_at DESC, wt.id DESC LIMIT $limit"
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function loadOrderHistory(PDO $connection, int $userId, int $limit = 50): array
{
    $limit = max(1, min(100, $limit));
    $statement = $connection->prepare(
        "SELECT o.id, o.order_number, o.status, o.subtotal, o.discount_total, o.total, o.created_at,
                oi.game_id, oi.game_title, oi.base_price, oi.discount_percent, oi.paid_price
         FROM orders o INNER JOIN order_items oi ON oi.order_id = o.id
         WHERE o.user_id = :user_id
         ORDER BY o.created_at DESC, o.id DESC, oi.id ASC LIMIT $limit"
    );
    $statement->execute(['user_id' => $userId]);
    $orders = [];
    foreach ($statement->fetchAll() as $row) {
        $orderId = (int) $row['id'];
        if (!isset($orders[$orderId])) {
            $orders[$orderId] = [
                'id' => $orderId,
                'order_number' => $row['order_number'],
                'status' => $row['status'],
                'subtotal' => $row['subtotal'],
                'discount_total' => $row['discount_total'],
                'total' => $row['total'],
                'created_at' => $row['created_at'],
                'items' => [],
            ];
        }
        $orders[$orderId]['items'][] = [
            'game_id' => (int) $row['game_id'],
            'game_title' => $row['game_title'],
            'base_price' => $row['base_price'],
            'discount_percent' => (int) $row['discount_percent'],
            'paid_price' => $row['paid_price'],
        ];
    }

    return array_values($orders);
}
