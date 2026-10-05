<?php
declare(strict_types=1);

// HTML for the admin. Everything printed goes through e().

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function take_flash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($f) ? $f : null;
}

function render_flash(?array $flash, ?string $section): void
{
    if (!$flash || ($flash['section'] ?? null) !== $section) {
        return;
    }
    $cls = $flash['type'] === 'ok' ? 'msg ok' : 'msg error';
    echo '<div class="' . $cls . '" role="' . ($flash['type'] === 'ok' ? 'status' : 'alert') . '">';
    foreach ($flash['messages'] as $m) {
        echo '<p>' . e($m) . '</p>';
    }
    echo '</div>';
}

function page_start(string $title): void
{
    ?><!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?></title>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<style>
  :root { --navy:#113080; --cyan:#03B4DD; --text:#1b2740; --muted:#4a5772; --border:#d5deea; --soft:#f4f7fa; --ok:#0f7b4b; --err:#b3261e; --warn:#8a5a00; }
  * { box-sizing: border-box; }
  html { -webkit-text-size-adjust: 100%; }
  body { margin:0; background:var(--soft); color:var(--text); font: 18px/1.55 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
  a { color: var(--navy); }
  .wrap { max-width: 760px; margin: 0 auto; padding: 16px; }
  .top { background:#fff; border-bottom:1px solid var(--border); }
  .top .wrap { display:flex; align-items:center; gap:12px; flex-wrap:wrap; padding-top:12px; padding-bottom:12px; }
  .top h1 { font-size: 20px; margin: 0; flex: 1 1 auto; color: var(--navy); }
  .top form { margin: 0; }
  .nav { display:flex; gap:8px; flex-wrap:wrap; margin: 16px 0 0; }
  .nav a { background:#fff; border:1px solid var(--border); border-radius: 999px; padding: 8px 16px; text-decoration:none; font-weight:600; }
  section.card { background:#fff; border:1px solid var(--border); border-radius:16px; padding:20px; margin:20px 0; scroll-margin-top: 16px; }
  h2 { margin: 0 0 6px; font-size: 24px; color: var(--navy); }
  .hint { color: var(--muted); font-size: 16px; margin: 0 0 16px; }
  label.field { display:block; margin: 18px 0 0; font-weight: 600; }
  label.field small { font-weight: 400; color: var(--muted); }
  input[type=text], input[type=password], input[type=date], textarea {
    display:block; width:100%; margin-top:6px; padding:14px; font: inherit; color: inherit;
    border:1.5px solid var(--border); border-radius:12px; background:#fff; min-height: 54px; }
  input[type=date] { appearance: none; -webkit-appearance: none; }
  textarea { min-height: 140px; resize: vertical; }
  input:focus, textarea:focus { outline: 3px solid #9be2f3; border-color: var(--cyan); }
  input[type=file] { display:block; margin-top:8px; font-size:16px; max-width:100%; }
  .row2 { display:grid; grid-template-columns: 1fr; gap: 0 16px; }
  @media (min-width: 560px) { .row2 { grid-template-columns: 1fr 1fr; } }
  .switch { display:flex; gap:14px; align-items:center; padding:16px; border:1.5px solid var(--border); border-radius:12px; font-weight:700; cursor:pointer; }
  .switch input { width:28px; height:28px; accent-color: var(--navy); flex: 0 0 auto; }
  .btn { display:inline-block; font: inherit; font-weight:700; border:0; border-radius:12px; padding:15px 24px; min-height:54px; cursor:pointer; text-decoration:none; text-align:center; }
  .btn-primary { background: var(--navy); color:#fff; }
  .btn-primary:hover { background: #0d2765; }
  .btn-primary[disabled] { opacity: .6; cursor: progress; }
  .btn-light { background:#fff; color: var(--navy); border:1.5px solid var(--border); }
  .btn-danger { background:#fff; color: var(--err); border:1.5px solid #f0c4c0; }
  .btn-small { padding: 10px 16px; min-height: 44px; font-size: 16px; }
  .btn-wide { width:100%; margin-top: 22px; font-size: 19px; }
  .msg { border-radius:12px; padding: 14px 16px; margin: 0 0 16px; font-weight:600; }
  .msg p { margin: 0; } .msg p + p { margin-top: 6px; }
  .msg.ok { background:#e6f6ee; color: var(--ok); border:1px solid #b7e2cb; }
  .msg.error { background:#fdecea; color: var(--err); border:1px solid #f3c1bc; }
  .msg.warn { background:#fff6e0; color: var(--warn); border:1px solid #f1d9a0; }
  .status { font-weight: 600; margin: 0 0 14px; }
  .status .dot { display:inline-block; width:12px; height:12px; border-radius:50%; margin-right:8px; vertical-align: middle; }
  .preview-label { font-size: 14px; font-weight:700; letter-spacing:.08em; color: var(--muted); text-transform: uppercase; margin: 0 0 8px; }
  .preview { border:1px dashed var(--border); border-radius:12px; overflow:hidden; background:#fff; }
  .preview .strip { background:#eaf7fc; color: var(--navy); padding: 12px 16px; font-size:16px; white-space: pre-line; }
  .preview .strip strong { font-weight: 800; }
  .preview img { display:block; width:100%; max-height: 260px; object-fit: cover; }
  .preview .off { padding: 14px 16px; color: var(--muted); font-style: italic; }
  .current-img { display:flex; gap: 14px; align-items:center; flex-wrap:wrap; margin-top: 10px; }
  .current-img img { width: 120px; height: 80px; object-fit: cover; border-radius: 8px; border:1px solid var(--border); }
  .counter { display:block; text-align:right; font-size:14px; color: var(--muted); font-weight:400; margin-top:4px; }
  .day-row { display:grid; grid-template-columns: 110px 1fr; gap: 12px; align-items:center; margin-top: 10px; }
  .day-row label { font-weight:600; }
  .day-row input { margin-top: 0; }
  .news-item { border-top:1px solid var(--border); padding: 16px 0; }
  .news-item:first-of-type { border-top: 0; }
  .news-item .date { color: var(--muted); font-size: 15px; }
  .news-item h3 { margin: 2px 0 4px; font-size: 19px; }
  .news-item p { margin: 0 0 10px; color: var(--muted); white-space: pre-line; overflow-wrap: anywhere; }
  .actions { display:flex; gap: 10px; flex-wrap: wrap; }
  .actions form { margin: 0; }
  .editing { background:#eaf7fc; border-radius: 12px; padding: 12px 16px; margin-bottom: 4px; font-weight:600; }
  .login { max-width: 420px; margin: 10vh auto; }
  .muted { color: var(--muted); }
  code { background: var(--soft); padding: 2px 6px; border-radius: 6px; word-break: break-all; font-size: 15px; }
</style>
</head>
<body>
<?php
}

function page_end(bool $withScript = false): void
{
    if ($withScript) {
        echo '<script>' . admin_script() . '</script>';
    }
    echo "</body>\n</html>\n";
}

function render_setup_missing_config(): void
{
    page_start('Nastavení správy webu');
    ?>
    <div class="wrap login"><section class="card">
      <h2>Správa webu není nastavená</h2>
      <p class="muted">Chybí soubor <code>admin/config.php</code>. Vytvořte ho podle <code>config.example.php</code> a nahrajte na server.</p>
    </section></div>
    <?php
    page_end();
}

function render_password_setup(?string $password): void
{
    page_start('Nastavení hesla');
    ?>
    <div class="wrap login"><section class="card">
      <h2>Nastavení hesla</h2>
      <p class="hint">V souboru <code>config.php</code> zatím není heslo. Zadejte nové heslo, zkopírujte vygenerovaný řádek do <code>config.php</code> a soubor znovu nahrajte na server. Heslo se nikam neukládá.</p>
      <?php if ($password !== null && mb_strlen($password) >= 10): ?>
        <div class="msg ok"><p>Vložte do config.php tento řádek:</p></div>
        <p><code>'password_hash' =&gt; '<?= e(password_hash($password, PASSWORD_DEFAULT)) ?>',</code></p>
      <?php else: ?>
        <?php if ($password !== null): ?><div class="msg error"><p>Heslo musí mít alespoň 10 znaků.</p></div><?php endif; ?>
        <form method="post" autocomplete="off">
          <label class="field">Nové heslo <small>(alespoň 10 znaků)</small>
            <input type="password" name="password" minlength="10" required autocomplete="new-password">
          </label>
          <button class="btn btn-primary btn-wide">Vygenerovat</button>
        </form>
      <?php endif; ?>
    </section></div>
    <?php
    page_end();
}

function render_login(int $lockedFor): void
{
    $flash = take_flash();
    page_start('Přihlášení — správa webu');
    ?>
    <div class="wrap login"><section class="card">
      <h2>Správa webu</h2>
      <p class="hint">Zubní ordinace Lišov</p>
      <?php render_flash($flash, null); ?>
      <?php if ($lockedFor > 0): ?>
        <div class="msg error" role="alert"><p>Příliš mnoho nesprávných pokusů. Zkuste to prosím znovu za <?= (int) ceil($lockedFor / 60) ?> min.</p></div>
      <?php else: ?>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="login">
          <label class="field">Heslo
            <input type="password" name="password" required autofocus autocomplete="current-password">
          </label>
          <button class="btn btn-primary btn-wide">Přihlásit se</button>
        </form>
      <?php endif; ?>
    </section></div>
    <?php
    page_end();
}

/** Human description of whether (and why) the banner shows on the website today. */
function banner_status(array $b, string $today): array
{
    $hasContent = trim((string) ($b['title'] ?? '')) !== '' || trim((string) ($b['text'] ?? '')) !== '';
    if (empty($b['active'])) {
        return ['off', 'Oznámení je vypnuté — na webu se nezobrazuje.'];
    }
    if (!$hasContent) {
        return ['off', 'Oznámení nemá žádný text — na webu se nezobrazuje.'];
    }
    if (!empty($b['until']) && $b['until'] < $today) {
        return ['past', 'Tento banner se už nezobrazuje, protože datum do je v minulosti.'];
    }
    if (!empty($b['from']) && $b['from'] > $today) {
        return ['future', 'Oznámení se začne zobrazovat ' . cz_date($b['from']) . '.'];
    }
    $until = !empty($b['until']) ? ' do ' . cz_date($b['until']) . ' (včetně)' : '';
    return ['on', 'Oznámení se teď na webu zobrazuje' . $until . '.'];
}

function cz_date(string $iso): string
{
    $d = DateTime::createFromFormat('!Y-m-d', $iso);
    return $d ? $d->format('j. n. Y') : $iso;
}

function render_dashboard(array $v): void
{
    $flash = take_flash();
    $old = $flash['old'] ?? null;
    $oldFor = fn(string $section) => ($flash['section'] ?? null) === $section ? $old : null;

    $banner = $v['banner'];
    $bOld = $oldFor('oznameni');
    $bf = [
        'active' => $bOld !== null ? !empty($bOld['active']) : !empty($banner['active']),
        'title' => $bOld['title'] ?? ($banner['title'] ?? ''),
        'text' => $bOld['text'] ?? ($banner['text'] ?? ''),
        'from' => $bOld['from'] ?? ($banner['from'] ?? ''),
        'until' => $bOld['until'] ?? ($banner['until'] ?? ''),
    ];
    $image = is_string($banner['image'] ?? null) ? $banner['image'] : null;
    $today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('Y-m-d');
    [$state, $stateText] = banner_status($banner, $today);
    $dotColor = ['on' => '#0f7b4b', 'future' => '#03B4DD', 'past' => '#8a5a00', 'off' => '#9aa7ba'][$state];

    $hours = $v['hours'];
    $hOld = $oldFor('hodiny');
    $hourValues = [];
    foreach (DAYS as $i => $day) {
        $saved = '';
        foreach ($hours['regular'] ?? [] as $row) {
            if (($row['day'] ?? '') === $day) {
                $saved = (string) ($row['value'] ?? '');
            }
        }
        $hourValues[$i] = $hOld['value'][$i] ?? $saved;
    }
    $note = $hOld['note'] ?? ($hours['note'] ?? '');

    $news = $v['news'];
    $editing = null;
    if ($v['editId'] !== null) {
        foreach ($news as $n) {
            if (($n['id'] ?? '') === $v['editId']) {
                $editing = $n;
            }
        }
    }
    $nOld = $oldFor('aktuality');
    $nf = [
        'id' => $editing['id'] ?? '',
        'date' => $nOld['date'] ?? ($editing['date'] ?? $today),
        'title' => $nOld['title'] ?? ($editing['title'] ?? ''),
        'text' => $nOld['text'] ?? ($editing['text'] ?? ''),
    ];
    $csrf = e(csrf_token());
    $maxMb = (int) round($v['config']['max_upload'] / 1024 / 1024);

    page_start('Správa webu — Zubní ordinace Lišov');
    ?>
    <div class="top"><div class="wrap">
      <h1>Správa webu</h1>
      <a href="/" target="_blank" rel="noopener" class="btn btn-light btn-small">Zobrazit web</a>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="logout">
        <button class="btn btn-light btn-small">Odhlásit</button>
      </form>
    </div></div>

    <div class="wrap">
      <nav class="nav" aria-label="Sekce">
        <a href="#oznameni">Oznámení</a><a href="#hodiny">Ordinační hodiny</a><a href="#aktuality">Aktuality</a>
      </nav>
      <?php render_flash($flash, null); ?>

      <section class="card" id="oznameni">
        <h2>Oznámení na webu</h2>
        <p class="hint">Krátká zpráva, která se pacientům ukáže nahoře na každé stránce — třeba dovolená nebo změna hodin. Pokud přidáte obrázek, zobrazí se zpráva navíc v okně uprostřed obrazovky.</p>
        <?php render_flash($flash, 'oznameni'); ?>
        <?php if ($state === 'past'): ?>
          <div class="msg warn" role="alert"><p><?= e($stateText) ?></p></div>
        <?php else: ?>
          <p class="status"><span class="dot" style="background:<?= $dotColor ?>"></span><?= e($stateText) ?></p>
        <?php endif; ?>

        <p class="preview-label">Náhled — takto to uvidí pacient</p>
        <div class="preview" id="preview">
          <img id="pv-img" alt="" <?= $image ? 'src="' . e($image) . '"' : 'hidden' ?>>
          <div class="strip" id="pv-strip"><strong id="pv-title"></strong> <span id="pv-text"></span></div>
          <div class="off" id="pv-off" hidden>Oznámení je vypnuté — pacienti nic neuvidí.</div>
        </div>

        <form method="post" enctype="multipart/form-data" id="banner-form">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="action" value="save_banner">
          <input type="hidden" name="MAX_FILE_SIZE" value="<?= (int) $v['config']['max_upload'] ?>">

          <label class="switch" style="margin-top:18px">
            <input type="checkbox" name="active" value="1" id="f-active" <?= $bf['active'] ? 'checked' : '' ?>>
            Zobrazit oznámení na webu
          </label>

          <label class="field">Nadpis <small>(např. „Dovolená“)</small>
            <input type="text" name="title" id="f-title" maxlength="<?= BANNER_TITLE_MAX ?>" value="<?= e($bf['title']) ?>" data-counter>
          </label>
          <label class="field">Text oznámení
            <textarea name="text" id="f-text" maxlength="<?= BANNER_TEXT_MAX ?>" data-counter><?= e($bf['text']) ?></textarea>
          </label>

          <div class="row2">
            <label class="field">Zobrazit od <small>(prázdné = hned)</small>
              <input type="date" name="from" value="<?= e($bf['from']) ?>">
            </label>
            <label class="field">Zobrazit do <small>(včetně; prázdné = stále)</small>
              <input type="date" name="until" value="<?= e($bf['until']) ?>">
            </label>
          </div>

          <label class="field">Obrázek <small>(nepovinné; fotka z mobilu je v pořádku, max. <?= $maxMb ?> MB)</small>
            <input type="file" name="image" id="f-image" accept="image/jpeg,image/png,image/webp">
          </label>
          <p class="hint" id="img-status" hidden></p>
          <?php if ($image): ?>
            <div class="current-img">
              <img src="<?= e($image) ?>" alt="Současný obrázek">
              <input type="hidden" name="remove_image" value="" id="f-remove-image">
              <button type="button" class="btn btn-danger btn-small" id="remove-image">Odebrat obrázek</button>
            </div>
          <?php endif; ?>

          <button class="btn btn-primary btn-wide" id="banner-save">Uložit oznámení</button>
        </form>
      </section>

      <section class="card" id="hodiny">
        <h2>Ordinační hodiny</h2>
        <p class="hint">Pište volně, např. „8:00 – 16:00“, „Zavřeno“ nebo „Pouze dentální hygiena“.</p>
        <?php render_flash($flash, 'hodiny'); ?>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="action" value="save_hours">
          <?php foreach (DAYS as $i => $day): ?>
            <div class="day-row">
              <label for="h-<?= $i ?>"><?= e($day) ?></label>
              <input type="text" id="h-<?= $i ?>" name="value[<?= $i ?>]" maxlength="<?= HOURS_VALUE_MAX ?>" value="<?= e($hourValues[$i]) ?>" required>
            </div>
          <?php endforeach; ?>
          <label class="field">Poznámka pod hodinami <small>(nepovinné, např. „Polední pauza 12:00 – 12:30“)</small>
            <input type="text" name="note" maxlength="<?= HOURS_NOTE_MAX ?>" value="<?= e($note) ?>">
          </label>
          <button class="btn btn-primary btn-wide">Uložit hodiny</button>
        </form>
      </section>

      <section class="card" id="aktuality">
        <h2>Aktuality</h2>
        <p class="hint">Novinky na stránce Aktuality. Nejnovější se zobrazují nahoře, dvě nejnovější i na úvodní stránce.</p>
        <?php render_flash($flash, 'aktuality'); ?>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="action" value="save_news">
          <input type="hidden" name="id" value="<?= e($nf['id']) ?>">
          <?php if ($editing): ?>
            <div class="editing">Upravujete novinku „<?= e($editing['title']) ?>“. <a href="./#aktuality">Zrušit úpravy</a></div>
          <?php endif; ?>
          <label class="field">Datum
            <input type="date" name="date" value="<?= e($nf['date']) ?>" required>
          </label>
          <label class="field">Nadpis
            <input type="text" name="title" maxlength="<?= NEWS_TITLE_MAX ?>" value="<?= e($nf['title']) ?>" required data-counter>
          </label>
          <label class="field">Text
            <textarea name="text" maxlength="<?= NEWS_TEXT_MAX ?>" data-counter><?= e($nf['text']) ?></textarea>
          </label>
          <button class="btn btn-primary btn-wide"><?= $editing ? 'Uložit změny' : 'Přidat novinku' ?></button>
        </form>

        <h2 style="margin-top:28px; font-size:20px">Zveřejněné novinky</h2>
        <?php if (!$news): ?>
          <p class="muted">Zatím tu nejsou žádné novinky.</p>
        <?php endif; ?>
        <?php foreach ($news as $n): ?>
          <div class="news-item">
            <div class="date"><?= e(cz_date((string) ($n['date'] ?? ''))) ?></div>
            <h3><?= e($n['title'] ?? '') ?></h3>
            <?php if (($n['text'] ?? '') !== ''): ?><p><?= e($n['text']) ?></p><?php endif; ?>
            <div class="actions">
              <a class="btn btn-light btn-small" href="./?upravit=<?= e(rawurlencode((string) $n['id'])) ?>#aktuality">Upravit</a>
              <form method="post">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="delete_news">
                <input type="hidden" name="id" value="<?= e($n['id']) ?>">
                <button class="btn btn-danger btn-small" data-confirm="Opravdu smazat novinku „<?= e($n['title'] ?? '') ?>“?">Smazat</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </section>
    </div>
    <?php
    page_end(true);
}

function admin_script(): string
{
    return <<<'JS'
(function () {
  // Confirm destructive buttons
  document.querySelectorAll('[data-confirm]').forEach(function (b) {
    b.addEventListener('click', function (e) { if (!confirm(b.getAttribute('data-confirm'))) e.preventDefault(); });
  });

  // "Odebrat obrázek" is not a submit button, so Enter in a text field never triggers it
  var rm = document.getElementById('remove-image');
  if (rm) rm.addEventListener('click', function () {
    if (!confirm('Opravdu odebrat obrázek? Ostatní změny ve formuláři se také uloží.')) return;
    document.getElementById('f-remove-image').value = '1';
    document.getElementById('banner-form').submit();
  });

  // Character counters
  document.querySelectorAll('[data-counter]').forEach(function (el) {
    var c = document.createElement('span'); c.className = 'counter';
    el.insertAdjacentElement('afterend', c);
    var up = function () { c.textContent = el.value.length + ' / ' + el.maxLength; };
    el.addEventListener('input', up); up();
  });

  // Live preview of the banner
  var active = document.getElementById('f-active'), title = document.getElementById('f-title'),
      text = document.getElementById('f-text'), file = document.getElementById('f-image');
  var pvTitle = document.getElementById('pv-title'), pvText = document.getElementById('pv-text'),
      pvImg = document.getElementById('pv-img'), pvStrip = document.getElementById('pv-strip'),
      pvOff = document.getElementById('pv-off');
  function preview() {
    if (!active) return;
    pvTitle.textContent = title.value.trim() ? title.value.trim() + ':' : 'Oznámení:';
    pvText.textContent = text.value;
    var on = active.checked && (title.value.trim() || text.value.trim());
    pvStrip.hidden = !on; pvOff.hidden = !!on;
    pvImg.style.display = on ? '' : 'none';
  }
  [active, title, text].forEach(function (el) { el && el.addEventListener('input', preview); });
  active && active.addEventListener('change', preview);
  preview();

  // Shrink big phone photos in the browser before upload (faster on mobile data,
  // stays under server limits). The server still validates and re-encodes.
  var save = document.getElementById('banner-save'), status = document.getElementById('img-status');
  if (file) file.addEventListener('change', function () {
    var f = file.files && file.files[0];
    if (!f) return;
    pvImg.src = URL.createObjectURL(f); pvImg.hidden = false; preview();
    if (!/^image\//.test(f.type) || f.size < 1.5 * 1024 * 1024 || !window.createImageBitmap || !window.DataTransfer) return;
    save.disabled = true; status.hidden = false; status.textContent = 'Připravuji fotku…';
    createImageBitmap(f, { imageOrientation: 'from-image' }).then(function (bmp) {
      var scale = Math.min(1, 2000 / bmp.width);
      var c = document.createElement('canvas');
      c.width = Math.round(bmp.width * scale); c.height = Math.round(bmp.height * scale);
      var ctx = c.getContext('2d'); ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height);
      ctx.drawImage(bmp, 0, 0, c.width, c.height);
      return new Promise(function (res) { c.toBlob(res, 'image/jpeg', 0.88); });
    }).then(function (blob) {
      if (blob) {
        var dt = new DataTransfer();
        dt.items.add(new File([blob], 'foto.jpg', { type: 'image/jpeg' }));
        file.files = dt.files;
      }
    }).catch(function () { /* keep the original file */ }).then(function () {
      save.disabled = false; status.hidden = true;
    });
  });
})();
JS;
}
