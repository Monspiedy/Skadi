<?php

declare(strict_types=1);

final class WeekendSaleException extends RuntimeException
{
}

function saleTimezone(): DateTimeZone
{
    static $timezone = null;

    if (!$timezone instanceof DateTimeZone) {
        $timezone = new DateTimeZone((string) appConfig('timezone', 'Europe/Helsinki'));
    }

    return $timezone;
}

function saleUtcTimezone(): DateTimeZone
{
    static $timezone = null;

    if (!$timezone instanceof DateTimeZone) {
        $timezone = new DateTimeZone('UTC');
    }

    return $timezone;
}

function saleNow(?DateTimeImmutable $now = null): DateTimeImmutable
{
    return ($now ?? new DateTimeImmutable('now', saleTimezone()))->setTimezone(saleTimezone());
}

/**
 * Return the Helsinki weekend containing $now, or the next weekend when outside a sale.
 * Adding calendar days in the real timezone preserves the correct DST offset at each boundary.
 */
function weekendSaleWindow(?DateTimeImmutable $now = null): array
{
    $localNow = saleNow($now);
    $weekday = (int) $localNow->format('N');
    $isActive = $weekday >= 5;

    if ($isActive) {
        $start = $localNow->modify('-' . ($weekday - 5) . ' days')->setTime(0, 0);
    } else {
        $start = $localNow->modify('+' . (5 - $weekday) . ' days')->setTime(0, 0);
    }

    $end = $start->modify('+3 days');

    return [
        'is_active' => $isActive && $localNow >= $start && $localNow < $end,
        'starts_local' => $start,
        'ends_local' => $end,
        'starts_utc' => $start->setTimezone(saleUtcTimezone()),
        'ends_utc' => $end->setTimezone(saleUtcTimezone()),
    ];
}

function saleDatabaseDateTime(DateTimeImmutable $date): string
{
    return $date->setTimezone(saleUtcTimezone())->format('Y-m-d H:i:s');
}

function salePeriodLockName(array $window): string
{
    return 'skadi_sale_' . $window['starts_utc']->format('YmdHis');
}

function acquireSaleLock(PDO $connection, string $lockName): void
{
    $statement = $connection->prepare('SELECT GET_LOCK(:lock_name, 10)');
    $statement->execute(['lock_name' => $lockName]);
    if ((int) $statement->fetchColumn() !== 1) {
        throw new WeekendSaleException('The weekend sale is busy. Please try again.');
    }
}

function releaseSaleLock(PDO $connection, string $lockName): void
{
    $statement = $connection->prepare('SELECT RELEASE_LOCK(:lock_name)');
    $statement->execute(['lock_name' => $lockName]);
}

function findActivePeriodSale(PDO $connection, array $window, bool $forUpdate = false): ?array
{
    $sql = "SELECT id, generation_number, status
            FROM weekly_sales
            WHERE starts_at = :starts_at AND ends_at = :ends_at AND status = 'active'
            ORDER BY generation_number DESC LIMIT 1";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }
    $statement = $connection->prepare($sql);
    $statement->execute([
        'starts_at' => saleDatabaseDateTime($window['starts_utc']),
        'ends_at' => saleDatabaseDateTime($window['ends_utc']),
    ]);
    $sale = $statement->fetch();

    return is_array($sale) ? $sale : null;
}

