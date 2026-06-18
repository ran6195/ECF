<?php

declare(strict_types=1);

namespace Ecf\Support;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Inizializza Eloquent (Capsule) usando le variabili d'ambiente.
 * Va chiamata una sola volta nel bootstrap.
 */
final class Database
{
    private static ?Capsule $capsule = null;

    public static function boot(): Capsule
    {
        if (self::$capsule instanceof Capsule) {
            return self::$capsule;
        }

        $capsule = new Capsule();

        $capsule->addConnection([
            'driver' => 'mysql',
            'host' => Env::get('DB_HOST', '127.0.0.1'),
            'port' => Env::get('DB_PORT', '3306'),
            'database' => Env::get('DB_DATABASE', 'ECFDatabase'),
            'username' => Env::get('DB_USERNAME', 'root'),
            // La password vuota è gestita correttamente: '' è un valore valido.
            'password' => (string) Env::get('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
        ]);

        // Rende disponibili i metodi statici sui Model Eloquent.
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        self::$capsule = $capsule;

        return $capsule;
    }
}
