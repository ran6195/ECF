<?php

declare(strict_types=1);

namespace Ecf\Services;

use Ecf\Models\Form;
use Ecf\Models\FormField;

/**
 * Validazione autoritativa lato server: applica le regole definite in
 * form_fields al payload ricevuto. Ritorna i valori puliti e gli errori per campo.
 */
final class FormValidator
{
    /** @var array<string, string[]> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $clean = [];

    /**
     * @param array<string, mixed> $input valori grezzi inviati dal client
     * @return bool true se valido
     */
    public function validate(Form $form, array $input): bool
    {
        $this->errors = [];
        $this->clean = [];

        foreach ($form->fields as $field) {
            $this->validateField($field, $input[$field->key] ?? null);
        }

        return $this->errors === [];
    }

    /** @return array<string, string[]> */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Valori puliti (solo i campi definiti nel form): da salvare nel payload.
     * @return array<string, mixed>
     */
    public function clean(): array
    {
        return $this->clean;
    }

    private function validateField(FormField $field, mixed $raw): void
    {
        $key = $field->key;

        // I checkbox possono arrivare come array (selezione multipla) o bool.
        if ($field->type === 'checkbox') {
            $this->validateCheckbox($field, $raw);
            return;
        }

        $value = is_string($raw) ? trim($raw) : $raw;
        $isEmpty = $value === null || $value === '';

        if ($field->required && $isEmpty) {
            $this->addError($key, sprintf('Il campo "%s" è obbligatorio.', $field->label));
            return;
        }

        if ($isEmpty) {
            // Campo facoltativo vuoto: salvo stringa vuota e non valido oltre.
            $this->clean[$key] = '';
            return;
        }

        $value = (string) $value;

        switch ($field->type) {
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($key, sprintf('"%s" non è un indirizzo email valido.', $field->label));
                }
                break;

            case 'number':
                if (!is_numeric($value)) {
                    $this->addError($key, sprintf('"%s" deve essere un numero.', $field->label));
                } else {
                    $this->validateNumericRange($field, (float) $value);
                }
                break;

            case 'date':
                if (!$this->isValidDate($value)) {
                    $this->addError($key, sprintf('"%s" non è una data valida (YYYY-MM-DD).', $field->label));
                }
                break;

            case 'select':
            case 'radio':
                if (!$this->isAllowedOption($field, $value)) {
                    $this->addError($key, sprintf('Valore non valido per "%s".', $field->label));
                }
                break;
        }

        // Regole generiche da "validation".
        $this->validateConstraints($field, $value);

        $this->clean[$key] = $value;
    }

    private function validateCheckbox(FormField $field, mixed $raw): void
    {
        $values = [];

        if (is_array($raw)) {
            $values = array_map('strval', $raw);
        } elseif ($raw !== null && $raw !== '' && $raw !== false) {
            $values = [(string) $raw];
        }

        if ($field->required && $values === []) {
            $this->addError($field->key, sprintf('Devi selezionare "%s".', $field->label));
            return;
        }

        // Se ci sono opzioni definite, ogni valore deve appartenere alla lista.
        if (!empty($field->options)) {
            foreach ($values as $v) {
                if (!$this->isAllowedOption($field, $v)) {
                    $this->addError($field->key, sprintf('Valore non valido per "%s".', $field->label));
                    break;
                }
            }
        }

        $this->clean[$field->key] = $values;
    }

    private function validateNumericRange(FormField $field, float $value): void
    {
        $rules = $field->validation ?? [];

        if (isset($rules['min']) && $value < (float) $rules['min']) {
            $this->addError($field->key, sprintf('"%s" deve essere ≥ %s.', $field->label, $rules['min']));
        }
        if (isset($rules['max']) && $value > (float) $rules['max']) {
            $this->addError($field->key, sprintf('"%s" deve essere ≤ %s.', $field->label, $rules['max']));
        }
    }

    private function validateConstraints(FormField $field, string $value): void
    {
        $rules = $field->validation ?? [];

        if (isset($rules['maxLength']) && mb_strlen($value) > (int) $rules['maxLength']) {
            $this->addError($field->key, sprintf('"%s" supera i %d caratteri.', $field->label, (int) $rules['maxLength']));
        }

        if (isset($rules['minLength']) && mb_strlen($value) < (int) $rules['minLength']) {
            $this->addError($field->key, sprintf('"%s" deve avere almeno %d caratteri.', $field->label, (int) $rules['minLength']));
        }

        if (!empty($rules['regex'])) {
            // Il pattern è fornito dall'admin; lo deli­mito in modo sicuro.
            $pattern = '/' . str_replace('/', '\/', $rules['regex']) . '/u';
            if (@preg_match($pattern, $value) !== 1) {
                $this->addError($field->key, sprintf('"%s" non rispetta il formato richiesto.', $field->label));
            }
        }
    }

    private function isAllowedOption(FormField $field, string $value): bool
    {
        $options = $field->options ?? [];
        if ($options === []) {
            return true; // nessun vincolo definito
        }

        foreach ($options as $opt) {
            $optValue = is_array($opt) ? ($opt['value'] ?? null) : $opt;
            if ((string) $optValue === $value) {
                return true;
            }
        }

        return false;
    }

    private function isValidDate(string $value): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $value);

        return $d !== false && $d->format('Y-m-d') === $value;
    }

    private function addError(string $key, string $message): void
    {
        $this->errors[$key][] = $message;
    }
}
