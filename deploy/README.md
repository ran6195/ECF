# Deploy su collaudo — edysma.net/testECF

Pacchetto **pre-buildato** (niente shell richiesta sul server): si costruisce in
locale e si carica via FTP/SFTP. Admin e backend stanno nella **stessa
sottocartella** `/testECF`:

```
https://edysma.net/testECF/            → pannello admin (SPA)
https://edysma.net/testECF/embed.js    → loader per i siti terzi
https://edysma.net/testECF/api/...     → API (gestite da Slim)
```

## Struttura caricata sul server

```
testECF/                 (radice della sottocartella)
├─ .htaccess             serve i file statici; instrada il resto a index.php
├─ index.html            admin SPA (build Vite)
├─ assets/               JS/CSS dell'admin
├─ embed.js              loader
├─ index.php             front controller Slim (base path = /testECF)
└─ app/                  codice backend NON servito (protetto da app/.htaccess)
   ├─ src/  vendor/  database/
   ├─ .env               configurazione di collaudo (DB, JWT, ...)
   └─ .htaccess          "Require all denied"
```

L'admin parla con l'API sulla **stessa origine** ⇒ nessun problema di CORS. Il
controllo origini resta attivo solo per gli **embed** sui siti terzi (whitelist
`allowed_origins` per form).

---

## Passi

### 1. Configura l'ambiente di collaudo
```bash
cp backend/.env.production.example backend/.env.production
# poi compila: DB_DATABASE / DB_USERNAME / DB_PASSWORD (dal pannello hosting),
# JWT_SECRET (stringa lunga e casuale), ADMIN_EMAIL, ADMIN_PASSWORD.
```
> `APP_BASE_PATH=testECF` e `APP_URL=https://edysma.net/testECF` sono già impostati.
> Se il DB di collaudo NON è su `localhost`, aggiorna anche `DB_HOST`.

### 2. Costruisci il pacchetto
```bash
bash deploy/build.sh
```
Produce:
- `deploy/build/testECF/` → contenuto da caricare nella sottocartella;
- `deploy/build/database.sql` → schema + admin + form "Contatti".

### 3. Inizializza il database (una tantum)
Apri **phpMyAdmin** sul collaudo, seleziona il database di collaudo e importa
`deploy/build/database.sql`. Crea le tabelle e l'utente admin.
> Lo script usa `CREATE TABLE IF NOT EXISTS`: su DB già popolato non sovrascrive
> nulla (per ripartire da zero, svuota prima le tabelle).

### 4. Carica i file
Via FTP/SFTP, copia **tutto il contenuto** di `deploy/build/testECF/` dentro la
cartella `testECF/` del server (inclusi `.htaccess` e la cartella `app/`).
> Verifica che il client FTP carichi anche i file nascosti (`.htaccess`, `app/.env`).

### 5. Verifica
- `https://edysma.net/testECF/api/embed/<UUID>/render` → HTML del form
  (l'`<UUID>` è in coda a `database.sql`).
- `https://edysma.net/testECF/` → login admin (credenziali da `.env.production`).
- Nella lista form copia lo **snippet**: punterà già a
  `https://edysma.net/testECF/embed.js` con `data-api=https://edysma.net/testECF`.

### 6. Ripristina l'ambiente locale
`build.sh` esegue `composer install --no-dev` (rimuove PHPUnit). Per tornare a
sviluppare in locale:
```bash
( cd backend && composer install )
```

---

## Aggiornamenti successivi
Ripeti i passi 2 e 4 (ricostruisci e ricarica). La **3** serve solo la prima
volta o se cambia lo schema. Se modifichi lo schema, aggiorna `app/` e applica le
variazioni al DB (nuovo import mirato o ALTER via phpMyAdmin).

### Migrazioni schema su DB esistente
Le variazioni di schema per DB già popolati stanno in `deploy/migrations/`. Da
applicare **una volta** via phpMyAdmin, in ordine:

- `2026-06-18-add-forms-style.sql` — aggiunge `forms.style` (stile/tema per form).

## Cambiare sottocartella
Modifica `BASE_PATH` in cima a `deploy/build.sh` e `APP_BASE_PATH` +
`APP_URL` in `backend/.env.production` (e `VITE_API_BASE_URL` in
`admin/.env.production`), poi ricostruisci.
