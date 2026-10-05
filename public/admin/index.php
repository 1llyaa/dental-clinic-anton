<?php
declare(strict_types=1);

// Clinic admin: one page, three forms (banner, opening hours, news).
// Plain PHP, no dependencies. See backend-spec-banner.md.

require __DIR__ . '/lib/store.php';
require __DIR__ . '/lib/validate.php';
require __DIR__ . '/lib/upload.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/view.php';

date_default_timezone_set('Europe/Prague');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; img-src 'self' blob: data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; frame-ancestors 'none'; form-action 'self'; base-uri 'none'");

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    render_setup_missing_config();
    exit;
}
$config = require $configFile;

if (!password_configured($config)) {
    render_password_setup($_SERVER['REQUEST_METHOD'] === 'POST' ? (string) ($_POST['password'] ?? '') : null);
    exit;
}

start_admin_session($config);
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A file larger than post_max_size makes PHP silently drop the whole form.
    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('error', upload_error_message(UPLOAD_ERR_INI_SIZE, (int) $config['max_upload']), 'oznameni');
        redirect('oznameni');
    }
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        flash('error', 'Platnost stránky vypršela. Zkuste to prosím znovu.');
        redirect();
    }
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'login') {
        handle_login($config, $ip);
    }
    if (!is_logged_in($config)) {
        redirect();
    }
    try {
        switch ($action) {
            case 'logout':
                logout();
                flash('ok', 'Byli jste odhlášeni.');
                redirect();
            case 'save_banner':
                handle_save_banner($config);
            case 'save_hours':
                handle_save_hours($config);
            case 'save_news':
                handle_save_news($config);
            case 'delete_news':
                handle_delete_news($config);
        }
    } catch (UploadError $e) {
        flash('error', $e->getMessage(), 'oznameni');
        redirect('oznameni');
    } catch (Throwable $e) {
        error_log('[admin] ' . $e->getMessage());
        flash('error', 'Uložení se nepovedlo. Zkuste to prosím znovu, případně kontaktujte správce webu.');
        redirect();
    }
    redirect();
}

if (!is_logged_in($config)) {
    render_login(login_locked_for($config, $ip));
    exit;
}

$editId = isset($_GET['upravit']) ? (string) $_GET['upravit'] : null;
render_dashboard([
    'banner' => read_json($config['data_dir'] . '/banner.json', default_banner()),
    'hours' => read_json($config['data_dir'] . '/hours.json', ['regular' => [], 'note' => '']),
    'news' => sort_news(read_json($config['data_dir'] . '/news.json', ['items' => []])['items'] ?? []),
    'editId' => $editId,
    'config' => $config,
]);

// ---------------------------------------------------------------------------

function handle_login(array $config, string $ip): void
{
    if (login_locked_for($config, $ip) > 0) {
        redirect();
    }
    $password = (string) ($_POST['password'] ?? '');
    if ($password !== '' && password_verify($password, $config['password_hash'])) {
        clear_login_failures($config, $ip);
        login();
        redirect();
    }
    record_login_failure($config, $ip);
    usleep(400000); // slow down guessing a little
    if (login_locked_for($config, $ip) === 0) {
        flash('error', 'Nesprávné heslo.');
    }
    redirect();
}

function handle_save_banner(array $config): void
{
    [$data, $errors] = validate_banner($_POST);
    if ($errors) {
        flash_form('error', $errors, 'oznameni', $_POST);
        redirect('oznameni');
    }
    $path = $config['data_dir'] . '/banner.json';
    $old = read_json($path, default_banner());
    $oldImage = is_string($old['image'] ?? null) ? $old['image'] : null;

    $image = $oldImage;
    $newUpload = null;
    if (has_upload($_FILES, 'image')) {
        try {
            $newUpload = process_banner_upload($_FILES['image'], $config);
        } catch (UploadError $e) {
            flash_form('error', [$e->getMessage()], 'oznameni', $_POST);
            redirect('oznameni');
        }
        $image = $newUpload;
    } elseif (!empty($_POST['remove_image'])) {
        $image = null;
    }

    $data = ['active' => $data['active'], 'title' => $data['title'], 'text' => $data['text'], 'image' => $image,
        'from' => $data['from'], 'until' => $data['until'], 'updated_at' => now_iso()];
    try {
        with_lock($config['data_dir'], fn() => save_data($config, 'banner', $data));
    } catch (Throwable $e) {
        delete_banner_image($newUpload, $config); // keep the old state consistent
        throw $e;
    }
    if ($oldImage !== $image) {
        delete_banner_image($oldImage, $config);
    }
    flash('ok', 'Uloženo. Na webu se změna projeví do minuty.', 'oznameni');
    redirect('oznameni');
}

