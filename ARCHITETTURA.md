# ECF — Edysma Centralized Forms · Architettura

Sistema web per centralizzare la definizione di form, iniettarli su siti terzi
via uno snippet JS e raccogliere le submission in un DB MySQL.

**Stack:** Slim 4 (PHP) + Eloquent ORM · Vue 3 (admin) · `embed.js` vanilla (Shadow DOM) · MySQL.

---

## 1. Componenti

| Componente | Tecnologia | Ruolo |
|---|---|---|
| **API / Backend** | Slim 4 + Eloquent | Serve l'HTML del form, riceve le submission, espone le API CRUD per l'admin. |
| **Admin SPA** | Vue 3 + Vite | Costruzione form (form builder), configurazione, lettura submission. |
| **Loader** | `embed.js` (vanilla) | Caricato sui siti terzi: fa fetch dell'HTML, lo inietta in Shadow DOM, gestisce il submit. |
| **Database** | MySQL 5.7+ | Definizioni form + submission (payload JSON). |

---

## 2. Flusso end-to-end

```
Sito terzo                         App ECF (Slim)                MySQL
----------                         --------------                -----
<div data-ecf-form="UUID">
<script src=".../embed.js">
        │
        │ 1. GET /api/embed/{uuid}/render  ───────►  FormRenderer
        │ ◄──────────  HTML fragment + <style>        (da form_fields)
        │
   [Shadow DOM inject]
        │
        │ 2. POST /api/embed/{uuid}/submit ───────►  FormValidator ──► submissions
        │ ◄──────────  { success, message } | { errors }
        │
   [mostra esito]
```

Snippet che l'admin copia/incolla sul sito:

```html
<div data-ecf-form="d3f1c2a0-..."></div>
<script src="https://api.tuodominio.it/embed.js" async></script>
```

---

## 3. Schema database

### `forms` — definizione del form
| Campo | Tipo | Note |
|---|---|---|
| id | BIGINT PK | |
| uuid | CHAR(36) UNIQUE | identificatore pubblico usato nello snippet |
| name | VARCHAR | nome interno |
| description | TEXT NULL | |
| success_message | VARCHAR NULL | messaggio post-invio |
| allowed_origins | JSON NULL | whitelist domini; `NULL`/vuoto = aperto (test) |
| status | ENUM('draft','active','disabled') | |
| created_at / updated_at | TIMESTAMP | |

### `form_fields` — definizione dei campi (fonte di verità per render + validazione)
| Campo | Tipo | Note |
|---|---|---|
| id | BIGINT PK | |
| form_id | BIGINT FK | |
| key | VARCHAR | nome macchina del campo (chiave nel payload) |
| label | VARCHAR | |
| type | ENUM | text, email, textarea, number, select, radio, checkbox, date, hidden |
| required | TINYINT | |
| placeholder | VARCHAR NULL | |
| options | JSON NULL | per select/radio/checkbox |
| validation | JSON NULL | `{min,max,regex,maxLength}` |
| sort_order | INT | ordinamento |

### `submissions` — dati raccolti (campi comuni + payload serializzato)
| Campo | Tipo | Note |
|---|---|---|
| id | BIGINT PK | |
| form_id | BIGINT FK | |
| payload | JSON / LONGTEXT | contenuto dei campi (`json_encode`) |
| source_url | VARCHAR NULL | pagina di origine |
| ip | VARCHAR NULL | |
| user_agent | VARCHAR NULL | |
| created_at | TIMESTAMP | |

> **Nota storage:** uso `json_encode` invece di `serialize()` → niente rischio di
> object injection in `unserialize`, leggibile a mano e interrogabile con le
> funzioni JSON di MySQL. I "campi comuni" stanno come colonne, il resto nel `payload`.

### `users` — accesso al pannello (minimale per ora)
`id, email, password_hash, created_at`. Auth via JWT.

---

## 4. Endpoint API

### Pubblici (CORS, con check origine attivabile per form)
| Metodo | Path | Descrizione |
|---|---|---|
| `GET` | `/api/embed/{uuid}/render` | Restituisce l'HTML del form (fragment + `<style>` inline). `Content-Type: text/html`. |
| `POST` | `/api/embed/{uuid}/submit` | Valida lato server contro `form_fields`, salva la submission. Risponde JSON. |
| `OPTIONS` | `/api/embed/...` | Preflight CORS. |

### Admin (protetti da JWT)
| Metodo | Path | Descrizione |
|---|---|---|
| `POST` | `/api/auth/login` | Login, ritorna token. |
| `GET/POST` | `/api/forms` | Lista / crea form. |
| `GET/PUT/DELETE` | `/api/forms/{id}` | Dettaglio (campi inclusi) / aggiorna / elimina. |
| `GET` | `/api/forms/{id}/submissions` | Lista submission (+ export CSV). |

