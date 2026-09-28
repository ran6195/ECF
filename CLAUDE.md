# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Panoramica

ECF centralizza la definizione di form in MySQL, li serve a **siti terzi** via uno snippet JS (isolato in Shadow DOM) e ne raccoglie le submission. Tre parti:

| Parte | Stack | Cartella |
|---|---|---|
| API / backend | PHP 8.1+ · Slim 4 · Eloquent (`illuminate/database`) · firebase/php-jwt | `backend/` |
| Loader embed | JS vanilla, zero dipendenze | `backend/public/embed.js` |
| Admin SPA | Vue 3 + Vite (Composition API, `<script setup>`) | `admin/` |

Documentazione estesa già presente: `README.md` (setup e API), `ARCHITETTURA.md` (schema DB e scelte), `deploy/README.md` (procedura di collaudo). `PROMPT_CLAUDE_CODE.md` è il prompt di generazione iniziale, storico.

## Comandi

```bash
# Backend (da backend/)
composer install
composer migrate                  # idempotente; --fresh (php database/migrate.php --fresh) ricrea da zero
composer seed                     # admin + form "Contatti"; stampa l'UUID pubblico del form
composer fresh                    # migrate + seed
composer test                     # PHPUnit
vendor/bin/phpunit --filter testNomeDelMetodo    # singolo test
vendor/bin/phpunit tests/FormValidatorTest.php   # singolo file
php -S localhost:8080 -t public public/index.php # dev server (index.php fa anche da router per i file statici)

# Admin (da admin/)
npm install && npm run dev        # http://localhost:5173, login con le credenziali del seed
npm run build

# Deploy di collaudo (dalla radice)
bash deploy/build.sh              # richiede backend/.env.production
( cd backend && composer install )  # ripristina le dipendenze dev dopo il build (--no-dev le rimuove)
```

Il DB locale è `ECFDatabase` su `127.0.0.1`, utente `root` senza password; lo schema deve esistere prima di `migrate`.

## Architettura

**Fonte di verità unica.** `form_fields` definisce sia l'HTML sia le regole di validazione. `FormRenderer` genera il fragment HTML + `<style>` inline; `FormValidator` valida il payload contro gli stessi campi. Il client non costruisce mai la struttura del form e fa solo validazione UX.

Flusso: `embed.js` trova gli elementi `[data-ecf-form]` → `GET /api/embed/{uuid}/render` → inietta HTML+CSS in uno Shadow Root → intercetta il submit → `POST /api/embed/{uuid}/submit` → mostra `success_message` (o fa redirect se il form ha `redirect_url`), oppure renderizza gli errori 422 per campo.

L'anteprima dell'admin **non** riproduce il rendering: `FormPreview.vue` chiama `POST /api/forms/preview`, che costruisce Form e FormField in memoria e usa lo stesso `FormRenderer` della produzione. Non duplicare logica di rendering lato Vue.

Le rotte stanno tutte in `backend/src/bootstrap.php`. Il dettaglio form viaggia come `{ ...form, fields[] }`: il `PUT` manda l'oggetto intero e `FormController::syncFields()` fa il diff (cancella prima i campi rimossi, così le loro `key` si liberano per il vincolo `UNIQUE(form_id, key)`).

## Modifiche che toccano più file

**Aggiungere un tipo di campo** — vanno allineati tutti:
1. `FormController::FIELD_TYPES`
2. `FormRenderer::renderField()` (match sul tipo) e, se serve, `FormValidator::validateField()`
3. `backend/database/migrate.php` — sia l'ENUM nella `create()` sia l'`ALTER TABLE ... MODIFY COLUMN type ENUM(...)` in coda, che tiene allineate le installazioni esistenti
4. `deploy/export-sql.php` (ENUM nello schema esportato) + un nuovo file in `deploy/migrations/AAAA-MM-GG-*.sql` per i DB già popolati
5. `admin/src/components/FieldEditor.vue` (lista tipi + editor delle proprietà specifiche)

Logica condivisa tra render e validazione va in un service dedicato (vedi `TimeSlotGenerator`, usato da entrambi per generare lo stesso insieme di slot).

