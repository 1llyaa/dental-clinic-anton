// Fails the build if the SEO invariants of dist/ break: every indexable page is
// in exactly one child sitemap (and nothing else is), canonicals match the
// sitemap, noindex pages stay out, robots.txt points at the index, and every
// page has a title, a description and a single <h1>.
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

const dist = new URL('../dist/', import.meta.url).pathname;
const problems = [];
const read = (rel) => readFileSync(join(dist, rel), 'utf8');
const locs = (xml) => [...xml.matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => m[1]);

// --- sitemap index and children
if (!existsSync(join(dist, 'sitemap_index.xml'))) {
  console.error('\n✗ check-seo: dist/sitemap_index.xml is missing\n');
  process.exit(1);
}
const index = read('sitemap_index.xml');
const childUrls = locs(index);
if (!childUrls.length) problems.push('sitemap_index.xml lists no child sitemaps');

const origin = new URL(childUrls[0] ?? 'https://invalid.invalid/').origin;
if (!origin.startsWith('https://') || /localhost|127\.0\.0\.1/.test(origin))
  problems.push(`sitemap origin is not a public https origin: ${origin}`);

/** sitemap URL -> name of the child sitemap that lists it */
const listed = new Map();
for (const childUrl of childUrls) {
  const rel = new URL(childUrl).pathname.slice(1);
  if (!existsSync(join(dist, rel))) {
    problems.push(`index lists ${rel}, which was not built`);
    continue;
  }
  const xml = read(rel);
  if (!xml.startsWith('<?xml') || !/<\/urlset>\s*$/.test(xml))
    problems.push(`${rel}: not a urlset`);
  const urls = locs(xml);
  if ((xml.match(/<lastmod>\d{4}-\d{2}-\d{2}<\/lastmod>/g) ?? []).length !== urls.length)
    problems.push(`${rel}: every <url> needs a <lastmod> (YYYY-MM-DD)`);
  for (const url of urls) {
    if (!url.startsWith(origin + '/')) problems.push(`${rel}: ${url} is not on ${origin}`);
    if (listed.has(url)) problems.push(`${url} is in both ${listed.get(url)} and ${rel}`);
    listed.set(url, rel);
  }
}

// --- built pages
const walk = (dir) =>
  readdirSync(dir, { withFileTypes: true }).flatMap((e) =>
    e.isDirectory() ? walk(join(dir, e.name)) : [join(dir, e.name)],
  );
const pages = walk(dist)
  .map((f) => f.slice(dist.length))
  .filter((rel) => rel.endsWith('.html') && !rel.startsWith('admin/'));

const attr = (html, re) => html.match(re)?.[1];
for (const rel of pages) {
  const html = read(rel);
  const url = origin + '/' + rel.replace(/index\.html$/, '');
  const noindex = /<meta name="robots" content="[^"]*noindex/.test(html);

  if (!attr(html, /<title>([^<]+)<\/title>/)) problems.push(`${rel}: empty <title>`);
  if (!attr(html, /<meta name="description" content="([^"]+)"/))
    problems.push(`${rel}: missing meta description`);
  const h1s = (html.match(/<h1[\s>]/g) ?? []).length;
  if (h1s !== 1) problems.push(`${rel}: ${h1s} <h1> elements (want 1)`);

  if (noindex) {
    if (listed.has(url)) problems.push(`${rel} is noindex but listed in ${listed.get(url)}`);
    continue;
  }
  if (rel === '404.html') problems.push('404.html must be noindex');
  if (!listed.delete(url)) problems.push(`${url} is not in any sitemap (src/lib/seo/sitemap.ts)`);
  const canonical = attr(html, /<link rel="canonical" href="([^"]+)"/);
  if (canonical !== url) problems.push(`${rel}: canonical ${canonical} ≠ ${url}`);
}
for (const [url, rel] of listed) problems.push(`${rel} lists ${url}, but no such page was built`);

// --- robots.txt and llms.txt
const robots = existsSync(join(dist, 'robots.txt')) ? read('robots.txt') : '';
if (!robots.includes(`Sitemap: ${origin}/sitemap_index.xml`))
  problems.push('robots.txt must point at sitemap_index.xml');
if (/^Disallow: \/\s*$/m.test(robots)) problems.push('robots.txt disallows the whole site');
if (!existsSync(join(dist, 'llms.txt'))) problems.push('llms.txt is missing');

if (problems.length) {
  console.error('\n✗ check-seo:\n  - ' + problems.join('\n  - ') + '\n');
  process.exit(1);
}
console.log(`✓ check-seo: ${pages.length} pages, ${childUrls.length} sitemaps consistent`);
