<?php

declare(strict_types=1);

namespace Paya\Core;

use InvalidArgumentException;
use JsonException;
use LengthException;

/** Encapsulates transport data; validation and authentication belong elsewhere. */
final class Request
{
    public const MAX_BODY_BYTES = 1048576; // 1 MiB; configure server limits too.

    /** @var array<string,string> */
    private array $headers = [];

    public function __construct(
        private string $httpMethod,
        private string $uriPath,
        array $headers = [],
        private array $queryParams = [],
        private array $bodyParams = [],
        private array $routeParams = [],
        private array $attributes = []
    ) {
        $this->httpMethod = strtoupper(trim($httpMethod));
        $path = parse_url($uriPath, PHP_URL_PATH);
        $this->uriPath = is_string($path) && $path !== '' ? $path : '/';
        if ($this->uriPath !== '/') {
            $this->uriPath = rtrim($this->uriPath, '/');
        }
        foreach ($headers as $name => $value) {
            $this->headers[strtolower(str_replace('_', '-', (string) $name))] = (string) $value;
        }
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $headers[str_replace('_', '-', substr($name, 5))] = (string) $value;
            }
        }
        foreach (['CONTENT_TYPE', 'CONTENT_LENGTH'] as $key) {
            if (isset($_SERVER[$key])) {
                $headers[str_replace('_', '-', $key)] = (string) $_SERVER[$key];
            }
        }
        $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($length > self::MAX_BODY_BYTES) {
            throw new LengthException('Request body is too large.');
        }
        $raw = file_get_contents('php://input', false, null, 0, self::MAX_BODY_BYTES + 1);
        if ($raw === false) {
            throw new InvalidArgumentException('Request body cannot be read.');
        }
        if (strlen($raw) > self::MAX_BODY_BYTES) {
            throw new LengthException('Request body is too large.');
        }

        $body = $_POST;
        $type = strtolower(trim(explode(';', $headers['CONTENT-TYPE'] ?? '')[0]));
        if ($raw !== '' && ($type === 'application/json' || str_ends_with($type, '+json'))) {
            if (!str_starts_with(ltrim($raw), '{')) {
                throw new InvalidArgumentException('JSON request body must be an object.');
            }
            try {
                $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new InvalidArgumentException('Malformed JSON request body.', 0, $e);
            }
            if (!is_array($decoded)) {
                throw new InvalidArgumentException('JSON request body must be an object.');
            }
            $body = $decoded;
        } elseif ($raw !== '' && $type === 'application/x-www-form-urlencoded') {
            parse_str($raw, $body);
        }

        return new self(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $_SERVER['REQUEST_URI'] ?? '/',
            $headers,
            $_GET,
            $body
        );
    }

    public function method(): string { return $this->httpMethod; }
    public function path(): string { return $this->uriPath; }
    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->bodyParams[$key] ?? $default;
    }
    public function all(): array { return $this->bodyParams; }
    public function bearerToken(): ?string
    {
        $value = $this->header('Authorization');
        return $value !== null && preg_match('/^Bearer[ \t]+([^ \t]+)$/iD', trim($value), $m) === 1
            ? $m[1] : null;
    }
    public function route(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }
    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
    public function withRouteParams(array $params): self
    {
        $copy = clone $this;
        $copy->routeParams = $params;
        return $copy;
    }
    public function withAttribute(string $key, mixed $value): self
    {
        $copy = clone $this;
        $copy->attributes[$key] = $value;
        return $copy;
    }
}
