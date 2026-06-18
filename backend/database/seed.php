<?php

declare(strict_types=1);

/**
 * Seed dei dati iniziali: un utente admin (da .env) e un form "Contatti" attivo.
 * Idempotente: usa updateOrCreate / firstOrCreate.
 *
 * Uso:  composer seed   (oppure  php database/seed.php)
 */

use Dotenv\Dotenv;
use Ecf\Models\Form;
use Ecf\Models\FormField;
use Ecf\Models\User;
use Ecf\Support\Database;
use Ecf\Support\Env;

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
if (file_exists($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

Database::boot();

// --- Admin ---
$email = Env::get('ADMIN_EMAIL', 'admin@edysma.test');
$password = Env::get('ADMIN_PASSWORD', 'password');

$user = User::updateOrCreate(
    ['email' => $email],
    ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]
);
echo "Admin pronto: {$user->email}\n";

// --- Form "Contatti" ---
$form = Form::firstOrCreate(
    ['name' => 'Contatti'],
    [
        'uuid' => uuid_v4(),
        'description' => 'Modulo di contatto di esempio.',
        'success_message' => 'Grazie! Ti risponderemo al più presto.',
        'allowed_origins' => null, // modalità aperta (per i test)
        'status' => 'active',
    ]
);

if ($form->fields()->count() === 0) {
    $fields = [
        ['key' => 'nome', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'placeholder' => 'Il tuo nome', 'sort_order' => 0],
        ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'placeholder' => 'tu@esempio.it', 'sort_order' => 1],
        ['key' => 'messaggio', 'label' => 'Messaggio', 'type' => 'textarea', 'required' => true, 'placeholder' => 'Scrivi qui...', 'sort_order' => 2],
    ];
    foreach ($fields as $f) {
        $form->fields()->create($f);
    }
    echo "Campi del form 'Contatti' creati.\n";
}

echo "Form 'Contatti' pronto.\n";
echo "UUID pubblico (per lo snippet): {$form->uuid}\n";

/**
 * UUID v4 standalone per il seed.
 */
function uuid_v4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
