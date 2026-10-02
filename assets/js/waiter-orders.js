// Waiter Orders: table orders as cards, with a "picked up" handoff back to the kitchen.
(() => {
  const $ = id => document.getElementById(id);
  const grid = $('woGrid'), stats = $('woStats'), tabs = $('woTabs');
  if (!grid) return;

  const TABLE_TYPES = ['dine_in', 'qr_table'];
  const PICKUP_TYPES = ['pickup', 'takeaway'];
  const ACTIVE = ['new', 'confirmed', 'preparing', 'ready'];
  const STAGES = [
    { key: 'received', label: 'Order received' },
    { key: 'confirmed', label: 'Confirmed' },
    { key: 'preparing', label: 'Preparing' },
    { key: 'ready', label: 'Ready' },
    { key: 'picked', label: 'Picked up' },
  ];
  const SORT = { ready: 0, picked: 1, preparing: 2, confirmed: 3, received: 4 };
  let orders = [], filter = 'all';

  const stageOf = o => o.status === 'ready' ? (o.picked_up_at ? 'picked' : 'ready') : (o.status === 'new' ? 'received' : o.status);
  const labelOf = key => (STAGES.find(s => s.key === key) || {}).label || key;
  const parse = v => new Date(String(v || '').replace(' ', 'T'));
  const minutes = v => { const d = parse(v); return Number.isNaN(d.getTime()) ? 0 : Math.max(0, Math.floor((Date.now() - d.getTime()) / 60000)); };
  const ago = v => {
    const m = minutes(v);
    if (m < 1) return 'Just in';
    if (m < 60) return m + ' min';
    const h = Math.floor(m / 60);
    return h < 24 ? `${h}h ${m % 60}m` : `${Math.floor(h / 24)}d ${h % 24}h`;
  };

  function active() {
    return orders.filter(o => TABLE_TYPES.includes(o.order_type) && ['new', 'confirmed', 'preparing', 'ready'].includes(o.status));
  }

  function pickups() {
    return orders.filter(o => PICKUP_TYPES.includes(o.order_type) && ACTIVE.includes(o.status));
  }

  function drawStats(list) {
    const n = key => list.filter(o => stageOf(o) === key).length;
    const inKitchen = n('received') + n('confirmed') + n('preparing');
    const card = (title, value, note) => `<div class="wo-stat"><small>${title}</small><b>${value}</b><span>${note}</span></div>`;
    stats.innerHTML = card('ACTIVE ORDERS', list.length, 'across all tables')
      + card('IN THE KITCHEN', inKitchen, 'received, confirmed or cooking')
      + card('READY TO COLLECT', n('ready'), 'waiting for a waiter')
      + card('PICKED UP', n('picked'), 'on the way to the table');
  }

  function drawTabs(list, pick) {
    const count = key => key === 'all' ? list.length : (key === 'pickup' ? pick.length : list.filter(o => stageOf(o) === key).length);
    tabs.innerHTML = [{ key: 'all', label: 'All' }].concat(STAGES, [{ key: 'pickup', label: 'Pickup' }])
      .map(t => `<button type="button" role="tab" class="wo-tab ${filter === t.key ? 'on' : ''}" data-filter="${esc(t.key)}">${esc(t.label)} (${count(t.key)})</button>`).join('');
  }

  // Customer pickup / takeaway orders: waiters hand over only orders that are already paid.
  function pickupCard(o) {
    const stage = o.status === 'new' ? 'received' : o.status;
    const num = String(o.order_number || '').split('-').pop();
    const kind = o.order_type === 'takeaway' ? 'Takeaway' : 'Pickup';
    const paid = o.payment_status === 'paid';
    const phone = o.customer_phone ? ` · <a href="tel:${esc(o.customer_phone)}">${esc(o.customer_phone)}</a>` : '';
    const items = (o.items || []).map(i => `<li>${Number(i.qty)} × ${esc(i.name)}${i.variation ? ` <small>(${esc(i.variation)})</small>` : ''}${i.notes ? `<br><small>${esc(i.notes)}</small>` : ''}</li>`).join('') || '<li><small>Order details unavailable</small></li>';
    let action;
    if (stage === 'ready' && paid) action = `<button class="wo-btn" type="button" data-act="handover" data-id="${Number(o.id)}">Handed to customer</button>`;
    else if (stage === 'ready') action = '<span class="wo-hint">Cashier takes payment first</span>';
    else action = `<span class="wo-hint">${stage === 'received' ? 'Waiting for the kitchen' : 'In the kitchen'}</span>`;
    return `<article class="wo-card"><div class="wo-card-top"><div><h3>${esc(o.customer_name || 'Customer')}</h3><p class="wo-card-sub">${kind} · #${esc(num)}${phone}</p></div><span class="wo-pill ${esc(stage)}">${esc(labelOf(stage))}</span></div><ul class="wo-items">${items}</ul>`
      + `<p class="wo-pay ${paid ? 'paid' : ''}">${paid ? 'Paid' : 'Customer pays ' + esc(money(o.total_amount)) + ' at the counter'}</p>`
      + `<div class="wo-card-foot"><span class="wo-age">${esc(ago(o.created_at))}</span>${action}</div></article>`;
  }

  function card(o) {
    const stage = stageOf(o);
    const title = o.table_label || (o.table_number ? 'Table ' + o.table_number : 'Table order');
    const num = String(o.order_number || '').split('-').pop();
    const items = (o.items || []).map(i => `<li>${Number(i.qty)} × ${esc(i.name)}${i.variation ? ` <small>(${esc(i.variation)})</small>` : ''}${i.notes ? `<br><small>${esc(i.notes)}</small>` : ''}</li>`).join('') || '<li><small>Order details unavailable</small></li>';
    const late = (stage === 'received' || stage === 'confirmed' || stage === 'preparing') && minutes(o.created_at) >= 20;
    let action;
    if (stage === 'ready') action = `<button class="wo-btn" type="button" data-act="pickup" data-id="${Number(o.id)}">I've picked it up</button>`;
    else if (stage === 'picked') action = `<button class="wo-btn alt" type="button" data-act="serve" data-id="${Number(o.id)}">Mark served</button>`;
    else action = `<span class="wo-hint">${stage === 'received' ? 'Waiting for the kitchen' : 'In the kitchen'}</span>`;
    return `<article class="wo-card"><div class="wo-card-top"><div><h3>${esc(title)}</h3><p class="wo-card-sub">#${esc(num)}${o.customer_name ? ' · ' + esc(o.customer_name) : ''}</p></div><span class="wo-pill ${esc(stage)}">${esc(labelOf(stage))}</span></div><ul class="wo-items">${items}</ul><div class="wo-card-foot"><span class="wo-age ${late ? 'late' : ''}">${esc(ago(o.created_at))}</span>${action}</div></article>`;
  }

  function draw() {
    grid.setAttribute('aria-busy', 'false');
    const list = active(), pick = pickups();
    drawStats(list); drawTabs(list, pick);
    if (filter === 'pickup') {
      const rank = o => o.status === 'ready' ? 0 : 1;
      const shownPick = pick.slice().sort((a, b) => rank(a) - rank(b) || String(a.created_at).localeCompare(String(b.created_at)));
      grid.innerHTML = shownPick.length ? shownPick.map(pickupCard).join('')
        : '<div class="dash-empty" style="grid-column:1/-1"><span>✳</span><b>No pickup orders</b><p>Customer pickup orders appear here while they are being prepared.</p></div>';
      return;
    }
    const shown = list.filter(o => filter === 'all' || stageOf(o) === filter)
      .sort((a, b) => SORT[stageOf(a)] - SORT[stageOf(b)] || String(a.created_at).localeCompare(String(b.created_at)));
    grid.innerHTML = shown.length ? shown.map(card).join('')
      : '<div class="dash-empty" style="grid-column:1/-1"><span>✳</span><b>No orders here</b><p>Table orders appear as soon as they are sent to the kitchen.</p></div>';
  }

  async function load() {
    const response = await api('staff/orders.php');
    if (response.status === 401 || response.status === 403) { location.href = BASE + '/auth/login.php'; return; }
    if (!response.ok) {
      grid.setAttribute('aria-busy', 'false');
      grid.innerHTML = `<div class="dash-empty" style="grid-column:1/-1"><b>Orders could not load</b><p>${esc(response.error || 'Please try again.')}</p></div>`;
      return;
    }
    orders = response.orders || [];
    draw();
  }

  tabs.addEventListener('click', e => {
    const b = e.target.closest('[data-filter]');
    if (!b) return;
    filter = b.dataset.filter; draw();
  });

  grid.addEventListener('click', async e => {
    const b = e.target.closest('[data-act]');
    if (!b) return;
    b.disabled = true;
    const result = await api('staff/service.php', { method: 'POST', body: { action: b.dataset.act, order_id: Number(b.dataset.id) } });
    toast(result.ok ? (result.message || 'Saved.') : (result.error || 'Could not update this order.'));
    await load();
  });

  $('woRefresh').addEventListener('click', load);
  load();
  setInterval(load, 10000);
})();
