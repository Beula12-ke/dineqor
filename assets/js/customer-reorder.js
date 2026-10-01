(() => {
  const buttons = [...document.querySelectorAll('[data-reorder-id]')];
  if (!buttons.length) return;
  const message = document.querySelector('[data-reorder-message]');
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[c]));

  buttons.forEach((button) => button.addEventListener('click', async () => {
    if (button.disabled) return;
    button.disabled = true;
    const original = button.textContent;
    button.textContent = 'Checking menu…';
    if (message) message.textContent = '';
    try {
      const response = await fetch(window.BASE + '/api/customer/reorder.php?id=' + encodeURIComponent(button.dataset.reorderId), {
        credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' },
      });
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.error || 'Could not start this order again.');

      const key = 'dq_cart_' + data.restaurant_slug;
      let current = [];
      try { current = JSON.parse(localStorage.getItem(key) || '[]'); } catch (e) { current = []; }
      if (current.length && !window.confirm('Replace your current ' + data.restaurant_name + ' cart with these items from your previous order?')) return;
      localStorage.setItem(key, JSON.stringify(data.lines));
      localStorage.setItem('dq_reorder_open', data.restaurant_slug);
      if (data.unavailable && data.unavailable.length) {
        window.alert('Some items are no longer available and were skipped: ' + data.unavailable.join(', '));
      }
      window.location.href = window.BASE + '/restaurant/' + encodeURIComponent(data.restaurant_slug);
    } catch (e) {
      if (message) message.textContent = e.message || 'Could not start this order again.';
      else window.alert(e.message || 'Could not start this order again.');
    } finally {
      button.disabled = false;
      button.textContent = original;
    }
  }));
})();