Il dettaglio form viaggia con i suoi `fields` annidati: l'admin invia l'intero
oggetto `{ form, fields[] }` in `PUT` e il backend fa il sync dei campi.

---

## 5. Backend — struttura (Slim + Eloquent)

```
backend/
├─ public/
│  ├─ index.php          # front controller
│  └─ embed.js           # loader statico (cache lunga + ?v=)
├─ src/
│  ├─ Models/            # Form, FormField, Submission, User (Eloquent)
│  ├─ Controllers/       # Embed, Form, Submission, Auth
│  ├─ Services/
│  │  ├─ FormRenderer.php    # form_fields -> HTML fragment + <style>
│  │  └─ FormValidator.php   # valida payload contro le regole dei campi
│  ├─ Middleware/
│  │  ├─ CorsOriginMiddleware.php  # legge allowed_origins, gestisce preflight
│  │  └─ AuthMiddleware.php        # verifica JWT sulle rotte admin
│  └─ bootstrap.php      # container, Eloquent Capsule, routes
├─ database/migrations/
├─ .env                  # credenziali DB, JWT secret
└─ composer.json         # slim/slim, slim/psr7, illuminate/database,
                         # vlucas/phpdotenv, firebase/php-jwt
```

**Punti chiave**
- **Rendering server-side:** `FormRenderer` genera l'HTML dai `form_fields`. Così
  la definizione dei campi è l'unica fonte di verità sia per l'HTML sia per la validazione.
- **Validazione autoritativa lato server:** `FormValidator` applica `required`,
  `type`, `validation.regex`, ecc. Il client fa solo validazione "estetica" per UX.
- **CORS middleware:** se `allowed_origins` è vuoto → modalità aperta (test);
  altrimenti confronta l'header `Origin`, e se autorizzato lo riflette in
  `Access-Control-Allow-Origin`. Gestisce `OPTIONS` preflight.

---

## 6. `embed.js` (loader, ~vanilla, zero dipendenze)

Responsabilità:
1. Trova tutti gli elementi `[data-ecf-form]`.
2. Per ciascuno: `fetch` `GET /render` → riceve l'HTML.
3. Crea uno **Shadow Root** sul container e inietta `HTML + <style>` (isolamento CSS).
4. Intercetta il `submit`: `preventDefault`, raccoglie i valori, `POST /submit`
   in JSON, mostra `success_message` o gli errori di validazione restituiti dal server.
5. Invia `source_url` (la pagina host) insieme al payload.

Lo script è servito come file statico con cache lunga e versioning via query string (`embed.js?v=1`).

---

## 7. Admin SPA (Vue 3)

- **Form builder:** elenco campi drag-and-drop, editor proprietà campo (label, tipo,
  required, opzioni, validazione), anteprima live del form.
- **Lista form:** stato, uuid, snippet pronto da copiare, n° submission.
- **Viewer submission:** tabella con le submission del form, dettaglio del payload, export CSV.
- Comunica solo via API; autenticazione JWT salvata in memoria/cookie.

---

## 8. Isolamento e sicurezza (scelte adottate)

- **Isolamento:** Shadow DOM — stili del sito host non entrano, i nostri non escono,
  con altezza/responsive naturali.
- **Domini:** in test `allowed_origins` aperto; struttura pronta per attivare la
  whitelist (check su `Origin`) per form. Il `uuid` identifica il form ma **non** è
  un segreto (è pubblico nello snippet).
- **Anti-spam:** nessuno per ora (predisporre un campo honeypot nascosto è gratis e
  lo si potrà attivare dopo senza cambiare l'architettura).
- Validazione e sanificazione sempre lato server; escape dell'HTML in render per
  evitare XSS sui valori delle opzioni.

---

## 9. Deploy su server gestito

- Backend Slim sotto `api.tuodominio.it` (o sottocartella) con rewrite a `public/index.php`.
- `embed.js` servito come statico dallo stesso host (così l'origine API è nota e coerente con CORS).
- Admin Vue buildato in statico (`dist/`), pubblicabile ovunque (anche su un path dedicato).

---

## 10. Prossimi passi proposti

1. Migrazioni + modelli Eloquent (`forms`, `form_fields`, `submissions`).
2. Endpoint `render` + `submit` con `FormRenderer` e `FormValidator`.
3. `embed.js` con Shadow DOM e una pagina di test locale.
4. Admin Vue minimale: crea form → genera snippet → vedi submission.
5. (Dopo) whitelist domini attiva, honeypot, export, auth completa.
