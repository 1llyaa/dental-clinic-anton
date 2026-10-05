// Fails the build if dist/ contains anything that would overwrite content the
// clinic owner manages on the server. dist/ is uploaded wholesale via SFTP.
import { existsSync, readdirSync } from 'node:fs';
import { join } from 'node:path';

const dist = new URL('../dist/', import.meta.url).pathname;
const problems = [];

const forbidden = ['data', 'uploads', 'admin/config.php', 'admin/.htpasswd'];
for (const rel of forbidden) {
  if (existsSync(join(dist, rel))) problems.push(rel);
}

if (!existsSync(join(dist, 'admin/index.php'))) problems.push('admin/index.php is missing');
if (!existsSync(join(dist, '.htaccess'))) problems.push('.htaccess is missing');

const walk = (dir) =>
  readdirSync(dir, { withFileTypes: true }).flatMap((e) =>
    e.isDirectory() ? walk(join(dir, e.name)) : [join(dir, e.name)],
  );
for (const f of walk(dist)) {
  const rel = f.slice(dist.length);
  if (/(^|\/)(banner|hours|news)\.json$/.test(rel)) problems.push(rel);
}

if (problems.length) {
  console.error(
    '\n✗ guard-dist: dist/ must not contain / must contain:\n  - ' + problems.join('\n  - '),
  );
  console.error('  Uploading this dist/ could overwrite what the clinic set in the admin.\n');
  process.exit(1);
}
console.log('✓ guard-dist: dist/ is safe to upload');
