<?php

declare(strict_types=1);

/**
 * Genera lo script SQL (schema + seed) da importare nel DB di collaudo via
 * phpMyAdmin, senza bisogno di shell sul server.
 *
 * Lo schema rispecchia backend/database/migrate.php; il seed rispecchia
 * backend/database/seed.php (1 admin + form "Contatti" attivo).
 *
 * Uso:  php deploy/export-sql.php > deploy/database.sql
 *
 * Legge ADMIN_EMAIL/ADMIN_PASSWORD da backend/.env.production se presente,
 * altrimenti da backend/.env, con fallback ai default.
 */

require __DIR__ . '/../backend/vendor/autoload.php';

use Dotenv\Dotenv;

$backend = dirname(__DIR__) . '/backend';
$envFile = file_exists($backend . '/.env.production') ? '.env.production' : '.env';
if (file_exists($backend . '/' . $envFile)) {
    Dotenv::createImmutable($backend, $envFile)->safeLoad();
}

$adminEmail = $_ENV['ADMIN_EMAIL'] ?? 'admin@edysma.net';
$adminPassword = $_ENV['ADMIN_PASSWORD'] ?? 'password';
// Se la password è ancora un placeholder, usa un default sensato.
if (str_contains($adminPassword, '__')) {
    $adminPassword = 'password';
}

$hash = password_hash($adminPassword, PASSWORD_DEFAULT);
$uuid = uuid_v4();

$q = static fn (string $s): string => "'" . str_replace("'", "''", $s) . "'";

$sql = <<<SQL
-- =====================================================================
-- ECF — Edysma Centralized Forms · schema + seed per il collaudo
-- Generato da deploy/export-sql.php — importare via phpMyAdmin.
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --- users ---
CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- forms ---
CREATE TABLE IF NOT EXISTS `forms` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `name` VARCHAR(190) NOT NULL,
  `description` TEXT NULL,
  `success_message` VARCHAR(255) NULL,
  `allowed_origins` JSON NULL,
  `style` JSON NULL,
  `status` ENUM('draft','active','disabled') NOT NULL DEFAULT 'draft',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `forms_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- form_fields ---
CREATE TABLE IF NOT EXISTS `form_fields` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `form_id` BIGINT UNSIGNED NOT NULL,
  `key` VARCHAR(100) NOT NULL,
  `label` VARCHAR(190) NOT NULL,
  `type` ENUM('text','email','textarea','number','select','radio','checkbox','date','hidden') NOT NULL,
  `required` TINYINT(1) NOT NULL DEFAULT 0,
  `placeholder` VARCHAR(190) NULL,
  `options` JSON NULL,
  `validation` JSON NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `form_fields_form_id_key_unique` (`form_id`,`key`),
  CONSTRAINT `form_fields_form_id_foreign` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- submissions ---
CREATE TABLE IF NOT EXISTS `submissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `form_id` BIGINT UNSIGNED NOT NULL,
  `payload` JSON NOT NULL,
  `source_url` VARCHAR(500) NULL,
  `ip` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `submissions_form_id_foreign` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Seed
-- =====================================================================

INSERT INTO `users` (`email`, `password_hash`, `created_at`, `updated_at`)
VALUES ({$q($adminEmail)}, {$q($hash)}, NOW(), NOW());

INSERT INTO `forms` (`uuid`, `name`, `description`, `success_message`, `allowed_origins`, `status`, `created_at`, `updated_at`)
VALUES ({$q($uuid)}, 'Contatti', 'Modulo di contatto di esempio.', 'Grazie! Ti risponderemo al più presto.', NULL, 'active', NOW(), NOW());

SET @form_id = LAST_INSERT_ID();

INSERT INTO `form_fields` (`form_id`, `key`, `label`, `type`, `required`, `placeholder`, `options`, `validation`, `sort_order`, `created_at`, `updated_at`) VALUES
(@form_id, 'nome', 'Nome', 'text', 1, 'Il tuo nome', NULL, NULL, 0, NOW(), NOW()),
(@form_id, 'email', 'Email', 'email', 1, 'tu@esempio.it', NULL, NULL, 1, NOW(), NOW()),
(@form_id, 'messaggio', 'Messaggio', 'textarea', 1, 'Scrivi qui...', NULL, NULL, 2, NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;

-- Admin: {$adminEmail}
-- Form "Contatti" UUID pubblico: {$uuid}

SQL;

echo $sql;

function uuid_v4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
