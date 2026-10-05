export interface HoursRow {
  day: string;
  value: string;
}
export interface Hours {
  regular: HoursRow[];
  note: string;
}
export interface NewsItem {
  id: string;
  date: string;
  title: string;
  text: string;
}

const isObj = (v: unknown): v is Record<string, unknown> => !!v && typeof v === 'object';

export function parseHours(raw: unknown): Hours | null {
  if (!isObj(raw) || !Array.isArray(raw.regular)) return null;
  const regular = raw.regular
    .filter(
      (r): r is HoursRow => isObj(r) && typeof r.day === 'string' && typeof r.value === 'string',
    )
    .map(({ day, value }) => ({ day, value }));
  if (!regular.length) return null;
  return { regular, note: typeof raw.note === 'string' ? raw.note : '' };
}

export function parseNews(raw: unknown): NewsItem[] {
  if (!isObj(raw) || !Array.isArray(raw.items)) return [];
  return raw.items
    .filter(
      (i): i is NewsItem =>
        isObj(i) &&
        typeof i.id === 'string' &&
        typeof i.date === 'string' &&
        /^\d{4}-\d{2}-\d{2}$/.test(i.date) &&
        typeof i.title === 'string' &&
        typeof i.text === 'string' &&
        (i.title.trim() !== '' || i.text.trim() !== ''),
    )
    .map(({ id, date, title, text }) => ({ id, date, title, text }))
    .sort((a, b) => (a.date < b.date ? 1 : a.date > b.date ? -1 : 0));
}

/** 2026-09-01 → "1. 9. 2026" */
export function formatCzechDate(iso: string): string {
  const [y, m, d] = iso.split('-').map(Number);
  return `${d}. ${m}. ${y}`;
}
