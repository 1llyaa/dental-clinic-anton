# Zubní ordinace a dentální hygiena Lišov — web

Static site (Astro) + tiny PHP admin for the clinic owner. Hosting: Wedos NoLimit (Apache + PHP, no Node).

- `src/` — Astro pages. Content lives in `src/data/*.ts` (team, services, prices, contact).
- `public/admin/` — PHP admin (no dependencies): banner, opening hours, news. See `backend-spec-banner.md`.
- `server-seed/` — `data/` and `uploads/` to upload **once**; never part of `dist/`.
- `Design/` — original design prototype (reference only, not shipped).

The owner's changes are JSON files in `data/` read by the browser at runtime (`src/scripts/runtime.ts`), so editing
the banner, hours or news never needs a rebuild.

## Commands

```sh
npm run dev           # dev server; /data/* served from server-seed/
npm run build         # build + safety check of dist/
npm run check         # TypeScript / Astro
npm test              # JS unit tests (banner date logic, data parsing)
npm run test:php      # PHP unit tests in Docker
npm run serve:php     # full local server like Wedos (Docker), http://localhost:8080
npm run test:e2e      # admin end-to-end smoke test (needs serve:php)
```

Deploy: see [DEPLOY.md](DEPLOY.md).

## Before launch — content from the clinic

Search for `TODO(Anton)`: phone, IČO, address check, portrait and hero photos (`team.ts` `photo`, `index.astro`),
service descriptions, full GDPR text, final domain (`astro.config.mjs` `site`, `public/robots.txt`).
