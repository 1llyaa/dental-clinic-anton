import type { APIRoute } from 'astro';
import { nav, site } from '../data/site';
import { services } from '../data/services';
import { team } from '../data/team';

// /llms.txt (llmstxt.org): a plain Markdown summary for AI assistants, most of
// which do not run JavaScript. Built from the same data as the pages.
// Opening hours are left out on purpose — they change in the admin at runtime.
export const GET: APIRoute = ({ site: origin }) => {
  const url = (path: string) => new URL(path, origin).href;
  const body = [
    `# ${site.name}`,
    '',
    `> ${site.description}`,
    '',
    `- Adresa: ${site.address}`,
    `- Telefon: ${site.phone}`,
    `- E-mail: ${site.email}`,
    `- Objednání: telefonicky — ${url('/objednani/')}`,
    `- Ordinační hodiny (aktuální): ${url('/kontakt/')}`,
    '',
    '## Služby',
    '',
    ...services.map((s) => `- [${s.title}](${url(`/sluzby/${s.id}/`)}): ${s.lead}`),
    '',
    '## Tým',
    '',
    ...team.map((m) => `- [${m.name}](${url(`/tym/${m.id}/`)}): ${m.role}`),
    '',
    '## Stránky',
    '',
    ...nav.map((n) => `- [${n.label}](${url(n.href)})`),
    `- [Ochrana osobních údajů](${url('/gdpr/')})`,
    '',
  ].join('\n');

  return new Response(body, { headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
};
