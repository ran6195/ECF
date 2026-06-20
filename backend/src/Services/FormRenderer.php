<?php

declare(strict_types=1);

namespace Ecf\Services;

use Ecf\Models\Form;
use Ecf\Models\FormField;

/**
 * Genera l'HTML del form (fragment + <style> inline) a partire da form_fields.
 * Pensato per vivere dentro uno Shadow DOM: gli stili sono confinati.
 * Tutti i valori dinamici sono escapati (anti-XSS).
 */
final class FormRenderer
{
    /**
     * Nome del campo honeypot nascosto: i bot tendono a compilarlo, gli umani no.
     */
    public const HONEYPOT_KEY = '_ecf_hp';

    public function render(Form $form): string
    {
        $fieldsHtml = '';
        foreach ($form->fields as $field) {
            $fieldsHtml .= $this->renderField($field);
        }

        $honeypot = $this->renderHoneypot();
        $style = $this->style($form);
        $title = $this->e($form->name);
        $description = $form->description
            ? '<p class="ecf-desc">' . $this->e($form->description) . '</p>'
            : '';

        // data-ecf-uuid permette a embed.js di sapere a quale form appartiene.
        return <<<HTML
        {$style}
        <div class="ecf-form-wrap">
          <form class="ecf-form" data-ecf-uuid="{$this->e($form->uuid)}" novalidate>
            <h3 class="ecf-title">{$title}</h3>
            {$description}
            <div class="ecf-fields">
        {$fieldsHtml}
            </div>
            {$honeypot}
            <div class="ecf-actions">
              <button type="submit" class="ecf-submit">Invia</button>
            </div>
            <div class="ecf-message" role="status" aria-live="polite" hidden></div>
          </form>
        </div>
        HTML;
    }

    private function renderField(FormField $field): string
    {
        $id = 'ecf-' . $this->e($field->key);
        $label = $this->e($field->label);
        $required = $field->required ? ' <span class="ecf-req" aria-hidden="true">*</span>' : '';
        $requiredAttr = $field->required ? ' required' : '';

        $control = match ($field->type) {
            'textarea' => $this->textarea($field, $id, $requiredAttr),
            'select' => $this->select($field, $id, $requiredAttr),
            'radio' => $this->radioGroup($field, $id),
            'checkbox' => $this->checkboxGroup($field, $id),
            'hidden' => $this->hidden($field),
            default => $this->input($field, $id, $requiredAttr),
        };

        // I campi hidden non hanno label/wrapper visibile.
        if ($field->type === 'hidden') {
            return $control . "\n";
        }

        // I gruppi radio/checkbox usano <fieldset> con <legend> al posto di <label>.
        if (in_array($field->type, ['radio', 'checkbox'], true)) {
            return <<<HTML
                  <fieldset class="ecf-field ecf-field-{$this->e($field->type)}">
                    <legend class="ecf-label">{$label}{$required}</legend>
                    {$control}
                  </fieldset>

            HTML;
        }

        return <<<HTML
                  <div class="ecf-field ecf-field-{$this->e($field->type)}">
                    <label class="ecf-label" for="{$id}">{$label}{$required}</label>
                    {$control}
                  </div>

        HTML;
    }

    private function input(FormField $field, string $id, string $requiredAttr): string
    {
        $type = in_array($field->type, ['text', 'email', 'number', 'date'], true) ? $field->type : 'text';
        $placeholder = $field->placeholder ? ' placeholder="' . $this->e($field->placeholder) . '"' : '';
        $attrs = $this->validationAttrs($field);

        return sprintf(
            '<input class="ecf-input" type="%s" id="%s" name="%s"%s%s%s>',
            $this->e($type),
            $id,
            $this->e($field->key),
            $placeholder,
            $requiredAttr,
            $attrs
        );
    }

    private function textarea(FormField $field, string $id, string $requiredAttr): string
    {
        $placeholder = $field->placeholder ? ' placeholder="' . $this->e($field->placeholder) . '"' : '';
        $attrs = $this->validationAttrs($field);

        return sprintf(
            '<textarea class="ecf-input ecf-textarea" id="%s" name="%s" rows="4"%s%s%s></textarea>',
            $id,
            $this->e($field->key),
            $placeholder,
            $requiredAttr,
            $attrs
        );
    }

