import '../../css/search-discovery.css';
// PeerHive v4.7.7g.1: authenticated global discovery, without private-message indexing.
const CATEGORIES = [
  ['all', 'All'], ['people', 'People'], ['communities', 'Communities'],
  ['skills', 'Skills'], ['sessions', 'Sessions'],
];
const ICONS = { people: 'bi-person-circle', communities: 'bi-people', skills: 'bi-book', sessions: 'bi-calendar-event' };
const STORAGE_KEY = 'peerhive.search.recent.session';
const MIN_QUERY = 2;
const esc = text => String(text ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
function localHref(value) {
  try {
    const url = new URL(String(value), window.location.origin);
    return url.origin === window.location.origin ? `${url.pathname}${url.search}${url.hash}` : '#';
  } catch { return '#'; }
}
function recent() {
  try { const data = JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '[]'); return Array.isArray(data) ? data.filter(s => typeof s === 'string').slice(0, 6) : []; }
  catch { return []; }
}
function remember(query) {
  const text = String(query ?? '').trim().slice(0, 100);
  if (text.length < MIN_QUERY) return;
  try { sessionStorage.setItem(STORAGE_KEY, JSON.stringify([text, ...recent().filter(q => q !== text)].slice(0, 6))); } catch {}
}
function categoryButtons(selected, scope) {
  return CATEGORIES.map(([value, label]) => `<button type="button" class="ph-search-filter ${selected === value ? 'is-active' : ''}" data-ph-search-category="${value}" data-ph-search-scope="${scope}" aria-pressed="${selected === value}">${label}</button>`).join('');
}
function resultItems(data) {
  return CATEGORIES.filter(([key]) => key !== 'all').flatMap(([key, label]) => {
    const group = Array.isArray(data?.results?.[key]) ? data.results[key] : [];
    if (!group.length) return [];
    return [{ key, label, items: group }];
  });
}
function resultCard(row, compact = false) {
  const name = esc(row.title);
  const subtitle = esc(row.subtitle);
  const icon = ICONS[row.type] || 'bi-search';
  const avatar = row.type === 'people' && row.avatar && localHref(row.avatar) !== '#'
    ? `<img src="${esc(localHref(row.avatar))}" class="ph-search-avatar-image" alt="" loading="lazy">`
    : `<i class="bi ${icon}" aria-hidden="true"></i>`;
  return `<a class="ph-search-result ${compact ? 'is-compact' : ''}" href="${esc(localHref(row.url))}" data-ph-search-result>
    <span class="ph-search-avatar">${avatar}</span>
    <span class="ph-search-copy"><strong>${name}</strong><small>${subtitle}</small></span>
    <i class="bi bi-arrow-up-right ph-search-arrow" aria-hidden="true"></i>
  </a>`;
}
function resultSections(data, compact = false) {
  const groups = resultItems(data);
  if (!groups.length) return '<div class="ph-search-empty">No matching results. Try another keyword or category.</div>';
  return groups.map(({key,label,items}) => `<section class="ph-search-section" aria-label="${label}">
    <h2 class="ph-search-section-title">${label}</h2>
    ${data?.has_more?.[key] && !compact ? `<p class="ph-search-limit">Showing the first ${items.length} matches. Narrow your search to find more specific results.</p>` : ''}
    ${items.map(row => resultCard(row,compact)).join('')}
    ${data?.has_more?.[key] && compact ? `<a class="ph-search-more" href="/discover?q=${encodeURIComponent(data.query)}&category=${key}" data-ph-search-viewall>More ${label.toLowerCase()}</a>` : ''}
  </section>`).join('');
}
async function queryServer(query, category, limit, signal) {
  const url = `/discover/results?q=${encodeURIComponent(query)}&category=${encodeURIComponent(category)}&limit=${limit}`;
  const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal });
  if (!response.ok) throw new Error('Could not load search results.');
  return response.json();
}

