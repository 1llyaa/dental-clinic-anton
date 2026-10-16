# SEO, GEO and legal checklist

Goal: whoever searches for the clinic (Google, Seznam, Mapy, ChatGPT/Claude/Perplexity/Gemini) gets the right
address, phone and hours, and a link here. The clinic is not marketing for new patients, so this is about being
**findable and correct**, not ranking for generic terms.

## What the code does

| Piece                         | Where                                         | Notes                                                                                                                                                   |
| ----------------------------- | --------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Origin / domain               | `astro.config.mjs` `site`                     | Single source for canonicals, sitemaps, robots.txt, llms.txt and JSON-LD. Change it there only.                                                          |
| Sitemap index + children      | `src/lib/seo/sitemap.ts`, `src/pages/*.xml.ts` | `/sitemap_index.xml` → `page-`, `service-`, `team-`, `legal-sitemap.xml`. No changefreq/priority (Google ignores them).                                  |
| `robots.txt`                  | `src/pages/robots.txt.ts`                     | Allows everything except `/admin/`, `/data/`. AI crawlers are named and allowed on purpose (GEO).                                                       |
| `llms.txt`                    | `src/pages/llms.txt.ts`                       | Markdown summary for AI assistants (most don't run JS). Hours left out — they change at runtime.                                                        |
| Structured data               | `src/lib/seo/jsonld.ts`, `Base.astro`         | One `@graph`: `Dentist` + `WebSite` on every page, `BreadcrumbList` on detail pages, `Person` on team pages. No `openingHoursSpecification` (runtime). |
| Head meta                     | `src/layouts/Base.astro`                      | Canonical, description, OG + `og:image` (hero, 1200×630), Twitter card, icons, manifest. `noindex` prop (used by 404).                                  |
| Canonical host, crawl headers | `public/.htaccess`                            | apex → `www` + HTTPS in one 301; `X-Robots-Tag: noindex` on `/data/`; sitemaps/robots served `no-cache`.                                                |
| Build guard                   | `scripts/check-seo.mjs` (runs in `npm run build`) | Fails if a page is missing from the sitemaps (or vice versa), canonical ≠ sitemap URL, a `noindex` page is listed, or a page lacks title/description/one `<h1>`. |

### When you change content

- **Edited a page's content** (text, data in `src/data/*.ts`)? Bump its date in `src/lib/seo/sitemap.ts`. Only real
  content changes count — Google ignores `<lastmod>` that changes on every build.
- **Added a service or team member**? Add its id to `SERVICE_DATES` / `TEAM_DATES`. The build fails until you do.
- **Added a page**? Add it to `PAGE_DATES` and to the right group in `SITEMAPS`. `check-seo` fails until you do.
- **Hidden page** (thank-you, test)? Pass `noindex` to `<Base>`; keep it out of `SITEMAPS`.

## Off-site — owner tasks (biggest impact for a local clinic)

Do these after launch, with the final phone/IČO. Name, address and phone must be **identical everywhere**.

1. **Google Business Profile** — claim/verify, category "Zubní lékař", hours, photos, website URL. This is where Google
   Maps and Gemini take hours from.
2. **Firmy.cz** (Seznam) — claim the listing; it feeds Mapy.com and Seznam search.
3. **Google Search Console** + **Seznam Webmaster** — verify, submit `https://www.zubnilisov.cz/sitemap_index.xml`.
4. **Bing Webmaster Tools** — import from Search Console, submit the sitemap. Bing powers ChatGPT search and Copilot.
5. **Apple Business Connect** — Apple Maps / Siri.
6. Check the ČSK directory (dent.cz), NRPZS and insurers' provider lists match the site.
7. Put the GBP and Firmy.cz URLs into `site.sameAs` (`src/data/site.ts`); add `site.geo` (entrance coordinates).

## Legal — to verify with the clinic (not legal advice)

| #   | Requirement                                                                                                                                                                                     | Status / action                                                                                                                                                                         |
| --- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | **§ 435 zák. 89/2012 Sb. (OZ)** — a business website shows name/firm, seat, IČO and registry entry (s.r.o.: court, section, insert).                                                            | IČO is a placeholder; footer and Impressum show the brand, not the legal entity. **TODO(Anton):** legal name (MUDr. … / … s.r.o.), seat, IČO, registry entry.                            |
| 2   | **Regulated profession** (Directive 2000/31/EC art. 5) — professional title + state, chamber, professional rules; plus who issued the health-services authorisation.                           | Missing. Recommended Impressum lines: "zubní lékař (ČR), člen České stomatologické komory, oprávnění k poskytování zdravotních služeb vydal Krajský úřad Jihočeského kraje".               |
| 3   | **GDPR art. 13** privacy notice — controller (the legal entity) and contact, DPO only if appointed, purposes + legal bases (art. 6, 9(2)(h)), recipients, retention (vyhl. 98/2012 Sb.), rights, ÚOOÚ. | Short placeholder (`src/data/gdpr.ts`). Full text from the clinic. Web part should mention Wedos server logs, e-mail contact, the OpenStreetMap map (IP to OSMF, UK — adequacy decision), localStorage. |
| 4   | **Cookies / device storage** (§ 89 odst. 3 zák. 127/2005 Sb.) — consent for anything not strictly necessary.                                                                                    | OK today: no analytics; localStorage only remembers a dismissed banner (user-requested). Optional: click-to-load map. Any future analytics → cookieless or consent-gated.               |
| 5   | **Price list** (§ 45 odst. 2 písm. a, b zák. 372/2011 Sb.) — price list of non-covered services accessible to patients (in the clinic).                                                         | Web ceník is "orientační" — keep it consistent with the one in the waiting room.                                                                                                       |
| 6   | **Advertising** — ČSK professional rules (accurate, not misleading, confidentiality; website text counts as presentation, cf. NSS 5 As 294/2023) and zák. 40/1995 Sb.                          | Current text is factual. Keep it: no testimonials, guarantees, superlatives, or patient photos without written consent.                                                                  |
| 7   | **Staff names and portraits** (§ 84–86 OZ + GDPR).                                                                                                                                              | Get written consent from each team member before publishing photos/bios.                                                                                                                |
| 8   | **Accessibility** (zák. 424/2023 Sb., EAA) — microenterprises (<10 staff, ≤ €2 M) are exempt; site is not an e-commerce service.                                                                | Not mandatory. Keep WCAG 2.2 AA as good practice. Re-check when online booking arrives.                                                                                                 |
| 9   | **Online booking** (planned) — health data (art. 9 GDPR), processing agreement with the booking vendor, minimal fields.                                                                         | Flag when it is built.                                                                                                                                                                  |

Not applicable: whistleblowing (< 50 staff), NIS2, DSA.

## Nice to have (later)

- `openingHoursSpecification` injected at runtime from `hours.json` (Google renders JS; most AI crawlers don't).
- List of contracted insurers (VZP, VoZP, ČPZP, OZP, ZPŠ, ZPMV, RBP) — people search "zubař VZP Lišov". Needs Anton.
- Body text for `zachovna`, `protetika`, `deti` — thin pages rank poorly.
- `isAcceptingNewPatients` in JSON-LD once the clinic decides what to say publicly.
- Per-person `og:image` once portraits exist.
- IndexNow ping after deploy (Bing, Seznam, Yandex) for faster recrawl.
- Visible FAQ (insurance, emergencies, first visit) with `FAQPage` markup — strong for GEO, but new wording, so the clinic's call.
