<?php

declare(strict_types=1);

namespace Ecf\Support;

use Firebase\JWT\JWT as FirebaseJwt;
use Firebase\JWT\Key;

/**
 * Wrapper minimale su firebase/php-jwt per emettere/verificare i token admin.
 */
final class Jwt
{
    private const ALGO = 'HS256';

    public static function issue(array $claims): string
    {
        $now = time();
        $ttl = Env::int('JWT_TTL', 28800);

        $payload = array_merge([
            'iat' => $now,
            'exp' => $now + $ttl,
        ], $claims);

        return FirebaseJwt::encode($payload, self::secret(), self::ALGO);
    }

    /**
     * Verifica il token e ritorna i claim, oppure null se non valido/scaduto.
     */
    public static function verify(string $token): ?array
    {
        try {
            $decoded = FirebaseJwt::decode($token, new Key(self::secret(), self::ALGO));

            return (array) $decoded;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function secret(): string
    {
        return (string) Env::get('JWT_SECRET', 'insecure-default-secret');
    }
}
