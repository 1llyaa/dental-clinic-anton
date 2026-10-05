import type { Hours } from '../lib/content';

// Build-time fallback (SEO / no-JS). Live values come from /data/hours.json,
// which the owner edits in the admin. Keep in sync with server-seed/data/hours.json.
export const defaultHours: Hours = {
  regular: [
    { day: 'Pondělí', value: '8:00 – 16:00' },
    { day: 'Úterý', value: '8:00 – 16:00' },
    { day: 'Středa', value: '8:00 – 16:00' },
    { day: 'Čtvrtek', value: '8:00 – 16:00' },
    { day: 'Pátek', value: '8:00 – 13:00' },
    { day: 'Sobota', value: 'Zavřeno' },
    { day: 'Neděle', value: 'Zavřeno' },
  ],
  note: '',
};
