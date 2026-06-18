<?php

declare(strict_types=1);

namespace Ecf\Controllers;

use Ecf\Models\User;
use Ecf\Support\Jwt;
use Ecf\Support\Response;
use Psr\Http\Message\ResponseInterface as Response7;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuthController
{
    /**
     * POST /api/auth/login → { token, user }
     */
    public function login(Request $request, Response7 $response): Response7
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $email = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($email === '' || $password === '') {
            return Response::error($response, 'Email e password sono obbligatorie.', 422);
        }

        $user = User::where('email', $email)->first();

        // Messaggio generico: non rivelo se l'email esiste.
        if ($user === null || !$user->verifyPassword($password)) {
            return Response::error($response, 'Credenziali non valide.', 401);
        }

        $token = Jwt::issue([
            'sub' => $user->id,
            'email' => $user->email,
        ]);

        return Response::success($response, [
            'token' => $token,
            'user' => ['id' => $user->id, 'email' => $user->email],
        ]);
    }
}
