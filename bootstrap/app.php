<?php

declare(strict_types=1);

use Paya\Core\Router;

$router = new Router();
require __DIR__ . '/../routes/api.php';
return $router;
