<?php
declare(strict_types=1);

// Pure validation helpers. Each validate_* returns [cleanData, errors[]].

const BANNER_TITLE_MAX = 80;
const BANNER_TEXT_MAX = 600;
const HOURS_VALUE_MAX = 60;
const HOURS_NOTE_MAX = 200;
const NEWS_TITLE_MAX = 120;
const NEWS_TEXT_MAX = 1500;
const DAYS = ['Pondělí', 'Úterý', 'Středa', 'Čtvrtek', 'Pátek', 'Sobota', 'Neděle'];

/** Trim, normalise newlines, drop control characters, cut to $max characters. */
function clean_text($value, int $max, bool $multiline = false): string
{
    $s = is_string($value) ? $value : '';
    if (!mb_check_encoding($s, 'UTF-8')) {
        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    }
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $s = $multiline
        ? preg_replace('/[^\P{C}\n]+/u', '', $s)
        : preg_replace('/\p{C}+/u', ' ', $s);
    if ($multiline) {
        $s = preg_replace("/\n{3,}/", "\n\n", (string) $s);
    }
    return mb_substr(trim((string) $s), 0, $max);
}

/** '' → null, valid Y-m-d → same string, anything else → false. */
function clean_date($value)
{
    $s = is_string($value) ? trim($value) : '';
    if ($s === '') {
        return null;
    }
    $d = DateTime::createFromFormat('!Y-m-d', $s);
    if (!$d || $d->format('Y-m-d') !== $s) {
        return false;
    }
    return $s;
}

function validate_banner(array $post): array
{
    $errors = [];
    $from = clean_date($post['from'] ?? '');
    $until = clean_date($post['until'] ?? '');
    if ($from === false) {
        $errors[] = 'Datum „Zobrazit od“ není platné.';
        $from = null;
    }
    if ($until === false) {
        $errors[] = 'Datum „Zobrazit do“ není platné.';
        $until = null;
    }
    if ($from !== null && $until !== null && $until < $from) {
        $errors[] = 'Datum „do“ je dříve než datum „od“. Opravte prosím data.';
    }
    $data = [
        'active' => !empty($post['active']),
        'title' => clean_text($post['title'] ?? '', BANNER_TITLE_MAX),
        'text' => clean_text($post['text'] ?? '', BANNER_TEXT_MAX, true),
        'from' => $from,
        'until' => $until,
    ];
    if ($data['active'] && $data['title'] === '' && $data['text'] === '') {
        $errors[] = 'Oznámení je zapnuté, ale nemá nadpis ani text. Doplňte alespoň jedno.';
    }
    return [$data, $errors];
}

function validate_hours(array $post): array
{
    $values = is_array($post['value'] ?? null) ? $post['value'] : [];
    $regular = [];
    foreach (DAYS as $i => $day) {
        $regular[] = ['day' => $day, 'value' => clean_text($values[$i] ?? '', HOURS_VALUE_MAX)];
    }
    $errors = [];
    foreach ($regular as $row) {
        if ($row['value'] === '') {
            $errors[] = "Vyplňte prosím hodiny pro den {$row['day']} (např. „Zavřeno“).";
        }
    }
    return [['regular' => $regular, 'note' => clean_text($post['note'] ?? '', HOURS_NOTE_MAX)], $errors];
}

function validate_news_item(array $post): array
{
    $errors = [];
    $date = clean_date($post['date'] ?? '');
    if ($date === null || $date === false) {
        $errors[] = 'Vyplňte prosím platné datum novinky.';
        $date = '';
    }
    $item = [
        'date' => $date,
        'title' => clean_text($post['title'] ?? '', NEWS_TITLE_MAX),
        'text' => clean_text($post['text'] ?? '', NEWS_TEXT_MAX, true),
    ];
    if ($item['title'] === '') {
        $errors[] = 'Vyplňte prosím nadpis novinky.';
    }
    return [$item, $errors];
}

function new_news_id(): string
{
    return 'n-' . time() . '-' . bin2hex(random_bytes(3));
}

/** Sort news newest first (stable for equal dates). */
function sort_news(array $items): array
{
    usort($items, fn($a, $b) => strcmp($b['date'], $a['date']));
    return array_values($items);
}