    private function select(FormField $field, string $id, string $requiredAttr): string
    {
        $opts = '<option value="">— Seleziona —</option>';
        foreach ($this->options($field) as [$value, $label]) {
            $opts .= sprintf('<option value="%s">%s</option>', $this->e($value), $this->e($label));
        }

        return sprintf(
            '<select class="ecf-input ecf-select" id="%s" name="%s"%s>%s</select>',
            $id,
            $this->e($field->key),
            $requiredAttr,
            $opts
        );
    }

    private function radioGroup(FormField $field, string $id): string
    {
        $html = '<div class="ecf-options">';
        $i = 0;
        foreach ($this->options($field) as [$value, $label]) {
            $optId = $id . '-' . $i++;
            $html .= sprintf(
                '<label class="ecf-option" for="%s"><input type="radio" id="%s" name="%s" value="%s"%s> <span>%s</span></label>',
                $optId,
                $optId,
                $this->e($field->key),
                $this->e($value),
                $field->required ? ' required' : '',
                $this->e($label)
            );
        }

        return $html . '</div>';
    }

    private function checkboxGroup(FormField $field, string $id): string
    {
        $options = $this->options($field);

        // Senza opzioni → singolo checkbox booleano.
        if ($options === []) {
            return sprintf(
                '<div class="ecf-options"><label class="ecf-option" for="%s"><input type="checkbox" id="%s" name="%s" value="1"> <span>%s</span></label></div>',
                $id,
                $id,
                $this->e($field->key),
                $this->e($field->label)
            );
        }

        // Con opzioni → name come array per la selezione multipla.
        $html = '<div class="ecf-options">';
        $i = 0;
        foreach ($options as [$value, $label]) {
            $optId = $id . '-' . $i++;
            $html .= sprintf(
                '<label class="ecf-option" for="%s"><input type="checkbox" id="%s" name="%s[]" value="%s"> <span>%s</span></label>',
                $optId,
                $optId,
                $this->e($field->key),
                $this->e($value),
                $this->e($label)
            );
        }

        return $html . '</div>';
    }

