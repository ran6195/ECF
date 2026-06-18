<?php

declare(strict_types=1);

namespace Ecf\Controllers;

use Ecf\Models\Form;
use Ecf\Models\Submission;
use Ecf\Services\FormRenderer;
use Ecf\Services\FormValidator;
use Ecf\Support\Response;
use Psr\Http\Message\ResponseInterface as Response7;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Endpoint pubblici consumati da embed.js: render dell'HTML e submit.
 */
final class EmbedController
{
    /**
     * GET /api/embed/{uuid}/render → fragment HTML (text/html).
     * 404 se il form non esiste o non è "active".
     */
    public function render(Request $request, Response7 $response, array $args): Response7
    {
        $form = Form::with('fields')->where('uuid', $args['uuid'])->first();

        if ($form === null || !$form->isActive()) {
            $response->getBody()->write('<!-- ECF: form non trovato o non attivo -->');
            return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withStatus(404);
        }

        $html = (new FormRenderer())->render($form);
        $response->getBody()->write($html);

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withStatus(200);
    }

    /**
     * POST /api/embed/{uuid}/submit → valida e salva la submission.
     */
    public function submit(Request $request, Response7 $response, array $args): Response7
    {
        $form = Form::with('fields')->where('uuid', $args['uuid'])->first();

        if ($form === null || !$form->isActive()) {
            return Response::error($response, 'Form non trovato o non attivo.', 404);
        }

        $input = (array) ($request->getParsedBody() ?? []);

        // Honeypot valorizzato → silent drop: rispondo ok senza salvare.
        $honeypot = $input[FormRenderer::HONEYPOT_KEY] ?? '';
        if (is_string($honeypot) && trim($honeypot) !== '') {
            return Response::success($response, null, $form->success_message ?: 'Grazie!');
        }

        $validator = new FormValidator();
        if (!$validator->validate($form, $input)) {
            return Response::validationError($response, $validator->errors());
        }

        $submission = new Submission([
            'form_id' => $form->id,
            'payload' => $validator->clean(),
            'source_url' => $this->str($input['source_url'] ?? null, 500),
            'ip' => $this->clientIp($request),
            'user_agent' => $this->str($request->getHeaderLine('User-Agent'), 255),
        ]);
        $submission->save();

        return Response::success(
            $response,
            ['id' => $submission->id],
            $form->success_message ?: 'Grazie! Il modulo è stato inviato.'
        );
    }

    private function str(?string $value, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }

    private function clientIp(Request $request): ?string
    {
        $server = $request->getServerParams();
        $ip = $server['REMOTE_ADDR'] ?? null;

        return $ip ? mb_substr((string) $ip, 0, 45) : null;
    }
}
