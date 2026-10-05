// Runtime content from /data/*.json, written by the PHP admin.
// Every failure is silent: patients never see an error, the build-time content stays.
import { isBannerVisible, parseBanner, pragueToday } from '../lib/bannerVisible';
import { formatCzechDate, parseHours, parseNews } from '../lib/content';

async function getJson(path: string): Promise<unknown> {
  const res = await fetch(path, { cache: 'no-cache', headers: { Accept: 'application/json' } });
  if (!res.ok) throw new Error(String(res.status));
  return res.json();
}

const store = {
  get(key: string): string | null {
    try {
      return localStorage.getItem(key);
    } catch {
      return null;
    }
  },
  set(key: string, value: string) {
    try {
      localStorage.setItem(key, value);
    } catch {
      /* private mode etc. */
    }
  },
};

async function initBanner() {
  const strip = document.querySelector<HTMLElement>('[data-banner]');
  if (!strip) return;
  const raw = await getJson('/data/banner.json');
  if (!isBannerVisible(raw, pragueToday())) return;
  const b = parseBanner(raw)!;
  const version = b.updated_at || `${b.title}|${b.text}|${b.from}|${b.until}`;

  const stripKey = 'banner-closed';
  if (store.get(stripKey) !== version) {
    const title = strip.querySelector('[data-banner-title]')!;
    const text = strip.querySelector('[data-banner-text]')!;
    title.textContent = b.title ? `${b.title}:` : 'Oznámení:';
    text.textContent = b.text;
    strip.hidden = false;
    strip.querySelector('[data-banner-close]')?.addEventListener('click', () => {
      strip.hidden = true;
      store.set(stripKey, version);
    });
  }

  const modal = document.querySelector<HTMLDialogElement>('[data-banner-modal]');
  const modalKey = 'banner-modal-seen';
  if (
    b.image &&
    modal &&
    typeof modal.showModal === 'function' &&
    store.get(modalKey) !== version
  ) {
    const img = modal.querySelector<HTMLImageElement>('[data-banner-modal-img]')!;
    img.src = b.image;
    img.alt = b.title || 'Oznámení';
    modal.querySelector('[data-banner-modal-title]')!.textContent = b.title || 'Oznámení';
    modal.querySelector('[data-banner-modal-text]')!.textContent = b.text;
    modal.addEventListener('close', () => store.set(modalKey, version), { once: true });
    modal.addEventListener('click', (e) => {
      if (e.target === modal) modal.close(); // click on backdrop
    });
    const open = () => modal.showModal();
    if (img.complete) open();
    else {
      img.addEventListener('load', open, { once: true });
      img.addEventListener('error', () => img.remove(), { once: true });
      img.addEventListener('error', open, { once: true });
    }
  }
}

async function initHours() {
  const blocks = document.querySelectorAll<HTMLElement>('[data-hours]');
  if (!blocks.length) return;
  const hours = parseHours(await getJson('/data/hours.json'));
  if (!hours) return;
  blocks.forEach((block) => {
    const rows = block.querySelector('[data-hours-rows]');
    const template = rows?.firstElementChild;
    if (!rows || !template) return;
    const fresh = hours.regular.map((r) => {
      const row = template.cloneNode(true) as HTMLElement;
      const [day, val] = row.children;
      day.textContent = r.day;
      val.textContent = r.value;
      return row;
    });
    rows.replaceChildren(...fresh);
    const note = block.querySelector<HTMLElement>('[data-hours-note]');
    if (note) {
      note.textContent = hours.note;
      note.hidden = !hours.note.trim();
    }
  });
}

async function initNews() {
  const list = document.querySelector<HTMLElement>('[data-news]');
  const template = document.querySelector<HTMLTemplateElement>('[data-news-template]');
  if (!list || !template) return;
  let items: ReturnType<typeof parseNews> = [];
  try {
    items = parseNews(await getJson('/data/news.json'));
  } finally {
    const limit = Number(list.dataset.newsLimit) || Infinity;
    const nodes = items.slice(0, limit).map((n) => {
      const el = template.content.firstElementChild!.cloneNode(true) as HTMLElement;
      const time = el.querySelector('time')!;
      time.dateTime = n.date;
      time.textContent = formatCzechDate(n.date);
      el.querySelector('[data-news-title]')!.textContent = n.title;
      el.querySelector('[data-news-text]')!.textContent = n.text;
      return el;
    });
    list.replaceChildren(...nodes);
    list.removeAttribute('aria-busy');
    document
      .querySelector<HTMLElement>('[data-news-empty]')
      ?.toggleAttribute('hidden', nodes.length > 0);
    document
      .querySelector<HTMLElement>('[data-news-section]')
      ?.toggleAttribute('hidden', nodes.length === 0);
  }
}

for (const init of [initBanner, initHours, initNews]) {
  init().catch(() => {});
}