**Aggiungere un token di tema:** `Form::THEME_DEFAULTS` + whitelist in `FormController::normalizeStyle()` + `admin/src/theme.js` (`DEFAULT_THEME`, che deve combaciare) + `StyleEditor.vue`. `submitBg` è volutamente fuori da `THEME_DEFAULTS`: senza valore esplicito segue `primary`.

**Cambiare schema DB:** `migrate.php` deve restare idempotente (guard `hasTable`/`hasColumn`) e la stessa variazione va replicata in `deploy/export-sql.php` e in `deploy/migrations/`, perché in collaudo non c'è shell e si importa via phpMyAdmin.

## Vincoli da non rompere

- **Escape**: ogni valore dinamico nel render passa da `FormRenderer::e()`. Il CSS custom per form è ripulito dai caratteri che spezzerebbero lo stile.
- **Validazione server autoritativa**; `privacy_consent` è forzato `required` lato server, mai sulla parola del client.
- **reCAPTCHA v2 non può vivere nello Shadow DOM** (SecurityError sulle iframe di Google): il sitekey viaggia come attributo `data-recaptcha-sitekey` sul `<form>`, `embed.js` crea il widget nel DOM principale e lo proietta via `<slot name="ecf-recaptcha">`. Il token è monouso → `grecaptcha.reset()` dopo ogni tentativo. La verifica server è **fail-closed**. Attivare reCAPTCHA rende `allowed_origins` obbligatorio (la site key è legata al dominio).
- **Honeypot** `_ecf_hp`: se valorizzato la submission è scartata in silenzio, rispondendo 200 come un invio riuscito.
- **Due middleware CORS distinti**: `CorsOriginMiddleware` (per-form, legge `allowed_origins`; vuoto/NULL = modalità aperta di test) sulle rotte embed, `AdminCorsMiddleware` (permissivo, riflette l'Origin, consente `Authorization`) sulle rotte admin. Non unificarli.
- **JWT su Apache**: l'header `Authorization` è inoltrato a PHP dal `.htaccess` (`SetEnvIf` + `RewriteRule E=HTTP_AUTHORIZATION`). Toccando il rewrite, verificare che l'admin resti autenticato in produzione.
- **Fuso orario**: `Database::boot()` fissa `Europe/Rome` per PHP e passa a MySQL un offset numerico (non il nome della zona, che richiederebbe le tabelle di fuso caricate).
- **`embed.js` è servito statico con cache lunga** (1 giorno): modificandolo, lo snippet va versionato con `?v=`.
- Il `uuid` del form è pubblico, non è un segreto; la `recaptcha_secret_key` non esce mai dagli endpoint pubblici.

## Deploy

Pacchetto pre-buildato per hosting gestito senza shell: `deploy/build.sh` produce `deploy/build/<BASE_PATH>/` (da caricare via FTP) e `deploy/build/database.sql`. Admin e API convivono nella stessa sottocartella, quindi l'admin parla con l'API in same-origin; il codice backend sta in `app/` protetto da `Require all denied`. La sottocartella è definita da `BASE_PATH` in `build.sh` + `APP_BASE_PATH`/`APP_URL` in `backend/.env.production` + `VITE_API_BASE_URL` in `admin/.env.production`: vanno cambiati insieme. `deploy/build/` è generato e gitignorato — non modificarlo a mano.

## Convenzioni

- Commenti in italiano, orientati al *perché* (soprattutto dove una scelta non ovvia evita un bug noto); messaggi di commit in italiano all'imperativo.
- PHP: `declare(strict_types=1)`, PSR-4 namespace `Ecf\`, classi `final` dove non estese, risposte JSON uniformi via `Ecf\Support\Response` (`{ success, data?, errors?, message? }`).
- Nessun container DI oltre a quello minimale di Slim: i service sono istanziati nei controller.
- I test (`backend/tests/`) girano **senza DB**: `Tests\Support::makeForm()` costruisce Form e FormField in memoria con `setRelation('fields', ...)`.
