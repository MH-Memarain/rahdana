<?php

declare(strict_types=1);

use Rahdana\Controllers\AuthController;
use Rahdana\Controllers\CheckInController;
use Rahdana\Controllers\HealthController;
use Rahdana\Controllers\StatsController;
use Rahdana\Controllers\TaskController;
use Rahdana\Middleware\Authenticate;
use Rahdana\Support\DatabaseProvider;

// The bootstrap creates $router. Use object handlers to share one lazy DB provider.
$database = new DatabaseProvider();
$auth = new AuthController($database);
$tasks = new TaskController($database);
$checkins = new CheckInController($database);
$stats = new StatsController($database);
$protected = [new Authenticate($database)];

$router->add('GET', '/api/v1/health', [HealthController::class, 'index']);

$router->add('POST', '/api/v1/auth/register', [$auth, 'register']);
$router->add('POST', '/api/v1/auth/login', [$auth, 'login']);
$router->add('POST', '/api/v1/auth/logout', [$auth, 'logout'], $protected);
$router->add('GET', '/api/v1/me', [$auth, 'me'], $protected);

$router->add('GET', '/api/v1/tasks', [$tasks, 'index'], $protected);
$router->add('POST', '/api/v1/tasks', [$tasks, 'create'], $protected);
$router->add('GET', '/api/v1/tasks/{id}', [$tasks, 'show'], $protected);
$router->add('PATCH', '/api/v1/tasks/{id}', [$tasks, 'update'], $protected);
$router->add('DELETE', '/api/v1/tasks/{id}', [$tasks, 'delete'], $protected);

$router->add('GET', '/api/v1/checkins', [$checkins, 'index'], $protected);
$router->add('GET', '/api/v1/checkins/latest', [$checkins, 'latest'], $protected);
$router->add('POST', '/api/v1/checkins', [$checkins, 'save'], $protected);

$router->add('GET', '/api/v1/stats/summary', [$stats, 'summary'], $protected);
$router->add('GET', '/api/v1/exports/context', [$stats, 'context'], $protected);
