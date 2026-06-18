<?php

declare(strict_types=1);

namespace Ecf\Tests;

use Ecf\Models\Form;
use Ecf\Models\FormField;
use Illuminate\Database\Eloquent\Collection;

/**
 * Helper per costruire un Form con i suoi campi in memoria (senza DB),
 * sfruibile da FormValidator e FormRenderer nei test.
 */
final class Support
{
    /**
     * @param array<int, array<string, mixed>> $fields
     */
    public static function makeForm(array $fields, array $attrs = []): Form
    {
        $form = new Form(array_merge([
            'uuid' => 'test-uuid-0000',
            'name' => 'Form di test',
            'status' => 'active',
        ], $attrs));

        $models = new Collection();
        foreach ($fields as $i => $f) {
            $field = new FormField(array_merge([
                'type' => 'text',
                'required' => false,
                'sort_order' => $i,
            ], $f));
            $models->push($field);
        }

        // Imposta la relazione "fields" senza toccare il DB.
        $form->setRelation('fields', $models);

        return $form;
    }
}
