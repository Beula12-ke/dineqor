// Keep status steps on the customer's order list current without opening each detail page.
(() => {
  const rows = [...document.querySelectorAll('.customer-order[data-order-id]')];
  if (!rows.some(row => row.querySelector('[data-order-progress]'))) return;

  const terminal = ['completed', 'delivered', 'cancelled'];
  const label = (status, orderType) => ({
    new: 'Order received', confirmed: 'Confirmed', preparing: 'Being prepared',
    ready: orderType === 'delivery' ? 'Ready for dispatch' : (['qr_table', 'dine_in'].includes(orderType) ? 'Ready to serve' : 'Ready for collection'),
    out_for_delivery: 'On the way', delivered: 'Delivered', completed: 'Completed', cancelled: 'Cancelled',
  }[status] || status.replaceAll('_', ' '));

  const paymentNote = (method, status) => {
    if (method === 'mpesa') return ({
      paid: 'M-Pesa payment confirmed.', failed: 'M-Pesa payment did not complete. Contact the restaurant.',
      refunded: 'M-Pesa payment refunded.',
    }[status] || 'Waiting for M-Pesa confirmation.');
    return (method || 'Cash').replace(/^./, letter => letter.toUpperCase()) + ({
      paid: ' · paid', refunded: ' · refunded',
    }[status] || ' · payment due');
  };

  function paint(row, data) {
    const delivery = data.order_type === 'delivery';
    const status = data.status;
    row.dataset.currentStatus = status;
    const badge = row.querySelector('[data-order-status]');
    if (badge) {
      badge.className = 'order-status ' + status;
      badge.textContent = label(status, data.order_type);
    }
    const paymentStatus = row.querySelector('[data-order-payment-status]');
    const paymentMessage = row.querySelector('[data-order-payment-note]');
    if (paymentStatus && data.payment_status) paymentStatus.textContent = data.payment_status.replace(/^./, letter => letter.toUpperCase());
    if (paymentMessage && data.payment_method && data.payment_status) paymentMessage.textContent = paymentNote(data.payment_method, data.payment_status);
    let progress = row.querySelector('[data-order-progress]');
    let cancelled = row.querySelector('[data-order-cancelled]');
    if (status === 'cancelled') {
      if (progress) progress.remove();
      if (!cancelled) {
        cancelled = document.createElement('div');
        cancelled.className = 'customer-order-cancelled';
        cancelled.dataset.orderCancelled = '';
        row.append(cancelled);
      }
      cancelled.textContent = 'Order cancelled' + (data.cancellation_reason ? ' · ' + data.cancellation_reason : '');
      return;
    }
    if (cancelled) cancelled.remove();
    if (!progress) return;
    progress.dataset.currentStatus = status;
    progress.setAttribute('aria-label', 'Order progress: ' + label(status, data.order_type));
    const estimate = row.querySelector('[data-order-estimate]');
    if (estimate && ['completed', 'delivered'].includes(status)) estimate.hidden = true;
    const shownStatus = delivery && status === 'completed' ? 'delivered' : (!delivery && status === 'delivered' ? 'completed' : status);
    const nodes = [...progress.querySelectorAll('[data-progress-step]')];
    const currentIndex = nodes.findIndex(node => node.dataset.progressStep === shownStatus);
    nodes.forEach((node, index) => {
      node.classList.toggle('done', currentIndex >= 0 && index <= currentIndex);
      node.classList.toggle('current', index === currentIndex);
      if (index === currentIndex) node.setAttribute('aria-current', 'step');
      else node.removeAttribute('aria-current');
    });
  }

  async function refresh() {
    try {
      const result = await api('customer/orders-progress.php');
      if (!result.ok) throw new Error(result.error || 'Could not refresh order progress.');
      const latest = new Map(result.orders.map(order => [String(order.id), order]));
      rows.forEach(row => {
        const data = latest.get(row.dataset.orderId);
        if (data) paint(row, data);
      });
    } catch (_) {
      // Keep the last status visible; the next refresh will retry.
    }
    if (rows.some(row => !terminal.includes(row.dataset.currentStatus || row.querySelector('[data-order-progress]')?.dataset.currentStatus))) {
      window.setTimeout(refresh, 15000);
    }
  }

  refresh();
})();
