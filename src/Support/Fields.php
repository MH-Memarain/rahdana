<?php

declare(strict_types=1);

namespace Rahdana\Support;

use InvalidArgumentException;

/** Input shape checks. Domain dates are Gregorian YYYY-MM-DD; UI may show Jalali. */
final class Fields
{
    public static function text(array $body, string $key, int $max, bool $required = false): ?string
    {
        $value = $body[$key] ?? null;
        if ($value === null && !$required) {
            return null;
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException($key . ' must be a string.');
        }
        $value = trim($value);
        if (($required && $value === '') || mb_strlen($value, 'UTF-8') > $max) {
            throw new InvalidArgumentException($key . ' must contain 1 to ' . $max . ' characters.');
        }
        return $value === '' ? null : $value;
    }

    public static function date(array $body, string $key, bool $required = false): ?string
    {
        $value = $body[$key] ?? null;
        if ($value === null && !$required) {
            return null;
        }
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) {
            throw new InvalidArgumentException($key . ' must be YYYY-MM-DD.');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException($key . ' is not a valid date.');
        }
        return $value;
    }

    public static function clock(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $value) !== 1) {
            throw new InvalidArgumentException($key . ' must be HH:MM.');
        }
        return $value . ':00';
    }

    public static function number(array $body, string $key, int $min, int $max): int
    {
        $value = $body[$key] ?? null;
        if (!is_int($value) || $value < $min || $value > $max) {
            throw new InvalidArgumentException($key . ' must be an integer from ' . $min . ' to ' . $max . '.');
        }
        return $value;
    }

    public static function choice(array $body, string $key, array $allowed): string
    {
        $value = $body[$key] ?? null;
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new InvalidArgumentException($key . ' must be one of: ' . implode(', ', $allowed));
        }
        return $value;
    }

    public static function id(mixed $value): int
    {
        if (!is_string($value) || preg_match('/^[1-9]\d{0,17}$/D', $value) !== 1) {
            throw new InvalidArgumentException('Invalid resource id.');
        }
        return (int) $value;
    }
}