    private function hidden(FormField $field): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            $this->e($field->key),
            $this->e((string) ($field->placeholder ?? ''))
        );
    }

    private function renderHoneypot(): string
    {
        // Nascosto via CSS e fuori dal flusso; aria-hidden e tabindex per gli umani.
        return sprintf(
            '<div class="ecf-hp" aria-hidden="true"><label>Lascia vuoto questo campo<input type="text" name="%s" tabindex="-1" autocomplete="off"></label></div>',
            self::HONEYPOT_KEY
        );
    }

    /**
     * @return array<int, array{0:string,1:string}> coppie [value, label]
     */
    private function options(FormField $field): array
    {
        $out = [];
        foreach ($field->options ?? [] as $opt) {
            if (is_array($opt)) {
                $value = (string) ($opt['value'] ?? $opt['label'] ?? '');
                $label = (string) ($opt['label'] ?? $opt['value'] ?? '');
            } else {
                $value = $label = (string) $opt;
            }
            $out[] = [$value, $label];
        }

        return $out;
    }

    private function validationAttrs(FormField $field): string
    {
        $rules = $field->validation ?? [];
        $attrs = '';

        if (isset($rules['min'])) {
            $attrs .= ' min="' . $this->e((string) $rules['min']) . '"';
        }
        if (isset($rules['max'])) {
            $attrs .= ' max="' . $this->e((string) $rules['max']) . '"';
        }
        if (isset($rules['maxLength'])) {
            $attrs .= ' maxlength="' . $this->e((string) $rules['maxLength']) . '"';
        }
        if (isset($rules['minLength'])) {
            $attrs .= ' minlength="' . $this->e((string) $rules['minLength']) . '"';
        }

        return $attrs;
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function style(Form $form): string
    {
        $vars = $this->themeVars($form->theme());
        $custom = $this->sanitizeCustomCss($form->customCss());
        $customBlock = $custom !== '' ? "\n          /* CSS personalizzato del form */\n          {$custom}" : '';

        // Il CSS base usa variabili (--ecf-*) sovrascrivibili dal tema del form.
        return <<<CSS
        <style>
          :host { all: initial; display: block; width: 100%; }
          .ecf-form-wrap {
        {$vars}
            color: var(--ecf-text); font-family: var(--ecf-font); line-height: 1.5; box-sizing: border-box;
          }
          .ecf-form-wrap *, .ecf-form-wrap *::before, .ecf-form-wrap *::after { box-sizing: inherit; }
          .ecf-form { width: 100%; max-width: var(--ecf-max-width); margin: 0; padding: 24px; border: 1px solid var(--ecf-border); border-radius: calc(var(--ecf-radius) + 4px); background: var(--ecf-bg); }
          .ecf-title { margin: 0 0 4px; font-size: 1.25rem; font-weight: 700; color: var(--ecf-text); }
          .ecf-desc { margin: 0 0 16px; color: #6b7280; font-size: .925rem; }
          .ecf-fields { display: flex; flex-direction: column; gap: 16px; }
          .ecf-field { display: flex; flex-direction: column; gap: 6px; border: 0; padding: 0; margin: 0; }
          .ecf-label { font-weight: 600; font-size: .9rem; color: var(--ecf-text); }
          .ecf-req { color: #dc2626; }
          .ecf-input { width: 100%; padding: 10px 12px; font-size: .95rem; border: 1px solid var(--ecf-border); border-radius: var(--ecf-radius); background: var(--ecf-bg); color: var(--ecf-text); transition: border-color .15s, box-shadow .15s; }
          .ecf-input:focus { outline: none; border-color: var(--ecf-primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--ecf-primary) 22%, transparent); }
          .ecf-textarea { resize: vertical; min-height: 96px; }
          .ecf-options { display: flex; flex-direction: column; gap: 8px; }
          .ecf-option { display: flex; align-items: center; gap: 8px; font-weight: 400; font-size: .95rem; cursor: pointer; color: var(--ecf-text); }
          .ecf-option input { margin: 0; accent-color: var(--ecf-primary); }
          .ecf-actions { margin-top: 20px; }
          .ecf-submit { appearance: none; border: 0; cursor: pointer; background: var(--ecf-primary); color: var(--ecf-btn-text); font-size: .95rem; font-weight: 600; padding: 11px 22px; border-radius: var(--ecf-radius); transition: background .15s; }
          .ecf-submit:hover { background: var(--ecf-primary-hover); }
          .ecf-submit:disabled { opacity: .6; cursor: default; }
          .ecf-hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
          .ecf-message { margin-top: 16px; padding: 12px 14px; border-radius: var(--ecf-radius); font-size: .9rem; }
          .ecf-message[hidden] { display: none; }
          .ecf-message.is-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
          .ecf-message.is-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
          .ecf-input.is-invalid { border-color: #dc2626; }
          .ecf-field-error { color: #dc2626; font-size: .82rem; margin: 0; }{$customBlock}
        </style>
        CSS;
    }

    /**
     * Genera le dichiarazioni delle variabili CSS dal tema.
     */
    private function themeVars(array $theme): string
    {
        $map = [
            '--ecf-primary' => $theme['primary'],
            '--ecf-primary-hover' => $theme['primaryHover'],
            '--ecf-text' => $theme['text'],
            '--ecf-bg' => $theme['background'],
            '--ecf-border' => $theme['border'],
            '--ecf-radius' => $theme['radius'],
            '--ecf-font' => $theme['fontFamily'],
            '--ecf-btn-text' => $theme['buttonText'],
            '--ecf-max-width' => $theme['maxWidth'],
        ];

        $lines = [];
        foreach ($map as $name => $value) {
            // I valori del tema sono colori/dimensioni/font: niente ';' o '}' che spezzino il CSS.
            $clean = str_replace([';', '}', '{', '<'], '', (string) $value);
            $lines[] = "    {$name}: {$clean};";
        }

        return implode("\n", $lines);
    }

    /**
     * Impedisce la chiusura anticipata del tag <style> dal CSS personalizzato.
     */
    private function sanitizeCustomCss(string $css): string
    {
        if (trim($css) === '') {
            return '';
        }

        // Rimuove qualsiasi tentativo di chiudere il blocco <style> o iniettare tag.
        $css = preg_replace('/<\s*\/?\s*(style|script)/i', '', $css) ?? '';

        return trim($css);
    }
}
