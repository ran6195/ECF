<?php

declare(strict_types=1);

namespace Ecf\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $form_id
 * @property array $payload
 * @property string|null $source_url
 * @property string|null $ip
 * @property string|null $user_agent
 */
class Submission extends Model
{
    protected $table = 'submissions';

    // Solo created_at (vedi migrazione): niente updated_at.
    public const UPDATED_AT = null;

    protected $fillable = [
        'form_id',
        'payload',
        'source_url',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
