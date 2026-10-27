<?php

declare(strict_types=1);

function normalizedRegistrationInput(array $input): array
{
    return [
        'login_name' => trim((string) ($input['login_name'] ?? '')),
        'email' => mb_strtolower(trim((string) ($input['email'] ?? ''))),
        'display_name' => trim((string) ($input['display_name'] ?? '')),
        'password' => (string) ($input['password'] ?? ''),
        'password_confirmation' => (string) ($input['password_confirmation'] ?? ''),
    ];
}

function registrationErrors(array $input): array
{
    $errors = [];

    if (($error = loginNameError($input['login_name'])) !== null) {
        $errors['login_name'] = $error;
    }

    if (strlen($input['email']) > 254 || ($error = emailError($input['email'])) !== null) {
        $errors['email'] = 'Email must be a valid address no longer than 254 characters.';
    }

    if (($error = displayNameError($input['display_name'])) !== null) {
        $errors['display_name'] = $error;
    }

    if (($error = passwordError($input['password'])) !== null) {
        $errors['password'] = $error;
    }

    if ($input['password'] !== $input['password_confirmation']) {
        $errors['password_confirmation'] = 'Password confirmation must match the password.';
    }

    return $errors;
}

function registrationAvailabilityErrors(PDO $connection, array $input): array
{
    $statement = $connection->prepare(
        'SELECT login_name, email FROM users WHERE login_name = :login_name OR email = :email'
    );
    $statement->execute([
        'login_name' => $input['login_name'],
        'email' => $input['email'],
    ]);
    $errors = [];

    foreach ($statement->fetchAll() as $match) {
        if (strcasecmp((string) $match['login_name'], $input['login_name']) === 0) {
            $errors['login_name'] = 'This login name is unavailable.';
        }

        if (strcasecmp((string) $match['email'], $input['email']) === 0) {
            // The wording supports correction without confirming another account's address.
            $errors['email'] = 'This email cannot be used for registration.';
        }
    }

    return $errors;
}

function generateSkadiId(PDO $connection): string
{
    $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $maximumIndex = strlen($alphabet) - 1;

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $randomPart = '';

        for ($index = 0; $index < 8; $index++) {
            $randomPart .= $alphabet[random_int(0, $maximumIndex)];
        }

        $skadiId = 'SKD-' . substr($randomPart, 0, 4) . '-' . substr($randomPart, 4, 4);
        $statement = $connection->prepare('SELECT 1 FROM users WHERE skadi_id = :skadi_id');
        $statement->execute(['skadi_id' => $skadiId]);

        if ($statement->fetchColumn() === false) {
            return $skadiId;
        }
    }

    throw new RuntimeException('A unique Skadi ID could not be generated.');
}

function registerUser(PDO $connection, array $input): array
{
    $connection->beginTransaction();

    try {
        $roleStatement = $connection->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
        $roleStatement->execute(['name' => 'user']);
        $roleId = $roleStatement->fetchColumn();

        if ($roleId === false) {
            throw new RuntimeException('The standard user role is missing.');
        }

        $userStatement = $connection->prepare(
            'INSERT INTO users '
            . '(role_id, login_name, email, password_hash, skadi_id, display_name, wallet_balance, status, must_change_password) '
            . 'VALUES (:role_id, :login_name, :email, :password_hash, :skadi_id, :display_name, 100.00, :status, 0)'
        );
        $userStatement->execute([
            'role_id' => (int) $roleId,
            'login_name' => $input['login_name'],
            'email' => $input['email'],
            'password_hash' => password_hash($input['password'], PASSWORD_DEFAULT),
            'skadi_id' => generateSkadiId($connection),
            'display_name' => $input['display_name'],
            'status' => 'active',
        ]);
        $userId = (int) $connection->lastInsertId();

        $profileStatement = $connection->prepare('INSERT INTO user_profiles (user_id) VALUES (:user_id)');
        $profileStatement->execute(['user_id' => $userId]);

        $walletStatement = $connection->prepare(
            'INSERT INTO wallet_transactions '
            . '(user_id, transaction_type, amount, balance_after, reason) '
            . 'VALUES (:user_id, :transaction_type, 100.00, 100.00, :reason)'
        );
        $walletStatement->execute([
            'user_id' => $userId,
            'transaction_type' => 'initial_credit',
            'reason' => 'Initial demonstration balance',
        ]);

        $connection->commit();

        return findAuthenticationUserById($connection, $userId);
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        throw $exception;
    }
}