function insertSaleDiscounts(PDO $connection, int $saleId, bool $fillMissing = false): int
{
    $games = $connection->query(
        "SELECT id FROM games
         WHERE status = 'published' AND base_price > 0
         ORDER BY id FOR UPDATE"
    )->fetchAll(PDO::FETCH_COLUMN);
    $insert = $connection->prepare(
        ($fillMissing ? 'INSERT IGNORE' : 'INSERT') . ' INTO weekly_discounts (sale_id, game_id, discount_percent)
         VALUES (:sale_id, :game_id, :discount_percent)'
    );

    foreach ($games as $gameId) {
        $insert->execute([
            'sale_id' => $saleId,
            'game_id' => (int) $gameId,
            'discount_percent' => random_int(2, 15) * 5,
        ]);
    }

    $countStatement = $connection->prepare('SELECT COUNT(*) FROM weekly_discounts WHERE sale_id = :sale_id');
    $countStatement->execute(['sale_id' => $saleId]);
    $discountCount = (int) $countStatement->fetchColumn();
    if ($discountCount !== count($games)) {
        throw new WeekendSaleException('The complete weekend discount set could not be created.');
    }

    return $discountCount;
}

/**
 * Lazily create generation one. A named lock serializes first requests, while the transaction
 * keeps the sale header and its complete discount set invisible until both are committed.
 */
function ensureCurrentWeekendSale(PDO $connection, ?DateTimeImmutable $now = null): ?int
{
    $window = weekendSaleWindow($now);
    if (!$window['is_active']) {
        return null;
    }

    $existing = findActivePeriodSale($connection, $window);
    if ($existing !== null) {
        return (int) $existing['id'];
    }

    // Lazy generation must own its transaction so the complete set is committed before
    // the advisory lock is released. Commerce callers preflight this before their transaction.
    if ($connection->inTransaction()) {
        throw new WeekendSaleException('The weekend sale must be prepared before starting this transaction.');
    }

    $lockName = salePeriodLockName($window);
    acquireSaleLock($connection, $lockName);

    try {
        $connection->beginTransaction();

        $existing = findActivePeriodSale($connection, $window, true);
        if ($existing !== null) {
            $connection->commit();
            return (int) $existing['id'];
        }

        $periodStatement = $connection->prepare(
            'SELECT id, generation_number, status FROM weekly_sales
             WHERE starts_at = :starts_at AND ends_at = :ends_at
             ORDER BY generation_number DESC FOR UPDATE'
        );
        $parameters = [
            'starts_at' => saleDatabaseDateTime($window['starts_utc']),
            'ends_at' => saleDatabaseDateTime($window['ends_utc']),
        ];
        $periodStatement->execute($parameters);
        $generations = $periodStatement->fetchAll();

        if ($generations !== [] && $generations[0]['status'] === 'scheduled') {
            $saleId = (int) $generations[0]['id'];
            $prune = $connection->prepare(
                "DELETE wd FROM weekly_discounts wd
                 LEFT JOIN games g ON g.id = wd.game_id
                 WHERE wd.sale_id = :sale_id
                   AND (g.id IS NULL OR g.status <> 'published' OR g.base_price = 0)"
            );
            $prune->execute(['sale_id' => $saleId]);
            insertSaleDiscounts($connection, $saleId, true);
            $activate = $connection->prepare("UPDATE weekly_sales SET status = 'active' WHERE id = :id");
            $activate->execute(['id' => $saleId]);
        } else {
            $generationNumber = $generations === [] ? 1 : ((int) $generations[0]['generation_number'] + 1);
            $insert = $connection->prepare(
                "INSERT INTO weekly_sales (starts_at, ends_at, generation_number, status)
                 VALUES (:starts_at, :ends_at, :generation_number, 'active')"
            );
            $insert->execute($parameters + ['generation_number' => $generationNumber]);
            $saleId = (int) $connection->lastInsertId();
            insertSaleDiscounts($connection, $saleId);
        }

        $connection->commit();
        return $saleId;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    } finally {
        releaseSaleLock($connection, $lockName);
    }
}

