import { services } from '../../data/services';
import { site } from '../../data/site';
import { team, type Member } from '../../data/team';

// Schema.org nodes for the page's single @graph. Every node is built from
// src/data/*.ts, so structured data and visible text cannot disagree.

type Node = Record<string, unknown>;

const abs = (origin: URL, path: string) => new URL(path, origin).href;
export const clinicId = (origin: URL) => abs(origin, '/#clinic');
const websiteId = (origin: URL) => abs(origin, '/#website');
const personId = (origin: URL, m: Member) => abs(origin, `/tym/${m.id}/#person`);

const personRef = (origin: URL, m: Member): Node => ({
  '@type': 'Person',
  '@id': personId(origin, m),
  name: m.name,
  jobTitle: m.role,
  url: abs(origin, `/tym/${m.id}/`),
});

/** Clinic + website — on every page. */
export function siteGraph(origin: URL, images: { photo: string; logo: string }): Node[] {
  // No openingHoursSpecification: hours are edited in the admin at runtime and a
  // build-time copy would go stale in search results. Google Business Profile
  // and Firmy.cz carry the hours instead.
  const clinic: Node = {
    '@type': 'Dentist',
    '@id': clinicId(origin),
    name: site.name,
    alternateName: site.shortName,
    description: site.description,
    url: abs(origin, '/'),
    image: images.photo,
    logo: images.logo,
    telephone: site.phone,
    email: site.email,
    medicalSpecialty: 'Dentistry',
    address: {
      '@type': 'PostalAddress',
      streetAddress: site.street,
      addressLocality: site.city,
      postalCode: site.zip,
      addressCountry: 'CZ',
    },
    areaServed: [
      { '@type': 'City', name: site.city },
      { '@type': 'City', name: 'České Budějovice' },
    ],
    employee: team.map((m) => personRef(origin, m)),
    availableService: services.map((s) => ({
      '@type': 'MedicalProcedure',
      name: s.title,
      description: s.lead,
      url: abs(origin, `/sluzby/${s.id}/`),
    })),
  };
  if (site.geo) {
    clinic.geo = { '@type': 'GeoCoordinates', latitude: site.geo.lat, longitude: site.geo.lng };
    const { lat, lng } = site.geo;
    clinic.hasMap = `https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}#map=17/${lat}/${lng}`;
  }
  if (site.sameAs.length) clinic.sameAs = site.sameAs;

  const website: Node = {
    '@type': 'WebSite',
    '@id': websiteId(origin),
    name: site.name,
    url: abs(origin, '/'),
    inLanguage: 'cs',
    publisher: { '@id': clinicId(origin) },
  };

  return [clinic, website];
}

export function breadcrumbs(origin: URL, items: { name: string; path: string }[]): Node {
  return {
    '@type': 'BreadcrumbList',
    itemListElement: [{ name: 'Domů', path: '/' }, ...items].map((item, i) => ({
      '@type': 'ListItem',
      position: i + 1,
      name: item.name,
      item: abs(origin, item.path),
    })),
  };
}

export function person(origin: URL, m: Member): Node {
  return {
    ...personRef(origin, m),
    description: m.bio,
    knowsAbout: m.focus,
    worksFor: { '@id': clinicId(origin) },
  };
}
