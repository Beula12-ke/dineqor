// Guest order-tracking page. The URL's token is issued and verified by the server; nothing here is trusted for authorisation.
(() => {
  const el = document.getElementById('track');
  const { r, n, t } = el.dataset;
  const LABELS = { new: 'Order received', confirmed: 'Confirmed', preparing: 'Being prepared', ready: 'Ready',
    out_for_delivery: 'Out for delivery', delivered: 'Delivered', completed: 'Completed', cancelled: 'Cancelled' };
  const FLOW = { qr_table: ['new', 'confirmed', 'preparing', 'ready', 'completed'], pickup: ['new', 'confirmed', 'preparing', 'ready', 'completed'],
    takeaway: ['new', 'confirmed', 'preparing', 'ready', 'completed'], dine_in: ['new', 'confirmed', 'preparing', 'ready', 'completed'], pos: ['new', 'confirmed', 'preparing', 'ready', 'completed'],
    delivery: ['new', 'confirmed', 'preparing', 'ready', 'out_for_delivery', 'delivered'] };

  function render(d) {
    const o = d.order, steps = FLOW[o.order_type] || FLOW.pickup;
    const cancelled = o.status === 'cancelled';
    const at = steps.indexOf(o.status);
    const paymentText = o.payment_method === 'mpesa'
      ? (o.payment_status === 'paid' ? 'M-Pesa payment confirmed.' : o.payment_status === 'failed' ? 'M-Pesa payment did not complete. Contact the restaurant.' : 'Waiting for M-Pesa confirmation.')
      : (o.payment_status === 'paid' ? 'Paid.' : 'Pay in cash on ' + (o.order_type === 'delivery' ? 'delivery' : 'collection') + '.');
    el.innerHTML = `<div class="panel" style="max-width:640px;margin:0 auto">
      <div class="row-head"><div><h1 style="margin:0">Order ${esc(n)}</h1><p class="sub">${esc(d.restaurant.name)}${d.restaurant.phone ? ' · ' + esc(d.restaurant.phone) : ''}</p></div>
      <span class="st ${cancelled ? 'suspended' : (o.status === 'completed' || o.status === 'delivered' ? 'active' : 'pending')}">${esc(LABELS[o.status] || o.status)}</span></div>
      ${cancelled ? `<div class="msg error">Cancelled${o.cancellation_reason ? ': ' + esc(o.cancellation_reason) : ''}.</div>` :
        `<ol class="track">${steps.map((s, i) => `<li class="${i <= at ? 'done' : ''}">${esc(LABELS[s])}</li>`).join('')}</ol>`}
      <h2>Items</h2><ul class="lines">${d.items.map(i => `<li class="line"><div><b>${i.qty}× ${esc(i.name)}</b>
        <small>${[i.variation, ...i.addons].filter(Boolean).map(esc).join(' · ')}</small></div><span class="lt">${money(i.total)}</span></li>`).join('')}</ul>
      <div class="sum"><span>Subtotal</span><b>${money(o.subtotal)}</b></div>
      ${+o.delivery_fee ? `<div class="sum"><span>Delivery</span><b>${money(o.delivery_fee)}</b></div>` : ''}
      <div class="sum big"><span>Total</span><b>${money(o.total_amount)}</b></div>
      <p class="sub">${esc(paymentText)}</p>
      ${o.special_instructions ? `<p class="sub">Note: ${esc(o.special_instructions)}</p>` : ''}</div>`;
  }

  async function load() {
    const res = await api(`orders/status.php?r=${encodeURIComponent(r)}&n=${encodeURIComponent(n)}&t=${encodeURIComponent(t)}`);
    if (!res.ok) { el.innerHTML = '<div class="empty"><b>We couldn\'t find that order</b>Check the link and try again.</div>'; return; }
    const done = ['completed', 'delivered', 'cancelled'].includes(res.order.status)
      && !(res.order.payment_method === 'mpesa' && res.order.payment_status === 'pending');
    render(res);
    if (!done) setTimeout(load, 12000);
  }
  load();
})();
