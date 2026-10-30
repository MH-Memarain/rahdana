<?php

declare(strict_types=1);

namespace Rahdana\Middleware;

use Paya\Core\Request;
use Paya\Core\Response;
use Rahdana\Support\DatabaseProvider;

final class Authenticate
{
    public function __construct(private DatabaseProvider $provider) {}

    public function handle(Request $request, callable $next): Response
    {
        $token = $request->bearerToken();
        if ($token === null || strlen($token) !== 64 || ctype_xdigit($token) === false) {
            return Response::error('UNAUTHENTICATED', 'Authentication required.', 401);
        }

        $user = $this->provider->get()->fetchOne(
            'SELECT u.id FROM api_tokens t INNER JOIN users u ON u.id = t.user_id WHERE t.token_hash = ? AND t.expires_at > UTC_TIMESTAMP() LIMIT 1',
            [hash('sha256', $token)]
        );
        if ($user === null) {
            return Response::error('UNAUTHENTICATED', 'Invalid or expired token.', 401);
        }
        return $next($request->withAttribute('user_id', (int) $user['id']));
    }
}
