<?php

declare(strict_types=1);

namespace Paya\Core;

use InvalidArgumentException;
use LogicException;

/** Routes one HTTP request to a controller through optional middleware. */
final class Router
{
    /** @var array<int, array{method:string,path:string,pattern:string,handler:array,middleware:array,static:int}> */
    private array $routes = [];

    public function add(string $method, string $path, array $handler, array $middleware = []): void
    {
        $method = strtoupper(trim($method));
        if (!preg_match('/^[A-Z]+$/D', $method)) {
            throw new InvalidArgumentException('Invalid HTTP method.');
        }
        if ($path === '' || $path[0] !== '/') {
            throw new InvalidArgumentException('Route paths must start with /.');
        }
        if (count($handler) !== 2 || !isset($handler[0], $handler[1]) || !is_string($handler[1])) {
            throw new InvalidArgumentException('A handler must be [Controller::class, method] or [$object, method].');
        }

        $path = $path === '/' ? '/' : rtrim($path, '/');
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['path'] === $path) {
                throw new LogicException('Duplicate HTTP route: ' . $method . ' ' . $path);
            }
        }

        $parts = explode('/', trim($path, '/'));
        $staticCount = 0;
        $parameters = [];
        $patternParts = [];
        foreach ($path === '/' ? [] : $parts as $part) {
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/D', $part, $m) === 1) {
                if (isset($parameters[$m[1]])) {
                    throw new InvalidArgumentException('Duplicate route parameter.');
                }
                $parameters[$m[1]] = true;
                $patternParts[] = '(?P<' . $m[1] . '>[^/]+)';
            } else {
                if (str_contains($part, '{') || str_contains($part, '}')) {
                    throw new InvalidArgumentException('Malformed route parameter.');
                }
                $patternParts[] = preg_quote($part, '~');
                $staticCount++;
            }
        }
        $pattern = $path === '/' ? '~^/$~D' : '~^/' . implode('/', $patternParts) . '$~D';
        $this->routes[] = compact('method', 'path', 'pattern', 'handler', 'middleware') + ['static' => $staticCount];
    }

    public function dispatch(Request $request): Response
    {
        $routes = $this->routes;
        // Static paths take priority over parameterized paths, regardless of registration order.
        usort($routes, static fn (array $a, array $b): int => $b['static'] <=> $a['static']);
        $allowed = [];

        foreach ($routes as $route) {
            if (preg_match($route['pattern'], $request->path(), $matches) !== 1) {
                continue;
            }
            $allowed[$route['method']] = true;
            if ($route['method'] !== $request->method()) {
                continue;
            }
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $decoded = rawurldecode($value);
                    // Escaped path separators must never be used to bypass path boundaries.
                    if (str_contains($decoded, '/')) {
                        return Response::error('NOT_FOUND', 'Route not found.', 404);
                    }
                    $params[$key] = $decoded;
                }
            }
            $request = $request->withRouteParams($params);
            $next = fn (Request $req): Response => $this->invokeHandler($route['handler'], $req);

            foreach (array_reverse($route['middleware']) as $middleware) {
                $following = $next;
                $next = function (Request $req) use ($middleware, $following): Response {
                    if (is_string($middleware) && class_exists($middleware)) {
                        $middleware = new $middleware();
                    }
                    if (is_object($middleware) && method_exists($middleware, 'handle')) {
                        $response = $middleware->handle($req, $following);
                    } elseif (is_callable($middleware)) {
                        $response = $middleware($req, $following);
                    } else {
                        throw new LogicException('Invalid route middleware.');
                    }
                    if (!$response instanceof Response) {
                        throw new LogicException('Middleware must return a Response.');
                    }
                    return $response;
                };
            }
            return $next($request);
        }

        if ($allowed !== []) {
            $methods = array_keys($allowed);
            sort($methods);
            return Response::error('METHOD_NOT_ALLOWED', 'HTTP method not allowed.', 405)
                ->withHeader('Allow', implode(', ', $methods));
        }
        return Response::error('NOT_FOUND', 'Route not found.', 404);
    }

    private function invokeHandler(array $handler, Request $request): Response
    {
        [$controller, $method] = $handler;
        if (is_string($controller) && class_exists($controller)) {
            $controller = new $controller();
        }
        if (!is_callable([$controller, $method])) {
            throw new LogicException('Route handler is not callable.');
        }
        $result = $controller->$method($request);
        if (!$result instanceof Response) {
            throw new LogicException('Controllers must return a Response.');
        }
        return $result;
    }
}
