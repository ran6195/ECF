<?php

declare(strict_types=1);

namespace Ecf\Services;

use Ecf\Models\Form;

/**
 * Client minimale per le API contatti di Brevo (v3). Mirror di
 * RecaptchaVerifier per lo stile delle chiamate HTTP in uscita (curl,
 * timeout brevi), ma qui gli errori vanno propagati con un messaggio
 * leggibile invece che ridotti a un booleano: il chiamante deve poter
 * mostrare "perché" una connessione o una sync sono fallite.
 */
final class BrevoService
{
    private const BASE_URL = 'https://api.brevo.com/v3';

    /**
     * Verifica che la coppia (api key, list id) sia valida per l'account Brevo
     * e ritorna i dati della lista (incluso il nome, per la conferma in UI).
     *
     * @throws \RuntimeException con un messaggio specifico sul motivo del fallimento.
     */
    public function verifyConnection(string $apiKey, int $listId): array
    {
        [$status, $body] = $this->request('GET', "/contacts/lists/{$listId}", $apiKey);

        if ($status === 401) {
            throw new \RuntimeException('Chiave API non valida.');
        }
        if ($status === 404) {
            throw new \RuntimeException('Lista non trovata per questo account Brevo.');
        }
        if ($status < 200 || $status >= 300 || !is_array($body)) {
            throw new \RuntimeException($this->errorMessage($status, $body));
        }

        return $body;
    }

    /**
     * Attributi contatto definiti sull'account, filtrati alla sola category
     * "normal": le altre (calculated/global/transactional) non sono
     * impostabili via API e confonderebbero solo la UI di mappatura.
     *
     * @return array<int, array{name: string, type: string|null}>
     * @throws \RuntimeException
     */
    public function fetchAttributes(string $apiKey): array
    {
        [$status, $body] = $this->request('GET', '/contacts/attributes', $apiKey);

        if ($status < 200 || $status >= 300 || !is_array($body)) {
            throw new \RuntimeException($this->errorMessage($status, $body));
        }

        $attributes = [];
        foreach ((array) ($body['attributes'] ?? []) as $attr) {
            if (($attr['category'] ?? null) === 'normal') {
                $attributes[] = [
                    'name' => (string) ($attr['name'] ?? ''),
                    'type' => is_string($attr['type'] ?? null) ? $attr['type'] : null,
                ];
            }
        }

        return $attributes;
    }

    /**
     * Crea/aggiorna il contatto su Brevo a partire dal payload pulito di una
     * submission e dalla mappatura campi del form (`brevo_field_mapping`:
     * nome attributo Brevo => key del campo form; "EMAIL" è sempre presente,
     * garantito da Form::brevoActive() prima di arrivare qui).
     *
     * @param array<string, mixed> $cleanPayload
     * @throws \RuntimeException
     */
    public function syncContact(Form $form, array $cleanPayload): void
    {
        $mapping = (array) ($form->brevo_field_mapping ?? []);

        $emailKey = (string) ($mapping['EMAIL'] ?? '');
        $email = is_string($cleanPayload[$emailKey] ?? null) ? trim($cleanPayload[$emailKey]) : '';
        if ($email === '') {
            throw new \RuntimeException('Email non disponibile per la sincronizzazione.');
        }

        $attributes = [];
        foreach ($mapping as $brevoAttr => $fieldKey) {
            if ($brevoAttr === 'EMAIL') {
                continue;
            }
            $value = $cleanPayload[$fieldKey] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $attributes[$brevoAttr] = is_array($value) ? implode(', ', $value) : $value;
        }

        $payload = [
            'email' => $email,
            'listIds' => [(int) $form->brevo_list_id],
            'updateEnabled' => true,
        ];
        if ($attributes !== []) {
            $payload['attributes'] = $attributes;
        }

        [$status, $body] = $this->request('POST', '/contacts', (string) $form->brevo_api_key, $payload);

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException($this->errorMessage($status, $body));
        }
    }

    /**
     * @return array{0: int, 1: array|null} [status HTTP, body decodificato (null se non JSON/errore di rete)]
     */
    private function request(string $method, string $path, string $apiKey, ?array $jsonBody = null): array
    {
        $ch = curl_init(self::BASE_URL . $path);
        if ($ch === false) {
            return [0, null];
        }

        $headers = ['api-key: ' . $apiKey, 'Accept: application/json'];

        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($jsonBody !== null) {
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
            $options[CURLOPT_POSTFIELDS] = json_encode($jsonBody, JSON_UNESCAPED_UNICODE);
        }
        curl_setopt_array($ch, $options);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($ch);
        curl_close($ch);

        if ($curlError !== 0 || $raw === false) {
            return [0, null];
        }

        $decoded = $raw !== '' ? json_decode((string) $raw, true) : [];

        return [$status, is_array($decoded) ? $decoded : null];
    }

    private function errorMessage(int $status, ?array $body): string
    {
        if ($status === 0) {
            return 'Impossibile contattare Brevo (rete o timeout).';
        }
        $message = is_string($body['message'] ?? null) ? $body['message'] : null;

        return $message !== null
            ? "Errore Brevo ({$status}): {$message}"
            : "Errore Brevo (HTTP {$status}).";
    }
}
