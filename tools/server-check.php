<?php
// One-off server check. Upload to /admin/check.php, open it in the browser,
// then DELETE it. Not part of dist/ on purpose.
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');

$root = dirname(__DIR__);
$rows = [];
$ok = function (string $label, bool $pass, string $detail = '') use (&$rows) {
    $rows[] = sprintf('[%s] %s%s', $pass ? ' OK ' : 'FAIL', $label, $detail !== '' ? " — $detail" : '');
};
$bytes = function (string $v): int {
    $n = (int) $v;
    switch (strtolower(substr(trim($v), -1))) {
        case 'g': return $n * 1024 ** 3;
        case 'm': return $n * 1024 ** 2;
        case 'k': return $n * 1024;
    }
    return $n;
};

$ok('PHP >= 8.0', PHP_VERSION_ID >= 80000, PHP_VERSION);
$ok('GD', extension_loaded('gd'), extension_loaded('gd') ? json_encode(array_intersect_key(gd_info(), array_flip(['JPEG Support', 'PNG Support', 'WebP Support']))) : 'missing');
$ok('fileinfo (finfo)', class_exists('finfo'));
$ok('mbstring', extension_loaded('mbstring'));
$ok('exif (photo rotation, optional)', function_exists('exif_read_data'));
$ok('HTTPS', ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

foreach (['data', 'data/backups', 'data/private', 'uploads'] as $d) {
    $path = "$root/$d";
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
    $probe = "$path/.write-test-" . bin2hex(random_bytes(4));
    $w = @file_put_contents($probe, 'x') === 1;
    @unlink($probe);
    $ok("writable $d/", $w, $path);
}
$ok('admin/config.php present', is_file(__DIR__ . '/config.php'));

$up = ini_get('upload_max_filesize');
$post = ini_get('post_max_size');
$ok('upload_max_filesize >= 10M', $bytes($up) >= 10 * 1024 ** 2, $up);
$ok('post_max_size >= 11M', $bytes($post) >= 11 * 1024 ** 2, $post);
$ok('memory_limit (256M recommended)', ini_get('memory_limit') === '-1' || $bytes(ini_get('memory_limit')) >= 128 * 1024 ** 2, ini_get('memory_limit'));

echo implode("\n", $rows), "\n\n";
echo "Absolute path of admin/ (for AuthUserFile in admin/.htaccess):\n  " . __DIR__ . "/.htpasswd\n\n";
echo "Delete this file after checking!\n";
