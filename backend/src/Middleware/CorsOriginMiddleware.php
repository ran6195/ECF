<?php

declare(strict_types=1);

namespace Ecf\Middleware;

use Ecf\Models\Form;
use Ecf\Support\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;
use Slim\Routing\RouteContext;

/**
 * CORS per le rotte di embed, con whitelist di origini per-form.
 *
 * - allowed_origins vuoto/NULL  → modalità aperta (riflette l'Origin, * di fallback).
 * - allowed_origins valorizzato → consente solo le origini in lista.
 * - Gestisce il preflight OPTIONS (risposta 204 senza eseguire il body).
 */
final class CorsOriginMiddleware implements MiddlewareInterface
{
    private const ALLOW_METHODS = 'GET, POST, OPTIONS';
    private const ALLOW_HEADERS = 'Content-Type, Authorization';

    public function process(Request $request, Handler $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');
        $uuid = $this->routeUuid($request);

        $form = $uuid !== null ? Form::where('uuid', $uuid)->first() : null;
        $allowedOrigin = $this->resolveAllowedOrigin($form, $origin);

        $isPreflight = strtoupper($request->getMethod()) === 'OPTIONS';

        // Origine non autorizzata su una richiesta reale → blocco.
        if ($allowedOrigin === false && !$isPreflight) {
            $response = new SlimResponse();
            return Response::error($response, 'Origine non autorizzata.', 403);
        }

        if ($isPreflight) {
            $response = new SlimResponse(204);
            return $this->withCorsHeaders($response, $allowedOrigin);
        }

        $response = $handler->handle($request);

        return $this->withCorsHeaders($response, $allowedOrigin);
    }

    /**
     * @return string|false|null  origine da riflettere, false se vietata, null se nessuna richiesta CORS
     */
    private function resolveAllowedOrigin(?Form $form, string $origin): string|false|null
    {
        // Form inesistente: lascio proseguire (sarà il controller a fare 404).
        if ($form === null) {
            return $origin !== '' ? $origin : '*';
        }

        if ($form->isOpenOrigin()) {
            return $origin !== '' ? $origin : '*';
        }

        if ($origin === '') {
            // Richiesta senza Origin (es. server-to-server) su form con whitelist:
            // non possiamo verificarla, la consideriamo non autorizzata per il browser.
            return false;
        }

        $allowed = array_map('strtolower', $form->allowed_origins ?? []);

        return in_array(strtolower($origin), $allowed, true) ? $origin : false;
    }

    private function withCorsHeaders(ResponseInterface $response, string|false|null $allowedOrigin): ResponseInterface
    {
        if ($allowedOrigin === false || $allowedOrigin === null) {
            return $response;
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
            ->withHeader('Access-Control-Allow-Methods', self::ALLOW_METHODS)
            ->withHeader('Access-Control-Allow-Headers', self::ALLOW_HEADERS)
            ->withHeader('Access-Control-Max-Age', '86400')
            ->withHeader('Vary', 'Origin');
    }

    private function routeUuid(Request $request): ?string
    {
        $route = RouteContext::fromRequest($request)->getRoute();

        return $route?->getArgument('uuid');
    }
}
