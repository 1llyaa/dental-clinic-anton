<?php
declare(strict_types=1);
// Plain-PHP test runner (no Composer). Run: php tests/php/run.php

require __DIR__ . '/../../public/admin/lib/store.php';
require __DIR__ . '/../../public/admin/lib/validate.php';
require __DIR__ . '/../../public/admin/lib/upload.php';
require __DIR__ . '/../../public/admin/lib/auth.php';
require __DIR__ . '/../../public/admin/lib/view.php';

$passed = 0;
$failed = [];
function test(string $name, callable $fn): void
{
    global $passed, $failed;
    try {
        $fn();
        $passed++;
        echo "  ✓ $name\n";
    } catch (Throwable $e) {
        $failed[] = $name;
        echo "  ✗ $name\n      " . $e->getMessage() . ' @ line ' . $e->getLine() . "\n";
    }
}
function eq($expected, $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new Exception(($msg ? "$msg: " : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}
function ok($cond, string $msg = 'assertion failed'): void
{
    if (!$cond) {
        throw new Exception($msg);
    }
}
function throws(callable $fn, string $class = Throwable::class): Throwable
{
    try {
        $fn();
    } catch (Throwable $e) {
        ok($e instanceof $class, 'wrong exception ' . get_class($e) . ': ' . $e->getMessage());
        return $e;
    }
    throw new Exception("expected $class");
}
function tmpdir(): string
{
    $d = sys_get_temp_dir() . '/zl-test-' . bin2hex(random_bytes(4));
    mkdir($d . '/private', 0777, true);
    mkdir($d . '/uploads', 0777, true);
    return $d;
}
function cfg(string $dir): array
{
    return ['data_dir' => $dir, 'uploads_dir' => $dir . '/uploads', 'uploads_url' => '/uploads',
        'max_upload' => 10 * 1024 * 1024, 'max_width' => 1200, 'session_ttl' => 3600, 'cookie_secure' => false];
}
$fakeUploaded = fn() => true;

echo "validation\n";
test('clean_text trims, strips control chars, cuts by characters', function () {
    eq('Dovolená', clean_text("  Dovolená\x07 ", 80));
    eq('ččč', clean_text('čččč', 3));
    eq("a\n\nb", clean_text("a\r\n\r\n\r\n\r\nb", 50, true));
    eq('a b', clean_text("a\nb", 50));
    eq('', clean_text(['array'], 10));
});
test('clean_date validates strictly', function () {
    eq(null, clean_date(''));
    eq('2026-07-25', clean_date('2026-07-25'));
    eq(false, clean_date('2026-02-30'));
    eq(false, clean_date('25.7.2026'));
    eq(false, clean_date('2026-7-5'));
});
test('banner: until before from is rejected', function () {
    [, $err] = validate_banner(['active' => '1', 'text' => 'x', 'from' => '2026-07-10', 'until' => '2026-07-01']);
    eq(1, count($err));
});
test('banner: active without any text is rejected', function () {
    [, $err] = validate_banner(['active' => '1', 'title' => ' ', 'text' => '']);
    eq(1, count($err));
});
test('banner: lengths are cut on the server', function () {
    [$d, $err] = validate_banner(['title' => str_repeat('á', 200), 'text' => str_repeat('b', 900)]);
    eq([], $err);
    eq(80, mb_strlen($d['title']));
    eq(600, mb_strlen($d['text']));
    eq(false, $d['active']);
});
test('banner: invalid date reported, not stored', function () {
    [$d, $err] = validate_banner(['active' => '1', 'text' => 'x', 'until' => 'zítra']);
    eq(null, $d['until']);
    eq(1, count($err));
});
test('hours: all seven days required, values free text', function () {
    [$d, $err] = validate_hours(['value' => ['8:00 – 16:00', 'Zavřeno', 'Dle objednání', 'x', 'x', 'x', ''], 'note' => 'Pauza']);
    eq(1, count($err));
    eq(7, count($d['regular']));
    eq('Neděle', $d['regular'][6]['day']);
    eq('Dle objednání', $d['regular'][2]['value']);
});
test('news: date and title required', function () {
    [, $err] = validate_news_item(['date' => '', 'title' => '']);
    eq(2, count($err));
    [$item, $err] = validate_news_item(['date' => '2026-09-01', 'title' => 'Nové termíny', 'text' => "a\nb"]);
    eq([], $err);
    eq("a\nb", $item['text']);
});
test('news sorted newest first', function () {
    $s = sort_news([['date' => '2026-01-01'], ['date' => '2026-09-01'], ['date' => '2026-03-01']]);
    eq(['2026-09-01', '2026-03-01', '2026-01-01'], array_column($s, 'date'));
});

echo "storage\n";
test('write_json_atomic writes readable unicode JSON and leaves no temp files', function () {
    $d = tmpdir();
    write_json_atomic("$d/banner.json", ['title' => 'Dovolená', 'image' => '/uploads/a.jpg']);
    $raw = file_get_contents("$d/banner.json");
    ok(strpos($raw, 'Dovolená') !== false, 'diacritics escaped');
    ok(strpos($raw, '/uploads/a.jpg') !== false, 'slashes escaped');
    eq('Dovolená', read_json("$d/banner.json")['title']);
    eq(['banner.json', 'private', 'uploads'], array_values(array_diff(scandir($d), ['.', '..'])));
});
test('failed write keeps the previous file intact', function () {
    $d = tmpdir();
    write_json_atomic("$d/banner.json", ['title' => 'old']);
    throws(fn() => write_json_atomic("$d/banner.json", ['bad' => NAN]));
    eq('old', read_json("$d/banner.json")['title']);
    // a stray temp file from a killed request is ignored by readers
    file_put_contents("$d/.banner.json.dead.tmp", '{"title": "hal');
    eq('old', read_json("$d/banner.json")['title']);
});
test('read_json falls back on broken file', function () {
    $d = tmpdir();
    file_put_contents("$d/x.json", '{broken');
    eq(['d' => 1], read_json("$d/x.json", ['d' => 1]));
});
test('backup keeps only the newest 10 per file', function () {
    $d = tmpdir();
    file_put_contents("$d/banner.json", '{}');
    file_put_contents("$d/hours.json", '{}');
    for ($i = 0; $i < 13; $i++) {
        backup_file("$d/banner.json", "$d/backups", 10);
    }
    backup_file("$d/hours.json", "$d/backups", 10);
    eq(10, count(glob("$d/backups/banner-*.json")));
    eq(1, count(glob("$d/backups/hours-*.json")));
});

echo "login rate limit\n";
test('5 failures lock the IP for 15 minutes, others unaffected', function () {
    $c = cfg(tmpdir());
    $t = 1_000_000;
    for ($i = 0; $i < 4; $i++) {
        record_login_failure($c, '1.2.3.4', $t);
    }
    eq(0, login_locked_for($c, '1.2.3.4', $t));
    record_login_failure($c, '1.2.3.4', $t);
    eq(900, login_locked_for($c, '1.2.3.4', $t));
    eq(0, login_locked_for($c, '5.6.7.8', $t));
    eq(0, login_locked_for($c, '1.2.3.4', $t + 901));
    record_login_failure($c, '1.2.3.4', $t + 901); // counter restarts after the lock
    eq(0, login_locked_for($c, '1.2.3.4', $t + 901));
});
test('successful login clears failures', function () {
    $c = cfg(tmpdir());
    record_login_failure($c, '1.2.3.4');
    clear_login_failures($c, '1.2.3.4');
    eq([], read_json(attempts_path($c)));
});
test('password_configured rejects empty and placeholder', function () {
    eq(false, password_configured(['password_hash' => '']));
    eq(false, password_configured(['password_hash' => '$2y$12$...']));
    eq(true, password_configured(['password_hash' => password_hash('x', PASSWORD_DEFAULT)]));
});

echo "upload\n";
function fake_upload(string $path): array
{
    return ['name' => 'x', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path)];
}
test('PHP file renamed to .jpg is rejected', function () use ($fakeUploaded) {
    $d = tmpdir();
    file_put_contents("$d/shell.jpg", "<?php system(\$_GET['c']); ?>");
    $e = throws(fn() => process_banner_upload(fake_upload("$d/shell.jpg"), cfg($d), $fakeUploaded), UploadError::class);
    ok(strpos($e->getMessage(), 'není fotka') !== false, $e->getMessage());
    eq([], glob("$d/uploads/*"));
});
test('PHP payload hidden in a valid JPEG is stripped by re-encoding', function () use ($fakeUploaded) {
    $d = tmpdir();
    $im = imagecreatetruecolor(50, 50);
    imagejpeg($im, "$d/p.jpg");
    file_put_contents("$d/p.jpg", '<?php echo 1; ?>', FILE_APPEND);
    $url = process_banner_upload(fake_upload("$d/p.jpg"), cfg($d), $fakeUploaded);
    $saved = file_get_contents($d . '/uploads/' . basename($url));
    ok(strpos($saved, '<?php') === false, 'payload survived');
});
test('large photo is resized to 1200px wide JPEG, small file', function () use ($fakeUploaded) {
    $d = tmpdir();
    $im = imagecreatetruecolor(4032, 3024);
    for ($y = 0; $y < 3024; $y += 8) { // gradient + noise, photo-like
        for ($x = 0; $x < 4032; $x += 8) {
            imagefilledrectangle($im, $x, $y, $x + 7, $y + 7, imagecolorallocate($im, intdiv($x, 16) % 256, intdiv($y, 12) % 256, random_int(80, 140)));
        }
    }
    imagejpeg($im, "$d/big.jpg", 98);
    $url = process_banner_upload(fake_upload("$d/big.jpg"), cfg($d), $fakeUploaded);
    ok(preg_match('#^/uploads/banner-\d+-[a-f0-9]{6}\.jpg$#', $url) === 1, $url);
    $out = $d . '/uploads/' . basename($url);
    [$w, $h, $type] = getimagesize($out);
    eq([1200, 900, IMAGETYPE_JPEG], [$w, $h, $type]);
    ok(filesize($out) < 600 * 1024, 'output too big: ' . filesize($out));
    echo '      (input ' . round(filesize("$d/big.jpg") / 1024) . ' kB → output ' . round(filesize($out) / 1024) . " kB)\n";
});
test('PNG with transparency becomes JPEG, small images are not upscaled', function () use ($fakeUploaded) {
    $d = tmpdir();
    $im = imagecreatetruecolor(300, 200);
    imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagepng($im, "$d/a.png");
    $url = process_banner_upload(fake_upload("$d/a.png"), cfg($d), $fakeUploaded);
    [$w, , $type] = getimagesize($d . '/uploads/' . basename($url));
    eq([300, IMAGETYPE_JPEG], [$w, $type]);
    $out = imagecreatefromjpeg($d . '/uploads/' . basename($url));
    eq(255, (imagecolorat($out, 5, 5) >> 16) & 0xFF, 'transparent area should be white');
});
test('too large upload gives a clear Czech message', function () use ($fakeUploaded) {
    $d = tmpdir();
    $e = throws(fn() => process_banner_upload(['error' => UPLOAD_ERR_INI_SIZE], cfg($d), $fakeUploaded), UploadError::class);
    ok(strpos($e->getMessage(), 'menší než 10 MB') !== false, $e->getMessage());
});
test('file not from an HTTP upload is refused', function () {
    $d = tmpdir();
    file_put_contents("$d/x.jpg", 'x');
    throws(fn() => process_banner_upload(fake_upload("$d/x.jpg"), cfg($d)), UploadError::class);
});
test('delete_banner_image only removes files the admin created', function () {
    $d = tmpdir();
    $c = cfg($d);
    touch("$d/uploads/banner-1720000000-abcdef.jpg");
    touch("$d/uploads/other.jpg");
    delete_banner_image('/uploads/../uploads/other.jpg', $c);
    delete_banner_image('/uploads/banner-1720000000-abcdef.jpg', $c);
    ok(is_file("$d/uploads/other.jpg"));
    ok(!is_file("$d/uploads/banner-1720000000-abcdef.jpg"));
});

echo "view\n";
test('e() escapes HTML', function () {
    eq('&lt;script&gt;&quot;&#039;', e('<script>"\''));
});
test('banner_status explains why the banner is (not) shown', function () {
    $b = ['active' => true, 'title' => 'Dovolená', 'text' => '', 'from' => null, 'until' => '2026-07-25'];
    eq('past', banner_status($b, '2026-07-26')[0]);
    eq('on', banner_status($b, '2026-07-25')[0]);
    eq('future', banner_status(['from' => '2026-08-01'] + $b, '2026-07-20')[0]);
    eq('off', banner_status(['active' => false] + $b, '2026-07-20')[0]);
});

echo "\n$passed passed, " . count($failed) . " failed\n";
exit($failed ? 1 : 0);
