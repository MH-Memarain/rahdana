<?php

declare(strict_types=1);

namespace Rahdana\Controllers;

use InvalidArgumentException;
use PDOException;
use Paya\Core\Request;
use Paya\Core\Response;
use Rahdana\Support\Api;
use Rahdana\Support\DatabaseProvider;
use Rahdana\Support\Fields;

final class AuthController
{
    public function __construct(private DatabaseProvider $provider) {}

    public function register(Request $request): Response
    {
        if (getenv('ALLOW_REGISTRATION') !== 'true') {
            return Response::error('REGISTRATION_DISABLED', 'Registration is currently closed.', 403);
        }

        return Api::validate(function () use ($request): Response {
            $body = $request->all();
            Api::only($body, ['name', 'email', 'password', 'timezone']);
            $name = Fields::text($body, 'name', 120, true);
            $email = self::email($body);
            $password = $body['password'] ?? null;
            if (!is_string($password) || strlen($password) < 12 || strlen($password) > 1024) {
                throw new InvalidArgumentException('Password must be between 12 and 1024 bytes.');
            }

            $zone = $body['timezone'] ?? 'UTC';
            if (!is_string($zone) || !in_array($zone, \DateTimeZone::listIdentifiers(), true)) {
                if ($zone !== 'UTC') {
                    throw new InvalidArgumentException('Invalid timezone.');
                }
            }
            $db = $this->provider->get();
            try {
                $db->execute(
                    'INSERT INTO users (name, email, password_hash, timezone) VALUES (?, ?, ?, ?)',
                    [$name, $email, password_hash($password, PASSWORD_DEFAULT), $zone]
                );
            } catch (PDOException $error) {
                if ($error->getCode() === '23000') {
                    return Response::error('EMAIL_EXISTS', 'An account with this email already exists.', 409);
                }
                throw $error;
            }
            return Response::json(['data' => ['id' => (int) $db->lastInsertId(), 'name' => $name, 'email' => $email, 'timezone' => $zone]], 201);
        });
    }

    public function login(Request $request): Response
    {
        return Api::validate(function () use ($request): Response {
            $body = $request->all();
            Api::only($body, ['email', 'password']);
            $email = self::email($body);
            $password = $body['password'] ?? null;
            if (!is_string($password) || $password === '' || strlen($password) > 1024) {
                throw new InvalidArgumentException('Invalid email or password format.');
            }

            $db = $this->provider->get();
            $identity = hash('sha256', $email);
            $db->execute('UPDATE auth_failures SET failure_count = 0, locked_until = NULL WHERE identity_hash = ? AND locked_until <= UTC_TIMESTAMP()', [$identity]);
            $lock = $db->fetchOne('SELECT locked_until > UTC_TIMESTAMP() AS blocked FROM auth_failures WHERE identity_hash = ?', [$identity]);
            if ($lock && (int) $lock['blocked'] === 1) {
                return Response::error('TOO_MANY_ATTEMPTS', 'Try again later.', 429);
            }

            $user = $db->fetchOne('SELECT id, password_hash FROM users WHERE email = ? LIMIT 1', [$email]);
            if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
                $db->execute(
                    'INSERT INTO auth_failures (identity_hash, failure_count) VALUES (?, 1) ON DUPLICATE KEY UPDATE failure_count = failure_count + 1, locked_until = CASE WHEN failure_count >= 4 THEN DATE_ADD(UTC_TIMESTAMP(), INTERVAL 15 MINUTE) ELSE locked_until END',
                    [$identity]
                );
                return Response::error('INVALID_CREDENTIALS', 'Invalid email or password.', 401);
            }

            $db->execute('DELETE FROM auth_failures WHERE identity_hash = ?', [$identity]);
            $plain = bin2hex(random_bytes(32));
            $db->execute(
                'INSERT INTO api_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY))',
                [(int) $user['id'], hash('sha256', $plain)]
            );
            return Response::json(['data' => ['token' => $plain, 'token_type' => 'Bearer', 'expires_in' => 2592000]])
                ->withHeader('Cache-Control', 'no-store');
        });
    }

    public function me(Request $request): Response
    {
        $user = $this->provider->get()->fetchOne(
            'SELECT id, name, email, timezone, created_at FROM users WHERE id = ?',
            [Api::userId($request)]
        );
        return Response::json(['data' => $user]);
    }

    public function logout(Request $request): Response
    {
        $this->provider->get()->execute('DELETE FROM api_tokens WHERE token_hash = ? AND user_id = ?', [
            hash('sha256', (string) $request->bearerToken()),
            Api::userId($request),
        ]);
        return Response::noContent();
    }

    private static function email(array $body): string
    {
        $value = $body['email'] ?? null;
        if (!is_string($value) || strlen($value) > 254) {
            throw new InvalidArgumentException('Invalid email address.');
        }
        $email = strtolower(trim($value));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Invalid email address.');
        }
        return $email;
    }
}
