// PeerHive shared tabs: animated indicator for Requests, Progress and Study Sessions.
// Supports route-based navigation, radio tabs, responsive resizing and reduced motion.
export function initPeerHiveTabs(root = document) {
  for (const nav of root.querySelectorAll('[data-ph-tabs]')) {
    const track = nav.querySelector('.ph-tabs-track');
    const ink = nav.querySelector('.ph-tabs-indicator');
    if (!track || !ink || nav.dataset.phTabsInitialized) continue;
    nav.dataset.phTabsInitialized = 'true';
    const key = nav.dataset.phTabsKey;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const activeItem = () => track.querySelector('input[type="radio"]:checked + .ph-tabs-item')
      || track.querySelector('.ph-tabs-item[aria-current="page"]')
      || track.querySelector('.ph-tabs-item.is-active');
    const place = item => {
      if (!item) return;
      ink.style.setProperty('--ph-tab-x', `${item.offsetLeft}px`);
      ink.style.setProperty('--ph-tab-width', `${item.offsetWidth}px`);
      ink.style.opacity = '1';
    };
    // Route links use last-tab coordinates to slide on the newly loaded page.
    let old = null;
    if (key && !reduceMotion) {
      try { old = JSON.parse(sessionStorage.getItem(`ph-tab-position:${key}`) || 'null'); } catch {}
    }
    const selected = activeItem();
    if (old && Number.isFinite(old.x) && Number.isFinite(old.width) && selected) {
      ink.style.transition = 'none';
      ink.style.setProperty('--ph-tab-x', `${old.x}px`);
      ink.style.setProperty('--ph-tab-width', `${old.width}px`);
      ink.style.opacity = '1';
      requestAnimationFrame(() => requestAnimationFrame(() => {
        ink.style.transition = '';
        place(selected);
      }));
    } else {
      place(selected);
    }
    nav.addEventListener('change', () => requestAnimationFrame(() => place(activeItem())));
    nav.addEventListener('ph:tabs-change', () => requestAnimationFrame(() => place(activeItem())));
    nav.addEventListener('click', event => {
      const anchor = event.target.closest('a.ph-tabs-item');
      if (!anchor || !key) return;
      try { sessionStorage.setItem(`ph-tab-position:${key}`, JSON.stringify({x: activeItem()?.offsetLeft ?? 0, width: activeItem()?.offsetWidth ?? 0})); } catch {}
    });
    const onResize = () => place(activeItem());
    window.addEventListener('resize', onResize, {passive: true});
    if (window.ResizeObserver) new ResizeObserver(onResize).observe(track);
  }
}

if (typeof document !== 'undefined') initPeerHiveTabs();
