# Laravel API + admin backend

Files in this folder are an **overlay**: they go on top of a fresh Laravel 11/12 app (this package does not ship `vendor/`).

## Install

```bash
cd laravel-api
./install.sh            # creates ../api-server, installs Sanctum, copies these files, migrates + seeds
cd ../api-server && php artisan serve      # http://localhost:8000
```

Windows without Git Bash/WSL — run the same steps by hand:

```bash
composer create-project laravel/laravel api-server
cd api-server
php artisan install:api
# copy the app/ bootstrap/ config/ database/ routes/ folders from laravel-api/ into api-server/ (overwrite when asked)
# add to .env:   ADMIN_EMAIL=you@example.com   ADMIN_PASSWORD=<a long password>   CORS_ALLOWED_ORIGINS=http://localhost:5173
php artisan migrate --seed
php artisan serve
```

Then in the React project's `.env`:

```
VITE_API_PROXY=http://localhost:8000
VITE_API_FALLBACK=false
```

and run `npm run dev`. Open `http://localhost:5173/login`.

## Endpoints

| Method | Path | Access |
|---|---|---|
| GET | `/api/health`, `/api/profile`, `/api/reports`, `/api/reports/{id}` | public (drafts are hidden) |
| POST | `/api/auth/login` | public, 6 attempts / minute |
| GET | `/api/auth/me` · POST `/api/auth/logout` | signed-in |
| POST | `/api/contact` | public, 3 / minute and 10 / hour per IP — a visitor's message sent through the site |
| GET | `/api/admin/messages` · `/api/admin/messages/unread-count` | admin — inbox |
| PATCH / DELETE | `/api/admin/messages/{id}` | admin — `{ "read": true|false }` / delete |
| PUT | `/api/admin/profile` | admin (now also accepts `contact`) |
| GET / POST | `/api/admin/reports` | admin (list includes drafts / create) |
| GET / PUT / DELETE | `/api/admin/reports/{id}` | admin |
| POST | `/api/admin/reports/{id}/attachments` | admin — multipart `files[]` (+ `caption`, `captionAr`); images, videos, PDF/text/zip |
| PATCH / DELETE | `/api/admin/attachments/{id}` | admin — edit captions / delete (also removes the file) |
| GET | `/api/attachments/{id}/file` | signed link only (the report JSON hands them out); `?download=1` forces download |

Login returns `{ "data": { "token", "expiresAt", "user" } }`; send the token as `Authorization: Bearer <token>`.
Tokens last 12 hours and carry the `admin` ability.

## Contact details and messages

- **Contact details** live on the profile: `GET /api/profile` returns `contact: { email, phone, whatsapp, telegram, linkedin, github, x, facebook, instagram, youtube, website }`
  (only the filled-in keys). The admin edits them with `PUT /api/admin/profile`; links must be `http(s)` URLs, phone/WhatsApp are digits (+, spaces, dashes allowed), Telegram is a username.
- **Contact form** — `POST /api/contact` `{ name, email, phone?, subject?, message }` stores the message (the IP is kept only as a keyed hash). A hidden `hp` field is a honeypot:
  if it is filled the API answers `201` but stores nothing. The admin reads, marks read/unread and deletes messages from the **Messages** tab.
  The other channels (e-mail / WhatsApp / Telegram) are handled in the browser: it opens the app with the message pre-filled, using the details above.
- Run `php artisan migrate` once to add `profiles.contact` and the `contact_messages` table. Tests: `php artisan test --filter=ContactTest`.

## Secret login link

The admin login is hidden behind `https://your-site/0xGlitch?secret=<ADMIN_LOGIN_SECRET>`.

