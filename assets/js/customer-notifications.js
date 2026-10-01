(() => {
  const list = document.querySelector('[data-notification-list]');
  if (!list) return;
  const api = window.BASE + '/api/customer/notifications.php';
  const readAll = document.querySelector('[data-read-all]');
  const error = document.querySelector('[data-notification-error]');
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[char]));
  const formatDate = (value) => {
    const date = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? '' : date.toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
  };

  function render(data) {
    const items = data.notifications || [];
    list.innerHTML = items.length ? items.map((item) =>
      '<article class="notification-item' + (item.read_at ? '' : ' unread') + '">' +
        '<span class="notification-mark" aria-hidden="true">' + (item.status === 'cancelled' ? '!' : '✓') + '</span>' +
        '<div class="notification-copy"><div class="notification-title-row"><b>' + escapeHtml(item.title) + '</b>' +
          (!item.read_at ? '<span class="notification-new">NEW</span>' : '') + '</div>' +
          '<p>' + escapeHtml(item.message) + '</p><small>' + escapeHtml(item.restaurant_name) + ' · ' + escapeHtml(formatDate(item.created_at)) + '</small>' +
          '<a href="' + escapeHtml(window.BASE + '/customer/order.php?id=' + encodeURIComponent(item.order_id)) + '">View order #' + escapeHtml(item.order_number) + ' →</a></div>' +
        (!item.read_at ? '<button type="button" class="notification-mark-read" data-read-id="' + Number(item.id) + '">Mark read</button>' : '') +
      '</article>'
    ).join('') : '<div class="dash-empty"><span>♧</span><b>No order updates yet</b><p>When a restaurant changes the status of your order, you’ll see it here.</p><a class="panel-link" href="' + escapeHtml(window.BASE + '/customer/orders.php') + '">View my orders →</a></div>';
    if (readAll) readAll.hidden = Number(data.unread_count) === 0;
    list.dataset.loaded = '1';
    document.querySelectorAll('[data-notification-count]').forEach((badge) => {
      const count = Number(data.unread_count) || 0;
      badge.textContent = count > 99 ? '99+' : String(count);
      badge.hidden = count === 0;
    });
  }

  async function load() {
    try {
      const response = await fetch(api, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.error || 'Could not load your updates.');
      render(data);
      if (error) error.textContent = '';
    } catch (e) {
      if (error) error.textContent = e.message || 'Could not load your updates. Please refresh and try again.';
      if (!list.dataset.loaded) list.innerHTML = '<div class="notification-loading">Your updates could not be loaded.</div>';
    }
  }

  async function markRead(payload) {
    const response = await fetch(api, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.DINEQOR_CSRF || '' },
      body: JSON.stringify(payload),
    });
    const data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.error || 'Could not update notification.');
    await load();
  }

  list.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-read-id]');
    if (!button) return;
    button.disabled = true;
    try { await markRead({ action: 'read', id: Number(button.dataset.readId) }); }
    catch (e) { if (error) error.textContent = e.message; button.disabled = false; }
  });
  if (readAll) readAll.addEventListener('click', async () => {
    readAll.disabled = true;
    try { await markRead({ action: 'read_all' }); }
    catch (e) { if (error) error.textContent = e.message; }
    finally { readAll.disabled = false; }
  });

  load();
  window.setInterval(load, 30000);
})();
