// Customer activity list styled to match the restaurant overview.
(() => {
  const board = document.getElementById('board');
  const typeLabel = value => String(value || '').replaceAll('_', ' ').replace(/\b\w/g, char => char.toUpperCase());
  const statusLabel = value => typeLabel(value);
  let canManage = false;
  // Ready pickup/takeaway orders can be handed over (and paid, if cash) by staff who can manage orders.
  const canHandOver = o => canManage && ['pickup', 'takeaway'].includes(o.order_type) && o.status === 'ready';
  const handoverRow = o => {
    const paid = o.payment_status === 'paid';
    return `<div class="row-handover"><span>${paid ? 'Paid · ready to hand over' : 'Customer pays ' + esc(money(o.total_amount)) + ' (' + esc(o.payment_method || 'cash') + ')'}</span><button class="wo-btn" type="button" data-handover="${Number(o.id)}" data-paid="${paid ? 1 : 0}" data-total="${Number(o.total_amount)}" data-method="${esc(o.payment_method || 'cash')}">${paid ? 'Hand over' : 'Take payment &amp; hand over'}</button></div>`;
  };

  function draw(orders) {
    board.setAttribute('aria-busy', 'false');
    if (!orders.length) {
      board.innerHTML = '<div class="dash-empty"><span>✳</span><b>Your first order is just around the corner</b><p>When a customer places an order, it will show up here.</p></div>';
      return;
    }
    board.innerHTML = `<div class="recent-head"><span>ORDER</span><span>CUSTOMER</span><span>TYPE</span><span>STATUS</span><span>TOTAL</span></div>${orders.map(o => `<div class="recent-row${canHandOver(o) ? ' has-action' : ''}"><b>#${esc(o.order_number)}</b><span>${esc(o.customer_name || 'Guest')}</span><span>${esc(typeLabel(o.order_type))}</span><span><i class="order-status ${esc(o.status)}">${esc(statusLabel(o.status))}</i></span><b>${money(o.total_amount)}</b>${canHandOver(o) ? handoverRow(o) : ''}</div>`).join('')}`;
  }

  async function load() {
    const response = await api('staff/orders.php');
    if (response.status === 401 || response.status === 403) { location.href = BASE + '/auth/login.php'; return; }
    if (!response.ok) {
      board.setAttribute('aria-busy', 'false');
      board.innerHTML = `<div class="dash-empty"><b>Orders could not load</b><p>${esc(response.error || 'Please try again.')}</p></div>`;
      return;
    }
    canManage = !!response.can_manage;
    draw(response.orders || []);
  }

  board.addEventListener('click', async event => {
    const button = event.target.closest('[data-handover]');
    if (!button) return;
    const text = button.dataset.paid === '1'
      ? 'Hand this order over to the customer?'
      : `Confirm the customer paid ${money(button.dataset.total)} (${button.dataset.method}) and hand over the order?`;
    if (!confirm(text)) return;
    button.disabled = true;
    const result = await api('staff/order-action.php', { method: 'POST', body: { id: Number(button.dataset.handover), action: 'advance' } });
    toast(result.ok ? 'Order handed over.' : (result.error || 'Could not update this order.'));
    await load();
  });

  document.getElementById('refresh').addEventListener('click', load);
  load();
  setInterval(load, 10000);
})();
