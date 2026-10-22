<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/autoload.php';

use App\Platform\FinancePro\FinancePro;
use App\Platform\FinancePro\FinProException;

header('Content-Type: application/json');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    switch (true) {
        case $path === '/api/health':
            echo json_encode(['ok' => true]);
            break;

        // Smoke test for the FinancePro connection. Local dev only, until admin auth lands.
        case $path === '/api/dev/platform/groups' && $method === 'GET' && \App\Config::get('APP_ENV') === 'local':
            echo json_encode(['data' => FinancePro::fromConfig()->listGroups($_GET)]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'not_found']);
    }
} catch (FinProException $e) {
    http_response_code($e->httpStatus >= 400 ? 502 : 500);
    echo json_encode(['error' => 'platform_error', 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
}