function initTopSearch() {
  const input = document.getElementById('globalSearch');
  const panel = document.getElementById('searchDropdown');
  const wrapper = document.getElementById('searchWrapper');
  if (!input || !panel || !wrapper) return;
  input.setAttribute('role', 'combobox');
  input.setAttribute('aria-autocomplete', 'list');
  input.setAttribute('aria-label', 'Search PeerHive');
  input.placeholder = 'Search PeerHive...';
  input.maxLength = 100;
  panel.setAttribute('role', 'region');
  panel.setAttribute('aria-label', 'Search suggestions');
  let chosen = 'all'; let timer = 0; let pending = null; let serial = 0; let activeIndex = -1;
  const open = () => { panel.classList.add('is-open'); input.setAttribute('aria-expanded','true'); };
  const close = () => { panel.classList.remove('is-open'); input.setAttribute('aria-expanded','false'); activeIndex = -1; };
  const showIntro = () => {
    const history = recent();
    panel.innerHTML = `<div class="ph-search-panel"><div class="ph-search-panel-head"><strong>Search PeerHive</strong><kbd>Esc</kbd></div>
      <div class="ph-search-filters">${categoryButtons(chosen,'top')}</div>
      ${history.length ? `<div class="ph-search-recent-head"><span>Recent searches</span><button type="button" data-ph-search-clear>Clear</button></div>${history.map(q => `<button type="button" class="ph-search-recent" data-ph-search-recent="${esc(q)}"><i class="bi bi-clock-history" aria-hidden="true"></i>${esc(q)}</button>`).join('')}` : '<div class="ph-search-empty">Type at least two characters to search.</div>'}
      <div class="ph-search-panel-footer"><span>Use ↑ ↓ to navigate results</span><a href="/discover" data-ph-search-viewall>Open search page</a></div></div>`;
    open();
  };
  const load = async () => {
    const q = input.value.trim(); const current = ++serial;
    if (pending) pending.abort();
    if (q.length < MIN_QUERY) { showIntro(); return; }
    pending = new AbortController();
    panel.innerHTML = `<div class="ph-search-panel"><div class="ph-search-panel-head"><strong>Search PeerHive</strong><kbd>Esc</kbd></div><div class="ph-search-filters">${categoryButtons(chosen,'top')}</div><div class="ph-search-empty" role="status">Searching...</div></div>`;
    open();
    try {
      const data = await queryServer(q, chosen, 4, pending.signal);
      if (current !== serial) return;
      panel.innerHTML = `<div class="ph-search-panel"><div class="ph-search-panel-head"><strong>Search PeerHive</strong><kbd>Esc</kbd></div>
        <div class="ph-search-filters">${categoryButtons(chosen,'top')}</div><div class="ph-search-scroll">${resultSections(data,true)}</div>
        <div class="ph-search-panel-footer"><span>Results are privacy-filtered</span><a href="/discover?q=${encodeURIComponent(q)}&category=${chosen}" data-ph-search-viewall>Open search results</a></div></div>`;
      activeIndex = -1;
    } catch (error) {
      if (error.name !== 'AbortError' && current === serial) panel.innerHTML = `<div class="ph-search-empty" role="alert">${esc(error.message)}</div>`;
    }
  };
  input.addEventListener('focus', () => { if (input.value.trim().length < MIN_QUERY) showIntro(); else load(); });
  input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 230); });
  input.addEventListener('keydown', event => {
    const links = [...panel.querySelectorAll('[data-ph-search-result]')];
    if (event.key === 'Escape') { close(); input.blur(); return; }
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault(); if (!panel.classList.contains('is-open')) { showIntro(); return; }
      if (!links.length) return;
      activeIndex = (activeIndex + (event.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
      links.forEach((link,index) => link.classList.toggle('is-keyboard-active',index === activeIndex));
      links[activeIndex].scrollIntoView({block:'nearest'});
    }
    if (event.key === 'Enter' && input.value.trim().length >= MIN_QUERY) {
      event.preventDefault(); remember(input.value);
      if (activeIndex >= 0 && links[activeIndex]) window.location.assign(links[activeIndex].href);
      else window.location.assign(`/discover?q=${encodeURIComponent(input.value.trim())}&category=${chosen}`);
    }
  });
  panel.addEventListener('click', event => {
    const filter = event.target.closest('[data-ph-search-category]');
    if (filter) { chosen = filter.dataset.phSearchCategory; load(); return; }
    const clear = event.target.closest('[data-ph-search-clear]');
    if (clear) { try { sessionStorage.removeItem(STORAGE_KEY); } catch {} showIntro(); return; }
    const history = event.target.closest('[data-ph-search-recent]');
    if (history) { input.value = history.dataset.phSearchRecent; remember(input.value); load(); input.focus(); return; }
    if (event.target.closest('[data-ph-search-result],[data-ph-search-viewall]')) remember(input.value);
  });
  document.addEventListener('pointerdown', event => { if (!wrapper.contains(event.target)) close(); });
  document.addEventListener('keydown', event => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault(); input.focus(); input.select();
    }
  });
}

function initSearchPage() {
  const page = document.getElementById('phDiscoveryPage');
  const input = document.getElementById('phDiscoveryInput');
  const filters = document.getElementById('phDiscoveryFilters');
  const results = document.getElementById('phDiscoveryResults');
  if (!page || !input || !filters || !results) return;
  const params = new URLSearchParams(window.location.search);
  let chosen = CATEGORIES.some(([value]) => value === params.get('category')) ? params.get('category') : 'all';
  let pending = null; let timer = 0; let serial = 0;
  input.value = (params.get('q') || '').slice(0,100);
  const refreshFilters = () => { filters.innerHTML = categoryButtons(chosen,'page'); };
  const load = async () => {
    const query = input.value.trim(); const current = ++serial;
    pending?.abort();
    refreshFilters();
    if (query.length < MIN_QUERY) {
      results.innerHTML = '<p class="ph-search-empty">Enter at least two characters to search PeerHive.</p>';
      results.setAttribute('aria-busy','false');
      return;
    }
    pending = new AbortController(); results.setAttribute('aria-busy','true');
    results.innerHTML = '<p class="ph-search-empty" role="status">Searching...</p>';
    try {
      const data = await queryServer(query,chosen,20,pending.signal);
      if (current !== serial) return;
      results.innerHTML = resultSections(data);
      const url = new URL(window.location.href); url.searchParams.set('q',query); url.searchParams.set('category',chosen);
      history.replaceState({},'',url.pathname+url.search); remember(query);
    } catch (error) {
      if (error.name !== 'AbortError' && current === serial) results.innerHTML = `<p class="ph-search-empty" role="alert">${esc(error.message)}</p>`;
    } finally { if (current === serial) results.setAttribute('aria-busy','false'); }
  };
  input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load,260); });
  filters.addEventListener('click', event => {
    const btn = event.target.closest('[data-ph-search-category]'); if (!btn) return;
    chosen = btn.dataset.phSearchCategory; load();
  });
  refreshFilters(); load();
}

initTopSearch();
initSearchPage();
