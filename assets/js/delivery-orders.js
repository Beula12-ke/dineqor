// Delivery Orders: delivery jobs as cards. Picking up moves the order to "out for delivery"
// (the kitchen sees it leave the Ready column); delivering completes it.
(() => {
  const $ = id => document.getElementById(id);
  const grid = $('woGrid'), stats = $('woStats'), tabs = $('woTabs');
  if (!grid) return;

  const STAGES = [
    { key: 'received', label: 'Order received' },
    { key: 'confirmed', label: 'Confirmed' },
    { key: 'preparing', label: 'Preparing' },
    { key: 'ready', label: 'Ready for pickup' },
    { key: 'out', label: 'Out for delivery' },
  ];
  const SORT = { ready: 0, out: 1, preparing: 2, confirmed: 3, received: 4 };
  let orders = [], filter = 'all';

  const stageOf = o => o.status === 'new' ? 'received' : (o.status === 'out_for_delivery' ? 'out' : o.status);
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
  const address = o => String(o.delivery_notes || '').replace(/^deliver to:\s*/i, '').replace(/\s*\n\s*/g, ', ').trim();

  function active() {
    return orders.filter(o => o.order_type === 'delivery' && ['new', 'confirmed', 'preparing', 'ready', 'out_for_delivery'].includes(o.status));
  }

  function drawStats(list) {
    const n = key => list.filter(o => stageOf(o) === key).length;
    const card = (title, value, note) => `<div class="wo-stat"><small>${title}</small><b>${value}</b><span>${note}</span></div>`;
    stats.innerHTML = card('ACTIVE DELIVERIES', list.length, 'not yet delivered')
      + card('IN THE KITCHEN', n('received') + n('confirmed') + n('preparing'), 'received, confirmed or cooking')
      + card('READY FOR PICKUP', n('ready'), 'waiting for a driver')
      + card('OUT FOR DELIVERY', n('out'), 'on the way to customers');
  }

  function drawTabs(list) {
    const count = key => key === 'all' ? list.length : list.filter(o => stageOf(o) === key).length;
    tabs.innerHTML = [{ key: 'all', label: 'All' }].concat(STAGES)
      .map(t => `<button type="button" role="tab" class="wo-tab ${filter === t.key ? 'on' : ''}" data-filter="${esc(t.key)}">${esc(t.label)} (${count(t.key)})</button>`).join('');
  }

  function card(o) {
    const stage = stageOf(o);
    const num = String(o.order_number || '').split('-').pop();
    const items = (o.items || []).map(i => `<li>${Number(i.qty)} × ${esc(i.name)}${i.variation ? ` <small>(${esc(i.variation)})</small>` : ''}</li>`).join('') || '<li><small>Order details unavailable</small></li>';
    const paid = o.payment_status === 'paid';
    const addr = address(o);
    const phone = o.customer_phone ? ` · <a href="tel:${esc(o.customer_phone)}">${esc(o.customer_phone)}</a>` : '';
    const late = ['received', 'confirmed', 'preparing'].includes(stage) && minutes(o.created_at) >= 30;
    let action;
    if (stage === 'ready') action = `<button class="wo-btn" type="button" data-act="advance" data-stage="ready" data-id="${Number(o.id)}">Picked up for delivery</button>`;
    else if (stage === 'out') action = `<button class="wo-btn alt" type="button" data-act="advance" data-stage="out" data-paid="${paid ? 1 : 0}" data-id="${Number(o.id)}">Mark delivered</button>`;
    else action = `<span class="wo-hint">${stage === 'received' ? 'Waiting for the kitchen' : 'In the kitchen'}</span>`;
    return `<article class="wo-card"><div class="wo-card-top"><div><h3>${esc(o.customer_name || 'Customer')}</h3><p class="wo-card-sub">#${esc(num)}${phone}</p></div><span class="wo-pill ${esc(stage)}">${esc(labelOf(stage))}</span></div>`
      + `<ul class="wo-items">${items}</ul>`
      + (addr ? `<p class="wo-meta"><b>Deliver to:</b> ${esc(addr)}</p>` : '')
      + (o.special_instructions ? `<p class="wo-note">Note: ${esc(o.special_instructions)}</p>` : '')
      + `<p class="wo-pay ${paid ? 'paid' : ''}">${paid ? 'Paid' : 'Collect ' + esc(money(o.total_amount)) + (o.payment_method ? ' (' + esc(o.payment_method) + ')' : '')}</p>`
      + `<div class="wo-card-foot"><span class="wo-age ${late ? 'late' : ''}">${esc(ago(o.created_at))}</span>${action}</div></article>`;
  }

  function draw() {
    grid.setAttribute('aria-busy', 'false');
    const list = active();
    drawStats(list); drawTabs(list);
    const shown = list.filter(o => filter === 'all' || stageOf(o) === filter)
      .sort((a, b) => SORT[stageOf(a)] - SORT[stageOf(b)] || String(a.created_at).localeCompare(String(b.created_at)));
    grid.innerHTML = shown.length ? shown.map(card).join('')
      : '<div class="dash-empty" style="grid-column:1/-1"><span>✳</span><b>No deliveries here</b><p>Delivery orders appear as soon as customers place them.</p></div>';
  }

  async function load() {
    const response = await api('staff/orders.php');
    if (response.status === 401 || response.status === 403) { location.href = BASE + '/auth/login.php'; return; }
    if (!response.ok) {
      grid.setAttribute('aria-busy', 'false');
      grid.innerHTML = `<div class="dash-empty" style="grid-column:1/-1"><b>Deliveries could not load</b><p>${esc(response.error || 'Please try again.')}</p></div>`;
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
    if (b.dataset.stage === 'out' && b.dataset.paid === '0' && !confirm('Mark as delivered? Cash on delivery will be recorded as paid.')) return;
    b.disabled = true;
    const result = await api('staff/order-action.php', { method: 'POST', body: { id: Number(b.dataset.id), action: 'advance' } });
    toast(result.ok ? (b.dataset.stage === 'ready' ? 'Marked as picked up. The customer is told it is on the way.' : 'Marked as delivered.') : (result.error || 'Could not update this order.'));
    await load();
  });

  $('woRefresh').addEventListener('click', load);
  load();
  setInterval(load, 10000);
})();
