// Admin dashboard: live stats + application review
(() => {
  const $ = id => document.getElementById(id);
  const LABELS = { total: ['Restaurants', 'Across the platform', '◫'], active: ['Active restaurants', 'Live on Dineqor', '✳'], pending: ['Applications to review', 'Partner applications', '◷'], suspended: ['Suspended', 'Currently offline', '—'], customers: ['Customers', 'Registered accounts', '♙'], orders: ['Orders', 'All-time orders', '▤'] };
  const ACTIONS = { pending: [['reject', 'Reject', 'ghost'], ['approve', 'Approve', '']], active: [['suspend', 'Suspend', 'ghost']], suspended: [['reactivate', 'Reactivate', '']] };

  function draw(d) {
    $('stats').innerHTML = Object.entries(LABELS).map(([k, [label, note, icon]]) =>
      `<article class="metric-card admin-metric ${k === 'pending' && Number(d.stats.pending) ? 'attention' : ''}"><div class="metric-label">${label}<span class="admin-metric-icon">${icon}</span></div><div class="metric-value">${Number(d.stats[k]).toLocaleString()}</div><div class="metric-foot">${note}</div></article>`).join('');
    $('pendingBadge').textContent = Number(d.stats.pending).toLocaleString();
    $('rows').innerHTML = d.restaurants.length ? d.restaurants.map(r => `<tr>
      <td><div class="restaurant-cell"><span class="restaurant-initial">${esc((r.name || '?').slice(0, 1).toUpperCase())}</span><span><b>${esc(r.name)}</b><small>${esc([r.cuisine, r.city].filter(Boolean).join(' · ') || 'Cuisine not set')}</small></span></div></td>
      <td><b class="owner-name">${esc(r.owner || '—')}</b><small class="owner-email">${esc(r.email || '')}</small></td>
      <td>${esc((r.created_at || '').slice(0, 10) || '—')}</td>
      <td><span class="status-pill ${esc(r.status)}"><i></i>${esc(r.status)}</span></td>
      <td><div class="act">${(r.status === 'active' ? `<a class="btn sm ghost" style="text-decoration:none" href="${BASE}/restaurant/${esc(r.slug)}">View</a>` : '')}
        ${(ACTIONS[r.status] || []).map(([a, t, c]) => `<button class="btn sm ${c}" data-id="${r.id}" data-a="${a}">${t}</button>`).join('')}</div></td></tr>`).join('')
      : '<tr><td colspan="5"><div class="dash-empty compact-empty"><span>✳</span><b>No restaurant applications yet</b><p>New partner applications will appear here.</p></div></td></tr>';
    $('lastUpdated').textContent = 'Updated ' + new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(new Date());
  }

  async function load() {
    const r = await api('admin/stats.php');
    if (r.status === 401 || r.status === 403) { location.href = BASE + '/auth/login.php'; return; }
    if (r.ok) draw(r);
    else $('rows').innerHTML = `<tr><td colspan="5"><div class="table-error">${esc(r.error || 'Could not load platform data.')} <button class="btn sm ghost" id="retryStats" type="button">Try again</button></div></td></tr>`;
  }

  $('rows').addEventListener('click', async e => {
    const b = e.target.closest('button[data-a]'); if (!b) return;
    b.disabled = true;
    const r = await api('admin/restaurant-status.php', { method: 'POST', body: { id: +b.dataset.id, action: b.dataset.a } });
    toast(r.ok ? 'Updated to ' + r.status : (r.error || 'Could not update.'));
    load();
  });
  $('refresh').onclick = () => { load(); toast('Refreshed'); };
  $('rows').addEventListener('click', e => { if (e.target.id === 'retryStats') load(); });
  load(); setInterval(load, 30000);
})();
