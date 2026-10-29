<?php

declare(strict_types=1);

final class AdminException extends RuntimeException
{
}

function assertAdminActor(PDO $connection, int $actorUserId): void
{
    $statement = $connection->prepare(
        "SELECT 1 FROM users u JOIN roles r ON r.id = u.role_id
         WHERE u.id = :id AND u.status = 'active' AND r.name = 'admin'"
    );
    $statement->execute(['id' => $actorUserId]);
    if (!$statement->fetchColumn()) {
        throw new AdminException('Administrator authorization is required.');
    }
}

function adminPositivePage(mixed $value): int
{
    $page = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $page === false ? 1 : (int) $page;
}

function adminPagination(int $total, int $requestedPage, int $perPage): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, $requestedPage), $pages);
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage, 'total' => $total];
}

function recordAdminAudit(
    PDO $connection,
    int $actorUserId,
    string $actionCode,
    string $targetType,
    int $targetId,
    string $details
): void {
    $statement = $connection->prepare(
        'INSERT INTO admin_audit_logs (actor_user_id, action_code, target_type, target_id, details)
         VALUES (:actor, :action, :target_type, :target_id, :details)'
    );
    $statement->execute([
        'actor' => $actorUserId,
        'action' => $actionCode,
        'target_type' => $targetType,
        'target_id' => $targetId,
        'details' => $details,
    ]);
}

function loadAdminDashboardCounts(PDO $connection): array
{
    $queries = [
        'users' => 'SELECT COUNT(*) FROM users',
        'active_users' => "SELECT COUNT(*) FROM users WHERE status = 'active'",
        'published_games' => "SELECT COUNT(*) FROM games WHERE status = 'published'",
        'completed_orders' => "SELECT COUNT(*) FROM orders WHERE status = 'completed'",
        'open_reports' => "SELECT COUNT(*) FROM content_reports WHERE status IN ('open','reviewing')",
        'hidden_content' => "SELECT
            (SELECT COUNT(*) FROM reviews WHERE status = 'hidden') +
            (SELECT COUNT(*) FROM guides WHERE status = 'hidden') +
            (SELECT COUNT(*) FROM guide_comments WHERE status = 'hidden') +
            (SELECT COUNT(*) FROM discussion_topics WHERE status = 'hidden') +
            (SELECT COUNT(*) FROM discussion_replies WHERE status = 'hidden') +
            (SELECT COUNT(*) FROM community_posts WHERE status = 'hidden') +
            (SELECT COUNT(*) FROM community_comments WHERE status = 'hidden')",
    ];
    $counts = [];
    foreach ($queries as $key => $sql) {
        $counts[$key] = (int) $connection->query($sql)->fetchColumn();
    }
    return $counts;
}

function loadAdminUsers(PDO $connection, string $search, int $requestedPage, int $perPage = 20): array
{
    $search = mb_substr(trim($search), 0, 100);
    $where = '';
    $parameters = [];
    if ($search !== '') {
        $where = " WHERE display_name LIKE :display ESCAPE '=' OR skadi_id = :skadi_id OR email LIKE :email ESCAPE '='";
        $escaped = str_replace(['=', '%', '_'], ['==', '=%', '=_'], $search);
        $parameters = ['display' => '%' . $escaped . '%', 'skadi_id' => strtoupper($search), 'email' => '%' . $escaped . '%'];
    }
    $count = $connection->prepare('SELECT COUNT(*) FROM admin_user_overview' . $where);
    $count->execute($parameters);
    $pagination = adminPagination((int) $count->fetchColumn(), $requestedPage, $perPage);
    $statement = $connection->prepare(
        'SELECT id, skadi_id, display_name, email, role_name, status, wallet_balance, created_at, last_activity_at
         FROM admin_user_overview' . $where . ' ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset'
    );
    foreach ($parameters as $name => $value) {
        $statement->bindValue(':' . $name, $value);
    }
    $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
    $statement->execute();
    return $pagination + ['items' => $statement->fetchAll(), 'search' => $search];
}

