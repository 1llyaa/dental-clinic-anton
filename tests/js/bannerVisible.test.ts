import { describe, expect, it } from 'vitest';
import { isBannerVisible, pragueToday } from '../../src/lib/bannerVisible';

const base = {
  active: true,
  title: 'Dovolená',
  text: 'Zavřeno',
  image: null,
  from: null,
  until: null,
};

describe('isBannerVisible', () => {
  it('shows active banner without dates', () => {
    expect(isBannerVisible(base, '2026-07-10')).toBe(true);
  });
  it('hides inactive banner', () => {
    expect(isBannerVisible({ ...base, active: false }, '2026-07-10')).toBe(false);
  });
  it('respects from (inclusive)', () => {
    expect(isBannerVisible({ ...base, from: '2026-07-10' }, '2026-07-09')).toBe(false);
    expect(isBannerVisible({ ...base, from: '2026-07-10' }, '2026-07-10')).toBe(true);
  });
  it('respects until (inclusive)', () => {
    expect(isBannerVisible({ ...base, until: '2026-07-25' }, '2026-07-25')).toBe(true);
    expect(isBannerVisible({ ...base, until: '2026-07-25' }, '2026-07-26')).toBe(false);
  });
  it('respects both bounds', () => {
    const b = { ...base, from: '2026-07-01', until: '2026-07-25' };
    expect(isBannerVisible(b, '2026-06-30')).toBe(false);
    expect(isBannerVisible(b, '2026-07-15')).toBe(true);
    expect(isBannerVisible(b, '2026-09-01')).toBe(false);
  });
  it('hides banner with nothing to say', () => {
    expect(isBannerVisible({ ...base, title: '', text: '' }, '2026-07-10')).toBe(false);
  });
  it('rejects malformed data silently', () => {
    expect(isBannerVisible(null, '2026-07-10')).toBe(false);
    expect(isBannerVisible('x', '2026-07-10')).toBe(false);
    expect(isBannerVisible({ active: 'yes', text: 'a' }, '2026-07-10')).toBe(false);
    expect(isBannerVisible({ ...base, from: 'tomorrow' }, '2026-07-10')).toBe(false);
  });
});

describe('pragueToday', () => {
  it('uses Prague calendar date, not UTC', () => {
    // 2026-07-09 23:30 UTC = 2026-07-10 01:30 in Prague (CEST)
    expect(pragueToday(new Date('2026-07-09T23:30:00Z'))).toBe('2026-07-10');
  });
});
