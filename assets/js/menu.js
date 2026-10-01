// Owner menu manager. Every call is authorised + tenant-scoped on the server.
(() => {
  const $ = id => document.getElementById(id);
  let cats = [], items = [], canManage = false, editing = null;

  async function load() {
    const r = await api('restaurant/menu.php');
    if (r.status === 401 || r.status === 403) { location.href = BASE + '/auth/login.php'; return; }
    cats = r.categories; items = r.products; canManage = r.can_manage;
    $('addCat').hidden = $('addItem').hidden = !canManage;
    draw();
  }

  function itemRow(p) {
    return `<div class="mrow"><div class="mimg" style="${p.image ? `background-image:url('${esc(p.image)}')` : ''}"></div>
      <div class="mtxt"><b>${esc(p.name)}</b> ${p.is_featured == 1 ? '<span class="star">Popular</span>' : ''}${p.is_available == 1 ? '' : '<span class="badge closed">Unavailable</span>'}
      <br><small>${esc(p.description || '')}</small></div><div class="mprice">${money(p.price)}</div>
      ${canManage ? `<div class="act"><button class="btn sm ghost" data-edit="${p.id}">Edit</button><button class="btn sm ghost" data-del="${p.id}">Delete</button></div>` : ''}</div>`;
  }

  function draw() {
    $('menu').setAttribute('aria-busy', 'false');
    const groups = [...cats.map(c => ({ ...c, list: items.filter(i => i.category_id === +c.id) })),
                    { id: 0, name: 'Uncategorised', list: items.filter(i => !i.category_id || !cats.some(c => +c.id === i.category_id)) }];
    const html = groups.filter(g => g.id || g.list.length).map(g => `<section class="panel" style="margin-bottom:16px">
      <div class="row-head"><h2>${esc(g.name)}</h2>${g.id && canManage ? `<div class="act"><button class="btn sm ghost" data-rename="${g.id}">Rename</button><button class="btn sm ghost" data-delcat="${g.id}">Delete</button></div>` : ''}</div>
      ${g.list.length ? g.list.map(itemRow).join('') : '<div class="empty" style="border:0">No items yet.</div>'}</section>`).join('');
    $('menu').innerHTML = html || '<div class="empty"><b>Your menu is empty</b>Add a category, then your first menu item.</div>';
  }

  function openForm(p) {
    editing = p || null;
    const f = $('pf'); f.reset(); showFieldErrors(f); $('pmsg').className = 'msg';
    $('dtitle').textContent = p ? 'Edit menu item' : 'New menu item';
    $('category_id').innerHTML = '<option value="">No category</option>' + cats.map(c => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
    if (p) {
      f.name.value = p.name; f.price.value = p.price; f.description.value = p.description || '';
      f.preparation_time.value = p.preparation_time ?? ''; f.category_id.value = p.category_id || '';
      f.is_available.checked = p.is_available == 1; f.is_featured.checked = p.is_featured == 1;
    }
    $('dlg').showModal();
  }

  $('addItem').onclick = () => openForm(null);
  $('cancel').onclick = () => $('dlg').close();
  $('addCat').onclick = async () => {
    const name = prompt('Category name (e.g. Burgers)'); if (!name) return;
    const r = await api('restaurant/categories.php', { method: 'POST', body: { action: 'create', name } });
    toast(r.ok ? 'Category added' : r.error); load();
  };

  $('pf').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target, btn = f.querySelector('button[type=submit]'); btn.disabled = true;
    const body = { action: editing ? 'update' : 'create', id: editing?.id, name: f.name.value, category_id: f.category_id.value,
      price: f.price.value, description: f.description.value, preparation_time: f.preparation_time.value,
      is_available: f.is_available.checked, is_featured: f.is_featured.checked };
    const r = await api('restaurant/products.php', { method: 'POST', body });
    if (!r.ok) { btn.disabled = false; $('pmsg').className = 'msg error'; $('pmsg').textContent = r.error; showFieldErrors(f, r.fields); return; }
    const file = $('image').files[0];
    if (file) {
      const fd = new FormData(); fd.append('product_id', r.id); fd.append('image', file);
      const up = await fetch(BASE + '/api/restaurant/product-image.php', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-Token': await getCsrf() }, body: fd });
      const ur = await up.json().catch(() => ({ ok: false, error: 'Upload failed.' }));
      if (!ur.ok) toast(ur.error);
    }
    btn.disabled = false; $('dlg').close(); toast('Saved'); load();
  });

  $('menu').addEventListener('click', async e => {
    const t = e.target.closest('button'); if (!t) return;
    if (t.dataset.edit) openForm(items.find(i => i.id === +t.dataset.edit));
    if (t.dataset.del && confirm('Delete this item?')) { const r = await api('restaurant/products.php', { method: 'POST', body: { action: 'delete', id: +t.dataset.del } }); toast(r.ok ? 'Deleted' : r.error); load(); }
    if (t.dataset.rename) { const name = prompt('New name'); if (name) { const r = await api('restaurant/categories.php', { method: 'POST', body: { action: 'update', id: +t.dataset.rename, name } }); toast(r.ok ? 'Renamed' : r.error); load(); } }
    if (t.dataset.delcat && confirm('Delete this category? Its items become uncategorised.')) { const r = await api('restaurant/categories.php', { method: 'POST', body: { action: 'delete', id: +t.dataset.delcat } }); toast(r.ok ? 'Deleted' : r.error); load(); }
  });
  load();
})();
