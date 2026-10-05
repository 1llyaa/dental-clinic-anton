export interface Member {
  id: string;
  name: string;
  role: string;
  photo: string | null; // TODO(Anton): portrait photos
  photoLabel: string;
  bio: string;
  bio2: string;
  education: string[];
  focus: string[];
}

export const team: Member[] = [
  {
    id: 'anton',
    name: 'MUDr. Anton Jechlakov, MBA',
    role: 'Zubní lékař',
    photo: null,
    photoLabel: 'Portrét — MUDr. Anton Jechlakov',
    bio: 'Vede ordinaci v Lišově a věnuje se celé šíři praktické stomatologie — od preventivních prohlídek přes ošetření zubního kazu až po estetické dostavby a protetiku.',
    bio2: 'Klade důraz na to, aby pacient rozuměl svému nálezu. Před každým ošetřením proto vysvětluje možnosti řešení včetně jejich ceny a životnosti. Zvlášť trpělivě pracuje s pacienty, kteří mají ze zubaře strach.',
    education: [
      'Lékařská fakulta — obor zubní lékařství',
      'MBA v oblasti řízení zdravotnictví',
      'Pravidelné odborné kurzy a kongresy',
    ],
    focus: [
      'Preventivní prohlídky dospělých i dětí',
      'Záchovná stomatologie a estetické výplně',
      'Protetika — korunky a můstky',
      'Konzultace léčebného plánu',
    ],
  },
  {
    id: 'elena',
    name: 'Mgr. Elena Jechlakova, DiS.',
    role: 'Dentální hygienistka',
    photo: null,
    photoLabel: 'Portrét — Mgr. Elena Jechlakova',
    bio: 'Stará se o dentální hygienu a o to nejdůležitější, co si z ordinace odnesete domů — návod, jak si čistit zuby tak, aby to skutečně fungovalo.',
    bio2: 'Součástí každé návštěvy je nácvik správné techniky čištění, doporučení pomůcek podle stavu vašich zubů a dásní a nastavení intervalu dalších návštěv. Pracuje šetrně, s ohledem na citlivé dásně.',
    education: [
      'Magisterské studium ve zdravotnickém oboru',
      'Diplomovaná dentální hygienistka (DiS.)',
      'Kurzy v oblasti parodontologie a prevence',
    ],
    focus: [
      'Profesionální dentální hygiena',
      'Nácvik techniky čištění a výběr pomůcek',
      'Péče o pacienty s parodontitidou',
      'Bělení zubů a odstranění pigmentací',
    ],
  },
  {
    id: 'lenka',
    name: 'Lenka Nosková',
    role: 'Zubní asistentka',
    photo: null,
    photoLabel: 'Portrét — Lenka Nosková',
    bio: 'První člověk, se kterým v ordinaci mluvíte. Objednává termíny, přijímá telefony a stará se o to, aby návštěva proběhla bez zdržení.',
    bio2: 'Během ošetření asistuje lékaři a hygienistce, připravuje nástroje a odpovídá za dodržování hygienických standardů ordinace. Ráda pomůže i s dotazy k pojišťovnám a dokumentaci.',
    education: ['Odborná kvalifikace zubní asistentky', 'Kurz sterilizace a hygienických režimů'],
    focus: [
      'Objednávání pacientů',
      'Asistence při ošetření',
      'Sterilizace a příprava ordinace',
      'Administrativa a pojišťovny',
    ],
  },
];