function updateAdminUser(PDO $connection, int $actorUserId, int $targetUserId, string $role, string $status): void
{
    if (!in_array($role, ['user', 'admin'], true) || !in_array($status, ['active', 'suspended', 'blocked'], true)) {
        throw new AdminException('Choose a valid role and account status.');
    }
    $connection->beginTransaction();
    try {
        assertAdminActor($connection, $actorUserId);
        $target = $connection->prepare(
            'SELECT u.id, u.status, r.name AS role_name FROM users u
             JOIN roles r ON r.id = u.role_id WHERE u.id = :id FOR UPDATE'
        );
        $target->execute(['id' => $targetUserId]);
        $user = $target->fetch();
        if (!is_array($user)) {
            throw new AdminException('The user no longer exists.');
        }
        $removesActiveAdmin = $user['role_name'] === 'admin' && $user['status'] === 'active'
            && ($role !== 'admin' || $status !== 'active');
        if ($removesActiveAdmin) {
            $activeAdminRows = $connection->query(
                "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
                 WHERE r.name = 'admin' AND u.status = 'active' ORDER BY u.id FOR UPDATE"
            )->fetchAll(PDO::FETCH_COLUMN);
            if (count($activeAdminRows) <= 1) {
                throw new AdminException('The final active administrator cannot be demoted or disabled.');
            }
        }
        $roleStatement = $connection->prepare('SELECT id FROM roles WHERE name = :name');
        $roleStatement->execute(['name' => $role]);
        $roleId = $roleStatement->fetchColumn();
        if ($roleId === false) {
            throw new AdminException('The selected role is unavailable.');
        }
        $update = $connection->prepare('UPDATE users SET role_id = :role_id, status = :status WHERE id = :id');
        $update->execute(['role_id' => $roleId, 'status' => $status, 'id' => $targetUserId]);
        recordAdminAudit(
            $connection,
            $actorUserId,
            'user.access_updated',
            'user',
            $targetUserId,
            "Role {$user['role_name']} -> $role; status {$user['status']} -> $status."
        );
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

function normalizeAdminAdjustment(mixed $value): string
{
    $amount = trim(is_string($value) ? $value : '');
    if (preg_match('/\A-?(?:0|[1-9]\d{0,9})\.\d{2}\z/', $amount) !== 1 || $amount === '0.00' || $amount === '-0.00') {
        throw new AdminException('Enter a non-zero amount with exactly two decimal places.');
    }
    return $amount;
}

function adjustUserWallet(PDO $connection, int $actorUserId, int $targetUserId, mixed $rawAmount, string $reason): array
{
    $amount = normalizeAdminAdjustment($rawAmount);
    $reason = trim($reason);
    if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
        throw new AdminException('The adjustment reason must be 3-500 characters.');
    }
    $connection->beginTransaction();
    try {
        assertAdminActor($connection, $actorUserId);
        $userStatement = $connection->prepare('SELECT wallet_balance FROM users WHERE id = :id FOR UPDATE');
        $userStatement->execute(['id' => $targetUserId]);
        $balance = $userStatement->fetchColumn();
        if ($balance === false) {
            throw new AdminException('The user no longer exists.');
        }
        $adjustmentCents = moneyToCents(ltrim($amount, '-')) * (str_starts_with($amount, '-') ? -1 : 1);
        $newBalanceCents = moneyToCents((string) $balance) + $adjustmentCents;
        if ($newBalanceCents < 0) {
            throw new AdminException('The adjustment would make the wallet balance negative.');
        }
        $newBalance = centsToMoney($newBalanceCents);
        $update = $connection->prepare('UPDATE users SET wallet_balance = :balance WHERE id = :id');
        $update->execute(['balance' => $newBalance, 'id' => $targetUserId]);
        $ledger = $connection->prepare(
            "INSERT INTO wallet_transactions
                (user_id, transaction_type, amount, balance_after, performed_by_user_id, reason)
             VALUES (:user, 'admin_adjustment', :amount, :balance, :actor, :reason)"
        );
        $ledger->execute([
            'user' => $targetUserId,
            'amount' => $amount,
            'balance' => $newBalance,
            'actor' => $actorUserId,
            'reason' => $reason,
        ]);
        $transactionId = (int) $connection->lastInsertId();
        recordAdminAudit(
            $connection,
            $actorUserId,
            'wallet.adjusted',
            'wallet_transaction',
            $transactionId,
            "User $targetUserId adjusted by $amount; resulting balance $newBalance; reason: $reason"
        );
        $connection->commit();
        return ['transaction_id' => $transactionId, 'balance_after' => $newBalance];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

function loadAdminOrders(PDO $connection, int $requestedPage, int $perPage = 20): array
{
    $pagination = adminPagination((int) $connection->query('SELECT COUNT(*) FROM orders')->fetchColumn(), $requestedPage, $perPage);
    $statement = $connection->prepare(
        'SELECT o.id, o.order_number, o.status, o.subtotal, o.discount_total, o.total, o.created_at,
                u.display_name, u.skadi_id, COUNT(oi.id) AS item_count
         FROM orders o JOIN users u ON u.id = o.user_id LEFT JOIN order_items oi ON oi.order_id = o.id
         GROUP BY o.id, u.display_name, u.skadi_id ORDER BY o.created_at DESC, o.id DESC LIMIT :limit OFFSET :offset'
    );
    $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
    $statement->execute();
    return $pagination + ['items' => $statement->fetchAll()];
}

function loadAdminWalletHistory(PDO $connection, int $requestedPage, int $perPage = 20): array
{
    $pagination = adminPagination((int) $connection->query('SELECT COUNT(*) FROM wallet_transactions')->fetchColumn(), $requestedPage, $perPage);
    $statement = $connection->prepare(
        'SELECT wt.id, wt.transaction_type, wt.amount, wt.balance_after, wt.reason, wt.created_at,
                target.display_name, target.skadi_id, actor.display_name AS actor_name, o.order_number
         FROM wallet_transactions wt JOIN users target ON target.id = wt.user_id
         LEFT JOIN users actor ON actor.id = wt.performed_by_user_id LEFT JOIN orders o ON o.id = wt.order_id
         ORDER BY wt.created_at DESC, wt.id DESC LIMIT :limit OFFSET :offset'
    );
    $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
    $statement->execute();
    return $pagination + ['items' => $statement->fetchAll()];
}

function adminModerationTypes(): array
{
    return [
        'review' => ['label' => 'Reviews', 'table' => 'reviews', 'title' => "CONCAT(game.title, ' review')", 'body' => 'COALESCE(item.body, \'\')', 'joins' => 'JOIN games game ON game.id = item.game_id JOIN users author ON author.id = item.user_id', 'public' => 'active'],
        'guide' => ['label' => 'Guides', 'table' => 'guides', 'title' => 'item.title', 'body' => 'item.summary', 'joins' => 'JOIN games game ON game.id = item.game_id JOIN users author ON author.id = item.author_user_id', 'public' => 'published'],
        'guide_comment' => ['label' => 'Guide comments', 'table' => 'guide_comments', 'title' => 'guide.title', 'body' => 'item.body', 'joins' => 'JOIN guides guide ON guide.id = item.guide_id JOIN games game ON game.id = guide.game_id JOIN users author ON author.id = item.author_user_id', 'public' => 'active'],
        'topic' => ['label' => 'Discussion topics', 'table' => 'discussion_topics', 'title' => 'item.title', 'body' => 'item.body', 'joins' => 'JOIN games game ON game.id = item.game_id JOIN users author ON author.id = item.author_user_id', 'public' => 'active'],
        'reply' => ['label' => 'Discussion replies', 'table' => 'discussion_replies', 'title' => 'topic.title', 'body' => 'item.body', 'joins' => 'JOIN discussion_topics topic ON topic.id = item.topic_id JOIN games game ON game.id = topic.game_id JOIN users author ON author.id = item.author_user_id', 'public' => 'active'],
        'post' => ['label' => 'Community posts', 'table' => 'community_posts', 'title' => "CONCAT(game.title, ' post')", 'body' => 'item.body', 'joins' => 'JOIN games game ON game.id = item.game_id JOIN users author ON author.id = item.author_user_id', 'public' => 'active'],
        'community_comment' => ['label' => 'Community comments', 'table' => 'community_comments', 'title' => "CONCAT(game.title, ' comment')", 'body' => 'item.body', 'joins' => 'JOIN community_posts post ON post.id = item.post_id JOIN games game ON game.id = post.game_id JOIN users author ON author.id = item.author_user_id', 'public' => 'active'],
    ];
}

function loadAdminModerationQueue(PDO $connection, string $type, int $requestedPage, int $perPage = 15): array
{
    $types = adminModerationTypes();
    if (!isset($types[$type])) {
        $type = 'review';
    }
    $definition = $types[$type];
    $count = (int) $connection->query("SELECT COUNT(*) FROM {$definition['table']}")->fetchColumn();
    $pagination = adminPagination($count, $requestedPage, $perPage);
    $topicFields = $type === 'topic' ? ', item.is_pinned, item.is_locked' : ', 0 AS is_pinned, 0 AS is_locked';
    $sql = "SELECT item.id, item.status, item.created_at, {$definition['title']} AS title,
                   {$definition['body']} AS body, game.title AS game_title,
                   author.display_name, author.skadi_id$topicFields
            FROM {$definition['table']} item {$definition['joins']}
            ORDER BY item.created_at DESC, item.id DESC LIMIT :limit OFFSET :offset";
    $statement = $connection->prepare($sql);
    $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
    $statement->execute();
    return $pagination + ['items' => $statement->fetchAll(), 'type' => $type, 'definition' => $definition];
}

function moderateAdminContent(
    PDO $connection,
    int $actorUserId,
    string $type,
    int $targetId,
    string $newStatus,
    string $reason,
    bool $isPinned = false,
    bool $isLocked = false
): void {
    $types = adminModerationTypes();
    if (!isset($types[$type])) {
        throw new AdminException('Choose a valid moderation target.');
    }
    $definition = $types[$type];
    $allowedStatuses = [$definition['public'], 'hidden', 'deleted'];
    if (!in_array($newStatus, $allowedStatuses, true)) {
        throw new AdminException('Choose a valid moderation status.');
    }
    $reason = trim($reason);
    if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
        throw new AdminException('The moderation reason must be 3-500 characters.');
    }
    $connection->beginTransaction();
    try {
        assertAdminActor($connection, $actorUserId);
        $lock = $connection->prepare("SELECT status FROM {$definition['table']} WHERE id = :id FOR UPDATE");
        $lock->execute(['id' => $targetId]);
        $oldStatus = $lock->fetchColumn();
        if ($oldStatus === false) {
            throw new AdminException('The moderation target no longer exists.');
        }
        if ($oldStatus === 'deleted' && $newStatus !== 'deleted') {
            throw new AdminException('Deleted content is retained for audit and cannot be restored.');
        }
        if ($type === 'topic') {
            moderateDiscussionTopic($connection, $actorUserId, $targetId, $newStatus, $isPinned, $isLocked, $reason);
        } elseif ($type === 'reply') {
            moderateDiscussionReply($connection, $actorUserId, $targetId, $newStatus, $reason);
        } else {
            $moderatorFields = in_array($type, ['review', 'guide', 'post'], true)
                ? ', moderated_by_user_id = :actor, moderation_reason = :reason' : '';
            $publishedField = $type === 'guide'
                ? ", published_at = CASE WHEN :published_status = 'published' THEN COALESCE(published_at, UTC_TIMESTAMP()) ELSE published_at END" : '';
            $statement = $connection->prepare(
                "UPDATE {$definition['table']} SET status = :status$moderatorFields$publishedField,
                 deleted_at = CASE WHEN :deleted = 'deleted' THEN UTC_TIMESTAMP() ELSE NULL END WHERE id = :id"
            );
            $parameters = ['status' => $newStatus, 'deleted' => $newStatus, 'id' => $targetId];
            if ($type === 'guide') {
                $parameters['published_status'] = $newStatus;
            }
            if ($moderatorFields !== '') {
                $parameters += ['actor' => $actorUserId, 'reason' => $reason];
            }
            $statement->execute($parameters);
        }
        recordAdminAudit(
            $connection,
            $actorUserId,
            'content.moderated',
            $type,
            $targetId,
            "Status $oldStatus -> $newStatus; reason: $reason" . ($type === 'topic' ? '; pinned=' . (int) $isPinned . '; locked=' . (int) $isLocked : '')
        );
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

function loadAdminReports(PDO $connection, string $status, int $requestedPage, int $perPage = 20): array
{
    $allowed = ['open', 'reviewing', 'resolved', 'dismissed', 'all'];
    if (!in_array($status, $allowed, true)) {
        $status = 'open';
    }
    $where = $status === 'all' ? '' : ' WHERE status = :status';
    $count = $connection->prepare('SELECT COUNT(*) FROM admin_content_report_queue' . $where);
    $count->execute($status === 'all' ? [] : ['status' => $status]);
    $pagination = adminPagination((int) $count->fetchColumn(), $requestedPage, $perPage);
    $statement = $connection->prepare(
        'SELECT * FROM admin_content_report_queue' . $where . ' ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset'
    );
    if ($status !== 'all') {
        $statement->bindValue(':status', $status);
    }
    $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
    $statement->execute();
    return $pagination + ['items' => $statement->fetchAll(), 'status' => $status];
}

function adminReportTargetLabel(int $typeCode): string
{
    return [
        1 => 'Review',
        2 => 'Guide',
        3 => 'Guide comment',
        4 => 'Discussion topic',
        5 => 'Discussion reply',
        6 => 'Community post',
        7 => 'Community comment',
    ][$typeCode] ?? 'Unknown public content';
}

function resolveAdminReport(PDO $connection, int $actorUserId, int $reportId, string $status, string $note): void
{
    if (!in_array($status, ['resolved', 'dismissed'], true)) {
        throw new AdminException('Choose resolve or dismiss.');
    }
    $note = trim($note);
    if (mb_strlen($note) < 3 || mb_strlen($note) > 1000) {
        throw new AdminException('The resolution note must be 3-1000 characters.');
    }
    $connection->beginTransaction();
    try {
        assertAdminActor($connection, $actorUserId);
        $lock = $connection->prepare('SELECT status FROM content_reports WHERE id = :id FOR UPDATE');
        $lock->execute(['id' => $reportId]);
        $oldStatus = $lock->fetchColumn();
        if ($oldStatus === false) {
            throw new AdminException('The report no longer exists.');
        }
        if (in_array($oldStatus, ['resolved', 'dismissed'], true)) {
            throw new AdminException('This report has already been handled.');
        }
        $update = $connection->prepare(
            'UPDATE content_reports SET status = :status, handled_by_user_id = :actor,
             resolution_note = :note, resolved_at = UTC_TIMESTAMP() WHERE id = :id'
        );
        $update->execute(['status' => $status, 'actor' => $actorUserId, 'note' => $note, 'id' => $reportId]);
        recordAdminAudit($connection, $actorUserId, 'report.' . $status, 'content_report', $reportId, $note);
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}
