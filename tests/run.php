<?php

declare(strict_types=1);

// Tiny dependency-free unit/smoke suite. Integration database checks need PDO drivers.
spl_autoload_register(static function (string $class): void {
    $prefixes = ['Paya\\Core\\' => 'Core/', 'Rahdana\\' => ''];
    foreach ($prefixes as $prefix => $directory) {
        if (!str_starts_with($class, $prefix)) { continue; }
        $path = __DIR__ . '/../src/' . $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) { require $path; }
        return;
    }
});

use Paya\Core\Database;
use Paya\Core\Request;
use Paya\Core\Response;
use Paya\Core\Router;

$passed = 0;
$failed = 0;
function check(bool $okay, string $name): void {
    global $passed, $failed;
    $okay ? $passed++ : $failed++;
    echo ($okay ? '[PASS] ' : '[FAIL] ') . $name . "\n";
}
function expectException(callable $fn, string $type): bool {
    try { $fn(); } catch (Throwable $e) { return $e instanceof $type; }
    return false;
}

$request = new Request('get', '/api/v1/tasks/12?page=2', [
    'AUTHORIZATION' => 'Bearer abc123', 'Content-TYPE' => 'application/json'
], ['page' => '2'], ['title' => 'PHP']);
check($request->method() === 'GET' && $request->path() === '/api/v1/tasks/12', 'Request normalizes URL and method');
check($request->header('authorization') === 'Bearer abc123' && $request->bearerToken() === 'abc123', 'Request case-insensitive headers and token');
check($request->query('page') === '2' && $request->input('title') === 'PHP', 'Request keeps query and body separate');
check($request->all() === ['title' => 'PHP'], 'Request all() only returns body');
$clone = $request->withAttribute('user_id', 5)->withRouteParams(['id' => '12']);
check($clone->route('id') === '12' && $clone->attribute('user_id') === 5 && $request->attribute('user_id') === null, 'Request with... immutable copies');
check((new Request('GET', '/'))->bearerToken() === null, 'Missing token returns null');

$response = Response::json(['data' => ['message' => 'سلام']], 201);
check($response->statusCode() === 201 && json_decode($response->body(), true)['data']['message'] === 'سلام', 'Response UTF-8 JSON');
check($response->headers()['Content-Type'] === 'application/json; charset=utf-8', 'Response Content-Type');
check(Response::noContent()->statusCode() === 204 && Response::noContent()->body() === '', '204 without body');
check(Response::error('NO', 'Denied', 403)->statusCode() === 403, 'Error status');
check(expectException(fn () => $response->withHeader('X-Debug', "bad\r\nInjected: yes"), InvalidArgumentException::class), 'Header injection rejected');
check(expectException(fn () => Response::json([], 204), InvalidArgumentException::class), 'JSON 204 rejected');

$router = new Router();
$controller = new class {
    public function show(Request $req): Response { return Response::json(['data' => ['id' => $req->route('id'), 'user' => $req->attribute('user_id')]]); }
    public function fixed(Request $req): Response { return Response::json(['data' => 'fixed']); }
};
$middleware = new class {
    public function handle(Request $req, callable $next): Response { return $next($req->withAttribute('user_id', 19)); }
};
$router->add('GET', '/api/v1/tasks/{id}', [$controller, 'show'], [$middleware]);
$router->add('GET', '/api/v1/tasks/fixed', [$controller, 'fixed']);
$router->add('POST', '/api/v1/tasks/{id}', [$controller, 'show']);
$result = $router->dispatch(new Request('GET', '/api/v1/tasks/15'));
check(json_decode($result->body(), true)['data'] === ['id' => '15', 'user' => 19], 'Router parameters, controller and middleware');
check(json_decode($router->dispatch(new Request('GET', '/api/v1/tasks/fixed'))->body(), true)['data'] === 'fixed', 'Static route priority');
check($router->dispatch(new Request('GET', '/missing'))->statusCode() === 404, '404 path not found');
$wrong = $router->dispatch(new Request('DELETE', '/api/v1/tasks/15'));
check($wrong->statusCode() === 405 && $wrong->headers()['Allow'] === 'GET, POST', '405 Allow methods');
check($router->dispatch(new Request('GET', '/api/v1/tasks/a%2Fb'))->statusCode() === 404, 'Encoded slash refused');
check(expectException(fn () => $router->add('GET', '/api/v1/tasks/{id}', [$controller, 'show']), LogicException::class), 'Duplicate route refused');
check(expectException(fn () => $router->add('GET', '/api/v1/{bad-name}', [$controller, 'show']), InvalidArgumentException::class), 'Malformed route parameter rejected');

$classRouter = new Router();
$classRouter->add('GET', '/api/v1/health', [Rahdana\Controllers\HealthController::class, 'index']);
check(json_decode($classRouter->dispatch(new Request('GET', '/api/v1/health'))->body(), true)['data']['status'] === 'ok', 'Controller class autoconstruction');
check(expectException(fn () => new Database(['driver' => 'unknown']), InvalidArgumentException::class), 'Unsupported DB driver rejected');
check(expectException(fn () => new Database(['driver' => 'mysql', 'database' => '']), InvalidArgumentException::class), 'Missing DB name rejected');

if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $db = new Database(['driver' => 'sqlite', 'database' => ':memory:']);
    $db->execute('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
    $db->execute('INSERT INTO items (name) VALUES (:name)', ['name' => 'Sample']);
    check($db->fetchOne('SELECT name FROM items WHERE id = ?', [1])['name'] === 'Sample', 'PDO fetchOne prepared SQL');
    check(count($db->fetchAll('SELECT * FROM items')) === 1, 'PDO fetchAll');
    try { $db->transaction(function (Database $db) { $db->execute('INSERT INTO items (name) VALUES (?)', ['Roll back']); throw new RuntimeException('cancel'); }); } catch (RuntimeException $e) {}
    check(count($db->fetchAll('SELECT * FROM items')) === 1, 'Transaction rollback');
    check(expectException(fn () => $db->transaction(fn (Database $db) => $db->transaction(fn () => true)), LogicException::class), 'Nested transaction refused');
} else {
    echo "[SKIP] PDO SQLite integration tests: pdo_sqlite is not installed.\n";
}

echo "\n$passed passed; $failed failed.\n";
exit($failed > 0 ? 1 : 0);
