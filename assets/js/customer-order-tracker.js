// Keep the signed-in customer's order view in sync while the restaurant prepares it.
(() => {
  const tracker = document.getElementById('customerOrderTracker');
  if (!tracker) return;

  const orderId = tracker.dataset.orderId;
  const api = tracker.dataset.api;
  const orderType = tracker.dataset.orderType;
  const liveText = tracker.querySelector('[data-order-live-status]');
  const liveMark = tracker.querySelector('[data-order-live-indicator]');
  const eta = tracker.querySelector('[data-order-eta]');
  const etaTime = tracker.querySelector('[data-order-eta-time]');
  const etaRemaining = tracker.querySelector('[data-order-eta-remaining]');
  const timeline = tracker.querySelector('[data-order-timeline]');
  const cancelNote = tracker.querySelector('[data-order-cancel-note]');
  const statusBadge = document.querySelector('.order-heading-actions .order-status');
  const paymentStatus = document.querySelector('[data-order-payment-status]');
  const paymentNote = document.querySelector('[data-order-payment-note]');
  const terminal = ['completed', 'delivered', 'cancelled'];
  const delivery = orderType === 'delivery';
  const steps = delivery
    ? [['new', 'Order received'], ['confirmed', 'Confirmed'], ['preparing', 'Being prepared'], ['ready', 'Ready for dispatch'], ['out_for_delivery', 'On the way'], ['delivered', 'Delivered']]
    : [['new', 'Order received'], ['confirmed', 'Confirmed'], ['preparing', 'Being prepared'], ['ready', 'Ready for collection'], ['completed', 'Completed']];
  let stopped = terminal.includes(tracker.dataset.currentStatus);

  const label = (status) => ({
    new: 'Order received', confirmed: 'Confirmed', preparing: 'Being prepared',
    ready: delivery ? 'Ready for dispatch' : 'Ready for collection',
    out_for_delivery: 'On the way', completed: 'Completed', delivered: 'Delivered', cancelled: 'Cancelled',
  }[status] || status.replaceAll('_', ' '));

  function renderTimeline(status) {
    if (!timeline) return;
    const shownStatus = delivery && status === 'completed' ? 'delivered' : (!delivery && status === 'delivered' ? 'completed' : status);
    const current = steps.findIndex(([key]) => key === shownStatus);
    timeline.querySelectorAll('[data-order-step]').forEach((node) => {
      const index = steps.findIndex(([key]) => key === node.dataset.orderStep);
      node.classList.toggle('done', current >= 0 && index <= current);
    });
  }

  function renderEta(etaAt) {
    if (!eta || !etaAt) return;
    const target = new Date(etaAt.replace(' ', 'T'));
    if (Number.isNaN(target.getTime())) return;
    eta.hidden = false;
    if (etaTime) etaTime.textContent = target.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    const remaining = Math.max(0, Math.ceil((target.getTime() - Date.now()) / 60000));
    if (etaRemaining) etaRemaining.textContent = remaining > 0
      ? 'About ' + remaining + ' min estimated'
      : 'Taking longer than the initial estimate';
  }

  function render(data) {
    const status = data.status;
    tracker.dataset.currentStatus = status;
    if (statusBadge) {
      statusBadge.className = 'order-status ' + status;
      statusBadge.textContent = label(status);
    }
    if (paymentStatus) paymentStatus.textContent = String(data.payment_status || 'pending').replace(/^./, (c) => c.toUpperCase());
    if (paymentNote) paymentNote.textContent = data.payment_method === 'mpesa'
      ? (data.payment_status === 'paid' ? 'M-Pesa payment confirmed.' : data.payment_status === 'failed' ? 'M-Pesa payment did not complete. Contact the restaurant.' : 'Waiting for M-Pesa confirmation.')
      : (data.payment_method || 'Cash') + (data.payment_status === 'paid' ? ' · paid' : ' · payment due');
    if (liveText) liveText.textContent = status === 'cancelled'
      ? 'This order is no longer being prepared.'
      : terminal.includes(status) ? 'Your order is complete.' : 'Current status: ' + label(status) + '.';
    if (liveMark) {
      liveMark.textContent = terminal.includes(status) ? 'UPDATED' : 'LIVE';
      liveMark.classList.toggle('is-active', !terminal.includes(status));
    }
    if (status === 'cancelled') {
      if (timeline) timeline.hidden = true;
      if (eta) eta.hidden = true;
      if (cancelNote) {
        cancelNote.hidden = false;
        cancelNote.textContent = data.cancellation_reason || 'The restaurant cancelled this order.';
      }
    } else {
      renderTimeline(status);
      if (status === 'ready' || terminal.includes(status)) {
        if (eta) eta.hidden = true;
      } else renderEta(data.eta_at);
    }
    stopped = terminal.includes(status);
  }

  async function refresh() {
    try {
      const response = await fetch(api + '?id=' + encodeURIComponent(orderId), {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        cache: 'no-store',
      });
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.error || 'Could not refresh order status.');
      render(data);
    } catch (error) {
      if (liveText && !stopped) liveText.textContent = 'We’ll retry the live status shortly.';
    } finally {
      if (!stopped) window.setTimeout(refresh, 15000);
    }
  }

  if (!stopped) refresh();
  else if (eta) eta.hidden = true;
})();
