-- Aggiunge le colonne per: thank-you page (redirect_url), reCAPTCHA v2 per-form,
-- e il nuovo tipo di campo `privacy_consent` (checkbox privacy obbligatoria).
-- Esegui UNA VOLTA via phpMyAdmin sul DB di collaudo già esistente.
-- (Le nuove installazioni le creano già da database.sql.)

ALTER TABLE `forms`
  ADD COLUMN `redirect_url` VARCHAR(500) NULL AFTER `success_message`,
  ADD COLUMN `recaptcha_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `style`,
  ADD COLUMN `recaptcha_site_key` VARCHAR(190) NULL AFTER `recaptcha_enabled`,
  ADD COLUMN `recaptcha_secret_key` VARCHAR(190) NULL AFTER `recaptcha_site_key`;

ALTER TABLE `form_fields`
  MODIFY COLUMN `type` ENUM('text','email','textarea','number','select','radio','checkbox','date','hidden','privacy_consent') NOT NULL;
