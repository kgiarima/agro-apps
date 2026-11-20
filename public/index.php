<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Http\Api;

$body = json_decode(file_get_contents('php://input'), true);
(new Api(app()['locations'], app()['forecasts']))->handle(
    $_SERVER['REQUEST_METHOD'],
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/',
    $_GET,
    is_array($body) ? $body : [],
);
