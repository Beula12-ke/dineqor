// QR & Tables manager. All authorisation and tenant checks happen in the PHP APIs.
(() => {
  const $ = id => document.getElementById(id);
  let D = null;
  const absUrl = path => location.origin + BASE + '/' + path;
  const title = t => t.label || 'Table ' + t.table_number;
  const statusLabels = {available:'Available', occupied:'Occupied', reserved:'Booked', inactive:'Inactive', ordering:'Occupied', preparing:'Occupied', ready:'Occupied', payment_pending:'Occupied', cleaning:'Available', closed:'Inactive'};

  function qrCard(name, sub, url, qrId, active, extra = '') {
    return `<div class="qcard ${active == 1 ? '' : 'off'}"><div class="qimg" data-url="${esc(url)}">${QR.svg(url)}</div>
      <b>${esc(name)}</b><small>${esc(sub)}</small>${active == 1 ? '' : '<span class="badge closed">Disabled</span>'}
      <div class="act">${extra}<button class="btn sm ghost" data-dl="${esc(url)}" data-name="${esc(name)}">Download</button>
      <button class="btn sm ghost" data-q="${qrId}" data-a="${active == 1 ? 'disable' : 'enable'}">${active == 1 ? 'Disable' : 'Enable'}</button>
      <button class="btn sm ghost" data-q="${qrId}" data-a="regenerate">Regenerate</button></div></div>`;
  }

  function draw() {
    if (window.TABLES_CAN_MANAGE) {
      const s = D.stats;
      $('stats').innerHTML = [['Total scans', s.total], ['Today', s.today], ['Last 7 days', s.week], ['Last 30 days', s.month]]
        .map(([l, v]) => `<div class="stat"><span>${l}</span><b>${v || 0}</b></div>`).join('');
      const r = D.restaurant_qr;
      $('rqr').innerHTML = r ? `<h2>Restaurant QR</h2><p class="sub">Opens your live storefront. Menu changes never require a new QR.</p>
        ${qrCard('Restaurant storefront', r.scan_count + ' scans · general browsing', absUrl(r.target_url), r.id, r.is_active)}` : '';
    }
    $('tables').setAttribute('aria-busy', 'false');
    $('tables').innerHTML = D.tables.length ? D.tables.map(t => {
      if (!window.TABLES_CAN_MANAGE) {
        const current = ['available','occupied','reserved'].includes(t.status) ? t.status : (t.status === 'inactive' || t.status === 'closed' ? 'available' : 'occupied');
        return `<article class="panel"><b>${esc(title(t))}</b><p class="sub">${Number(t.capacity)} seats${t.section ? ' · ' + esc(t.section) : ''} · ${Number(t.orders)} orders</p><label class="setting-field"><span>Table status</span><select data-status="${Number(t.id)}"><option value="available" ${current==='available'?'selected':''}>Available</option><option value="occupied" ${current==='occupied'?'selected':''}>Occupied</option><option value="reserved" ${current==='reserved'?'selected':''}>Booked</option></select></label><button class="btn sm" type="button" data-save-status="${Number(t.id)}">Save status</button></article>`;
      }
      return `<div>${t.qr_id ? qrCard(title(t), `${esc(statusLabels[t.status] || t.status)} · ${t.scan_count} scans · ${t.orders} orders · seats ${t.capacity}${t.section ? ' · ' + esc(t.section) : ''}`,
        absUrl(t.target_url), t.qr_id, t.is_active, `<button class="btn sm ghost" data-edit="${t.id}">Edit</button><button class="btn sm ghost" data-del="${t.id}">Delete</button>`) : ''}</div>`;
    }).join('') : `<div class="empty" style="grid-column:1/-1"><b>No tables yet</b>${window.TABLES_CAN_MANAGE ? 'Enter how many tables you have and click Generate.' : 'Ask the restaurant manager to add tables.'}</div>`;
  }

  async function load() {
    const r = await api('restaurant/tables.php');
    if (r.status === 401 || r.status === 403) { location.href = BASE + '/auth/login.php'; return; }
    D = r; draw();
  }

  function download(url, name) {
    const svg = QR.svg(url), img = new Image();
    img.onload = () => {
      const c = document.createElement('canvas'); c.width = c.height = 800;
      const x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, 800, 800); x.imageSmoothingEnabled = false;
      x.drawImage(img, 0, 0, 800, 800);
      c.toBlob(b => { const a = document.createElement('a'); a.href = URL.createObjectURL(b); a.download = name.replace(/[^\w-]+/g, '_') + '.png'; a.click(); setTimeout(() => URL.revokeObjectURL(a.href), 2000); });
    };
    img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
  }

  async function act(body, ok) {
    const r = await api(body.path || 'restaurant/qr.php', { method: 'POST', body });
    toast(r.ok ? ok : (r.error || 'Something went wrong.')); await load(); return r;
  }

  document.addEventListener('click', async e => {
    const b = e.target.closest('button'); if (!b) return;
    if (b.dataset.saveStatus) {
      const select = document.querySelector(`[data-status="${b.dataset.saveStatus}"]`);
      const r = await api('restaurant/tables.php', { method: 'POST', body: { action: 'status', id: +b.dataset.saveStatus, status: select.value } });
      toast(r.ok ? 'Table status updated' : (r.error || 'Could not update table status.')); await load(); return;
    }
    if (b.dataset.dl) download(b.dataset.dl, b.dataset.name);
    if (b.dataset.q) {
      if (b.dataset.a === 'regenerate' && !confirm('Regenerate this QR code? The old printed QR will stop working.')) return;
      act({ id: +b.dataset.q, action: b.dataset.a }, 'Updated');
    }
    if (b.dataset.del && confirm('Delete this table and its QR code?')) {
      const r = await api('restaurant/tables.php', { method: 'POST', body: { action: 'delete', id: +b.dataset.del } }); toast(r.ok ? 'Table deleted' : r.error); load();
    }
    if (b.dataset.edit) {
      const t = D.tables.find(x => x.id == b.dataset.edit);
      const label = prompt('Table name', title(t)); if (label === null) return;
      const cap = prompt('Seats', t.capacity); if (cap === null) return;
      const section = prompt('Section (e.g. Indoor, Outdoor, VIP) - optional', t.section || ''); if (section === null) return;
      const r = await api('restaurant/tables.php', { method: 'POST', body: { action: 'update', id: t.id, label, capacity: +cap, section } }); toast(r.ok ? 'Saved' : r.error); load();
    }
  });

  if (window.TABLES_CAN_MANAGE) {
    $('gen').addEventListener('submit', async e => {
      e.preventDefault();
      const r = await api('restaurant/tables.php', { method: 'POST', body: { action: 'generate', count: +$('count').value } });
      toast(r.ok ? 'Tables created' : r.error); if (r.ok) $('count').value = ''; load();
    });
    $('addOne').onclick = async () => { const r = await api('restaurant/tables.php', { method: 'POST', body: { action: 'add' } }); toast(r.ok ? 'Table added' : r.error); load(); };

    $('dlAll').onclick = () => {
    const list = [...D.tables.filter(t => t.qr_id && t.is_active == 1).map(t => [absUrl(t.target_url), title(t)])];
    if (!list.length) return toast('No active table QR codes');
    list.forEach(([u, n], i) => setTimeout(() => download(u, n), i * 350));       // browsers may ask permission for multiple downloads
    };
    $('printAll').onclick = () => {
    const list = D.tables.filter(t => t.qr_id && t.is_active == 1);
    if (!list.length) return toast('No active table QR codes');
    $('printArea').innerHTML = list.map(t => `<div class="pq"><div class="pqi">${QR.svg(absUrl(t.target_url))}</div><h2>${esc(title(t))}</h2><p>Scan to browse the menu and order from your table</p></div>`).join('');
    window.print();
    };
  }
  load();
})();
