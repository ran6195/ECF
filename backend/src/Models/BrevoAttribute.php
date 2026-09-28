<?php

declare(strict_types=1);

namespace Ecf\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cache per-form degli attributi contatto definiti su Brevo (solo category
 * "normal", le uniche scrivibili via API), ricaricata ad ogni test di
 * connessione riuscito dalla modale di configurazione admin. Serve a popolare
 * lo step di mappatura campi senza richiamare Brevo ogni volta.
 *
 * @property int $id
 * @property int $form_id
 * @property string $name
 * @property string|null $type
 */
class BrevoAttribute extends Model
{
    protected $table = 'brevo_attributes';

    protected $fillable = [
        'form_id',
        'name',
        'type',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
