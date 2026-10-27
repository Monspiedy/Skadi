<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function themeResponse(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    themeResponse(405, ['ok' => false, 'message' => 'Method not allowed.']);
}

$user = currentUser();

if ($user === null) {
    themeResponse(401, ['ok' => false, 'message' => 'Sign in to save this theme to your account.']);
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : $_POST;
$submittedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($payload['csrf_token'] ?? null);

if (!isValidCsrfToken(is_string($submittedToken) ? $submittedToken : null)) {
    themeResponse(403, ['ok' => false, 'message' => 'The security token expired. Refresh and try again.']);
}

$theme = is_string($payload['theme'] ?? null) ? strtolower(trim($payload['theme'])) : '';

if (!in_array($theme, allowedThemes(), true)) {
    themeResponse(422, ['ok' => false, 'message' => 'Choose a supported theme.']);
}

if (!saveUserTheme(db(), (int) $user['id'], $theme)) {
    themeResponse(500, ['ok' => false, 'message' => 'The theme could not be saved.']);
}

themeResponse(200, ['ok' => true, 'theme' => $theme]);
