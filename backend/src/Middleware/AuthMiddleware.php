<?php

declare(strict_types=1);

namespace Ecf\Middleware;

use Ecf\Support\Jwt;
use Ecf\Support\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Protegge le rotte admin: richiede un JWT valido in "Authorization: Bearer".
 * In caso di successo espone i claim nell'attributo "auth" della request.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');

        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return Response::error(new SlimResponse(), 'Token mancante.', 401);
        }

        $claims = Jwt::verify(trim($m[1]));

        if ($claims === null) {
            return Response::error(new SlimResponse(), 'Token non valido o scaduto.', 401);
        }

        $request = $request->withAttribute('auth', $claims);

        return $handler->handle($request);
    }
}