function regenerateCurrentWeekendSale(PDO $connection, int $adminUserId, ?DateTimeImmutable $now = null): array
{
    $window = weekendSaleWindow($now);
    if (!$window['is_active']) {
        throw new WeekendSaleException('A weekend sale can only be regenerated while its Friday-to-Monday window is active.');
    }

    $lockName = salePeriodLockName($window);
    acquireSaleLock($connection, $lockName);

    try {
        $connection->beginTransaction();
        $periodStatement = $connection->prepare(
            'SELECT id, generation_number FROM weekly_sales
             WHERE starts_at = :starts_at AND ends_at = :ends_at
             ORDER BY generation_number DESC FOR UPDATE'
        );
        $parameters = [
            'starts_at' => saleDatabaseDateTime($window['starts_utc']),
            'ends_at' => saleDatabaseDateTime($window['ends_utc']),
        ];
        $periodStatement->execute($parameters);
        $generations = $periodStatement->fetchAll();
        $generationNumber = $generations === [] ? 1 : ((int) $generations[0]['generation_number'] + 1);

        $supersede = $connection->prepare(
            "UPDATE weekly_sales SET status = 'superseded'
             WHERE starts_at = :starts_at AND ends_at = :ends_at AND status IN ('active', 'scheduled')"
        );
        $supersede->execute($parameters);

        $insert = $connection->prepare(
            "INSERT INTO weekly_sales
                (starts_at, ends_at, generation_number, status, generated_by_user_id)
             VALUES (:starts_at, :ends_at, :generation_number, 'active', :admin_user_id)"
        );
        $insert->execute($parameters + [
            'generation_number' => $generationNumber,
            'admin_user_id' => $adminUserId,
        ]);
        $saleId = (int) $connection->lastInsertId();
        $discountCount = insertSaleDiscounts($connection, $saleId);
        $connection->commit();

        return ['sale_id' => $saleId, 'generation_number' => $generationNumber, 'discount_count' => $discountCount];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    } finally {
        releaseSaleLock($connection, $lockName);
    }
}

function loadWeekendSaleAudit(PDO $connection, int $limit = 12): array
{
    $limit = max(1, min(50, $limit));
    return $connection->query(
        "SELECT ws.id, ws.starts_at, ws.ends_at, ws.generation_number, ws.status, ws.created_at,
                u.display_name AS generated_by, COUNT(wd.game_id) AS discount_count
         FROM weekly_sales ws
         LEFT JOIN users u ON u.id = ws.generated_by_user_id
         LEFT JOIN weekly_discounts wd ON wd.sale_id = ws.id
         GROUP BY ws.id, ws.starts_at, ws.ends_at, ws.generation_number, ws.status,
                  ws.created_at, u.display_name
         ORDER BY ws.starts_at DESC, ws.generation_number DESC LIMIT $limit"
    )->fetchAll();
}

function loadLatestWeekendSaleDiscounts(PDO $connection): array
{
    return $connection->query(
        "SELECT ws.id AS sale_id, ws.generation_number, ws.status, ws.starts_at, ws.ends_at,
                g.title, g.base_price, wd.discount_percent
         FROM weekly_sales ws
         JOIN weekly_discounts wd ON wd.sale_id = ws.id
         JOIN games g ON g.id = wd.game_id
         WHERE ws.id = (SELECT id FROM weekly_sales ORDER BY starts_at DESC, generation_number DESC LIMIT 1)
         ORDER BY g.title"
    )->fetchAll();
}

function weekendSaleCountdownState(?DateTimeImmutable $now = null): array
{
    $window = weekendSaleWindow($now);
    $target = $window['is_active'] ? $window['ends_local'] : $window['starts_local'];

    return [
        'is_active' => $window['is_active'],
        'target_iso' => $target->format(DateTimeInterface::ATOM),
        'fallback' => $window['is_active']
            ? 'Weekend sale ends ' . $target->format('l, M j \a\t H:i T') . '.'
            : 'Next weekend sale begins ' . $target->format('l, M j \a\t H:i T') . '.',
    ];
}

function formatSaleAuditDate(string $utcDate): string
{
    return (new DateTimeImmutable($utcDate, saleUtcTimezone()))
        ->setTimezone(saleTimezone())
        ->format('M j, Y H:i T');
}
