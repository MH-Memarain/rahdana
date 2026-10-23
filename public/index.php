<?php

declare(strict_types=1);

use Paya\Core\Request;
use Paya\Core\Response;

require __DIR__ . '/../vendor/autoload.php';

try {
    $request = Request::fromGlobals();
} catch (LengthException $error) {
    Response::error('PAYLOAD_TOO_LARGE', 'Request body is too large.', 413)->send();
    return;
} catch (InvalidArgumentException $error) {
    Response::error('BAD_REQUEST', 'Invalid request body.', 400)->send();
    return;
}

try {
    $router = require __DIR__ . '/../bootstrap/app.php';
    $router->dispatch($request)->send();
} catch (Throwable $error) {
    // Error detail stays in the server log, never in the HTTP response.
    error_log('[Rahdana] ' . get_class($error) . ': ' . $error->getMessage());
    Response::error('INTERNAL_ERROR', 'Internal server error.', 500)->send();
}
