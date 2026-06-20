<?php

declare(strict_types=1);

namespace Ecf\Controllers;

use Ecf\Models\Form;
use Ecf\Models\FormField;
use Ecf\Services\FormRenderer;
use Ecf\Support\Response;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Collection;
use Psr\Http\Message\ResponseInterface as Response7;
use Psr\Http\Message\ServerRequestInterface as Request;

final class FormController
{
    private const FIELD_TYPES = ['text', 'email', 'textarea', 'number', 'select', 'radio', 'checkbox', 'date', 'hidden'];
    private const STATUSES = ['draft', 'active', 'disabled'];

    /** GET /api/forms → lista con conteggio submission. */
    public function index(Request $request, Response7 $response): Response7
    {
        $forms = Form::withCount('submissions')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Form $f) => [
                'id' => $f->id,
                'uuid' => $f->uuid,
                'name' => $f->name,
                'status' => $f->status,
                'allowed_origins' => $f->allowed_origins,
                'submissions_count' => $f->submissions_count,
                'created_at' => optional($f->created_at)->toDateTimeString(),
            ]);

        return Response::success($response, $forms);
    }

    /** GET /api/forms/{id} → dettaglio + fields[]. */
    public function show(Request $request, Response7 $response, array $args): Response7
    {
        $form = Form::with('fields')->find((int) $args['id']);

        if ($form === null) {
            return Response::error($response, 'Form non trovato.', 404);
        }

        return Response::success($response, $this->serialize($form));
    }

    /** POST /api/forms → crea form + fields. */
    public function store(Request $request, Response7 $response): Response7
    {
        $body = (array) ($request->getParsedBody() ?? []);

        $errors = $this->validateFormBody($body);
        if ($errors !== []) {
            return Response::validationError($response, $errors);
        }

        $form = new Form([
            'uuid' => $this->uuid(),
            'name' => trim((string) $body['name']),
            'description' => $this->nullableStr($body['description'] ?? null),
            'success_message' => $this->nullableStr($body['success_message'] ?? null),
            'allowed_origins' => $this->normalizeOrigins($body['allowed_origins'] ?? null),
            'style' => $this->normalizeStyle($body['style'] ?? null),
            'status' => $body['status'] ?? 'draft',
        ]);
        $form->save();

        $this->syncFields($form, (array) ($body['fields'] ?? []));

        return Response::success($response, $this->serialize($form->fresh('fields')), 'Form creato.', 201);
    }

    /** PUT /api/forms/{id} → aggiorna form e sincronizza i fields. */
    public function update(Request $request, Response7 $response, array $args): Response7
    {
        $form = Form::find((int) $args['id']);
        if ($form === null) {
            return Response::error($response, 'Form non trovato.', 404);
        }

        $body = (array) ($request->getParsedBody() ?? []);

        $errors = $this->validateFormBody($body);
        if ($errors !== []) {
            return Response::validationError($response, $errors);
        }

        $form->fill([
            'name' => trim((string) $body['name']),
            'description' => $this->nullableStr($body['description'] ?? null),
            'success_message' => $this->nullableStr($body['success_message'] ?? null),
            'allowed_origins' => $this->normalizeOrigins($body['allowed_origins'] ?? null),
            'style' => $this->normalizeStyle($body['style'] ?? null),
            'status' => $body['status'] ?? $form->status,
        ]);
        $form->save();

        $this->syncFields($form, (array) ($body['fields'] ?? []));

        return Response::success($response, $this->serialize($form->fresh('fields')), 'Form aggiornato.');
    }

    /** DELETE /api/forms/{id}. */
    public function destroy(Request $request, Response7 $response, array $args): Response7
    {
        $form = Form::find((int) $args['id']);
        if ($form === null) {
            return Response::error($response, 'Form non trovato.', 404);
        }

        $form->delete(); // FK CASCADE rimuove fields e submissions

        return Response::success($response, null, 'Form eliminato.');
    }

    /**
     * POST /api/forms/preview → rende l'HTML del form (senza salvarlo) usando
     * lo stesso FormRenderer della produzione. Serve all'anteprima live dell'admin.
     */
    public function preview(Request $request, Response7 $response): Response7
    {
        $body = (array) ($request->getParsedBody() ?? []);

        $form = new Form([
            'uuid' => 'preview',
            'name' => trim((string) ($body['name'] ?? 'Anteprima')),
            'description' => $this->nullableStr($body['description'] ?? null),
            'style' => $this->normalizeStyle($body['style'] ?? null),
        ]);

        // Costruisce i campi in memoria (non tocca il DB).
        $fields = new Collection();
        foreach (array_values((array) ($body['fields'] ?? [])) as $i => $raw) {
            $fields->push(new FormField([
                'key' => $this->slugKey((string) ($raw['key'] ?? ''), $i),
                'label' => trim((string) ($raw['label'] ?? '')),
                'type' => in_array($raw['type'] ?? '', self::FIELD_TYPES, true) ? $raw['type'] : 'text',
                'required' => !empty($raw['required']),
                'placeholder' => $this->nullableStr($raw['placeholder'] ?? null),
                'options' => $this->normalizeOptions($raw['options'] ?? null),
                'validation' => $this->normalizeValidation($raw['validation'] ?? null),
                'sort_order' => $i,
            ]));
        }
        $form->setRelation('fields', $fields);

        $html = (new FormRenderer())->render($form);

        return Response::success($response, ['html' => $html]);
    }

    // --- Helpers ---------------------------------------------------------

    /**
     * Sincronizza i campi: crea i nuovi, aggiorna gli esistenti, elimina i mancanti.
     * @param array<int, array<string, mixed>> $incoming
     */
    private function syncFields(Form $form, array $incoming): void
    {
        DB::connection()->transaction(function () use ($form, $incoming) {
            $existingIds = $form->fields()->pluck('id')->all();

            // Cancella PRIMA i campi non più presenti: così le loro `key` si
            // liberano e un nuovo campo può riusarle senza violare UNIQUE(form_id,key).
            $incomingIds = [];
            foreach ($incoming as $raw) {
                $id = isset($raw['id']) ? (int) $raw['id'] : 0;
                if ($id > 0 && in_array($id, $existingIds, true)) {
                    $incomingIds[] = $id;
                }
            }
            $toDelete = array_diff($existingIds, $incomingIds);
            if ($toDelete !== []) {
                FormField::whereIn('id', $toDelete)->delete();
            }

            foreach (array_values($incoming) as $index => $raw) {
                $data = [
                    'key' => $this->slugKey((string) ($raw['key'] ?? ''), $index),
                    'label' => trim((string) ($raw['label'] ?? '')),
                    'type' => in_array($raw['type'] ?? '', self::FIELD_TYPES, true) ? $raw['type'] : 'text',
                    'required' => !empty($raw['required']),
                    'placeholder' => $this->nullableStr($raw['placeholder'] ?? null),
                    'options' => $this->normalizeOptions($raw['options'] ?? null),
                    'validation' => $this->normalizeValidation($raw['validation'] ?? null),
                    'sort_order' => isset($raw['sort_order']) ? (int) $raw['sort_order'] : $index,
                ];

                $id = isset($raw['id']) ? (int) $raw['id'] : 0;
                if ($id > 0 && in_array($id, $existingIds, true)) {
                    $form->fields()->find($id)->update($data);
                } else {
                    $data['form_id'] = $form->id;
                    FormField::create($data);
                }
            }
        });
    }

    private function serialize(Form $form): array
    {
        return [
            'id' => $form->id,
            'uuid' => $form->uuid,
            'name' => $form->name,
            'description' => $form->description,
            'success_message' => $form->success_message,
            'allowed_origins' => $form->allowed_origins,
            'style' => $form->style,
            'status' => $form->status,
            'fields' => $form->fields->map(fn (FormField $f) => [
                'id' => $f->id,
                'key' => $f->key,
                'label' => $f->label,
                'type' => $f->type,
                'required' => $f->required,
                'placeholder' => $f->placeholder,
                'options' => $f->options,
                'validation' => $f->validation,
                'sort_order' => $f->sort_order,
            ])->values(),
        ];
    }

    /** @return array<string, string[]> */
    private function validateFormBody(array $body): array
    {
        $errors = [];

        if (trim((string) ($body['name'] ?? '')) === '') {
            $errors['name'][] = 'Il nome è obbligatorio.';
        }

        $status = $body['status'] ?? 'draft';
        if (!in_array($status, self::STATUSES, true)) {
            $errors['status'][] = 'Stato non valido.';
        }

        $keys = [];
        foreach ((array) ($body['fields'] ?? []) as $i => $f) {
            $label = trim((string) ($f['label'] ?? ''));
            if ($label === '') {
                $errors["fields.$i.label"][] = 'La label è obbligatoria.';
            }
            $key = $this->slugKey((string) ($f['key'] ?? ''), (int) $i);
            if (in_array($key, $keys, true)) {
                $errors["fields.$i.key"][] = "Chiave duplicata: $key";
            }
            $keys[] = $key;
        }

        return $errors;
    }

    private function normalizeOrigins(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $clean = array_values(array_filter(array_map(
            fn ($o) => trim((string) $o),
            $value
        ), fn ($o) => $o !== ''));

        return $clean === [] ? null : $clean;
    }

    /**
     * Normalizza lo stile del form: { theme: {token:valore}, customCss: string }.
     * Tiene solo i token di tema conosciuti e ripulisce il CSS personalizzato.
     */
    private function normalizeStyle(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $allowedTokens = array_keys(Form::THEME_DEFAULTS);
        $theme = [];
        $rawTheme = is_array($value['theme'] ?? null) ? $value['theme'] : [];
        foreach ($allowedTokens as $token) {
            $v = $rawTheme[$token] ?? null;
            if (is_string($v) && trim($v) !== '') {
                // Niente caratteri che possano spezzare il CSS.
                $clean = trim(str_replace([';', '{', '}', '<', '>'], '', $v));
                // La larghezza accetta solo una lunghezza CSS semplice (px/% o "none").
                if ($token === 'maxWidth' && !preg_match('/^(\d{1,4}(px|%)|none)$/', $clean)) {
                    continue;
                }
                // La posizione accetta solo i tre allineamenti previsti.
                if ($token === 'align' && !in_array($clean, ['left', 'center', 'right'], true)) {
                    continue;
                }
                $theme[$token] = $clean;
            }
        }

        $customCss = is_string($value['customCss'] ?? null) ? trim($value['customCss']) : '';
        // Taglia a una lunghezza ragionevole per sicurezza.
        $customCss = mb_substr($customCss, 0, 20000);

        $out = [];
        if ($theme !== []) {
            $out['theme'] = $theme;
        }
        if ($customCss !== '') {
            $out['customCss'] = $customCss;
        }

        return $out === [] ? null : $out;
    }

    private function normalizeOptions(mixed $value): ?array
    {
        if (!is_array($value) || $value === []) {
            return null;
        }

        $out = [];
        foreach ($value as $opt) {
            if (is_array($opt)) {
                $v = trim((string) ($opt['value'] ?? $opt['label'] ?? ''));
                $l = trim((string) ($opt['label'] ?? $opt['value'] ?? ''));
            } else {
                $v = $l = trim((string) $opt);
            }
            if ($v !== '' || $l !== '') {
                $out[] = ['value' => $v, 'label' => $l];
            }
        }

        return $out === [] ? null : $out;
    }

    private function normalizeValidation(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $allowed = ['min', 'max', 'minLength', 'maxLength', 'regex'];
        $out = [];
        foreach ($allowed as $k) {
            if (isset($value[$k]) && $value[$k] !== '' && $value[$k] !== null) {
                $out[$k] = in_array($k, ['regex'], true) ? (string) $value[$k] : $value[$k];
            }
        }

        return $out === [] ? null : $out;
    }

    private function slugKey(string $key, int $index): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9_]+/', '_', $key) ?? '';
        $key = trim($key, '_');

        return $key !== '' ? $key : 'field_' . ($index + 1);
    }

    private function nullableStr(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
