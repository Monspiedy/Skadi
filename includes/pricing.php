<?php

declare(strict_types=1);

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

/**
 * Authoritative current-price function used by catalog, cart, wishlist and checkout.
 * The optional clock makes boundary behavior testable; callers can lock rows for checkout.
 */
function loadCurrentPriceRows(
    PDO $connection,
    array $gameIds,
    ?DateTimeImmutable $now = null,
    bool $forUpdate = false
): array {
    $gameIds = array_values(array_unique(array_filter(array_map('intval', $gameIds), static fn (int $id): bool => $id > 0)));
    if ($gameIds === []) {
        return [];
    }

    $window = weekendSaleWindow($now);
    $saleId = ensureCurrentWeekendSale($connection, $now);
    if ($saleId !== null && $forUpdate) {
        // Re-select the active generation while locking it. If regeneration committed between
        // the initial read and this lock, checkout receives the new generation, never base price.
        $lockedSale = findActivePeriodSale($connection, $window, true);
        $saleId = $lockedSale === null ? null : (int) $lockedSale['id'];
    }

    $placeholders = implode(',', array_fill(0, count($gameIds), '?'));
    $gameSql = "SELECT id, title, base_price, status FROM games WHERE id IN ($placeholders) ORDER BY id";
    if ($forUpdate) {
        $gameSql .= ' FOR UPDATE';
    }
    $gameStatement = $connection->prepare($gameSql);
    $gameStatement->execute($gameIds);
    $rows = [];
    foreach ($gameStatement->fetchAll() as $game) {
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
        $row['sale_id'] = $saleId;
    }
    unset($row);

    return $rows;
}
