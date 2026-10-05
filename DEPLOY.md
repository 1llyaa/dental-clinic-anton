# Deploy — Wedos NoLimit (manual)

The site is static files plus one small PHP admin. Nothing is deployed by CI; you upload `dist/` yourself.

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
7. Second layer (Basic Auth):
   ```sh
   htpasswd -nbB ordinace 'SOME-LONG-PASSWORD' > .htpasswd     # macOS has htpasswd built in
   ```
   Upload as `admin/.htpasswd`, then in `admin/.htaccess` uncomment the `Auth*` lines and set `AuthUserFile` to the
   path from step 5. Wrong path = error 500 on `/admin/` → fix the path. Keep this edited `.htaccess` locally too
   (see "Every release").
8. Smoke test on the live site: turn the banner on with a photo, check the website within a minute, turn it off.

## Every release

```sh
npm ci && npm run build      # fails if dist/ is unsafe
```

Upload `dist/` contents. Exclude: `data/`, `uploads/`, `admin/config.php`, `admin/.htpasswd`.
If you enabled Basic Auth, also exclude `admin/.htaccess` (otherwise the release replaces it with the version where
Basic Auth is commented out).

Example with `lftp` (mirror, deleting stale files but never the excluded ones):

```sh
lftp -u USER sftp://HOST -e "mirror --reverse --delete --verbose \
  --exclude-glob data/ --exclude-glob uploads/ \
  --exclude-glob admin/config.php --exclude-glob admin/.htpasswd --exclude-glob admin/.htaccess \
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
