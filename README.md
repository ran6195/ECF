# ECF — Edysma Centralized Forms

Sistema che centralizza la definizione di form collegati a MySQL, li serve a siti
terzi tramite uno snippet JavaScript (isolato in **Shadow DOM**) e ne raccoglie le
submission. Composto da tre parti:

| Parte | Tecnologia | Cartella |
|---|---|---|
| **Backend / API** | PHP 8.1+ · Slim 4 · Eloquent ORM | `backend/` |
| **Loader embed** | JavaScript vanilla, zero dipendenze | `backend/public/embed.js` |
| **Admin SPA** | Vue 3 + Vite | `admin/` |

Pagina di prova dell'embed: `test-embed/index.html`.

---

## 1. Requisiti

- PHP **8.1+** con estensioni `pdo_mysql`, `json`, `mbstring`.
- Composer.
- MySQL **5.7+** (qui testato con MySQL 9).
- Node.js **18+** e npm (per l'admin).

---

## 2. Setup del backend

```bash
cd backend
cp .env.example .env          # già pronto per MySQL locale root senza password
composer install
composer migrate              # crea le tabelle nello schema ECFDatabase
composer seed                 # crea l'admin e il form "Contatti" di esempio
```

Lo schema `ECFDatabase` deve esistere. Se non c'è:

```sql
CREATE DATABASE IF NOT EXISTS ECFDatabase CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Il seed stampa l'**UUID pubblico** del form di esempio: ti serve per lo snippet e
per la pagina di test.

### Variabili `.env`

```dotenv
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ECFDatabase
DB_USERNAME=root
DB_PASSWORD=
APP_URL=http://localhost:8080
JWT_SECRET=...            # cambialo in produzione
JWT_TTL=28800            # durata token (s)
ADMIN_EMAIL=admin@edysma.test
ADMIN_PASSWORD=password
```

### Avvio del server di sviluppo

```bash
php -S localhost:8080 -t public public/index.php
```

> **Nota:** `public/index.php` funge anche da router per il server integrato di
> PHP, così i file statici (es. `embed.js`) sono serviti direttamente e tutto il
> resto è instradato a Slim. In produzione (Apache) ci pensa `public/.htaccess`.

Verifica rapida:

```bash
curl http://localhost:8080/health
curl http://localhost:8080/api/embed/<UUID>/render
```

`composer migrate --fresh` ricrea le tabelle da zero; `composer fresh` esegue
migrate + seed in sequenza. I test: `composer test`.

---

## 3. Admin SPA

```bash
cd admin
cp .env.example .env          # VITE_API_BASE_URL=http://localhost:8080
npm install
npm run dev                   # http://localhost:5173
```

Login con le credenziali del seed (`admin@edysma.test` / `password`). Da qui:

- **Lista form** con stato, UUID, numero di submission e **snippet copiabile**;
- **Form builder** con editor dei campi (drag & drop per riordinare), proprietà
  per campo, anteprima live, `success_message`, `status` e origini autorizzate;
- **Stile per form**: tema (colori, font, raggio bordi) + CSS avanzato opzionale,
  applicati nello Shadow DOM. L'anteprima live è renderizzata dal backend
  (`POST /api/forms/preview`), identica al render reale;
- **Submission** con tabella, dettaglio del payload ed **export CSV**.

Build di produzione: `npm run build` → cartella `dist/` (statica, pubblicabile
ovunque).

---

## 4. Embed su un sito terzo

Snippet generato dall'admin (copiabile dalla lista form):

```html
<div data-ecf-form="UUID-DEL-FORM"></div>
<script src="http://localhost:8080/embed.js" data-api="http://localhost:8080" async></script>
```

- `embed.js` cerca tutti gli elementi `[data-ecf-form]`, fa `GET /render`,
  inietta l'HTML in uno **Shadow DOM** e intercetta il submit (`POST /submit`).
- La base URL dell'API è ricavata dall'attributo `src` dello script oppure da
  `data-api`. Supporta più form nella stessa pagina con un solo tag `<script>`.

### Pagina di test

Apri `test-embed/index.html` (anche con un semplice file server statico), inserisci
l'UUID del form nel `data-ecf-form`, e verifica che il form appaia e che l'invio
crei una riga in `submissions`.

---

## 5. API

### Pubbliche (CORS per-form)
| Metodo | Path | Note |
|---|---|---|
| `GET` | `/api/embed/{uuid}/render` | HTML del form (`text/html`). 404 se non `active`. |
| `POST` | `/api/embed/{uuid}/submit` | Valida e salva. `422` con errori per campo. Honeypot → silent drop. |
| `OPTIONS` | `/api/embed/...` | Preflight CORS. |

### Admin (JWT — `Authorization: Bearer`)
| Metodo | Path |
|---|---|
| `POST` | `/api/auth/login` |
| `GET` / `POST` | `/api/forms` |
| `GET` / `PUT` / `DELETE` | `/api/forms/{id}` |
| `GET` | `/api/forms/{id}/submissions` |
| `GET` | `/api/forms/{id}/submissions/export` (CSV) |

Risposte JSON coerenti: `{ success, data?, errors?, message? }` (tranne `/render`
che è `text/html` e l'export che è `text/csv`).

---

## 6. Sicurezza e scelte adottate

- **Rendering server-side**: `FormRenderer` genera l'HTML dai `form_fields` (unica
  fonte di verità per HTML e validazione). Tutti i valori dinamici sono escapati
  (`htmlspecialchars`) → niente XSS.
- **Validazione autoritativa lato server** (`FormValidator`): `required`, coerenza
  di tipo, `min`/`max`/`minLength`/`maxLength`/`regex`. Il client fa solo
  validazione UX.
- **Isolamento**: Shadow DOM, nessun iframe.
- **Whitelist domini per form** (`allowed_origins`): vuoto/NULL → modalità aperta
  (test); altrimenti il middleware confronta l'header `Origin` e blocca le origini
  non autorizzate (gestisce anche il preflight `OPTIONS`).
- **Honeypot**: campo nascosto `_ecf_hp`; se valorizzato la submission è scartata
  silenziosamente (risposta 200 senza salvare).
- **Auth admin**: JWT (`firebase/php-jwt`) su tutte le rotte admin.
- **Submission**: campi comuni come colonne + `payload` in **JSON**
  (`json_encode`, mai `serialize()`).

### Decisioni di dettaglio (non specificate nel prompt)

- **Migrazioni**: runner PHP basato sullo Schema Builder di Eloquent
  (`database/migrate.php`), niente Phinx. Idempotente, con flag `--fresh`.
- **Container**: si usa il container minimale di Slim; i servizi sono istanziati
  nei controller (nessuna dipendenza DI aggiuntiva).
- **Admin**: client HTTP con `fetch` nativo; sessione (token JWT) in
  `localStorage` via composable `useAuth`; routing con hash history; riordino
  campi con `vuedraggable`.
- **CORS admin**: middleware permissivo dedicato (`AdminCorsMiddleware`) che
  riflette l'`Origin` e consente l'header `Authorization`, separato dalla logica
  per-form usata sugli endpoint di embed.

---

## 7. Struttura del repository

```
backend/
├─ public/        index.php · embed.js · .htaccess
├─ src/
│  ├─ Models/        Form · FormField · Submission · User
│  ├─ Controllers/   Embed · Auth · Form · Submission
│  ├─ Services/      FormRenderer · FormValidator
│  ├─ Middleware/    CorsOrigin · AdminCors · Auth
│  ├─ Support/       Env · Database · Response · Jwt
│  └─ bootstrap.php  container + Eloquent + rotte
├─ database/      migrate.php · seed.php
└─ tests/         FormValidatorTest · FormRendererTest
admin/            Vue 3 + Vite (views, components, api, router)
test-embed/       index.html
```
