<?php

declare(strict_types=1);

namespace Ecf\Controllers;

use Ecf\Models\BrevoAttribute;
use Ecf\Models\Form;
use Ecf\Services\BrevoService;
use Ecf\Support\Response;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\ResponseInterface as Response7;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Configurazione dell'integrazione Brevo per un form: test di connessione
 * (che aggiorna anche la cache degli attributi) e lettura della cache.
 * Vive sotto /api/forms/{id}/brevo/... perché entrambe le operazioni sono
 * legate a un form già salvato (vedi Form::brevoAttributes()).
 */
final class BrevoController
{
    /**
     * POST /api/forms/{id}/brevo/test-connection
     * Body: { api_key, list_id }.
     * Verifica chiave + lista contro Brevo; se ok, ricarica anche la cache
     * degli attributi contatto (sostituzione completa) e la ritorna.
     */
    public function testConnection(Request $request, Response7 $response, array $args): Response7
    {
        $form = Form::find((int) $args['id']);
        if ($form === null) {
            return Response::error($response, 'Form non trovato.', 404);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $apiKey = trim((string) ($body['api_key'] ?? ''));
        $listId = (int) ($body['list_id'] ?? 0);

        if ($apiKey === '' || $listId <= 0) {
            return Response::validationError($response, [
                'api_key' => $apiKey === '' ? ['La chiave API è obbligatoria.'] : [],
                'list_id' => $listId <= 0 ? ['L\'ID della lista è obbligatorio.'] : [],
            ]);
        }

        $service = new BrevoService();

        try {
            $list = $service->verifyConnection($apiKey, $listId);
            $attributes = $service->fetchAttributes($apiKey);
        } catch (\RuntimeException $e) {
            return Response::error($response, $e->getMessage(), 422);
        }

        DB::connection()->transaction(function () use ($form, $attributes) {
            BrevoAttribute::where('form_id', $form->id)->delete();
            if ($attributes !== []) {
                // Model::insert() è un bulk insert "raw": non passa dai cast di
                // Eloquent, quindi serve una stringa (non un helper/oggetto data:
                // "now()" non esiste in questo progetto, che usa i soli componenti
                // Illuminate senza illuminate/foundation).
                $now = date('Y-m-d H:i:s');
                BrevoAttribute::insert(array_map(
                    fn (array $a) => [
                        'form_id' => $form->id,
                        'name' => $a['name'],
                        'type' => $a['type'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    $attributes
                ));
            }
        });

        return Response::success($response, [
            'list_name' => $list['name'] ?? null,
            'attributes' => $attributes,
        ]);
    }

    /** GET /api/forms/{id}/brevo/attributes → cache attualmente salvata. */
    public function attributes(Request $request, Response7 $response, array $args): Response7
    {
        $form = Form::find((int) $args['id']);
        if ($form === null) {
            return Response::error($response, 'Form non trovato.', 404);
        }

        $attributes = $form->brevoAttributes()->get(['name', 'type'])->values();

        return Response::success($response, ['attributes' => $attributes]);
    }
}
