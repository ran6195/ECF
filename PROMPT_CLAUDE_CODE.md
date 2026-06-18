# Prompt per Claude Code — ECF (Edysma Centralized Forms)

> Copia il contenuto qui sotto come istruzione iniziale per Claude Code nella
> cartella del progetto. È auto-contenuto: descrive obiettivo, stack, decisioni
> già prese, schema DB, API, struttura e criteri di accettazione.

---

## Ruolo e obiettivo

Sei uno sviluppatore full-stack (PHP, JS, MySQL). Genera da zero **ECF — Edysma
Centralized Forms**: un sistema web che centralizza la definizione di form
collegati a MySQL, li serve a siti terzi tramite uno snippet JS, e ne raccoglie
le submission.

Genera **tutto in questo giro**: backend API, loader `embed.js`, admin SPA Vue 3,
migrazioni DB, seed di esempio e una pagina di test per l'embed.

## Ambiente locale (configurazione DB)

- **Database:** MySQL locale, schema **`ECFDatabase`**.
- **Utente:** `root` **senza password**.
- Host `127.0.0.1`, porta `3306`.
- Valori da riportare in `backend/.env` (e `.env.example` con gli stessi default):

```dotenv
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ECFDatabase
DB_USERNAME=root
DB_PASSWORD=
```

La connessione Eloquent deve gestire correttamente la **password vuota**.
Le migrazioni assumono che lo schema `ECFDatabase` esista già (in alternativa
crearlo con `CREATE DATABASE IF NOT EXISTS ECFDatabase CHARACTER SET utf8mb4`).

## Stack tecnologico (vincolante)

- **Backend:** PHP 8.1+, **Slim 4** (`slim/slim`, `slim/psr7`), **Eloquent ORM**
  (`illuminate/database`), `vlucas/phpdotenv`, `firebase/php-jwt`.
- **DB:** MySQL 5.7+ (usare tipo/funzioni JSON).
- **Loader embed:** JavaScript vanilla, **zero dipendenze**, con **Shadow DOM**.
- **Admin:** **Vue 3 + Vite** (Composition API, `<script setup>`), Vue Router,
  client HTTP a scelta (fetch o axios). State minimale (Pinia opzionale).
- Server di destinazione: hosting gestito con Apache → includere `.htaccess` con
  rewrite verso `public/index.php`.

## Decisioni di architettura già prese (NON ridiscutere)

1. **Definizione form in tabelle relazionali** (`forms` + `form_fields`): è la
   fonte di verità unica per il rendering HTML **e** per la validazione server-side.
2. **Submission** salvate in una tabella unica: campi comuni come colonne +
   `payload` in **JSON** (`json_encode`, NON `serialize()`).
3. **Rendering server-side:** l'HTML del form è generato dal backend a partire da
   `form_fields`. Il client non costruisce la struttura del form.
4. **Validazione autoritativa lato server**; il client fa solo validazione UX.
5. **Isolamento sui siti terzi:** Shadow DOM (no iframe, no iniezione diretta).
6. **Autorizzazione domini:** whitelist per form basata su header `Origin`.
   Se `allowed_origins` è vuoto/NULL → modalità aperta (per i test). Predisporre
   il middleware CORS che gestisce anche il preflight `OPTIONS`.
7. **Auth admin:** JWT da subito, middleware su tutte le rotte admin.
8. **Anti-spam:** nessuno ora, ma includere nel form un campo **honeypot nascosto**
   (predisposto, non bloccante).
9. **Identificatore pubblico del form:** `uuid` (non è un segreto, viaggia nello snippet).

## Schema database (migrazioni Eloquent/Phinx o SQL puro)

**forms**
- `id` BIGINT PK AI
- `uuid` CHAR(36) UNIQUE
- `name` VARCHAR(190)
- `description` TEXT NULL
- `success_message` VARCHAR(255) NULL
- `allowed_origins` JSON NULL  *(lista di origin autorizzate; NULL = aperto)*
- `status` ENUM('draft','active','disabled') DEFAULT 'draft'
- `created_at`, `updated_at` TIMESTAMP

