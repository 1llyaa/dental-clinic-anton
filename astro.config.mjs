// @ts-check
import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';
import { createReadStream, existsSync, statSync } from 'node:fs';
import { join, normalize } from 'node:path';
import { fileURLToPath } from 'node:url';

const seedDir = fileURLToPath(new URL('./server-seed', import.meta.url));

/**
 * Dev only: serve /data/* and /uploads/* from server-seed/, so the runtime
 * scripts work in `astro dev`. In production these folders exist only on the
 * server and are never part of dist/.
 * @returns {import('vite').Plugin}
 */
function serveSeedData() {
  return {
    name: 'serve-seed-data',
    apply: 'serve',
    configureServer(server) {
      server.middlewares.use((req, res, next) => {
        const url = (req.url || '').split('?')[0];
        if (!url.startsWith('/data/') && !url.startsWith('/uploads/')) return next();
        const file = normalize(join(seedDir, decodeURIComponent(url)));
        if (!file.startsWith(seedDir) || !existsSync(file) || !statSync(file).isFile())
          return next();
        if (file.endsWith('.json'))
          res.setHeader('Content-Type', 'application/json; charset=utf-8');
        createReadStream(file).pipe(res);
      });
    },
  };
}

export default defineConfig({
  site: 'https://www.zubnilisov.cz',
  trailingSlash: 'always',
  build: { format: 'directory' },
  integrations: [sitemap({ filter: (page) => !page.includes('/admin/') })],
  vite: { plugins: [serveSeedData()] },
});
