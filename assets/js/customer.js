// Customer-only actions shared by restaurant pages and the customer dashboard.
(() => {
  const buttons = () => [...document.querySelectorAll('[data-favorite-toggle]')];
  const setButton = (button, saved) => {
    button.dataset.favorite = saved ? 'true' : 'false';
    button.setAttribute('aria-pressed', saved ? 'true' : 'false');
    const card = button.closest('[data-saved-card]');
    if (card) button.setAttribute('aria-label', `Remove ${card.querySelector('.saved-card-copy b')?.textContent || 'restaurant'} from saved places`);
    else button.setAttribute('aria-label', saved ? 'Remove from favourites' : 'Save to favourites');
    button.textContent = card ? '♥' : saved ? '♥ Saved' : '♡ Save';
  };
  const emptySaved = () => {
    const container = document.getElementById('savedRestaurants');
    if (!container || container.querySelector('[data-saved-card]')) return;
    container.className = 'saved-empty';
    container.innerHTML = '<span>♡</span><b>No saved places yet</b><p>Tap the heart on a restaurant page to keep it here.</p><a href="' + BASE + '/">Find a restaurant →</a>';
  };
  document.addEventListener('click', async event => {
    const cancelButton = event.target.closest('[data-cancel-reservation]');
    if (cancelButton && !cancelButton.disabled) {
      if (!window.confirm('Cancel this reservation?')) return;
      cancelButton.disabled = true;
      const result = await api('customer/reservations.php', { method: 'POST', body: { action: 'cancel', id: Number(cancelButton.dataset.cancelReservation) } });
      cancelButton.disabled = false;
      if (!result.ok) { toast(result.error || 'Could not cancel this reservation'); return; }
      const row = cancelButton.closest('[data-reservation]');
      const status = row?.querySelector('[data-reservation-status]');
      if (status) { status.textContent = 'Cancelled'; status.className = 'order-status cancelled'; }
      cancelButton.remove();
      toast(result.message || 'Reservation cancelled');
      return;
    }
    const button = event.target.closest('[data-favorite-toggle]');
    if (!button || button.disabled) return;
    const saved = button.dataset.favorite === 'true';
    button.disabled = true;
    const result = await api('customer/favorites.php', { method: 'POST', body: {
      action: saved ? 'remove' : 'add', restaurant_id: Number(button.dataset.restaurantId)
    }});
    button.disabled = false;
    if (!result.ok) { toast(result.error || 'Could not update saved places'); return; }
    buttons().filter(other => other.dataset.restaurantId === button.dataset.restaurantId).forEach(other => setButton(other, result.favorite));
    const card = button.closest('[data-saved-card]');
    if (card && !result.favorite) {
      card.remove();
      const count = document.getElementById('favoriteCount');
      if (count) count.textContent = String(Math.max(0, Number(count.textContent.replace(/\D/g, '')) - 1));
      emptySaved();
    } else if (!saved) {
      const count = document.getElementById('favoriteCount');
      if (count) count.textContent = String(Number(count.textContent.replace(/\D/g, '')) + 1);
      toast('Added to your saved places');
    } else toast('Removed from your saved places');
  });
})();
