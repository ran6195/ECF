<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Ecf\Controllers\AuthController;
use Ecf\Controllers\EmbedController;
use Ecf\Controllers\FormController;
use Ecf\Controllers\SubmissionController;
use Ecf\Middleware\AdminCorsMiddleware;
use Ecf\Middleware\AuthMiddleware;
use Ecf\Middleware\CorsOriginMiddleware;
use Ecf\Support\Database;
use Ecf\Support\Env;
use Slim\Factory\AppFactory;

/**
 * Bootstrap dell'applicazione: carica l'ambiente, avvia Eloquent, costruisce
 * l'app Slim con middleware e rotte. Ritorna l'istanza pronta per ->run().
 */
return (static function () {
    $root = dirname(__DIR__);

    // .env (silent: in produzione le var possono arrivare dall'ambiente reale).
    if (file_exists($root . '/.env')) {
        Dotenv::createImmutable($root)->safeLoad();
    }

    // Avvia la connessione Eloquent globale.
    Database::boot();

    $app = AppFactory::create();

    // --- Middleware globali (l'ordine conta: l'ultimo aggiunto è il più esterno) ---
    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();

    $displayErrors = Env::bool('APP_DEBUG', false);
    $app->addErrorMiddleware($displayErrors, true, true);

    // --- Rotte pubbliche di embed (CORS per-form) ---
    $app->group('/api/embed/{uuid}', function ($group) {
        $group->map(['GET', 'OPTIONS'], '/render', [EmbedController::class, 'render']);
        $group->map(['POST', 'OPTIONS'], '/submit', [EmbedController::class, 'submit']);
    })->add(new CorsOriginMiddleware());

    // --- Auth ---
    $app->map(['POST', 'OPTIONS'], '/api/auth/login', [AuthController::class, 'login'])
        ->add(new AdminCorsMiddleware());

    // --- Rotte admin (JWT + CORS permissivo) ---
    $app->group('/api/forms', function ($group) {
        $group->get('', [FormController::class, 'index']);
        $group->post('', [FormController::class, 'store']);
        $group->get('/{id:[0-9]+}', [FormController::class, 'show']);
        $group->put('/{id:[0-9]+}', [FormController::class, 'update']);
        $group->delete('/{id:[0-9]+}', [FormController::class, 'destroy']);
        $group->get('/{id:[0-9]+}/submissions', [SubmissionController::class, 'index']);
        $group->get('/{id:[0-9]+}/submissions/export', [SubmissionController::class, 'export']);
    })->add(new AuthMiddleware())->add(new AdminCorsMiddleware());

    // Preflight OPTIONS per le rotte admin (non coperte dai metodi sopra).
    $app->options('/{routes:.+}', fn ($request, $response) => $response)
        ->add(new AdminCorsMiddleware());

    // Healthcheck semplice.
    $app->get('/health', function ($request, $response) {
        $response->getBody()->write(json_encode(['success' => true, 'service' => 'ecf']));
        return $response->withHeader('Content-Type', 'application/json');
    });

    return $app;
})();
