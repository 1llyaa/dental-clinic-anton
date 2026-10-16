import { services } from '../../data/services';
import { team } from '../../data/team';

// The sitemap is an index plus one child per content type:
//
//   /sitemap_index.xml -> /page-sitemap.xml, /service-sitemap.xml,
//                         /team-sitemap.xml, /legal-sitemap.xml
//
// A new section becomes another key in SITEMAPS; the endpoint in
// src/pages/[name]-sitemap.xml.ts picks it up without further changes.
// scripts/check-seo.mjs fails the build when a built page is missing here
// (or a listed path has no page), so the list cannot silently drift.

export type PageEntry = { path: string; lastModified: string };

// Google only uses <lastmod> when it is "consistently and verifiably accurate",
// so it is the date of the last real change to the page's content (text, data
// in src/data/*.ts, structured data) — not the build time. Bump it by hand when
// that content changes; a dependency bump or a CSS tweak does not count.
const PAGE_DATES = {
  '/': '2026-10-16',
  '/o-nas/': '2026-10-16',
  '/objednani/': '2026-10-16',
  '/kontakt/': '2026-10-16',
  '/aktuality/': '2026-10-16',
  '/cenik/': '2026-10-16',
  '/sluzby/': '2026-10-16',
  '/tym/': '2026-10-16',
  '/gdpr/': '2026-10-16',
};

// Keyed by the id in services.ts / team.ts.
const SERVICE_DATES: Record<string, string> = {
  prevence: '2026-10-16',
  hygiena: '2026-10-16',
  estetika: '2026-10-16',
  zachovna: '2026-10-16',
  protetika: '2026-10-16',
  deti: '2026-10-16',
};
const TEAM_DATES: Record<string, string> = {
  anton: '2026-10-16',
  elena: '2026-10-16',
  lenka: '2026-10-16',
};

function dated(dates: Record<string, string>, id: string, path: string): PageEntry {
  const lastModified = dates[id];
  if (!lastModified) throw new Error(`sitemap: no lastModified for "${id}" (${path})`);
  return { path, lastModified };
}

const page = (path: keyof typeof PAGE_DATES): PageEntry => ({
  path,
  lastModified: PAGE_DATES[path],
});

export const SITEMAPS = {
  page: [
    page('/'),
    page('/o-nas/'),
    page('/objednani/'),
    page('/kontakt/'),
    page('/aktuality/'),
    page('/cenik/'),
  ],
  service: [
    page('/sluzby/'),
    ...services.map((s) => dated(SERVICE_DATES, s.id, `/sluzby/${s.id}/`)),
  ],
  team: [page('/tym/'), ...team.map((m) => dated(TEAM_DATES, m.id, `/tym/${m.id}/`))],
  legal: [page('/gdpr/')],
} satisfies Record<string, PageEntry[]>;

export type SitemapName = keyof typeof SITEMAPS;

export const SITEMAP_NAMES = Object.keys(SITEMAPS) as SitemapName[];

/** Public path of a child sitemap, e.g. `page` -> `/page-sitemap.xml`. */
export function sitemapPath(name: SitemapName): string {
  return `/${name}-sitemap.xml`;
}

// The protocol requires entity-escaped values. Today's paths are plain ASCII
// slugs, but a future slug with `&` would otherwise break every parser.
export function escapeXml(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&apos;');
}

// `site` is the origin from astro.config.mjs (`context.site` in endpoints), so
// the domain lives in exactly one place.
function absolute(site: URL, path: string): string {
  return escapeXml(new URL(path, site).href);
}

// <changefreq> and <priority> are omitted on purpose: Google ignores both.
export function buildUrlsetXml(site: URL, name: SitemapName): string {
  const urls = SITEMAPS[name].map(({ path, lastModified }) =>
    [
      '<url>',
      `<loc>${absolute(site, path)}</loc>`,
      `<lastmod>${lastModified}</lastmod>`,
      '</url>',
    ].join('\n'),
  );

  return [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
    ...urls,
    '</urlset>',
    '',
  ].join('\n');
}

/** Newest date in a child sitemap — that child's <lastmod> in the index. */
export function newestDate(name: SitemapName): string {
  return SITEMAPS[name]
    .map((entry) => entry.lastModified)
    .reduce((newest, date) => (date > newest ? date : newest));
}

export function buildSitemapIndexXml(site: URL): string {
  const entries = SITEMAP_NAMES.map((name) =>
    [
      '<sitemap>',
      `<loc>${absolute(site, sitemapPath(name))}</loc>`,
      `<lastmod>${newestDate(name)}</lastmod>`,
      '</sitemap>',
    ].join('\n'),
  );

  return [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
    ...entries,
    '</sitemapindex>',
    '',
  ].join('\n');
}