**form_fields**
- `id` BIGINT PK AI
- `form_id` BIGINT FK → forms.id (ON DELETE CASCADE)
- `key` VARCHAR(100)  *(chiave macchina, usata nel payload)*
- `label` VARCHAR(190)
- `type` ENUM('text','email','textarea','number','select','radio','checkbox','date','hidden')
- `required` TINYINT(1) DEFAULT 0
- `placeholder` VARCHAR(190) NULL
- `options` JSON NULL  *(per select/radio/checkbox: lista {value,label})*
- `validation` JSON NULL  *( {min,max,maxLength,regex} )*
- `sort_order` INT DEFAULT 0
- UNIQUE(`form_id`,`key`)

**submissions**
- `id` BIGINT PK AI
- `form_id` BIGINT FK → forms.id (ON DELETE CASCADE)
- `payload` JSON  *(contenuto dei campi)*
- `source_url` VARCHAR(500) NULL
- `ip` VARCHAR(45) NULL
- `user_agent` VARCHAR(255) NULL
- `created_at` TIMESTAMP

**users** (admin)
- `id` BIGINT PK AI
- `email` VARCHAR(190) UNIQUE
- `password_hash` VARCHAR(255)
- `created_at`, `updated_at` TIMESTAMP

Includere **seed**: 1 utente admin (da `.env`), 1 form "Contatti" di esempio con
campi (nome=text required, email=email required, messaggio=textarea required) e
`allowed_origins` NULL.

## API

### Pubbliche (CORS, check origine attivabile per form)
- `GET /api/embed/{uuid}/render` → `Content-Type: text/html`. Restituisce un
  **fragment HTML** del form + un blocco `<style>` inline (pensato per vivere
  dentro lo Shadow DOM). Include il campo honeypot nascosto. 404 se form
  inesistente o non `active`.
- `POST /api/embed/{uuid}/submit` → body JSON con i valori dei campi +
  `source_url`. Valida contro `form_fields`. Salva in `submissions`. Risponde
  `{ success:true, message }` oppure `422 { success:false, errors:{campo:[...]} }`.
  Se honeypot valorizzato → rispondi 200 "ok" senza salvare (silent drop).
- Gestire `OPTIONS` preflight per entrambe.

### Admin (protette da JWT — `Authorization: Bearer`)
- `POST /api/auth/login` → `{email,password}` → `{ token, user }`.
- `GET  /api/forms` → lista form (con conteggio submission).
- `POST /api/forms` → crea form con i suoi `fields`.
- `GET  /api/forms/{id}` → dettaglio form + `fields[]`.
- `PUT  /api/forms/{id}` → aggiorna form e fa il **sync** dei `fields` (crea/aggiorna/elimina).
- `DELETE /api/forms/{id}`.
- `GET  /api/forms/{id}/submissions` → lista paginata.
- `GET  /api/forms/{id}/submissions/export` → CSV.

## Struttura del repository

```
ecf/
├─ backend/
│  ├─ public/
│  │  ├─ index.php           # front controller Slim
│  │  ├─ embed.js            # loader statico (servito da qui)
│  │  └─ .htaccess           # rewrite -> index.php
│  ├─ src/
│  │  ├─ Models/             # Form, FormField, Submission, User
│  │  ├─ Controllers/        # Embed, Form, Submission, Auth
│  │  ├─ Services/
│  │  │  ├─ FormRenderer.php     # form_fields -> HTML + <style>
│  │  │  └─ FormValidator.php    # valida payload contro le regole
│  │  ├─ Middleware/
│  │  │  ├─ CorsOriginMiddleware.php
│  │  │  └─ AuthMiddleware.php
│  │  ├─ Support/                # helper (Jwt, Response)
│  │  └─ bootstrap.php           # container + Eloquent Capsule + routes
│  ├─ database/
│  │  ├─ migrations/
│  │  └─ seeds/
│  ├─ tests/                     # test minimi su validator + render
│  ├─ .env.example               # DB_*, JWT_SECRET, ADMIN_EMAIL/PASSWORD
│  └─ composer.json
├─ admin/                        # Vue 3 + Vite
│  ├─ src/
│  │  ├─ views/    (Login, FormsList, FormBuilder, Submissions)
│  │  ├─ components/ (FieldEditor, FormPreview, SnippetBox)
│  │  ├─ api/      (client http + auth token)
│  │  └─ router/
│  ├─ .env.example   # VITE_API_BASE_URL
│  └─ package.json
├─ test-embed/
│  └─ index.html     # pagina statica che carica embed.js e mostra il form
└─ README.md         # come installare, configurare .env, avviare, testare
```

