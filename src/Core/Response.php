<?php

declare(strict_types=1);

namespace Paya\Core;

use InvalidArgumentException;

/** A response value object; only the front controller calls send(). */
final class Response
{
    private function __construct(
        private int $status,
        private string $body,
        private array $headers = []
    ) {
        if ($status < 100 || $status > 599) {
            throw new InvalidArgumentException('Invalid HTTP status.');
        }
    }

    public static function json(array $payload, int $status = 200, array $headers = []): self
    {
        if ($status === 204 || $status === 304) {
            throw new InvalidArgumentException('Use noContent() for empty responses.');
        }
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $response = new self($status, $body);
        $response = $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        foreach ($headers as $name => $value) {
            $response = $response->withHeader((string) $name, (string) $value);
        }
        return $response;
    }

    public static function error(string $code, string $message, int $status, array $fields = []): self
    {
        $error = ['code' => $code, 'message' => $message];
        if ($fields !== []) {
            $error['fields'] = $fields;
        }
        return self::json(['error' => $error], $status);
    }

    public static function noContent(): self { return new self(204, ''); }

    public function withHeader(string $name, string $value): self
    {
        if (preg_match('/^[!#$%&\x27*+.^_\x60|~0-9A-Za-z-]+$/D', $name) !== 1
            || str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new InvalidArgumentException('Invalid response header.');
        }
        $copy = clone $this;
        foreach (array_keys($copy->headers) as $old) {
            if (strcasecmp($old, $name) === 0) {
                unset($copy->headers[$old]);
            }
        }
        $copy->headers[$name] = $value;
        return $copy;
    }

    public function statusCode(): int { return $this->status; }
    public function body(): string { return $this->body; }
    public function headers(): array { return $this->headers; }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
