// Home page: live restaurant discovery from /api/public/restaurants.php
(() => {
  const $ = id => document.getElementById(id);
  const grid = $('grid'), count = $('count'), chips = $('chips'), qEl = $('q'), openEl = $('open');
  const state = { q: '', cuisine: '', open: false };
  let seq = 0, cuisinesDrawn = false;

  // restore state from URL so results are shareable
  const p = new URLSearchParams(location.search);
  state.q = p.get('q') || ''; state.cuisine = p.get('cuisine') || ''; state.open = p.get('open') === '1';
  qEl.value = state.q; openEl.checked = state.open;

  const tone = name => `hsl(${[...name].reduce((a, c) => a + c.charCodeAt(0), 0) % 360} 45% 55%)`;

  function card(r) {
    const badge = r.open === true ? `<span class="badge open">Open${r.until ? ' until ' + esc(r.until) : ''}</span>`
                : r.open === false ? '<span class="badge closed">Closed now</span>' : '';
    const bg = r.cover ? `background-image:url('${esc(r.cover)}')` : `--tone:${r.color || tone(r.name)}`;
    const meta = [r.cuisine, r.city, r.price ? '$'.repeat(r.price) : ''].filter(Boolean).join(' · ');
    return `<a class="rcard" href="${esc(r.url)}">
      <div class="cover" style="${bg}">${r.cover ? '' : `<span class="initial" aria-hidden="true">${esc(r.name[0])}</span>`}${badge}</div>
      <div class="info"><h3>${esc(r.name)}</h3><p>${esc(meta)}</p>${r.rating ? `<div class="restaurant-rating"><span>★</span> ${Number(r.rating).toFixed(1)} <small>(${Number(r.review_count)} ${Number(r.review_count) === 1 ? 'review' : 'reviews'})</small></div>` : ''}
      <div class="tags">${r.delivery ? '<span>Delivery</span>' : ''}${r.pickup ? '<span>Pickup</span>' : ''}</div></div></a>`;
  }

  function drawChips(list) {
    chips.innerHTML = ['', ...list].map(c =>
      `<button type="button" class="chip" data-c="${esc(c)}" aria-pressed="${c === state.cuisine}">${c ? esc(c) : 'All cuisines'}</button>`).join('');
  }

  function empty() {
    const filtered = state.q || state.cuisine || state.open;
    return filtered
      ? `<div class="empty" style="grid-column:1/-1"><b>No matches</b>Try a different search or <button class="link" id="reset">clear the filters</button>.</div>`
      : `<div class="empty" style="grid-column:1/-1"><b>No restaurants yet</b>Be the first: <a href="${BASE}/register-restaurant.php">partner with Dineqor</a>.</div>`;
  }

  function sync() {
    const u = new URLSearchParams();
    if (state.q) u.set('q', state.q); if (state.cuisine) u.set('cuisine', state.cuisine); if (state.open) u.set('open', '1');
    history.replaceState(null, '', location.pathname + (u.toString() ? '?' + u : ''));
  }

  async function load() {
    const my = ++seq;
    grid.setAttribute('aria-busy', 'true');
    if (!grid.children.length) grid.innerHTML = '<div class="skel"></div>'.repeat(6);
    const qs = new URLSearchParams({ q: state.q, cuisine: state.cuisine, open: state.open ? 1 : 0 });
    const r = await api('public/restaurants.php?' + qs);
    if (my !== seq) return;                       // a newer search superseded this one
    grid.setAttribute('aria-busy', 'false');
    if (!r.ok) {
      count.textContent = '';
      grid.innerHTML = `<div class="empty" style="grid-column:1/-1"><b>Couldn't load restaurants</b><button class="link" id="retry">Try again</button></div>`;
      $('retry').onclick = load; return;
    }
    if (!cuisinesDrawn) { drawChips(r.cuisines); cuisinesDrawn = true; }
    count.textContent = r.total === 1 ? '1 restaurant' : r.total + ' restaurants';
    grid.innerHTML = r.restaurants.length ? r.restaurants.map(card).join('') : empty();
    const rs = $('reset'); if (rs) rs.onclick = () => { state.q = state.cuisine = ''; state.open = false; qEl.value = ''; openEl.checked = false; drawChips([...chips.querySelectorAll('.chip')].slice(1).map(b => b.dataset.c)); sync(); load(); };
  }

  qEl.addEventListener('input', debounce(() => { state.q = qEl.value.trim(); sync(); load(); }));
  $('search').addEventListener('submit', e => {
    const destination = e.currentTarget.dataset.destination;
    if (destination) {
      e.preventDefault();
      const q = qEl.value.trim();
      location.href = destination + (q ? '?q=' + encodeURIComponent(q) : '');
      return;
    }
    e.preventDefault(); state.q = qEl.value.trim(); sync(); load();
  });
  openEl.addEventListener('change', () => { state.open = openEl.checked; sync(); load(); });
  chips.addEventListener('click', e => {
    const b = e.target.closest('.chip'); if (!b) return;
    state.cuisine = b.dataset.c;
    chips.querySelectorAll('.chip').forEach(x => x.setAttribute('aria-pressed', x === b));
    sync(); load();
  });
  load();
})();
