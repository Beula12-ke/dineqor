// Owner/staff reservations list. Every action re-checks the current status server-side.
(() => {
  const $ = id => document.getElementById(id);
  const ACTIONS = {
    pending:   [['confirm', 'Confirm'], ['reject', 'Reject']],
    confirmed: [['complete', 'Mark completed'], ['no_show', 'No-show'], ['cancel', 'Cancel']],
  };
  const LABEL = { pending: 'Pending', confirmed: 'Confirmed', rejected: 'Rejected', cancelled: 'Cancelled', completed: 'Completed', no_show: 'No-show' };

  function row(r) {
    const acts = ACTIONS[r.status] || [];
    return `<div class="rrow"><div class="mtxt">
        <b>${esc(r.name)}</b> <span class="badge ${r.status === 'confirmed' ? 'open' : r.status === 'pending' ? '' : 'closed'}">${LABEL[r.status]}</span><br>
        <small>${esc(r.reservation_date)} at ${esc(r.reservation_time.slice(0, 5))} · ${r.guests} guest${r.guests == 1 ? '' : 's'} · ${esc(r.phone)}</small>
        ${r.special_request ? `<br><small>“${esc(r.special_request)}”</small>` : ''}
      </div>
      <div class="act">${acts.map(([a, l]) => `<button class="btn sm ghost" data-a="${a}" data-id="${r.id}">${l}</button>`).join('')}</div></div>`;
  }

  async function load() {
    const r = await api('restaurant/reservations.php');
    if (r.status === 401 || r.status === 403) { location.href = BASE + '/auth/login.php'; return; }
    $('list').setAttribute('aria-busy', 'false');
    $('list').innerHTML = r.reservations.length ? `<div class="panel">${r.reservations.map(row).join('')}</div>`
      : '<div class="empty"><b>No reservations yet</b>Requests from your storefront will appear here.</div>';
  }

  $('list').addEventListener('click', async e => {
    const b = e.target.closest('button[data-a]'); if (!b) return;
    if (b.dataset.a === 'cancel' && !confirm('Cancel this reservation?')) return;
    b.disabled = true;
    const r = await api('restaurant/reservations.php', { method: 'POST', body: { id: +b.dataset.id, action: b.dataset.a } });
    toast(r.ok ? 'Updated' : (r.error || 'Could not update.')); load();
  });
  $('refresh').onclick = load;
  load(); setInterval(() => load(), 30000);
})();
