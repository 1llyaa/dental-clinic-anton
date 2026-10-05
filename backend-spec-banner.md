# Backend spec — self-service banner pro zubní ordinaci

Zadání pro AI agenta, který implementuje **backend část**. Frontend (statický web) si naplánuj sám, tento dokument popisuje jen serverovou vrstvu a kontrakt mezi nimi.

---

## 1. Kontext

Web pro zubní ordinaci. Statický vícestránkový web (Astro nebo podobné, build probíhá v CI, na server se nahrává jen `dist/`).

Jediná dynamická funkce: **majitel ordinace (nezkušený uživatel) si sám mění vyskakovací banner** — typicky oznámení o dovolené nebo uzavření ordinace. Musí to zvládnout bez vývojáře, bez znalosti gitu, bez instalace čehokoli.

Druhotně: stejným mechanismem se mění **ordinační hodiny**.

### Prostředí — tvrdá omezení

Hosting: **Wedos NoLimit** (sdílený webhosting).

- PHP 8.x, MySQL/MariaDB k dispozici
- **žádný Node.js runtime na serveru**, žádné serverless funkce, žádný Docker
- přístup přes FTP, k dispozici cron
- HTTPS přes Let's Encrypt (zapíná se v administraci Wedos)
- `.htaccess` (Apache) funguje

Z toho plyne: backend **musí být čisté PHP**, bez Composeru a bez externích závislostí. Žádný framework. Cíl je, aby to běželo roky bez údržby.

### Databáze

**Nepoužívat.** Stav je jeden malý JSON soubor na disku. MySQL je tu zbytečná komplikace (zálohy, migrace, přístupové údaje) pro pár polí.

---

## 2. Struktura na serveru

```
/www/domains/<domena>/
├── index.html              # statický build (nahrává CI, backend se jich nedotýká)
├── ...
├── data/
│   ├── banner.json         # stav banneru — zapisuje admin, čte frontend
│   ├── hours.json          # ordinační hodiny
│   └── .htaccess           # povolit GET na *.json, zakázat zbytek
├── uploads/
│   ├── banner-<timestamp>.jpg
│   └── .htaccess           # zakázat spouštění PHP
└── admin/
    ├── index.php           # celý admin — login + formulář + zápis
    ├── config.php          # hash hesla, cesty, limity (mimo git)
    ├── config.example.php  # šablona do gitu
    └── .htaccess
```

**Důležité pro deploy:** složky `data/` a `uploads/` musí být z CI deploye **vyloučené**, jinak každý push přepíše to, co klient nastavil. Do repa patří `data/banner.example.json`, který se nahraje jen jednou ručně.

---

## 3. Datový kontrakt

### `data/banner.json`

```json
{
  "active": true,
  "title": "Dovolená",
  "text": "Ordinace bude uzavřena od 14. 7. do 25. 7. V akutních případech kontaktujte pohotovost…",
  "image": "/uploads/banner-1720000000.jpg",
  "from": "2026-07-01",
  "until": "2026-07-25",
  "updated_at": "2026-06-20T10:13:00+02:00"
}
```

| pole | typ | pravidla |
|---|---|---|
| `active` | bool | hlavní vypínač |
| `title` | string | max 80 znaků, může být prázdný |
| `text` | string | max 600 znaků, může být prázdný |
| `image` | string\|null | cesta od rootu webu, nebo `null` |
| `from` | string\|null | `YYYY-MM-DD`, od kdy se banner zobrazuje (null = ihned) |
| `until` | string\|null | `YYYY-MM-DD`, včetně tohoto dne (null = bez konce) |
| `updated_at` | string | ISO 8601, nastavuje server |

**Logika zobrazení** (vyhodnocuje frontend, backend jen ukládá):
banner se zobrazí, když `active === true` **a zároveň** dnešní datum spadá do `from`–`until` (inkluzivně, obě hranice volitelné).

Pole `from`/`until` jsou klíčová — klient si dovolenou nastaví dopředu a banner sám zmizí. Bez toho by tam viselo „Zavřeno do 25. 7." ještě v září.

### `data/hours.json`

