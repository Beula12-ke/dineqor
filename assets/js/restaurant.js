// Storefront: one template, data from /api/public/restaurant.php?slug=...
(() => {
  const root = document.getElementById('store');
  const slug = root.dataset.slug;
  const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  let data = null;

  const badge = r => r.open === true ? `<span class="badge open">Open${r.until ? ' until ' + esc(r.until) : ''}</span>`
                   : r.open === false ? '<span class="badge closed">Closed now</span>' : '';

  function product(p) {
    const from = p.variations.length ? 'From ' + money(p.price + Math.min(...p.variations.map(v => v.delta))) : money(p.price);
    return `<article class="pcard" data-pid="${p.id}">${p.image ? `<div class="pimg" style="background-image:url('${esc(p.image)}')"></div>` : ''}
      <div class="pbody"><h3>${esc(p.name)}${p.featured ? '<span class="star">Popular</span>' : ''}</h3>
      ${p.description ? `<p>${esc(p.description)}</p>` : ''}
      <div class="prow"><span class="price">${from}</span><button type="button" class="add" data-add="${p.id}" aria-label="Add ${esc(p.name)} to your order">Add</button></div></div></article>`;
  }

  function menuHtml(term) {
    const t = term.toLowerCase();
    const sections = data.menu.map(c => ({ ...c, items: t ? c.items.filter(i => (i.name + ' ' + (i.description || '')).toLowerCase().includes(t)) : c.items }))
                              .filter(c => c.items.length);
    document.getElementById('cats').innerHTML = sections.map((c, i) => `<a href="#cat-${c.id}" data-id="cat-${c.id}" class="${i ? '' : 'on'}">${esc(c.name)}</a>`).join('');
    if (!sections.length) return `<div class="empty"><b>${t ? 'Nothing matches “' + esc(term) + '”' : 'The menu is being prepared'}</b>${t ? 'Try another dish.' : 'Please check back soon.'}</div>`;
    return sections.map(c => `<section id="cat-${c.id}"><h2 class="cat">${esc(c.name)}</h2><div class="pgrid">${c.items.map(product).join('')}</div></section>`).join('');
  }

  function spy() {
    const links = [...document.querySelectorAll('#cats a')];
    const io = new IntersectionObserver(es => es.forEach(e => {
      if (e.isIntersecting) links.forEach(a => {
        const on = a.dataset.id === e.target.id; a.classList.toggle('on', on);
        if (on) a.scrollIntoView({ block: 'nearest', inline: 'center' });
      });
    }), { rootMargin: '-140px 0px -65% 0px' });
    document.querySelectorAll('#menu section').forEach(s => io.observe(s));
  }

  function reviewsHtml() {
    const r = data.reviews || { average: null, total: 0, items: [] };
    const cards = r.items.map(item => { const score = Math.max(0, Math.min(5, Number(item.rating) || 0)); return `<article class="review-card"><div class="review-card-top"><span class="review-stars" aria-label="${score} out of 5 stars">${'★'.repeat(score)}${'☆'.repeat(5 - score)}</span><small>${esc(item.date)}</small></div>${item.title ? `<h3>${esc(item.title)}</h3>` : ''}${item.comment ? `<p>${esc(item.comment)}</p>` : ''}<b>${esc(item.customer)}</b></article>`; }).join('');
    return `<section class="store-reviews" id="reviews"><div class="review-heading"><div><div class="eyebrow">GUEST FEEDBACK</div><h2>Reviews</h2><p>${r.total ? `<strong>${Number(r.average).toFixed(1)} ★</strong> · ${r.total} ${r.total === 1 ? 'review' : 'reviews'}` : 'Be the first to share your experience.'}</p></div></div>${cards ? `<div class="review-grid">${cards}</div>` : '<div class="review-empty">No reviews yet. Visit us and tell others how it went.</div>'}</section>`;
  }

  function render() {
    const r = data.restaurant;
    document.title = r.name + ' · Dineqor';
    document.documentElement.style.setProperty('--brand', r.brand);
    document.documentElement.style.setProperty('--ink', r.ink);
    const bg = r.cover && !data.table ? `background-image:linear-gradient(rgba(0,0,0,.4),rgba(0,0,0,.65)),url('${esc(r.cover)}')` : '';
    const hours = data.hours.length ? data.hours.map(h =>
      `<tr class="${h.weekday === data.today ? 'today' : ''}"><td>${DAYS[h.weekday]}</td><td>${h.closed ? 'Closed' : esc(h.open) + ' – ' + esc(h.close)}</td></tr>`).join('') : '';
    const contact = [r.phone && `<a href="tel:${esc(r.phone)}">${esc(r.phone)}</a>`, r.address && esc(r.address)].filter(Boolean).join('<br>');

    root.innerHTML = `
      <section class="shero${data.table ? ' qr-order-hero' : ''}" style="${bg}"><div class="wrap">
        ${r.logo ? `<div class="logo-img" style="background-image:url('${esc(r.logo)}')"></div>` : ''}
        <div class="store-title-row"><div><h1>${esc(r.name)}</h1><p>${esc([r.cuisine, r.city].filter(Boolean).join(' · '))}</p></div>
        ${r.favorite_available ? `<button type="button" class="favorite-toggle store-favorite" data-favorite-toggle data-restaurant-id="${r.id}" data-favorite="${r.is_favorite ? 'true' : 'false'}" aria-pressed="${r.is_favorite ? 'true' : 'false'}" aria-label="${r.is_favorite ? 'Remove from' : 'Save to'} favourites">${r.is_favorite ? '♥ Saved' : '♡ Save'}</button>` : ''}
        ${r.website_url && !data.table ? `<a class="store-website-link" href="${esc(r.website_url)}" target="_blank" rel="noopener noreferrer" aria-label="Visit ${esc(r.name)}’s official website (opens in a new tab)">Visit website <span aria-hidden="true">↗</span></a>` : ''}
        </div><div>
        ${data.table ? `<div class="qr-table-prompt"><b>Order from your table</b><span>Choose from the menu below and we’ll bring your order to you.</span></div>` : (r.description ? `<p class="desc">${esc(r.description)}</p>` : '')}
        <div class="tags">${badge(r)}${r.delivery ? '<span>Delivery</span>' : ''}${r.pickup ? '<span>Pickup</span>' : ''}${data.reviews?.total ? `<a class="rating-pill" href="#reviews">★ ${Number(data.reviews.average).toFixed(1)} · ${data.reviews.total} reviews</a>` : ''}</div></div>
      </div></section>
      <div class="menubar"><div class="wrap"><nav class="cats" id="cats" aria-label="Menu categories"></nav>
        <div class="msearch"><label class="sr" for="ms">Search this menu</label><input id="ms" type="search" placeholder="Search this menu"></div></div></div>
      <main class="wrap${data.table ? ' qr-menu-main' : ''}">${data.table ? `<div class="qr-menu-intro"><div><div class="eyebrow">TABLE ORDERING</div><h2>Choose from the menu</h2><p>Your order is linked to <b>${esc(data.table.label)}</b> and goes straight to the kitchen.</p></div><a href="#menu">Browse menu ↓</a></div>` : ''}<div id="menu"></div>
        ${!data.table && (hours || contact) ? `<div class="two">
          ${hours ? `<div class="panel"><h2>Opening hours</h2><table class="hours">${hours}</table></div>` : ''}
          ${contact ? `<div class="panel"><h2>Contact</h2><p>${contact}</p></div>` : ''}</div>` : ''}
        ${data.table ? '' : reviewsHtml()}
      </main>`;
    const menu = document.getElementById('menu');
    menu.innerHTML = menuHtml(''); spy();
    document.dispatchEvent(new CustomEvent('store:ready', { detail: data }));
    document.getElementById('ms').addEventListener('input', debounce(e => { menu.innerHTML = menuHtml(e.target.value.trim()); spy(); }, 150));
  }

  async function load() {
    const r = await api('public/restaurant.php?slug=' + encodeURIComponent(slug));
    if (r.status === 404) { root.innerHTML = '<main class="wrap"><div class="empty"><b>Restaurant not found</b><a href="' + BASE + '/">Browse all restaurants</a></div></main>'; return; }
    if (!r.ok) { root.innerHTML = '<main class="wrap"><div class="empty"><b>Couldn\'t load this menu</b><button class="link" id="retry">Try again</button></div></main>'; document.getElementById('retry').onclick = load; return; }
    data = r; render();
    setInterval(async () => {                       // keep the open/closed badge and menu fresh
      const n = await api('public/restaurant.php?slug=' + encodeURIComponent(slug));
      if (n.ok && (JSON.stringify(n.menu) !== JSON.stringify(data.menu) || JSON.stringify(n.reviews) !== JSON.stringify(data.reviews))) { data = n; render(); }
    }, 60000);
  }
  load();
})();
