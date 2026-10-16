import type { APIRoute } from 'astro';

// /admin/ is the owner's PHP admin; /data/ holds the runtime JSON the pages
// read (banner, hours, news) — its content is already on the pages themselves.
const DISALLOW = ['/admin/', '/data/'];

// Named explicitly so the door stays open for AI answer engines and assistants
// (GEO), not only classic search bots. Each named group replaces `*` for that
// bot, so every group repeats the same rules.
const AI_USER_AGENTS = [
  'GPTBot',
  'OAI-SearchBot',
  'ChatGPT-User',
  'ClaudeBot',
  'Claude-User',
  'Claude-SearchBot',
  'anthropic-ai',
  'PerplexityBot',
  'Perplexity-User',
  'Google-Extended',
  'Applebot-Extended',
  'CCBot',
];

const group = (agent: string) =>
  [`User-agent: ${agent}`, 'Allow: /', ...DISALLOW.map((p) => `Disallow: ${p}`)].join('\n');

export const GET: APIRoute = ({ site }) =>
  new Response(
    [
      ...['*', ...AI_USER_AGENTS].map(group),
      `Sitemap: ${new URL('/sitemap_index.xml', site).href}`,
    ].join('\n\n') + '\n',
    { headers: { 'Content-Type': 'text/plain; charset=utf-8' } },
  );
