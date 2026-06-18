<?php

declare(strict_types=1);

namespace Ecf\Support;

use Psr\Http\Message\ResponseInterface;

/**
 * Helper per risposte JSON coerenti: { success, data?, errors?, message? }.
 */
final class Response
{
    public static function json(ResponseInterface $response, array $payload, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }

    public static function success(ResponseInterface $response, mixed $data = null, ?string $message = null, int $status = 200): ResponseInterface
    {
        $payload = ['success' => true];

        if ($data !== null) {
            $payload['data'] = $data;
        }
        if ($message !== null) {
            $payload['message'] = $message;
        }

        return self::json($response, $payload, $status);
    }

    public static function error(ResponseInterface $response, string $message, int $status = 400, ?array $errors = null): ResponseInterface
    {
        $payload = ['success' => false, 'message' => $message];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return self::json($response, $payload, $status);
    }

    public static function validationError(ResponseInterface $response, array $errors, string $message = 'Validazione fallita.'): ResponseInterface
    {
        return self::error($response, $message, 422, $errors);
    }
}
