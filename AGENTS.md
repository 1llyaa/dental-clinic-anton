# Agent guide

Instructions for coding agents (Claude Code, Codex, Cursor, …) working in this repo. Project overview: [README.md](README.md).
Deploy details: [DEPLOY.md](DEPLOY.md).

## Golden rule: `main` is production

Every push to `main` deploys to the live site (`.github/workflows/ci.yml`, job `deploy`). So:

- Never commit or push directly to `main`. Always work on a branch and open a PR.
- Never merge a PR yourself unless the user explicitly asks. Merging = deploying.
- Never force-push to `main`.

## Workflow: branch → commit → PR

1. Start from up-to-date `main`:
   ```sh
   git fetch origin
   git checkout -b <type>/<short-kebab-name> origin/main
   ```
   Branch types: `feat/`, `fix/`, `docs/`, `ci/`, `chore/` (e.g. `feat/hero-photo`, `ci/ftp-deploy`).
2. Make the change. Before committing, run the same checks as CI:
   ```sh
   npm run format:check   # or `npm run format` to fix
   npm run check
   npm test
   npm run build          # includes guard-dist
   ```
   Touched PHP (`public/admin/`, `tools/`)? Also `npm run test:php` (needs Docker).
3. Commit with [Conventional Commits](https://www.conventionalcommits.org/): `feat: …`, `fix: …`, `docs: …`, `ci: …`,
   `chore: …`. Imperative, lowercase, no trailing period. Body explains _why_ when not obvious.
4. Push and open the PR against `main`:
   ```sh
   git push -u origin HEAD
   gh pr create --base main --title "<same style as commit>" --body-file <file>
   ```
   PR body format:

   ```markdown
   ## Summary

   - What changed and why (bullets).

   ## Test plan

   - [x] `npm run format:check`, `npm run check`, `npm test`
   - [x] `npm run build` passes
   - [ ] Anything not verified — say so honestly
   ```

5. Things the user must do by hand (secrets, Wedos admin, server files) go in the PR body or a PR comment
   (`gh pr comment <n> --body …`).

## CI / deploy

- Check runs: `gh run list --branch main -L 5`, details: `gh run view <id>`, logs: `gh run view <id> --log-failed`.
- Run stuck in `queued` for minutes = usually a GitHub Actions incident, not our code. Check
  https://www.githubstatus.com. Re-run a cancelled run: `gh run rerun <id>`.
- Manual redeploy or dry run: `gh workflow run CI --ref main -f dry_run=true` (or `false`).
- Deploy secrets live in the GitHub environment `production`: `FTP_HOST`, `FTP_USER`, `FTP_PASSWORD`, `FTP_REMOTE_DIR`.
  Never print them or put them in files.

## Never touch on the server / never ship in `dist/`

`data/`, `uploads/`, `admin/config.php`, `admin/.htpasswd` belong to the clinic owner on the server. `guard-dist`
fails the build if they end up in `dist/`, and the deploy excludes them. If you change that list, update both
`scripts/guard-dist.mjs` and the `--exclude-glob` flags in `.github/workflows/ci.yml` (and the fallback in DEPLOY.md).

## Content

Site texts and data are in `src/data/*.ts`. Placeholders waiting on the clinic are marked `TODO(Anton)` — don't invent
real-world facts (phone, IČO, prices, names); leave the TODO.
