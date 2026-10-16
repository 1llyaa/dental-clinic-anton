import type { APIRoute } from 'astro';
import { buildSitemapIndexXml } from '../lib/seo/sitemap';

export const GET: APIRoute = ({ site }) =>
  new Response(buildSitemapIndexXml(site!), {
    headers: { 'Content-Type': 'application/xml; charset=utf-8' },
  });
