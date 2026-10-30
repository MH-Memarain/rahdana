<?php

declare(strict_types=1);

namespace Rahdana\Support;

use InvalidArgumentException;
use Paya\Core\Request;
use Paya\Core\Response;

final class Api
{
    public static function validate(callable $operation): Response
    {
        try {
            return $operation();
        } catch (InvalidArgumentException $error) {
            return Response::error('VALIDATION_ERROR', $error->getMessage(), 422);
        }
    }

    public static function userId(Request $request): int
    {
        return (int) $request->attribute('user_id');
    }

    /** Reject unexpected mutable keys to avoid silent client mistakes. */
    public static function only(array $body, array $allowed): void
    {
        $unknown = array_diff(array_keys($body), $allowed);
        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown fields: ' . implode(', ', $unknown));
        }
    }
}
