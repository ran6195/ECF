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
 * @property string|null $redirect_url
 * @property array|null $allowed_origins
 * @property bool $recaptcha_enabled
 * @property string|null $recaptcha_site_key
 * @property string|null $recaptcha_secret_key
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
        'redirect_url',
        'allowed_origins',
        'style',
        'recaptcha_enabled',
        'recaptcha_site_key',
        'recaptcha_secret_key',
        'status',
    ];

    protected $casts = [
        'allowed_origins' => 'array',
        'style' => 'array',
        'recaptcha_enabled' => 'boolean',
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
        'align' => 'center',
        'submitLabel' => 'Invia',
    ];

    /**
     * Tema risolto: default + override del form.
     *
     * `submitBg` non ha un default fisso in THEME_DEFAULTS: se il form non lo
     * imposta esplicitamente, il bottone segue `primary` (comportamento identico
     * a prima dell'introduzione del colore custom del bottone). Un default fisso
     * "congelerebbe" silenziosamente il colore del bottone al primo salvataggio
     * di qualunque form esistente, sganciandolo dal `primary` reale in uso.
     *
     * @return array<string, string>
     */
    public function theme(): array
    {
        $style = $this->style ?? [];
        $theme = is_array($style['theme'] ?? null) ? $style['theme'] : [];

        $resolved = array_merge(self::THEME_DEFAULTS, array_filter(
            $theme,
            fn ($v) => is_string($v) && $v !== ''
        ));

        $resolved['submitBg'] = (is_string($theme['submitBg'] ?? null) && $theme['submitBg'] !== '')
            ? $theme['submitBg']
            : $resolved['primary'];

        return $resolved;
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

    /**
     * True se reCAPTCHA è attivo e configurato correttamente (chiavi presenti).
     */
    public function recaptchaActive(): bool
    {
        return (bool) $this->recaptcha_enabled
            && is_string($this->recaptcha_site_key) && $this->recaptcha_site_key !== ''
            && is_string($this->recaptcha_secret_key) && $this->recaptcha_secret_key !== '';
    }
}
