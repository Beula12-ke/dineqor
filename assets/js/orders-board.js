// Customer activity list styled to match the restaurant overview.
(() => {
  const board = document.getElementById('board');
  const typeLabel = value => String(value || '').replaceAll('_', ' ').replace(/\b\w/g, char => char.toUpperCase());
  const statusLabel = value => typeLabel(value);

  function draw(orders) {
    board.setAttribute('aria-busy', 'false');
    if (!orders.length) {
      board.innerHTML = '<div class="dash-empty"><span>✳</span><b>Your first order is just around the corner</b><p>When a customer places an order, it will show up here.</p></div>';
      return;
    }
    board.innerHTML = `<div class="recent-head"><span>ORDER</span><span>CUSTOMER</span><span>TYPE</span><span>STATUS</span><span>TOTAL</span></div>${orders.map(o => `<div class="recent-row"><b>#${esc(o.order_number)}</b><span>${esc(o.customer_name || 'Guest')}</span><span>${esc(typeLabel(o.order_type))}</span><span><i class="order-status ${esc(o.status)}">${esc(statusLabel(o.status))}</i></span><b>${money(o.total_amount)}</b></div>`).join('')}`;
  }

  async function load() {
    const response = await api('staff/orders.php');
    if (response.status === 401 || response.status === 403) { location.href = BASE + '/auth/login.php'; return; }
    if (!response.ok) {
      board.setAttribute('aria-busy', 'false');
      board.innerHTML = `<div class="dash-empty"><b>Orders could not load</b><p>${esc(response.error || 'Please try again.')}</p></div>`;
      return;
    }
    draw(response.orders || []);
  }

  document.getElementById('refresh').addEventListener('click', load);
  load();
  setInterval(load, 10000);
})();