1. Set `ADMIN_LOGIN_SECRET` in `.env` (e.g. `openssl rand -hex 24`). The secret must be **at least 32 characters** (shorter => login locked). Optional: `ADMIN_LOGIN_GATE_MINUTES` (default 10).
2. Opening the link sends the secret once to `POST /api/auth/gate`; the API answers with a signed HttpOnly, SameSite=Strict cookie
   (`__Host-login_gate`, 10 min, bound to the browser's User-Agent) and the front-end removes the secret from the address bar.
   Prefer the **fragment form** `/0xGlitch#secret=<token>`: a `#fragment` is never sent to any server, so it can't end up in Vercel/Railway access logs (`?secret=` still works).
3. Only with that cookie does `POST /api/auth/login` work: the `login.gate` middleware answers a plain 404 *before* validation and rate limiting. The gate also lives server-side (cache), so closing it revokes even a copied cookie. A successful sign-in, a logout or 5 wrong passwords close it.
4. Extra guards: over HTTPS only the `__Host-` cookies are accepted (no cookie tossing), one bcrypt check always runs (no e-mail timing leak), and 20 wrong passwords per hour across all gates lock the sign-in (reachable only with the secret, so strangers cannot use it to lock you out).

If `ADMIN_LOGIN_SECRET` is empty the login is locked for everyone. Changing it invalidates every open gate. Treat the link like a password.

## Attachments (images, videos, files)

Every report can carry attachments. They show up in the report JSON as `attachments: [{ id, kind, name, mime, size, caption, ar:{caption}, url, downloadUrl }]`
(`kind` = `image | video | file`) and the React site displays them inside the page (gallery, video player, PDF/text viewer).

- Allowed: `jpg jpeg png gif webp` · `mp4 m4v webm mov` · `pdf txt log md csv json har zip`. SVG/HTML/JS/PHP are refused on purpose. The file *content* is checked, not just the extension.
- Files are stored on the **private** disk (`storage/app/private/report-attachments/<report-id>/`), never directly under `public/`.
  They are served by `AttachmentController` through **short-lived signed links** (default 12 h), so files of a draft report cannot be opened by guessing a URL, and HTTP Range works (video seeking).
- Limits: `REPORT_UPLOAD_MAX_MB` (default 100) in `.env` — **and** raise PHP's own caps, otherwise big uploads fail before Laravel sees them:
  `upload_max_filesize = 128M`, `post_max_size = 130M` in `php.ini` (nginx: `client_max_body_size 130m;`). Optional: `REPORT_FILE_URL_TTL_HOURS`.
- Deleting an attachment or a report deletes the stored files too.
- If you already installed an earlier version, run `php artisan migrate` once to create the `report_attachments` table.

## Security notes

- Only users with `is_admin = true` can sign in; `is_admin` is not mass-assignable. The seeder creates one admin from `ADMIN_EMAIL` / `ADMIN_PASSWORD` and never overwrites an existing password.
- All input is validated in Form Requests; unknown fields are dropped.
- Serve the API over HTTPS in production, set `APP_ENV=production`, `APP_DEBUG=false`, and list only your real site in `CORS_ALLOWED_ORIGINS`.
- Schedule `php artisan sanctum:prune-expired --hours=24` daily to clear old tokens.
- Reset the admin password: `php artisan tinker` → `User::where('email','you@example.com')->first()->update(['password'=>'new-long-password'])`.

## Deploying

Frontend on Vercel + API on any PHP host: set the frontend variables `VITE_API_URL=https://api.your-domain.com/api` and `VITE_API_FALLBACK=false`.
(The Node functions in the React project's `api/` folder are read-only sample data and have no login — use this Laravel API for the admin panel.)


## Encryption at rest

- **Uploaded files** are stored encrypted (AES-256-GCM, one key per file derived from `APP_KEY`, see `app/Support/FileVault.php`) and decrypted on the fly when a signed link is opened. Turn off with `REPORT_ENCRYPT_FILES=false`.
- **Contact messages** (name, e-mail, phone, subject, message) are stored encrypted (`app/Casts/SafeEncrypted.php`).
- The migration `2026_10_14_000000_encrypt_contact_messages_and_files` converts existing data; `php artisan data:encrypt` does the same and is safe to re-run.
- **Back up `APP_KEY`.** Without it the encrypted data cannot be read. When rotating the key, move the old one into `APP_PREVIOUS_KEYS` (comma separated) before changing `APP_KEY`.

## Database

The project uses **MySQL** only (`DB_CONNECTION=mysql`, plus `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` in `.env`).
To run the tests, create an empty database called `portfolio_test` (or change `DB_DATABASE` in `phpunit.xml`): the tests wipe it.
