<?php

declare(strict_types=1);

function allowedThemes(): array
{
    return ['skadi', 'dark', 'light'];
}

function normalizeTheme(mixed $theme): string
{
    $normalized = is_string($theme) ? strtolower(trim($theme)) : '';

    return in_array($normalized, allowedThemes(), true) ? $normalized : 'skadi';
}

function currentTheme(): string
{
    $user = currentUser();

    return $user === null ? 'skadi' : normalizeTheme($user['theme'] ?? 'skadi');
}

function bootstrapColorMode(string $theme): string
{
    return $theme === 'light' ? 'light' : 'dark';
}

function saveUserTheme(PDO $connection, int $userId, string $theme): bool
{
    if (!in_array($theme, allowedThemes(), true)) {
        return false;
    }

    $statement = $connection->prepare(
        'UPDATE user_profiles SET theme = :theme WHERE user_id = :user_id'
    );
    $statement->execute([
        'theme' => $theme,
        'user_id' => $userId,
    ]);

    if ($statement->rowCount() === 0) {
        $exists = $connection->prepare('SELECT 1 FROM user_profiles WHERE user_id = :user_id');
        $exists->execute(['user_id' => $userId]);

        if ($exists->fetchColumn() === false) {
            return false;
        }
    }

    $_SESSION['authenticated_user']['theme'] = $theme;

    return true;
}
