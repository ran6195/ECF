<?php

declare(strict_types=1);

namespace Ecf\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $description
 * @property string|null $success_message
 * @property array|null $allowed_origins
 * @property string $status
 */
class Form extends Model
{
    protected $table = 'forms';

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'success_message',
        'allowed_origins',
        'status',
    ];

    protected $casts = [
        'allowed_origins' => 'array',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('sort_order')->orderBy('id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * True se il form accetta richieste da qualunque origine (whitelist vuota).
     */
    public function isOpenOrigin(): bool
    {
        return empty($this->allowed_origins);
    }
}
