-- Aggiunge il connettore Brevo (sincronizzazione submission su una lista di
-- contatti): configurazione per-form, cache degli attributi contatto e stato
-- di sincronizzazione per submission.
-- Esegui UNA VOLTA via phpMyAdmin sul DB di collaudo già esistente.
-- (Le nuove installazioni lo creano già da database.sql.)

ALTER TABLE `forms`
  ADD COLUMN `brevo_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `recaptcha_secret_key`,
  ADD COLUMN `brevo_api_key` VARCHAR(190) NULL AFTER `brevo_enabled`,
  ADD COLUMN `brevo_list_id` INT UNSIGNED NULL AFTER `brevo_api_key`,
  ADD COLUMN `brevo_field_mapping` JSON NULL AFTER `brevo_list_id`;

ALTER TABLE `submissions`
  ADD COLUMN `brevo_synced_at` TIMESTAMP NULL DEFAULT NULL AFTER `user_agent`,
  ADD COLUMN `brevo_sync_error` VARCHAR(500) NULL AFTER `brevo_synced_at`;

CREATE TABLE IF NOT EXISTS `brevo_attributes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `form_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(190) NOT NULL,
  `type` VARCHAR(50) NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `brevo_attributes_form_id_name_unique` (`form_id`,`name`),
  CONSTRAINT `brevo_attributes_form_id_foreign` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
