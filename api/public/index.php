<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/autoload.php';

use App\App;
use App\Http\Request;

[$status, $body] = App::handle(Request::fromGlobals());

http_response_code($status);
header('Content-Type: application/json');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo json_encode($body, JSON_UNESCAPED_UNICODE);
