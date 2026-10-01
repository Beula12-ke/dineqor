(() => {
  const badges = document.querySelectorAll('[data-notification-count]');
  if (!badges.length) return;
  const url = window.BASE + '/api/customer/notifications.php?count_only=1';
  async function update() {
    try {
      const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
      if (!response.ok) return;
      const data = await response.json();
      if (!data.ok) return;
      badges.forEach((badge) => {
        const count = Number(data.unread_count) || 0;
        badge.textContent = count > 99 ? '99+' : String(count);
        badge.hidden = count === 0;
      });
    } catch (_) { /* Keep the account navigation usable when the connection is unavailable. */ }
  }
  update();
  window.setInterval(update, 30000);
})();
