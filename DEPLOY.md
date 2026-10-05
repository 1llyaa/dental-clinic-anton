# Deploy — Wedos NoLimit

The site is static files plus one small PHP admin. Every push to `main` deploys automatically (GitHub Actions, FTPS)
once build and tests pass. The first deploy is manual, see below.

## What must never be overwritten on the server

| Path on server       | Owner          | In `dist/`? |
| -------------------- | -------------- | ----------- |
| `data/`              | admin (client) | never       |
| `uploads/`           | admin (client) | never       |
| `admin/config.php`   | you, once      | never       |
| `admin/.htpasswd`    | you, once      | never       |

`npm run build` runs `scripts/guard-dist.mjs` and fails if any of these ended up in `dist/`. Still, configure your
upload tool to **exclude** them, so a sync with "delete remote files" can't remove them.

## First deploy

1. **Wedos admin**: set PHP 8.2+ for the domain, enable Let's Encrypt (HTTPS). The root `.htaccess` redirects to HTTPS,
   so enable the certificate before the first upload.
2. `npm ci && npm run build`
3. Upload the **contents** of `dist/` to the web root (e.g. `/www/domains/zubnilisov.cz/`), including the `.htaccess`
   files (hidden files — check that your client shows/uploads them).
4. Upload once, from `server-seed/`: `data/` (with `.htaccess`, `backups/`, `private/`) and `uploads/` (with `.htaccess`).
5. Upload `tools/server-check.php` as `admin/check.php`, open `https://<domain>/admin/check.php`.
   Everything except "exif" must be OK. Note the printed `.htpasswd` path. **Delete `admin/check.php` afterwards.**
   If a folder is not writable, set `data/`, `data/backups/`, `data/private/`, `uploads/` to `0755` (or `0775`).
6. Copy `admin/config.example.php` → `admin/config.php`, upload it, open `https://<domain>/admin/`. With no password
   configured, the page lets you type a password and prints the `'password_hash' => '…'` line. Paste it into
   `config.php`, upload again. Log in.
7. Optional second layer (Basic Auth):
   ```sh
   htpasswd -nbB ordinace 'SOME-LONG-PASSWORD' > .htpasswd     # macOS has htpasswd built in
   ```
   Upload as `admin/.htpasswd` (never deployed by CI). Then in the repo's `public/admin/.htaccess` uncomment the
   `Auth*` lines, set `AuthUserFile` to the path from step 5 and push to `main` — the deploy ships it. Wrong path =
   error 500 on `/admin/` → fix the path and push again.
8. Smoke test on the live site: turn the banner on with a photo, check the website within a minute, turn it off.

## Every release (automatic)

Merge or push to `main`. CI (`.github/workflows/ci.yml`) builds, tests, runs `guard-dist`, then the `deploy` job
mirrors `dist/` into the web root over FTPS with `lftp mirror --reverse --delete`. Other branches and PRs never deploy.

Server layout (FTP root):

```
www/
├─ domains/
│  └─ zubnilisov.cz/   ← deploy target; everything here comes from dist/, except:
│     ├─ data/         ← admin content, never touched
│     ├─ uploads/      ← admin photos, never touched
│     └─ admin/config.php, admin/.htpasswd  ← set up once on the server, never touched
└─ subdom/             ← outside the target, never touched
```

`admin/.htaccess` is deployed from the repo like everything else (see step 7 for Basic Auth).

### One-time setup

1. GitHub → Settings → Environments → New environment `production`. Add secrets:
   - `FTP_HOST` — Wedos FTP server
   - `FTP_USER`, `FTP_PASSWORD` — preferably a dedicated FTP account limited to this domain
   - `FTP_REMOTE_DIR` — `/www/domains/zubnilisov.cz/`
2. GitHub → Settings → Branches: protect `main`, require the `site` and `php` checks.
3. Actions → CI → Run workflow on `main` with **dry run** checked. Read the log: only paths under the web root, no
   deletes, nothing in `data/`, `uploads/` or the excluded admin files. Then merge/push to `main` for the real deploy.

Actions → CI → Run workflow (dry run unchecked) redeploys the current `main` without a new commit.

### Manual fallback

```sh
npm ci && npm run build      # fails if dist/ is unsafe
lftp -u USER ftp://HOST -e "set ftp:ssl-force true; mirror --reverse --delete --verbose \
  --exclude-glob data/ --exclude-glob uploads/ \
  --exclude-glob admin/config.php --exclude-glob admin/.htpasswd \
  dist/ /www/domains/zubnilisov.cz/; quit"
```

FileZilla / WinSCP: use plain upload (overwrite), not synchronise-with-delete, unless the same excludes are set
as a filter.

## Backups

- Every save in the admin keeps the previous version in `data/backups/` (last 10 per file).
- Wedos backs up the hosting daily. Optionally download `data/*.json` now and then.
- To restore: copy a file from `data/backups/` over `data/banner.json` (or hours/news) via FTP.

## Local server (same setup as Wedos)

```sh
npm run serve:php     # Docker: Apache + PHP 8.2 + GD with .htaccess, http://localhost:8080
npm run test:e2e      # admin smoke test against it
```
