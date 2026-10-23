<?php

declare(strict_types=1);

use Rahdana\Controllers\HealthController;

// $router is provided by bootstrap/app.php. Add project routes below.
$router->add('GET', '/api/v1/health', [HealthController::class, 'index']);
