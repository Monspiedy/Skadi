<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/config/bootstrap.php';

requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Games must be saved from the administration form.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);

$rawGameId = $_POST['game_id'] ?? null;
$gameId = is_string($rawGameId) && ctype_digit($rawGameId) && (int) $rawGameId > 0 ? (int) $rawGameId : null;
if ($rawGameId !== null && $gameId === null) {
    renderErrorPage(404, 'Game not found', 'The submitted game identifier is invalid.');
}
if ($gameId !== null && loadAdminGame(db(), $gameId) === null) {
    renderErrorPage(404, 'Game not found', 'The submitted game no longer exists.');
}

$input = normalizeGameInput($_POST);
$errors = gameInputErrors(db(), $input, $gameId);
if ($errors !== []) {
    $_SESSION['admin_game_form'] = $input;
    $_SESSION['admin_game_errors'] = $errors;
    $_SESSION['admin_game_form_id'] = $gameId;
    redirectTo('admin/game-form.php' . ($gameId === null ? '' : '?id=' . $gameId));
}

try {
    $savedGameId = saveAdminGame(db(), $input, $gameId);
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        $_SESSION['admin_game_form'] = $input;
        $_SESSION['admin_game_errors'] = ['slug' => 'Another game already uses this slug.'];
        $_SESSION['admin_game_form_id'] = $gameId;
        redirectTo('admin/game-form.php' . ($gameId === null ? '' : '?id=' . $gameId));
    }
    throw $exception;
}

addFlashMessage('success', $gameId === null ? 'Game created. You can now add its media.' : 'Game details saved.');
redirectTo('admin/game-form.php?id=' . $savedGameId);
