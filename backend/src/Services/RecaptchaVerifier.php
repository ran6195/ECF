<?php

declare(strict_types=1);

namespace Ecf\Services;

/**
 * Verifica un token reCAPTCHA v2 contro l'endpoint siteverify di Google.
 *
 * Classe separata da FormValidator (che resta pura, senza I/O, come testato in
 * tests/FormValidatorTest.php): qui c'è una vera chiamata HTTP in uscita.
 *
 * Fail-closed: qualunque errore di rete, timeout o risposta malformata è
 * trattato come verifica fallita. È la scelta più prudente per una feature
 * anti-spam, ma ha un trade-off esplicito: un'interruzione temporanea di Google
 * rifiuta anche submission legittime nella stessa finestra.
 */
final class RecaptchaVerifier
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function verify(string $secretKey, string $token, ?string $remoteIp = null): bool
    {
        if (trim($token) === '') {
            return false;
        }

        $fields = ['secret' => $secretKey, 'response' => $token];
        if ($remoteIp !== null && $remoteIp !== '') {
            $fields['remoteip'] = $remoteIp;
        }

        $ch = curl_init(self::VERIFY_URL);
        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
        ]);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($ch);
        curl_close($ch);

        if ($curlError !== 0 || $body === false || $httpCode !== 200) {
            return false;
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            return false;
        }

        return (bool) ($decoded['success'] ?? false);
    }
}
