<?php
declare(strict_types=1);

// JSON state on disk. Every write is atomic (temp file + rename) so an
// interrupted request can never leave a half-written file the website reads.

const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

function now_iso(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format(DATE_ATOM);
}

/** Read and decode a JSON file; returns $default when missing or broken. */
function read_json(string $path, array $default = []): array
{
    if (!is_file($path)) {
        return $default;
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return $default;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
}

/** Write $data as JSON to $path atomically. Throws RuntimeException on failure. */
function write_json_atomic(string $path, array $data): void
{
    $json = json_encode($data, JSON_FLAGS | JSON_THROW_ON_ERROR) . "\n";
    $dir = dirname($path);
    $tmp = $dir . '/.' . basename($path) . '.' . bin2hex(random_bytes(6)) . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) !== strlen($json)) {
        @unlink($tmp);
        throw new RuntimeException("Cannot write $tmp");
    }
    @chmod($tmp, 0644);
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException("Cannot replace $path");
    }
}

/** Copy the current version of $path into $backupDir, keeping the newest $keep copies. */
function backup_file(string $path, string $backupDir, int $keep = 10): void
{
    if (!is_file($path)) {
        return;
    }
    if (!is_dir($backupDir) && !@mkdir($backupDir, 0755, true)) {
        throw new RuntimeException("Cannot create $backupDir");
    }
    $name = pathinfo($path, PATHINFO_FILENAME);
    $stamp = (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('Ymd-His');
    $target = sprintf('%s/%s-%s-%s.json', $backupDir, $name, $stamp, bin2hex(random_bytes(3)));
    if (!copy($path, $target)) {
        throw new RuntimeException("Cannot back up $path");
    }
    $all = glob($backupDir . '/' . $name . '-*.json') ?: [];
    sort($all); // names start with a sortable timestamp
    foreach (array_slice($all, 0, max(0, count($all) - $keep)) as $old) {
        @unlink($old);
    }
}

/**
 * Run $fn while holding an exclusive lock, so two saves at the same moment
 * cannot overwrite each other's changes.
 */
function with_lock(string $dataDir, callable $fn)
{
    $lock = fopen($dataDir . '/private/.write.lock', 'c');
    if ($lock === false) {
        throw new RuntimeException('Cannot open lock file');
    }
    try {
        flock($lock, LOCK_EX);
        return $fn();
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Back up and atomically save one of the public data files. */
function save_data(array $config, string $name, array $data): void
{
    $path = $config['data_dir'] . '/' . $name . '.json';
    backup_file($path, $config['data_dir'] . '/backups', 10);
    write_json_atomic($path, $data);
}