```json
{
  "regular": [
    { "day": "Pondělí", "value": "8:00 – 16:00" },
    { "day": "Úterý",   "value": "8:00 – 16:00" },
    { "day": "Středa",  "value": "8:00 – 18:00" },
    { "day": "Čtvrtek", "value": "8:00 – 16:00" },
    { "day": "Pátek",   "value": "8:00 – 13:00" },
    { "day": "Sobota",  "value": "Zavřeno" },
    { "day": "Neděle",  "value": "Zavřeno" }
  ],
  "note": "Polední pauza 12:00 – 12:30",
  "updated_at": "2026-06-20T10:13:00+02:00"
}
```

Hodnoty jsou **volný text**, ne strukturovaný čas. Klient tam potřebuje napsat „Zavřeno", „Dle objednání", „Pouze dentální hygiena" — parsování časů by překáželo.

### Konzumace frontendem

Frontend si JSONy stáhne za běhu:

```
GET /data/banner.json
GET /data/hours.json
```

- odpověď musí jít servírovat jako `application/json`
- `Cache-Control: max-age=60` — změna je u klienta vidět do minuty, zároveň to neubíjí server
- při chybě/404 frontend **tiše nic nezobrazí** (fail silent, nikdy chybová hláška pacientovi)

Statický web se kvůli změně banneru **nerebuilduje**. To je celý smysl návrhu.

---

## 4. Admin (`admin/index.php`)

Jeden PHP soubor. Žádný router, žádné MVC, žádné SPA.

### Autentizace

- jeden uživatel, heslo uložené jako `password_hash()` v `config.php`
- přihlašovací formulář → ověření přes `password_verify()` → PHP session
- `session_regenerate_id(true)` po úspěšném loginu
- cookie: `HttpOnly`, `Secure`, `SameSite=Strict`
- odhlášení, session timeout ~8 h
- **rate limiting**: po 5 špatných pokusech z jedné IP zamknout na 15 minut (stačí soubor s pokusy v `data/`)
- v `admin/.htaccess` navíc basic auth jako druhá vrstva — levné a odfiltruje to boty

### UI

Jedna obrazovka, dvě sekce (banner / ordinační hodiny), jedno tlačítko Uložit u každé.

Požadavky:

- **česky**, žádná technická terminologie — ne „JSON", ne „pole", ne „deploy"
- formulář pro banner: checkbox *Zobrazit banner*, nadpis, text, upload obrázku, datum od, datum do
- datumy přes `<input type="date">` — v mobilu to vyvolá nativní kalendář
- **živý náhled** toho, jak to pacient uvidí (stačí jednoduchý box nad formulářem)
- jasná hláška po uložení: „Uloženo. Na webu se změna projeví do minuty."
- pokud je banner aktivní ale `until` už uplynulo, zobrazit varování: „Tento banner se už nezobrazuje, protože datum do je v minulosti."
- tlačítko *Odebrat obrázek*
- responzivní — klient to bude obsluhovat z mobilu

Styl: čistý, velká písma, velká tlačítka. Žádný framework, inline CSS v souboru je v pořádku.

### Zápis

- **atomický zápis**: `file_put_contents($tmp, $json)` → `rename($tmp, $target)`. Nikdy nezapisovat přímo do cílového souboru, jinak při přerušení zůstane rozbitý JSON a banner zmizí z webu.
- `JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES` — diakritika musí být čitelná, soubor může někdy číst člověk
- před přepsáním zkopírovat stávající verzi do `data/backups/banner-<timestamp>.json`, držet posledních 10
- `updated_at` nastavuje server, ne formulář

### Upload obrázku

Tady vzniká většina reálných problémů, proto detailně:

