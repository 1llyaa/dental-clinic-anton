// TODO(Anton): replace placeholder phone, IČO and verify address/e-mail before launch.
export const site = {
  name: 'Zubní ordinace a dentální hygiena Lišov',
  shortName: 'Zubní Lišov',
  domain: 'zubnilisov.cz',
  phone: '+420 387 000 000',
  email: 'info@zubnilisov.cz',
  address: 'Náměstí Míru 1, 373 72 Lišov',
  street: 'Náměstí Míru 1',
  city: 'Lišov',
  zip: '373 72',
  ico: '000 000 00 (doplnit)',
  mapEmbed:
    'https://www.openstreetmap.org/export/embed.html?bbox=14.5720%2C49.0060%2C14.6180%2C49.0290&layer=mapnik',
};

export const telHref = 'tel:' + site.phone.replace(/\s/g, '');
export const mailHref = 'mailto:' + site.email;

export const nav = [
  { label: 'Domů', href: '/' },
  { label: 'O nás', href: '/o-nas/' },
  { label: 'Tým', href: '/tym/' },
  { label: 'Služby', href: '/sluzby/' },
  { label: 'Ceník', href: '/cenik/' },
  { label: 'Objednání', href: '/objednani/' },
  { label: 'Kontakt', href: '/kontakt/' },
  { label: 'Aktuality', href: '/aktuality/' },
];

export const quickLinks = [
  {
    title: 'Naše služby',
    text: 'Prevence, dentální hygiena i estetické ošetření — přehledně na jednom místě.',
    cta: 'Zobrazit služby',
    href: '/sluzby/',
  },
  {
    title: 'Náš tým',
    text: 'Lékař, dentální hygienistka a asistentka. Poznejte je ještě před návštěvou.',
    cta: 'Poznat tým',
    href: '/tym/',
  },
  {
    title: 'Kontakt a mapa',
    text: 'Adresa, ordinační hodiny a telefon.',
    cta: 'Kde nás najdete',
    href: '/kontakt/',
  },
];

export const values = [
  {
    title: 'Prevence na prvním místě',
    text: 'Většině problémů se dá předejít pravidelnou prohlídkou a hygienou.',
  },
  {
    title: 'Vysvětlíme dopředu',
    text: 'Nález, možnosti řešení i cenu znáte, než se čehokoliv dotkneme.',
  },
  { title: 'Klid a čas', text: 'Neošetřujeme ve spěchu. Tempo přizpůsobíme pacientovi.' },
  { title: 'Stále stejný tým', text: 'Znáte nás jmény a my známe vaši anamnézu.' },
];

export const bookingPrep = [
  'Kartičku zdravotní pojišťovny.',
  'Seznam léků, které pravidelně užíváte.',
  'Informaci o alergiích a chronických nemocech.',
  'U dětí přítomnost zákonného zástupce.',
];