## Requisiti `embed.js`

1. All'`DOMContentLoaded` cerca tutti gli elementi `[data-ecf-form]`.
2. Per ciascuno legge l'uuid, fa `fetch GET {API}/api/embed/{uuid}/render`.
3. Crea uno **shadow root** (`attachShadow({mode:'open'})`) sul container e
   inietta l'HTML ricevuto (che contiene già il proprio `<style>`).
4. Intercetta il `submit` del form: `preventDefault`, raccoglie i valori, aggiunge
   `source_url = location.href`, fa `POST .../submit` in JSON.
5. Mostra `success_message` in caso di successo, o evidenzia gli errori `422`
   restituiti dal server, dentro lo shadow root.
6. Deve funzionare con più form nella stessa pagina e con un solo tag `<script>`.
7. La base URL dell'API è ricavata dall'attributo `src` dello stesso `<script>`
   (oppure da `data-api` sul tag), così non va hard-codata.

## Admin SPA (Vue 3) — schermate

- **Login** (email/password → salva token).
- **Lista form**: nome, stato, uuid, n° submission, **box snippet** copiabile.
- **Form builder**: editor dei campi (aggiungi/riordina/elimina), proprietà per
  campo (key, label, type, required, options, validation), **anteprima live**,
  configurazione `success_message`, `status` e `allowed_origins`.
- **Submissions**: tabella delle submission del form, dettaglio payload, export CSV.

## Convenzioni e qualità

- Codice PHP PSR-12, namespace `Ecf\`. Risposte JSON coerenti
  (`{success, data?, errors?, message?}`), tranne `/render` che è `text/html`.
- Escape dell'HTML su tutti i valori dinamici nel `FormRenderer` (no XSS).
- Validazione e sanificazione **sempre** lato server.
- Niente segreti hard-coded: tutto in `.env`. Fornire `.env.example`.
- Commenti in italiano dove utile; nomi di codice in inglese.

## Criteri di accettazione (come verifico che funziona)

1. `composer install` + import migrazioni + seed → DB popolato con admin e form "Contatti".
2. Avvio backend (`php -S localhost:8080 -t public`) → `GET /api/embed/{uuid}/render`
   restituisce l'HTML del form di esempio.
3. Apro `test-embed/index.html` (puntando all'API locale): il form appare dentro
   uno Shadow DOM e l'invio crea una riga in `submissions`.
4. `npm run dev` in `admin/` → login con le credenziali del seed, vedo il form,
   apro il builder, vedo le submission, copio lo snippet.
5. La validazione server rifiuta i campi `required` mancanti con `422` e messaggi
   per campo; l'honeypot valorizzato non crea submission.
6. Con `allowed_origins` valorizzato, una richiesta da origine non in lista viene
   bloccata dal middleware CORS; con lista vuota passa.

## Ordine di costruzione consigliato

1. Scaffold backend + composer + bootstrap Eloquent + migrazioni + seed.
2. `FormRenderer`, `FormValidator`, endpoint `/render` e `/submit` + CORS middleware.
3. `embed.js` + `test-embed/index.html` e verifica end-to-end.
4. Auth JWT + rotte admin CRUD/submissions/export.
5. Admin Vue (login → lista → builder → submissions).
6. README con setup e test.

Procedi generando il codice. Dove servono scelte di dettaglio non specificate qui,
prendi la decisione più semplice e robusta e annotala nel README.