1. validovat **reálný MIME typ** přes `finfo_file()`, ne příponu a ne `$_FILES['type']`
2. povolit `image/jpeg`, `image/png`, `image/webp`
3. max velikost 5 MB na vstupu (zkontrolovat i `UPLOAD_ERR_INI_SIZE` a dát srozumitelnou hlášku — klient nahraje 8MB fotku z mobilu a musí pochopit proč)
4. **přeškálovat přes GD** na max. šířku 1200 px a znovu zakódovat do JPEG s kvalitou ~82. Tím se zároveň zahodí EXIF a případný škodlivý payload. Pokud GD není dostupné, upload odmítnout se srozumitelnou hláškou.
5. uložit pod vygenerovaným jménem `banner-<timestamp>.jpg` — **nikdy nepoužívat původní název souboru**
6. smazat předchozí obrázek, ať `uploads/` neroste donekonečna
7. `uploads/.htaccess` musí zakázat spouštění PHP:

```apache
php_flag engine off
<FilesMatch "\.(php|phtml|php\d|phar)$">
    Require all denied
</FilesMatch>
```

### Validace a ochrany

- CSRF token ve formuláři (token v session, ověřit při POST)
- escapovat veškerý výstup přes `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`
- ořezat délky textů na serveru, ne jen v HTML
- validovat formát datumů přes `DateTime::createFromFormat('Y-m-d', ...)`
- odmítnout `until < from` se srozumitelnou hláškou
- bezpečnostní hlavičky: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`
- `admin/` vyloučit z `robots.txt` a poslat `X-Robots-Tag: noindex`

---

## 5. `config.php`

```php
<?php
return [
    'password_hash' => '$2y$12$...',   // vygenerovat přes password_hash()
    'data_dir'      => __DIR__ . '/../data',
    'uploads_dir'   => __DIR__ . '/../uploads',
    'uploads_url'   => '/uploads',
    'max_upload'    => 5 * 1024 * 1024,
    'max_width'     => 1200,
    'session_ttl'   => 8 * 3600,
];
```

Do repa patří jen `config.example.php`. Skutečný `config.php` nahrává vývojář ručně přes FTP.

---

## 6. Nasazení — co musí agent zajistit

- `data/`, `data/backups/` a `uploads/` musí být zapisovatelné PHP procesem (na Wedosu obvykle `0755` / `0644` stačí, ale **ověřit hned na začátku**, ne den před předáním)
- skript `admin/check.php` (smazat po ověření), který vypíše: verzi PHP, dostupnost GD, `finfo`, práva na zápis do obou složek, `upload_max_filesize`, `post_max_size`
- v administraci Wedos nastavit PHP 8.x a vynutit HTTPS redirect v kořenovém `.htaccess`
- `data/.htaccess`: povolit GET na `*.json`, zakázat výpis adresáře a přístup k `backups/`
- cron (volitelně): denní stažení `data/*.json` do zálohy, nebo spoléhat na zálohy Wedosu

### CI deploy

GitHub Action s FTP deployem. **Exclude list musí obsahovat** `data/**`, `uploads/**`, `admin/config.php`. Tohle je nejčastější způsob, jak si rozbít produkci — ošetřit to explicitně.

---

## 7. Co nedělat

- ❌ nepřidávat databázi
- ❌ nepoužívat Composer ani žádnou externí knihovnu
- ❌ nestavět REST API s tokeny — admin je formulář, ne SPA
- ❌ nenasazovat hotové CMS (WordPress, Decap, Sanity) — kvůli třem polím je to řádově víc údržby a pro klienta víc kliků
- ❌ nespouštět rebuild statického webu při změně banneru
- ❌ neukládat původní názvy nahraných souborů
- ❌ nevymýšlet role a uživatele — jeden účet stačí

---

## 8. Definition of done

- [ ] klient se přihlásí, zapne banner, nahraje obrázek, nastaví datumy, uloží
- [ ] změna je viditelná na webu do 60 s bez rebuildu
- [ ] banner po uplynutí `until` sám zmizí
- [ ] upload 8MB fotky z mobilu projde a uloží se jako ~200 kB JPEG
- [ ] upload `.php` souboru přejmenovaného na `.jpg` je odmítnut
- [ ] přímé volání `/uploads/<cokoli>.php` nespustí PHP
- [ ] přerušený zápis nezanechá rozbitý `banner.json`
- [ ] bez přihlášení není admin přístupný
- [ ] `git push` nepřepíše klientem nastavený obsah
- [ ] vše funguje na mobilu
