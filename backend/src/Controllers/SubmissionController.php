<?php

declare(strict_types=1);

namespace Ecf\Controllers;

use Ecf\Models\Form;
use Ecf\Models\Submission;
use Ecf\Support\Response;
use Psr\Http\Message\ResponseInterface as Response7;
use Psr\Http\Message\ServerRequestInterface as Request;

final class SubmissionController
{
    /** GET /api/forms/{id}/submissions?page=&per_page= → lista paginata. */
    public function index(Request $request, Response7 $response, array $args): Response7
    {
        $form = Form::find((int) $args['id']);
        if ($form === null) {
            return Response::error($response, 'Form non trovato.', 404);
        }

        $query = $request->getQueryParams();
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($query['per_page'] ?? 20)));

        $total = $form->submissions()->count();
        $items = $form->submissions()
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (Submission $s) => $this->serialize($s));

        return Response::success($response, [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage) ?: 1,
            ],
            // Le chiavi dei campi: utile all'admin per costruire le colonne.
            'fields' => $form->fields->map(fn ($f) => ['key' => $f->key, 'label' => $f->label])->values(),
        ]);
    }

    /** GET /api/forms/{id}/submissions/export → CSV. */
    public function export(Request $request, Response7 $response, array $args): Response7
    {
        $form = Form::with('fields')->find((int) $args['id']);
        if ($form === null) {
            return Response::error($response, 'Form non trovato.', 404);
        }

        $fieldKeys = $form->fields->pluck('key')->all();
        $headers = array_merge(['id', 'created_at'], $fieldKeys, ['source_url', 'ip']);

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);

        $form->submissions()->orderBy('id')->chunk(200, function ($chunk) use ($stream, $fieldKeys) {
            foreach ($chunk as $s) {
                $payload = $s->payload ?? [];
                $row = [$s->id, optional($s->created_at)->toDateTimeString()];
                foreach ($fieldKeys as $key) {
                    $value = $payload[$key] ?? '';
                    $row[] = is_array($value) ? implode(', ', $value) : (string) $value;
                }
                $row[] = $s->source_url ?? '';
                $row[] = $s->ip ?? '';
                fputcsv($stream, $row);
            }
        });

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        $filename = 'submissions-' . preg_replace('/[^a-z0-9]+/i', '-', $form->name) . '.csv';
        $response->getBody()->write($csv);

        return $response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withStatus(200);
    }

    private function serialize(Submission $s): array
    {
        return [
            'id' => $s->id,
            'payload' => $s->payload,
            'source_url' => $s->source_url,
            'ip' => $s->ip,
            'user_agent' => $s->user_agent,
            'created_at' => optional($s->created_at)->toDateTimeString(),
        ];
    }
}
