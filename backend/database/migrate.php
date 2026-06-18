<?php

declare(strict_types=1);

/**
 * Migrazione delle tabelle ECF tramite lo Schema Builder di Eloquent.
 * Idempotente: crea le tabelle solo se non esistono.
 *
 * Uso:  composer migrate     (oppure  php database/migrate.php)
 *       php database/migrate.php --fresh   → elimina e ricrea le tabelle
 */

use Dotenv\Dotenv;
use Ecf\Support\Database;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
if (file_exists($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

Database::boot();

$schema = Capsule::schema();
$fresh = in_array('--fresh', $argv ?? [], true);

if ($fresh) {
    echo "Modalità --fresh: elimino le tabelle esistenti...\n";
    $schema->disableForeignKeyConstraints();
    foreach (['submissions', 'form_fields', 'forms', 'users'] as $table) {
        $schema->dropIfExists($table);
    }
    $schema->enableForeignKeyConstraints();
}

// --- users ---
if (!$schema->hasTable('users')) {
    $schema->create('users', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('email', 190)->unique();
        $t->string('password_hash', 255);
        $t->timestamps();
    });
    echo "Creata tabella: users\n";
}

// --- forms ---
if (!$schema->hasTable('forms')) {
    $schema->create('forms', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->char('uuid', 36)->unique();
        $t->string('name', 190);
        $t->text('description')->nullable();
        $t->string('success_message', 255)->nullable();
        $t->json('allowed_origins')->nullable();
        $t->enum('status', ['draft', 'active', 'disabled'])->default('draft');
        $t->timestamps();
    });
    echo "Creata tabella: forms\n";
}

// --- form_fields ---
if (!$schema->hasTable('form_fields')) {
    $schema->create('form_fields', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('form_id');
        $t->string('key', 100);
        $t->string('label', 190);
        $t->enum('type', ['text', 'email', 'textarea', 'number', 'select', 'radio', 'checkbox', 'date', 'hidden']);
        $t->boolean('required')->default(false);
        $t->string('placeholder', 190)->nullable();
        $t->json('options')->nullable();
        $t->json('validation')->nullable();
        $t->integer('sort_order')->default(0);
        $t->timestamps();

        $t->unique(['form_id', 'key']);
        $t->foreign('form_id')->references('id')->on('forms')->onDelete('cascade');
    });
    echo "Creata tabella: form_fields\n";
}

// --- submissions ---
if (!$schema->hasTable('submissions')) {
    $schema->create('submissions', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('form_id');
        $t->json('payload');
        $t->string('source_url', 500)->nullable();
        $t->string('ip', 45)->nullable();
        $t->string('user_agent', 255)->nullable();
        $t->timestamp('created_at')->useCurrent();

        $t->foreign('form_id')->references('id')->on('forms')->onDelete('cascade');
    });
    echo "Creata tabella: submissions\n";
}

echo "Migrazione completata.\n";
