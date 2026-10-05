export interface Service {
  id: string;
  title: string;
  lead: string;
  price: string;
  body: string | null; // null = text not yet supplied by the clinic
  steps: string[];
}

// TODO(Anton): body texts for záchovná, protetika, děti.
export const services: Service[] = [
  {
    id: 'prevence',
    title: 'Preventivní prohlídky',
    lead: 'Pravidelná kontrola dvakrát ročně, kterou hradí pojišťovna.',
    price: 'hrazeno pojišťovnou',
    body: 'Při prohlídce zkontrolujeme stav zubů, dásní i sliznic, zhodnotíme starší výplně a podle potřeby doplníme rentgenový snímek. Nález s vámi projdeme a navrhneme další postup.',
    steps: [
      'Vstupní rozhovor a zjištění obtíží.',
      'Prohlídka chrupu, dásní a sliznic.',
      'Vysvětlení nálezu a plán ošetření.',
    ],
  },
  {
    id: 'hygiena',
    title: 'Dentální hygiena',
    lead: 'Odstranění zubního kamene a plaku, nácvik domácí péče.',
    price: 'od 1 200 Kč',
    body: 'Kompletní ošetření u dentální hygienistky včetně odstranění zubního kamene, plaku i pigmentací a nácviku správného čištění. Interval dalších návštěv nastavíme podle stavu vašich dásní.',
    steps: [
      'Vyšetření dásní a zhodnocení hygieny.',
      'Odstranění zubního kamene a plaku.',
      'Nácvik čištění a doporučení pomůcek.',
    ],
  },
  {
    id: 'estetika',
    title: 'Estetická stomatologie',
    lead: 'Bílé výplně, dostavby a bělení zubů.',
    price: 'od 2 500 Kč',
    body: 'Řešíme vzhled předních zubů — bílé kompozitní výplně, dostavby nalomených hrotů, uzavření mezer i šetrné bělení. Vždy začínáme konzultací, na které si společně nastavíme realistické očekávání.',
    steps: [
      'Konzultace a fotodokumentace.',
      'Návrh řešení a orientační cena.',
      'Ošetření a kontrola po zhojení.',
    ],
  },
  {
    id: 'zachovna',
    title: 'Záchovná stomatologie',
    lead: 'Ošetření zubního kazu a ošetření kořenových kanálků.',
    price: 'od 900 Kč',
    body: null,
    steps: [
      'Diagnostika a rentgen.',
      'Ošetření v místním znecitlivění.',
      'Kontrola a doporučení péče.',
    ],
  },
  {
    id: 'protetika',
    title: 'Protetika',
    lead: 'Korunky, můstky a náhrady chybějících zubů.',
    price: 'od 6 000 Kč',
    body: null,
    steps: [
      'Konzultace a plán náhrady.',
      'Příprava zubu a odběr modelu.',
      'Nasazení hotové náhrady.',
    ],
  },
  {
    id: 'deti',
    title: 'Péče o děti',
    lead: 'Prohlídky, prevence a přátelské první návštěvy.',
    price: 'hrazeno pojišťovnou',
    body: null,
    steps: [
      'Seznámení dítěte s ordinací.',
      'Prohlídka a instruktáž čištění.',
      'Prevence — fluoridace, pečetění.',
    ],
  },
];
