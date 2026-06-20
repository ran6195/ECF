-- Aggiunge la colonna `style` (tema + CSS personalizzato per form).
-- Esegui UNA VOLTA via phpMyAdmin sul DB di collaudo già esistente.
-- (Le nuove installazioni la creano già da database.sql.)

ALTER TABLE `forms`
  ADD COLUMN `style` JSON NULL AFTER `allowed_origins`;