function findAuthenticationUserById(PDO $connection, int $userId): array
{
    $statement = $connection->prepare(
        'SELECT u.id, u.password_hash, u.display_name, u.skadi_id, u.status, '
        . 'u.must_change_password, r.name AS role, COALESCE(up.theme, :default_theme) AS theme '
        . 'FROM users u INNER JOIN roles r ON r.id = u.role_id '
        . 'LEFT JOIN user_profiles up ON up.user_id = u.id WHERE u.id = :id LIMIT 1'
    );
    $statement->execute([
        'default_theme' => 'skadi',
        'id' => $userId,
    ]);
    $user = $statement->fetch();

    if (!is_array($user)) {
        throw new RuntimeException('The account could not be loaded.');
    }

    return $user;
}

function authenticateUser(PDO $connection, string $identifier, string $password): array
{
    $statement = $connection->prepare(
        'SELECT u.id, u.password_hash, u.display_name, u.skadi_id, u.status, '
        . 'u.must_change_password, r.name AS role, COALESCE(up.theme, :default_theme) AS theme '
        . 'FROM users u INNER JOIN roles r ON r.id = u.role_id '
        . 'LEFT JOIN user_profiles up ON up.user_id = u.id '
        . 'WHERE u.login_name = :login_name OR u.email = :email LIMIT 1'
    );
    $statement->execute([
        'login_name' => trim($identifier),
        'email' => mb_strtolower(trim($identifier)),
        'default_theme' => 'skadi',
    ]);
    $user = $statement->fetch();
    $fallbackHash = '$2y$10$Jq25tCUgQj0x3KrJYONKdeB.7x1NZ3O6ZTvQGXFt2XcfvTn0Gf2o2';
    $hash = is_array($user) ? (string) $user['password_hash'] : $fallbackHash;

    if (!password_verify($password, $hash) || !is_array($user)) {
        return ['status' => 'invalid'];
    }

    if ($user['status'] !== 'active') {
        return ['status' => 'unavailable'];
    }

    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        $rehash = $connection->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
        $rehash->execute([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'id' => (int) $user['id'],
        ]);
    }

    $activity = $connection->prepare('UPDATE users SET last_activity_at = UTC_TIMESTAMP() WHERE id = :id');
    $activity->execute(['id' => (int) $user['id']]);

    return ['status' => 'authenticated', 'user' => $user];
}

function changeUserPassword(PDO $connection, int $userId, string $currentPassword, string $newPassword): array
{
    $user = findAuthenticationUserById($connection, $userId);

    if (
        $currentPassword === ''
        || strlen($currentPassword) > 72
        || !password_verify($currentPassword, (string) $user['password_hash'])
    ) {
        return ['current_password' => 'Current password is incorrect.'];
    }

    if (($error = passwordError($newPassword, 'New password')) !== null) {
        return ['new_password' => $error];
    }

    if (password_verify($newPassword, (string) $user['password_hash'])) {
        return ['new_password' => 'New password must be different from the current password.'];
    }

    $statement = $connection->prepare(
        'UPDATE users SET password_hash = :password_hash, must_change_password = 0, '
        . 'last_activity_at = UTC_TIMESTAMP() WHERE id = :id AND status = :status'
    );
    $statement->execute([
        'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        'id' => $userId,
        'status' => 'active',
    ]);

    if ($statement->rowCount() !== 1) {
        throw new RuntimeException('The password could not be changed for this account.');
    }

    return [];
}
