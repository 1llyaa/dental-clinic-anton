import type { APIRoute, GetStaticPaths } from 'astro';
import { buildUrlsetXml, SITEMAP_NAMES, type SitemapName } from '../lib/seo/sitemap';

export const getStaticPaths = (() =>
  SITEMAP_NAMES.map((name) => ({ params: { name } }))) satisfies GetStaticPaths;

export const GET: APIRoute = ({ site, params }) =>
  new Response(buildUrlsetXml(site!, params.name as SitemapName), {
    headers: { 'Content-Type': 'application/xml; charset=utf-8' },
  });
