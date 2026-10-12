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
| PUT | `/api/admin/profile` | admin |
| GET / POST | `/api/admin/reports` | admin (list includes drafts / create) |
| GET / PUT / DELETE | `/api/admin/reports/{id}` | admin |
| POST | `/api/admin/reports/{id}/attachments` | admin — multipart `files[]` (+ `caption`, `captionAr`); images, videos, PDF/text/zip |
| PATCH / DELETE | `/api/admin/attachments/{id}` | admin — edit captions / delete (also removes the file) |
| GET | `/api/attachments/{id}/file` | signed link only (the report JSON hands them out); `?download=1` forces download |

Login returns `{ "data": { "token", "expiresAt", "user" } }`; send the token as `Authorization: Bearer <token>`.
Tokens last 12 hours and carry the `admin` ability.

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
