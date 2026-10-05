<?php
declare(strict_types=1);

// Banner image upload: validate the real file type, resize with GD and
// re-encode to JPEG (drops EXIF and anything hidden in the file).

final class UploadError extends RuntimeException
{
}

const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_IMAGE_PIXELS = 50_000_000; // guards GD memory; ~50 MP phone photos

function upload_error_message(int $code, int $maxBytes): string
{
    $mb = (int) round($maxBytes / 1024 / 1024);
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return "Obrázek je příliš velký. Nahrajte prosím fotku menší než $mb MB.";
        case UPLOAD_ERR_PARTIAL:
            return 'Obrázek se nenahrál celý. Zkuste to prosím znovu.';
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
        case UPLOAD_ERR_EXTENSION:
            return 'Obrázek se nepodařilo uložit na server. Kontaktujte prosím správce webu.';
        default:
            return 'Obrázek se nepodařilo nahrát. Zkuste to prosím znovu.';
    }
}

/** True when the request carried a file in field $field. */
function has_upload(array $files, string $field): bool
{
    return isset($files[$field]) && ($files[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

/**
 * Validate an uploaded file and store it as uploads/banner-<timestamp>.jpg.
 * $isUploaded lets tests bypass is_uploaded_file(). Returns the public URL.
 */
function process_banner_upload(array $file, array $config, ?callable $isUploaded = null): string
{
    $isUploaded = $isUploaded ?? 'is_uploaded_file';
    $max = (int) $config['max_upload'];

    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) {
        throw new UploadError(upload_error_message($err, $max));
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !$isUploaded($tmp)) {
        throw new UploadError(upload_error_message(-1, $max));
    }
    if (filesize($tmp) > $max) {
        throw new UploadError(upload_error_message(UPLOAD_ERR_INI_SIZE, $max));
    }
    if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
        throw new UploadError('Server teď neumí upravovat obrázky, proto ho nelze nahrát. Kontaktujte prosím správce webu.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) {
        throw new UploadError('Tento soubor není fotka. Nahrajte prosím obrázek ve formátu JPG, PNG nebo WEBP.');
    }
    $info = @getimagesize($tmp);
    if (!$info || $info[0] < 1 || $info[1] < 1) {
        throw new UploadError('Obrázek se nepodařilo přečíst. Zkuste prosím jinou fotku.');
    }
    if ($info[0] * $info[1] > MAX_IMAGE_PIXELS) {
        throw new UploadError('Fotka má příliš velké rozlišení. Zmenšete ji prosím a nahrajte znovu.');
    }

    @ini_set('memory_limit', '256M');
    if (!image_fits_memory($info[0], $info[1], (int) $config['max_width'], ini_bytes((string) ini_get('memory_limit')))) {
        // A fatal out-of-memory error would show a blank page instead of this message.
        throw new UploadError('Fotka má příliš velké rozlišení. Zmenšete ji prosím a nahrajte znovu.');
    }
    $angle = $mime === 'image/jpeg' ? exif_rotation($tmp) : 0;
    $src = load_image($tmp, $mime);
    if (!$src) {
        throw new UploadError('Obrázek se nepodařilo přečíst. Zkuste prosím jinou fotku.');
    }
    // Resize first, rotate the small copy: rotating the full photo would need a second full-size bitmap.
    $out = resize_to_width($src, (int) $config['max_width'], $angle !== 0 && $angle !== 180);
    if ($angle !== 0) {
        $rotated = imagerotate($out, $angle, 0);
        if ($rotated) {
            imagedestroy($out);
            $out = $rotated;
        }
    }

    $dir = $config['uploads_dir'];
    if (!is_dir($dir) || !is_writable($dir)) {
        throw new UploadError('Složka pro obrázky není dostupná. Kontaktujte prosím správce webu.');
    }
    $name = 'banner-' . time() . '-' . bin2hex(random_bytes(3)) . '.jpg';
    $tmpOut = $dir . '/.' . $name . '.tmp';
    $ok = imagejpeg($out, $tmpOut, 82);
    imagedestroy($out);
    if (!$ok || !rename($tmpOut, $dir . '/' . $name)) {
        @unlink($tmpOut);
        throw new UploadError('Obrázek se nepodařilo uložit. Zkuste to prosím znovu.');
    }
    @chmod($dir . '/' . $name, 0644);
    return rtrim($config['uploads_url'], '/') . '/' . $name;
}

/** @return GdImage|false */
function load_image(string $path, string $mime)
{
    switch ($mime) {
        case 'image/jpeg':
            return @imagecreatefromjpeg($path);
        case 'image/png':
            return @imagecreatefrompng($path);
        case 'image/webp':
            return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
    }
    return false;
}

/**
 * Phone photos store rotation in EXIF; we bake it into the pixels because
 * re-encoding drops EXIF. Returns the imagerotate() angle (0 = none).
 */
function exif_rotation(string $path): int
{
    if (!function_exists('exif_read_data')) {
        return 0;
    }
    $exif = @exif_read_data($path);
    return [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
}

/** '128M' → bytes; -1 means unlimited. */
function ini_bytes(string $v): int
{
    $v = trim($v);
    if ($v === '' || $v === '-1') {
        return -1;
    }
    $n = (int) $v;
    switch (strtolower(substr($v, -1))) {
        case 'g': return $n * 1024 ** 3;
        case 'm': return $n * 1024 ** 2;
        case 'k': return $n * 1024;
    }
    return $n;
}

/** Rough GD memory need (~5 B/pixel for source + output, plus PHP overhead) vs. the limit. */
function image_fits_memory(int $w, int $h, int $maxWidth, int $limit): bool
{
    if ($limit < 0) {
        return true;
    }
    $outW = min(max($w, $h), $maxWidth);
    $need = $w * $h * 5 + $outW * $outW * 5 * 2 + 24 * 1024 ** 2;
    return $need <= $limit - memory_get_usage();
}

/**
 * Scale down so the final width is at most $maxWidth (never up), flattening
 * transparency onto white. $willRotate90: the image is rotated by 90° afterwards,
 * so its current height becomes the final width.
 */
function resize_to_width($src, int $maxWidth, bool $willRotate90 = false)
{
    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1, $maxWidth / ($willRotate90 ? $h : $w));
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);
    return $dst;
}

/** Delete a previous banner image, but only files we created ourselves. */
function delete_banner_image(?string $url, array $config): void
{
    if (!$url) {
        return;
    }
    $name = basename($url);
    if (!preg_match('/^banner-\d+(-[a-f0-9]+)?\.jpg$/', $name)) {
        return;
    }
    $path = $config['uploads_dir'] . '/' . $name;
    if (is_file($path)) {
        @unlink($path);
    }
}
