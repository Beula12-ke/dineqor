// Link ingredients + quantities to a menu item.
(() => {
  const $ = id => document.getElementById(id);
  let products = [], ingredients = [], lines = [];

  async function loadProducts() {
    const r = await api('restaurant/recipe.php');
    if (r.status === 401 || r.status === 403) { location.href = BASE + '/auth/login.php'; return; }
    products = r.products; ingredients = r.ingredients;
    $('product').innerHTML = '<option value="">Choose a menu item&hellip;</option>' + products.map(p => `<option value="${p.id}">${esc(p.name)}</option>`).join('');
  }

  async function loadRecipe(pid) {
    const r = await api('restaurant/recipe.php?product_id=' + pid);
    if (!r.ok) { toast(r.error); return; }
    lines = r.lines.length ? r.lines : [];
    $('pname').textContent = products.find(p => p.id === +pid)?.name || '';
    $('editor').style.display = '';
    $('rmsg').className = 'msg';
    draw();
  }

  function lineRow(l, i) {
    const opts = ingredients.map(ing => `<option value="${ing.id}" ${ing.id === l.ingredient_id ? 'selected' : ''}>${esc(ing.name)} (${esc(ing.unit)})</option>`).join('');
    return `<tr data-i="${i}"><td><select class="ling">${opts}</select></td>
      <td><input class="lqty" type="number" min="0.001" step="0.001" value="${l.quantity}"></td>
      <td><button class="btn sm ghost" type="button" data-rm="${i}">Remove</button></td></tr>`;
  }

  function draw() {
    if (!ingredients.length) { $('lines').innerHTML = '<tr><td colspan="3">Add ingredients on the Inventory page first.</td></tr>'; return; }
    $('lines').innerHTML = lines.length ? lines.map(lineRow).join('') : '<tr><td colspan="3">No ingredients linked yet. This item will not deduct stock when sold.</td></tr>';
  }

  $('product').addEventListener('change', e => { if (e.target.value) loadRecipe(e.target.value); else $('editor').style.display = 'none'; });

  $('addLine').onclick = () => {
    if (!ingredients.length) { toast('Add an ingredient on the Inventory page first.'); return; }
    lines.push({ ingredient_id: ingredients[0].id, quantity: 1 });
    draw();
  };

  $('lines').addEventListener('click', e => {
    const t = e.target.closest('button[data-rm]'); if (!t) return;
    lines.splice(+t.dataset.rm, 1); draw();
  });

  $('save').addEventListener('click', async () => {
    const pid = $('product').value; if (!pid) return;
    const rows = [...$('lines').querySelectorAll('tr[data-i]')];
    const body = { product_id: pid, lines: rows.map(tr => ({ ingredient_id: +tr.querySelector('.ling').value, quantity: tr.querySelector('.lqty').value })) };
    const btn = $('save'); btn.disabled = true;
    const r = await api('restaurant/recipe.php', { method: 'POST', body });
    btn.disabled = false;
    $('rmsg').className = 'msg ' + (r.ok ? 'ok' : 'error');
    $('rmsg').textContent = r.ok ? 'Recipe saved' : r.error;
    if (r.ok) toast('Recipe saved');
  });

  loadProducts();
})();
