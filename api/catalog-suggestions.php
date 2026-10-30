<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    exit;
}

$normalized = normalizeCatalogSuggestionQuery($_GET['q'] ?? '');
if (!$normalized['valid']) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $normalized['error']], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    exit;
}

try {
    $suggestions = loadCatalogSuggestions(db(), $normalized['query']);
} catch (Throwable) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Suggestions are temporarily unavailable.']);
    exit;
}

echo json_encode([
    'ok' => true,
    'suggestions' => $suggestions,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
