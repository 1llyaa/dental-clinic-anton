export interface Banner {
  active: boolean;
  title: string;
  text: string;
  image: string | null;
  from: string | null;
  until: string | null;
  updated_at?: string;
}

const DATE_RE = /^\d{4}-\d{2}-\d{2}$/;

const isDateOrNull = (v: unknown): v is string | null =>
  v === null || v === undefined || (typeof v === 'string' && DATE_RE.test(v));

/** Narrow unknown JSON to a Banner; returns null when the shape is wrong. */
export function parseBanner(raw: unknown): Banner | null {
  if (!raw || typeof raw !== 'object') return null;
  const b = raw as Record<string, unknown>;
  if (typeof b.active !== 'boolean') return null;
  if (!isDateOrNull(b.from) || !isDateOrNull(b.until)) return null;
  const str = (v: unknown) => (typeof v === 'string' ? v : '');
  return {
    active: b.active,
    title: str(b.title),
    text: str(b.text),
    image: typeof b.image === 'string' && b.image.startsWith('/uploads/') ? b.image : null,
    from: (b.from as string | null | undefined) ?? null,
    until: (b.until as string | null | undefined) ?? null,
    updated_at: str(b.updated_at),
  };
}

/** Banner shows when active and today (YYYY-MM-DD) is within from–until, both inclusive and optional. */
export function isBannerVisible(raw: unknown, today: string): boolean {
  const b = parseBanner(raw);
  if (!b || !b.active) return false;
  if (!b.title.trim() && !b.text.trim()) return false;
  if (b.from && today < b.from) return false;
  if (b.until && today > b.until) return false;
  return true;
}

/** Calendar date in the clinic's time zone, as YYYY-MM-DD. */
export function pragueToday(now: Date = new Date()): string {
  return new Intl.DateTimeFormat('sv-SE', {
    timeZone: 'Europe/Prague',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(now);
}
