<?php

declare(strict_types=1);

namespace Rahdana\Controllers;

use Paya\Core\Request;
use Paya\Core\Response;

/** Transport-only smoke endpoint, not a product module. */
final class HealthController
{
    public function index(Request $request): Response
    {
        return Response::json(['data' => ['status' => 'ok']]);
    }
}
