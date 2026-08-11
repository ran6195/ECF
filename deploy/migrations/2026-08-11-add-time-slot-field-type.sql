-- Aggiunge il nuovo tipo di campo `time_slot` (fascia oraria: select generata da
-- ora inizio, ora fine e step in minuti, salvati in form_fields.validation).
-- Esegui UNA VOLTA via phpMyAdmin sul DB di collaudo già esistente.
-- (Le nuove installazioni lo creano già da database.sql.)

ALTER TABLE `form_fields`
  MODIFY COLUMN `type` ENUM('text','email','textarea','number','select','radio','checkbox','date','hidden','privacy_consent','time_slot') NOT NULL;
