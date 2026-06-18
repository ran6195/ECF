<?php

declare(strict_types=1);

namespace Ecf\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

/**
 * CORS permissivo per le rotte admin/auth: la SPA Vue gira su un'origine diversa
 * (es. http://localhost:5173) e deve poter chiamare l'API. Riflette l'Origin e
 * consente l'header Authorization. Gestisce il preflight OPTIONS.
 */
final class AdminCorsMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');

        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $this->withHeaders(new SlimResponse(204), $origin);
        }

        return $this->withHeaders($handler->handle($request), $origin);
    }

    private function withHeaders(ResponseInterface $response, string $origin): ResponseInterface
    {
        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin !== '' ? $origin : '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withHeader('Access-Control-Max-Age', '86400')
            ->withHeader('Vary', 'Origin');
    }
}
