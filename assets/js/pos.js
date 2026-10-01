// Point of sale: search/add products, choose order type & table, apply a discount, take payment.
(() => {
  const $ = id => document.getElementById(id);
  let D = null, cart = {}, activeCat = 0;

  function filtered() {
    const term = $('posSearch').value.trim().toLowerCase();
    return D.products.filter(p => (!activeCat || p.category_id === activeCat) && (!term || p.name.toLowerCase().includes(term)));
  }

  function drawCats() {
    $('posCats').innerHTML = ['<button type="button" class="cat-chip' + (activeCat === 0 ? ' on' : '') + '" data-c="0">All</button>']
      .concat(D.categories.map(c => `<button type="button" class="cat-chip${activeCat === c.id ? ' on' : ''}" data-c="${c.id}">${esc(c.name)}</button>`)).join('');
  }

  function drawGrid() {
    $('posGrid').setAttribute('aria-busy', 'false');
    $('posGrid').innerHTML = filtered().map(p => `<button type="button" class="pos-item" data-add="${p.id}">
      ${p.image ? `<div class="pimg" style="background-image:url('${esc(p.image)}')"></div>` : '<div class="pimg"></div>'}
      <b>${esc(p.name)}</b><span>${money(p.price)}</span></button>`).join('') || '<div class="empty">No items match.</div>';
  }

  function lineKey(pid, vid) { return pid + ':' + (vid || 0); }

  function addToCart(p, variationId) {
    const v = p.variations.find(x => x.id === variationId);
    const key = lineKey(p.id, variationId);
    const unit = p.price + (v ? v.delta : 0);
    if (cart[key]) cart[key].qty++;
    else cart[key] = { product_id: p.id, variation_id: variationId || null, name: p.name + (v ? ' (' + v.name + ')' : ''), unit, qty: 1 };
    drawCart();
  }

  $('posGrid').addEventListener('click', e => {
    const b = e.target.closest('[data-add]'); if (!b) return;
    const p = D.products.find(x => x.id === +b.dataset.add); if (!p) return;
    if (p.variations.length > 1) {
      const names = p.variations.map((v, i) => `${i + 1}) ${v.name}`).join('\n');
      const pick = prompt(`Choose an option for ${p.name}:\n${names}`, '1');
      const v = p.variations[(+pick || 1) - 1]; if (!v) return;
      addToCart(p, v.id);
    } else addToCart(p, p.variations[0]?.id || null);
  });

  function subtotal() { return Object.values(cart).reduce((a, l) => a + l.unit * l.qty, 0); }

  function drawCart() {
    const lines = Object.entries(cart);
    $('posLines').innerHTML = lines.length ? lines.map(([k, l]) => `
      <div class="crow"><div class="mtxt"><b>${esc(l.name)}</b><br><small>${money(l.unit)}</small></div>
      <div class="qty"><button type="button" data-dec="${k}">−</button>${l.qty}<button type="button" data-inc="${k}">+</button></div>
      <div class="mprice">${money(l.unit * l.qty)}</div></div>`).join('') : '<div class="empty" style="border:0">No items yet.</div>';
    const sub = subtotal();
    const disc = Math.min(Math.max(0, +$('posDiscount').value || 0), sub);
    $('posSub').textContent = money(sub);
    $('posTotal').textContent = money(sub - disc);
    $('posLines').querySelectorAll('[data-inc]').forEach(b => b.onclick = () => { cart[b.dataset.inc].qty++; drawCart(); });
    $('posLines').querySelectorAll('[data-dec]').forEach(b => b.onclick = () => { const k = b.dataset.dec; cart[k].qty--; if (cart[k].qty <= 0) delete cart[k]; drawCart(); });
  }

  $('posDiscount').addEventListener('input', drawCart);
  $('posType').addEventListener('change', () => { $('posTable').hidden = $('posType').value !== 'dine_in'; });

  function receipt(r, restaurantName) {
    return `<div class="pq" style="text-align:left;max-width:320px;margin:0 auto">
      <h2 style="text-align:center">${esc(restaurantName)}</h2>
      <p style="text-align:center">Order #${esc(r.order_number)}</p><hr>
      ${r.items.map(i => `<div class="mrow" style="border:0;padding:4px 0"><span>${i.quantity}× ${esc(i.product_name)}</span><span>${money(i.line_total)}</span></div>`).join('')}
      <hr><div class="mrow" style="border:0"><span>Subtotal</span><span>${money(r.subtotal)}</span></div>
      ${r.discount ? `<div class="mrow" style="border:0"><span>Discount</span><span>-${money(r.discount)}</span></div>` : ''}
      <div class="mrow" style="border:0;font-weight:800"><span>Total</span><span>${money(r.total)}</span></div>
      <p style="text-align:center;margin-top:12px">Thank you!</p></div>`;
  }

  $('posComplete').onclick = async () => {
    const items = Object.values(cart).map(l => ({ product_id: l.product_id, variation_id: l.variation_id, qty: l.qty }));
    if (!items.length) { $('posMsg').className = 'msg error'; $('posMsg').textContent = 'Add at least one item.'; return; }
    const type = $('posType').value;
    if (type === 'dine_in' && !$('posTable').value) { $('posMsg').className = 'msg error'; $('posMsg').textContent = 'Choose a table.'; return; }
    $('posComplete').disabled = true; $('posMsg').className = 'msg';
    const body = { order_type: type, table_id: $('posTable').value || null, items,
      payment_method: $('posMethod').value, discount_amount: +$('posDiscount').value || 0, customer_name: $('posName').value };
    const r = await api('staff/pos-checkout.php', { method: 'POST', body });
    $('posComplete').disabled = false;
    if (!r.ok) { $('posMsg').className = 'msg error'; $('posMsg').textContent = r.error || 'Could not complete the sale.'; return; }
    toast('Sale completed · #' + r.order_number);
    document.getElementById('posReceipt').innerHTML = receipt(r, document.title.split(' · ')[0] === 'POS' ? 'Dineqor' : document.title);
    window.print();
    cart = {}; $('posDiscount').value = 0; $('posName').value = ''; drawCart();
  };
  $('posClear').onclick = () => { cart = {}; drawCart(); };
  $('posSearch').addEventListener('input', debounce(drawGrid, 120));
  $('posCats').addEventListener('click', e => { const b = e.target.closest('[data-c]'); if (!b) return; activeCat = +b.dataset.c; drawCats(); drawGrid(); });

  async function load() {
    const r = await api('staff/pos-catalog.php');
    if (r.status === 401 || r.status === 403) { location.href = BASE + '/auth/login.php'; return; }
    D = r;
    $('posTable').innerHTML = D.tables.map(t => `<option value="${t.id}">${esc(t.label || 'Table ' + t.table_number)}</option>`).join('') || '<option value="">No tables</option>';
    drawCats(); drawGrid(); drawCart();
  }
  load();
})();
