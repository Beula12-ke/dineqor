// Kitchen-focused order board. Tenant data and status changes are checked by the staff APIs.
(() => {
  const board = document.getElementById('kitchenBoard');
  if (!board) return;
  const summary = document.getElementById('kitchenSummary');
  const columns = [['new', 'New'], ['confirmed', 'Queued'], ['preparing', 'Cooking'], ['ready', 'Ready']];
  const types = { qr_table: 'Dine-in', dine_in: 'Dine-in', pickup: 'Pickup', takeaway: 'Takeaway', delivery: 'Delivery', pos: 'POS' };
  const labels = { new: 'Accept order', confirmed: 'Start cooking', preparing: 'Mark ready' };
  let knownNew = new Set();

  function elapsed(value) {
    const created = new Date(String(value || '').replace(' ', 'T'));
    if (Number.isNaN(created.getTime())) return '';
    const mins = Math.max(0, Math.floor((Date.now() - created.getTime()) / 60000));
    return mins < 1 ? 'Just in' : `${mins} min`;
  }

  function card(order) {
    const where = order.table_number ? (order.table_label || `Table ${order.table_number}`) : (types[order.order_type] || order.order_type);
    const items = (order.items || []).map(item => `<li><b>${Number(item.qty)}×</b> ${esc(item.name)}${item.variation ? ` <small>(${esc(item.variation)})</small>` : ''}${item.addons?.length ? `<small class="kitchen-addons">+ ${item.addons.map(esc).join(', ')}</small>` : ''}${item.notes ? `<small class="kitchen-note">${esc(item.notes)}</small>` : ''}</li>`).join('');
    return `<article class="kitchen-order ${order.status === 'new' ? 'is-new' : ''}" data-id="${Number(order.id)}">
      <div class="kitchen-order-top"><b>#${esc(String(order.order_number).split('-').pop())}</b><span class="kitchen-age" data-created="${esc(order.created_at)}">${esc(elapsed(order.created_at))}</span></div>
      <div class="kitchen-order-meta"><span>${esc(where)}</span><span>${esc(order.customer_name || 'Guest')}</span></div>
      <ul class="kitchen-items">${items || '<li>Order details unavailable</li>'}</ul>
      ${order.special_instructions ? `<div class="kitchen-special"><b>Order note</b><span>${esc(order.special_instructions)}</span></div>` : ''}
      <div class="kitchen-order-bottom"><span>${esc(new Date(String(order.created_at || '').replace(' ', 'T')).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }))}</span>
      ${labels[order.status] ? `<button class="btn sm kitchen-advance" type="button" data-id="${Number(order.id)}">${labels[order.status]}</button>` : '<span class="kitchen-handoff">Awaiting pickup</span>'}</div>
    </article>`;
  }

  function draw(orders) {
    const active = orders.filter(order => columns.some(([status]) => order.status === status));
    summary.innerHTML = `<b>${active.length}</b> active ${active.length === 1 ? 'order' : 'orders'} <span>·</span> <b>${active.filter(order => order.status === 'new').length}</b> new <span class="kitchen-refresh-time">Updated ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>`;
    board.innerHTML = columns.map(([status, label]) => {
      const laneOrders = active.filter(order => order.status === status);
      return `<section class="kitchen-lane lane-${status}"><header><h2>${label}</h2><span>${laneOrders.length}</span></header><div class="kitchen-lane-list">${laneOrders.length ? laneOrders.map(card).join('') : '<p class="kitchen-empty">Nothing here yet</p>'}</div></section>`;
    }).join('');
    board.setAttribute('aria-busy', 'false');
  }

  async function load(silent = false) {
    try {
      const response = await api('staff/orders.php');
      if (response.status === 401 || response.status === 403) { location.href = BASE + '/auth/login.php'; return; }
      if (!response.ok) { summary.textContent = response.error || 'Could not load orders.'; return; }
      const newOrders = new Set(response.orders.filter(order => order.status === 'new').map(order => Number(order.id)));
      if (silent && [...newOrders].some(id => !knownNew.has(id))) toast('New kitchen order');
      knownNew = newOrders;
      draw(response.orders);
    } catch {
      if (!board.querySelector('.kitchen-error')) summary.innerHTML = '<span class="kitchen-error">Connection lost. Retrying shortly…</span>';
    }
  }

  board.addEventListener('click', async event => {
    const button = event.target.closest('.kitchen-advance');
    if (!button) return;
    button.disabled = true;
    const orderId = Number(button.dataset.id);
    const response = await api('staff/order-action.php', { method: 'POST', body: { id: orderId, action: 'advance' } });
    if (!response.ok) toast(response.error || 'Could not update this order.');
    await load();
  });

  document.getElementById('kitchenRefresh').addEventListener('click', () => load());
  document.getElementById('kitchenFullscreen').addEventListener('click', async event => {
    try {
      if (!document.fullscreenElement) { await document.documentElement.requestFullscreen(); event.currentTarget.textContent = 'Exit full screen'; }
      else { await document.exitFullscreen(); event.currentTarget.textContent = 'Full screen'; }
    } catch { toast('Full screen is unavailable in this browser.'); }
  });
  document.addEventListener('fullscreenchange', () => {
    const button = document.getElementById('kitchenFullscreen');
    button.textContent = document.fullscreenElement ? 'Exit full screen' : 'Full screen';
  });
  load();
  setInterval(() => load(true), 7000);
  setInterval(() => board.querySelectorAll('.kitchen-age').forEach(node => { node.textContent = elapsed(node.dataset.created); }), 30000);
})();
