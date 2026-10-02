<?php

declare(strict_types=1);

namespace Ecf\Services;

use Ecf\Models\Form;
use Ecf\Models\FormField;
use Ecf\Support\GoogleFonts;

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

        $theme = $form->theme();
        $honeypot = $this->renderHoneypot();
        $recaptcha = $this->renderRecaptcha($form);
        // Il sitekey viaggia come attributo sul <form> (leggibile da embed.js
        // attraversando lo shadow root senza problemi): il widget reCAPTCHA vero
        // e proprio NON può vivere dentro lo shadow DOM (vedi renderRecaptcha()),
        // quindi embed.js lo crea nel DOM principale e lo assegna allo <slot> qui sotto.
        $recaptchaAttr = $form->recaptchaActive()
            ? ' data-recaptcha-sitekey="' . $this->e((string) $form->recaptcha_site_key) . '"'
            : '';
        $style = $this->style($form, $theme);
        $title = $this->e($form->name);
        $description = $form->description
            ? '<p class="ecf-desc">' . $this->e($form->description) . '</p>'
            : '';
        $submitLabel = $this->e($theme['submitLabel'] ?: 'Invia');

        // data-ecf-uuid permette a embed.js di sapere a quale form appartiene.
        return <<<HTML
        {$style}
        <div class="ecf-form-wrap">
          <form class="ecf-form" data-ecf-uuid="{$this->e($form->uuid)}"{$recaptchaAttr} novalidate>
            <h3 class="ecf-title">{$title}</h3>
            {$description}
            <div class="ecf-fields">
        {$fieldsHtml}
            </div>
            {$honeypot}
            {$recaptcha}
            <div class="ecf-actions">
              <button type="submit" class="ecf-submit">{$submitLabel}</button>
            </div>
            <div class="ecf-message" role="status" aria-live="polite" hidden></div>
          </form>
        </div>
        HTML;
    }

    private function renderField(FormField $field): string
    {
        // La checkbox privacy ha un markup dedicato (label composta da testo + link):
        // il wrapper generico sotto produrrebbe un <label> esterno duplicato/non
        // valido attorno al <label><input></label> interno.
        if ($field->type === 'privacy_consent') {
            return $this->renderPrivacyConsent($field) . "\n";
        }

        $id = 'ecf-' . $this->e($field->key);
        $label = $this->e($field->label);
        $required = $field->required ? ' <span class="ecf-req" aria-hidden="true">*</span>' : '';
        $requiredAttr = $field->required ? ' required' : '';

        $control = match ($field->type) {
            'textarea' => $this->textarea($field, $id, $requiredAttr),
            'select' => $this->select($field, $id, $requiredAttr),
            'time_slot' => $this->select($field, $id, $requiredAttr, $this->timeSlotOptions($field)),
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

        // "Escludi weekend" non ha un equivalente HTML nativo visibile prima della
        // selezione (a differenza di "escludi passato", che il browser disabilita
        // nativamente nel picker via l'attributo min): un hint testuale statico
        // avvisa l'utente in anticipo. embed.js resta comunque la rete di sicurezza
        // reattiva se l'utente seleziona un weekend nonostante l'avviso.
        $hint = '';
        if ($field->type === 'date' && !empty(($field->validation ?? [])['exclude_weekends'])) {
            $hint = '<p class="ecf-field-hint">Sabato e domenica non disponibili.</p>';
        }

        return <<<HTML
                  <div class="ecf-field ecf-field-{$this->e($field->type)}">
                    <label class="ecf-label" for="{$id}">{$label}{$required}</label>
                    {$control}
                    {$hint}
                  </div>

        HTML;
    }

    private function input(FormField $field, string $id, string $requiredAttr): string
    {
        $type = in_array($field->type, ['text', 'email', 'number', 'date'], true) ? $field->type : 'text';
        $placeholder = $field->placeholder ? ' placeholder="' . $this->e($field->placeholder) . '"' : '';
        $attrs = $this->validationAttrs($field);
        $dateAttrs = $type === 'date' ? $this->dateConstraintAttrs($field) : '';

        return sprintf(
            '<input class="ecf-input" type="%s" id="%s" name="%s"%s%s%s%s>',
            $this->e($type),
            $id,
            $this->e($field->key),
            $placeholder,
            $requiredAttr,
            $attrs,
            $dateAttrs
        );
    }

    /**
     * "Escludi date passate" usa l'attributo nativo `min` (il picker del browser
     * disabilita/ingrigisce le date precedenti). "Escludi weekend" non ha un
     * equivalente HTML nativo: il data-attribute viene letto da embed.js, che
     * annulla lato client la selezione di sabato/domenica (vedi wireDateConstraints).
     * In entrambi i casi FormValidator resta l'autorità che rifiuta il valore.
     */
    private function dateConstraintAttrs(FormField $field): string
    {
        $rules = $field->validation ?? [];
        $attrs = '';

        if (!empty($rules['exclude_past'])) {
            $attrs .= ' min="' . date('Y-m-d') . '"';
        }
        if (!empty($rules['exclude_weekends'])) {
            $attrs .= ' data-ecf-exclude-weekends="1"';
        }

        return $attrs;
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

    /**
     * @param array<int, array{0:string,1:string}>|null $opts coppie [value,label]; se
     *        omesso vengono lette da $field->options (comportamento per il tipo "select").
     */
    private function select(FormField $field, string $id, string $requiredAttr, ?array $opts = null): string
    {
        $rules = $field->validation ?? [];
        $placeholderLabel = (string) ($rules['placeholder_label'] ?? '— Seleziona —');
        $optionsHtml = '<option value="">' . $this->e($placeholderLabel) . '</option>';
        foreach ($opts ?? $this->options($field) as [$value, $label]) {
            $optionsHtml .= sprintf('<option value="%s">%s</option>', $this->e($value), $this->e($label));
        }

        return sprintf(
            '<select class="ecf-input ecf-select" id="%s" name="%s"%s>%s</select>',
            $id,
            $this->e($field->key),
            $requiredAttr,
            $optionsHtml
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

    private function renderPrivacyConsent(FormField $field): string
    {
        $id = 'ecf-' . $this->e($field->key);
        $label = $this->e($field->label);
        $rules = $field->validation ?? [];
        $linkUrl = $this->e((string) ($rules['link_url'] ?? ''));
        $linkText = $this->e((string) ($rules['link_text'] ?? 'informativa sulla privacy'));

        return sprintf(
            '<div class="ecf-field ecf-field-privacy_consent"><label class="ecf-option" for="%s">'
            . '<input type="checkbox" id="%s" name="%s" value="1" required> '
            . '<span>%s <a href="%s" target="_blank" rel="noopener noreferrer">%s</a></span></label></div>',
            $id,
            $id,
            $this->e($field->key),
            $label,
            $linkUrl,
            $linkText
        );
    }

    /**
     * Google reCAPTCHA non è progettato per funzionare dentro uno Shadow DOM: le
     * sue iframe interne usano window.frames per nome e vanno in conflitto con
     * l'incapsulamento dello shadow root (SecurityError "Blocked a frame..."),
     * a prescindere da come viene renderizzato. Il workaround standard è uno
     * <slot>: il widget vero e proprio viene creato da embed.js nel DOM
     * principale (light DOM) e proiettato qui visivamente tramite lo slot.
     */
    private function renderRecaptcha(Form $form): string
    {
        if (!$form->recaptchaActive()) {
            return '';
        }

        return '<div class="ecf-recaptcha"><slot name="ecf-recaptcha"></slot></div>';
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

    /**
     * @return array<int, array{0:string,1:string}> coppie [value, label] generate
     *         da ora inizio/fine e step (field->validation), non da $field->options.
     */
    private function timeSlotOptions(FormField $field): array
    {
        $rules = $field->validation ?? [];
        $slots = TimeSlotGenerator::generate($rules['start_time'] ?? null, $rules['end_time'] ?? null, $rules['step'] ?? null);

        return array_map(static fn (string $slot) => [$slot, $slot], $slots);
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

    private function style(Form $form, array $theme): string
    {
        $vars = $this->themeVars($theme);
        $custom = $this->sanitizeCustomCss($form->customCss());
        $customBlock = $custom !== '' ? "\n          /* CSS personalizzato del form */\n          {$custom}" : '';
        $fontLinks = $this->googleFontLinks($theme);

        // Il CSS base usa variabili (--ecf-*) sovrascrivibili dal tema del form.
        return <<<CSS
        {$fontLinks}<style>
          :host { all: initial; display: block; width: 100%; }
          .ecf-form-wrap {
        {$vars}
            color: var(--ecf-text); font-family: var(--ecf-font); line-height: 1.5; box-sizing: border-box;
          }
          .ecf-form-wrap *, .ecf-form-wrap *::before, .ecf-form-wrap *::after { box-sizing: inherit; }
          .ecf-form { width: 100%; max-width: var(--ecf-max-width); margin: var(--ecf-form-margin); padding: 24px; border: 1px solid var(--ecf-border); border-radius: calc(var(--ecf-radius) + 4px); background: var(--ecf-bg); }
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
          .ecf-submit { appearance: none; border: 0; cursor: pointer; background: var(--ecf-submit-bg); color: var(--ecf-btn-text); font-size: .95rem; font-weight: 600; padding: 11px 22px; border-radius: var(--ecf-radius); transition: filter .15s; }
          .ecf-submit:hover { filter: brightness(0.92); }
          .ecf-submit:disabled { opacity: .6; cursor: default; }
          .ecf-hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
          .ecf-recaptcha { margin-top: 16px; }
          .ecf-message { margin-top: 16px; padding: 12px 14px; border-radius: var(--ecf-radius); font-size: .9rem; }
          .ecf-message[hidden] { display: none; }
          .ecf-message.is-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
          .ecf-message.is-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
          .ecf-input.is-invalid { border-color: #dc2626; }
          .ecf-field-error { color: #dc2626; font-size: .82rem; margin: 0; }
          .ecf-field-hint { color: #6b7280; font-size: .82rem; margin: 2px 0 0; }{$customBlock}
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
            '--ecf-font' => $this->fontFamilyValue($theme),
            '--ecf-btn-text' => $theme['buttonText'],
            '--ecf-submit-bg' => $theme['submitBg'] ?? $theme['primary'],
            '--ecf-max-width' => $theme['maxWidth'],
            '--ecf-form-margin' => $this->alignToMargin($theme['align'] ?? 'center'),
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
     * Font effettivo del form: se è impostato un font Google valido ha sempre
     * la precedenza sul fontFamily "di sistema" salvato (coerenza: il client
     * non deve tenere i due valori perfettamente sincronizzati, decide il server).
     */
    private function fontFamilyValue(array $theme): string
    {
        $googleFont = $theme['googleFont'] ?? null;
        if (is_string($googleFont) && $googleFont !== '' && GoogleFonts::isValid($googleFont)) {
            return GoogleFonts::cssStack($googleFont);
        }

        return (string) $theme['fontFamily'];
    }

    /**
     * <link> per caricare il font Google scelto (preconnect + foglio di stile
     * CSS2), da anteporre al <style> del form. Stringa vuota se non impostato
     * o non valido: stesso trust boundary di normalizeStyle() lato controller,
     * controllato di nuovo qui perché FormRenderer non si fida ciecamente di
     * ciò che legge da Form::theme() (difesa in profondità).
     */
    private function googleFontLinks(array $theme): string
    {
        $name = $theme['googleFont'] ?? null;
        if (!is_string($name) || $name === '' || !GoogleFonts::isValid($name)) {
            return '';
        }

        $url = $this->e(GoogleFonts::cssUrl($name));

        return <<<HTML
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="{$url}">

        HTML;
    }

    /**
     * Converte l'allineamento del form (left|center|right) nel margine orizzontale.
     * Ha effetto solo quando il form è più stretto del contenitore (max-width attivo).
     */
    private function alignToMargin(string $align): string
    {
        return match ($align) {
            'center' => '0 auto',
            'right' => '0 0 0 auto',
            default => '0 auto 0 0',
        };
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
