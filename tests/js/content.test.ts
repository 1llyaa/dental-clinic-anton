import { describe, expect, it } from 'vitest';
import { formatCzechDate, parseHours, parseNews } from '../../src/lib/content';

describe('parseHours', () => {
  it('accepts valid hours', () => {
    const h = parseHours({
      regular: [{ day: 'Pondělí', value: '8:00 – 16:00' }],
      note: 'Pauza',
      updated_at: 'x',
    });
    expect(h).toEqual({ regular: [{ day: 'Pondělí', value: '8:00 – 16:00' }], note: 'Pauza' });
  });
  it('drops malformed rows and requires at least one', () => {
    expect(parseHours({ regular: [{ day: 1 }] })).toBeNull();
    expect(parseHours({ regular: 'x' })).toBeNull();
    expect(parseHours(null)).toBeNull();
    expect(parseHours({ regular: [{ day: 'Po', value: 'x' }, { foo: 1 }] })?.regular).toHaveLength(
      1,
    );
  });
  it('defaults note to empty string', () => {
    expect(parseHours({ regular: [{ day: 'Po', value: 'x' }] })?.note).toBe('');
  });
});

describe('parseNews', () => {
  it('sorts by date descending and drops invalid items', () => {
    const n = parseNews({
      items: [
        { id: 'a', date: '2026-03-10', title: 'Starší', text: 't' },
        { id: 'b', date: '2026-09-01', title: 'Novější', text: 't' },
        { id: 'c', date: 'bad', title: 'x', text: 't' },
        { id: 'd', date: '2026-01-01', title: '', text: '' },
      ],
    });
    expect(n.map((i) => i.id)).toEqual(['b', 'a']);
  });
  it('returns empty list for garbage', () => {
    expect(parseNews(undefined)).toEqual([]);
    expect(parseNews({ items: 5 })).toEqual([]);
  });
});

describe('formatCzechDate', () => {
  it('formats ISO date as Czech short date', () => {
    expect(formatCzechDate('2026-09-01')).toBe('1. 9. 2026');
    expect(formatCzechDate('2026-12-24')).toBe('24. 12. 2026');
  });
});
