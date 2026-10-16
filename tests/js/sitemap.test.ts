import { describe, expect, it } from 'vitest';
import { services } from '../../src/data/services';
import { team } from '../../src/data/team';
import {
  SITEMAP_NAMES,
  SITEMAPS,
  buildSitemapIndexXml,
  buildUrlsetXml,
  escapeXml,
  newestDate,
  sitemapPath,
} from '../../src/lib/seo/sitemap';

const site = new URL('https://www.example.cz');

describe('sitemap', () => {
  it('lists every service and team member', () => {
    const paths = Object.values(SITEMAPS)
      .flat()
      .map((e) => e.path);
    for (const s of services) expect(paths).toContain(`/sluzby/${s.id}/`);
    for (const m of team) expect(paths).toContain(`/tym/${m.id}/`);
    expect(new Set(paths).size).toBe(paths.length);
  });

  it('uses real calendar dates and trailing-slash paths', () => {
    for (const { path, lastModified } of Object.values(SITEMAPS).flat()) {
      expect(lastModified).toMatch(/^\d{4}-\d{2}-\d{2}$/);
      expect(Number.isNaN(Date.parse(lastModified))).toBe(false);
      expect(path).toMatch(/^\/([a-z0-9-]+\/)*$/);
    }
  });

  it('builds a urlset with loc then lastmod and no changefreq/priority', () => {
    const xml = buildUrlsetXml(site, 'legal');
    expect(xml).toContain('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');
    expect(xml).toMatch(/<url>\n<loc>https:\/\/www\.example\.cz\/gdpr\/<\/loc>\n<lastmod>/);
    expect(xml).not.toMatch(/changefreq|priority/);
  });

  it('index links every child with its newest date', () => {
    const xml = buildSitemapIndexXml(site);
    for (const name of SITEMAP_NAMES) {
      expect(xml).toContain(
        `<loc>https://www.example.cz${sitemapPath(name)}</loc>\n<lastmod>${newestDate(name)}</lastmod>`,
      );
    }
  });

  it('escapes XML entities', () => {
    expect(escapeXml(`a&b<c>"d'`)).toBe('a&amp;b&lt;c&gt;&quot;d&apos;');
  });
});
