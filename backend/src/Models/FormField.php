<?php

declare(strict_types=1);

namespace Ecf\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $form_id
 * @property string $key
 * @property string $label
 * @property string $type
 * @property bool $required
 * @property string|null $placeholder
 * @property array|null $options
 * @property array|null $validation
 * @property int $sort_order
 */
class FormField extends Model
{
    protected $table = 'form_fields';

    protected $fillable = [
        'form_id',
        'key',
        'label',
        'type',
        'required',
        'placeholder',
        'options',
        'validation',
        'sort_order',
    ];

    protected $casts = [
        'required' => 'boolean',
        'options' => 'array',
        'validation' => 'array',
        'sort_order' => 'integer',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
