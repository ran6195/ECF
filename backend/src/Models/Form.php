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
        'style',
        'status',
    ];

    protected $casts = [
        'allowed_origins' => 'array',
        'style' => 'array',
    ];

    /**
     * Valori di default del tema (usati quando il form non li sovrascrive).
     * @var array<string, string>
     */
    public const THEME_DEFAULTS = [
        'primary' => '#4f46e5',
        'primaryHover' => '#4338ca',
        'text' => '#1f2937',
        'background' => '#ffffff',
        'border' => '#e5e7eb',
        'radius' => '8px',
        'fontFamily' => 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
        'buttonText' => '#ffffff',
        'maxWidth' => '720px',
    ];

    /**
     * Tema risolto: default + override del form.
     * @return array<string, string>
     */
    public function theme(): array
    {
        $style = $this->style ?? [];
        $theme = is_array($style['theme'] ?? null) ? $style['theme'] : [];

        return array_merge(self::THEME_DEFAULTS, array_filter(
            $theme,
            fn ($v) => is_string($v) && $v !== ''
        ));
    }

    public function customCss(): string
    {
        $style = $this->style ?? [];

        return is_string($style['customCss'] ?? null) ? $style['customCss'] : '';
    }

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