function handle_save_hours(array $config): void
{
    [$data, $errors] = validate_hours($_POST);
    if ($errors) {
        flash_form('error', $errors, 'hodiny', $_POST);
        redirect('hodiny');
    }
    $data['updated_at'] = now_iso();
    with_lock($config['data_dir'], fn() => save_data($config, 'hours', $data));
    flash('ok', 'Uloženo. Na webu se změna projeví do minuty.', 'hodiny');
    redirect('hodiny');
}

function handle_save_news(array $config): void
{
    [$item, $errors] = validate_news_item($_POST);
    $id = (string) ($_POST['id'] ?? '');
    if ($errors) {
        flash_form('error', $errors, 'aktuality', $_POST);
        redirect('aktuality', $id !== '' ? $id : null);
    }
    $result = with_lock($config['data_dir'], function () use ($config, $item, $id) {
        $news = read_json($config['data_dir'] . '/news.json', ['items' => []]);
        $items = is_array($news['items'] ?? null) ? $news['items'] : [];
        if ($id !== '') {
            $found = false;
            foreach ($items as &$it) {
                if (($it['id'] ?? '') === $id) {
                    $it = ['id' => $id] + $item;
                    $found = true;
                }
            }
            unset($it);
            if (!$found) {
                return 'missing';
            }
        } else {
            if (count($items) >= (int) ($config['news_max'] ?? 50)) {
                return 'full';
            }
            $items[] = ['id' => new_news_id()] + $item;
        }
        save_data($config, 'news', ['items' => sort_news($items), 'updated_at' => now_iso()]);
        return 'ok';
    });
    if ($result === 'full') {
        flash_form('error', ['Novinek je už hodně. Smažte prosím nějakou starší a zkuste to znovu.'], 'aktuality', $_POST);
        redirect('aktuality');
    }
    if ($result === 'missing') {
        flash('error', 'Tato novinka už neexistuje (možná byla smazána).', 'aktuality');
        redirect('aktuality');
    }
    flash('ok', 'Uloženo. Na webu se změna projeví do minuty.', 'aktuality');
    redirect('aktuality');
}

function handle_delete_news(array $config): void
{
    $id = (string) ($_POST['id'] ?? '');
    with_lock($config['data_dir'], function () use ($config, $id) {
        $news = read_json($config['data_dir'] . '/news.json', ['items' => []]);
        $items = array_values(array_filter($news['items'] ?? [], fn($it) => ($it['id'] ?? '') !== $id));
        save_data($config, 'news', ['items' => $items, 'updated_at' => now_iso()]);
    });
    flash('ok', 'Novinka byla smazána.', 'aktuality');
    redirect('aktuality');
}

function default_banner(): array
{
    return ['active' => false, 'title' => '', 'text' => '', 'image' => null, 'from' => null, 'until' => null];
}

function flash(string $type, string $message, ?string $section = null): void
{
    $_SESSION['flash'] = ['type' => $type, 'messages' => [$message], 'section' => $section];
}

/** Flash errors and keep what the user typed so they don't lose it. */
function flash_form(string $type, array $messages, string $section, array $post): void
{
    unset($post['csrf'], $post['action']);
    $_SESSION['flash'] = ['type' => $type, 'messages' => $messages, 'section' => $section, 'old' => $post];
}

/** Post/Redirect/Get: never leave the browser on a POST result. */
function redirect(?string $anchor = null, ?string $editId = null): void
{
    $url = './' . ($editId !== null ? '?upravit=' . rawurlencode($editId) : '') . ($anchor ? '#' . $anchor : '');
    header('Location: ' . $url, true, 303);
    exit;
}
