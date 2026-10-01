// Cart, item options and checkout for the storefront. Prices shown here are for display only;
// the server recalculates everything from the database (and decides dine-in from the table's QR session) when the order is placed.
(() => {
  const root = document.getElementById('store');
  const slug = root.dataset.slug;
  const KEY = 'dq_cart_' + slug;
  let store = null, lines = [];

  const load = () => { try { lines = JSON.parse(localStorage.getItem(KEY)) || []; } catch (e) { lines = []; } };
  const save = () => { try { localStorage.setItem(KEY, JSON.stringify(lines)); } catch (e) {} render(); };
  const remember = (k, v) => { try { localStorage.setItem('dq_' + k, v); } catch (e) {} };
  const recall = k => { try { return localStorage.getItem('dq_' + k) || ''; } catch (e) { return ''; } };
  const lineUnit = l => l.unit + l.addons.reduce((a, x) => a + x.price * x.qty, 0);
  const lineTotal = l => lineUnit(l) * l.qty;
  const subtotal = () => lines.reduce((a, l) => a + lineTotal(l), 0);
  const count = () => lines.reduce((a, l) => a + l.qty, 0);
  const products = () => (store ? store.menu.flatMap(c => c.items) : []);
  const canOrder = () => store && (store.table ? store.restaurant.dinein : (store.restaurant.pickup || store.restaurant.delivery));
  const closed = () => store && store.restaurant.open === false && !store.table;

  // ---------- chrome ----------
  const bar = document.createElement('button');
  bar.type = 'button'; bar.id = 'cartbar'; bar.hidden = true;
  const cartDlg = document.createElement('dialog'); cartDlg.className = 'sheet'; cartDlg.setAttribute('aria-labelledby', 'cart-title');
  const optDlg = document.createElement('dialog'); optDlg.className = 'sheet'; optDlg.setAttribute('aria-labelledby', 'opt-title');
  document.body.append(bar, cartDlg, optDlg);
  [cartDlg, optDlg].forEach(d => d.addEventListener('click', e => { if (e.target === d) d.close(); }));   // click backdrop to close

  function render() {
    const n = count();
    bar.hidden = n === 0;
    const total = money(subtotal());
    bar.setAttribute('aria-label', `View cart, ${n} item${n === 1 ? '' : 's'}, ${total}`);
    bar.innerHTML = `<svg class="cartbar-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 10.1a2 2 0 002 1.6h8.7a2 2 0 001.9-1.4L22 8H6"/><circle cx="10" cy="20" r="1.25"/><circle cx="18" cy="20" r="1.25"/></svg><span>View cart · ${n} item${n === 1 ? '' : 's'}</span><b>${total}</b>`;
    if (cartDlg.open && cartDlg.dataset.view === 'cart') showCart();
  }

  // ---------- add to order ----------
  root.addEventListener('click', e => {
    const b = e.target.closest('[data-add]'); if (!b) return;
    const p = products().find(x => x.id === +b.dataset.add); if (!p) return;
    if (!p.variations.length && !p.addons.length) { addLine({ pid: p.id, name: p.name, unit: p.price, vid: null, vname: null, addons: [], qty: 1, notes: '' }); toast('Added ' + p.name); }
    else openOptions(p);
  });

  function addLine(l) {
    const key = [l.pid, l.vid, JSON.stringify(l.addons.map(a => [a.id, a.qty]).sort()), l.notes].join('|');
    const ex = lines.find(x => x.key === key);
    if (ex) ex.qty = Math.min(50, ex.qty + l.qty); else lines.push({ ...l, key });
    save();
  }

  function openOptions(p) {
    const def = p.variations.find(v => v.default) || p.variations[0];
    optDlg.innerHTML = `<form method="dialog" class="sheet-in" id="optf">
      <div class="sheet-head"><h2 id="opt-title">${esc(p.name)}</h2><button type="button" class="x" aria-label="Close" data-close>×</button></div>
      ${p.description ? `<p class="sub">${esc(p.description)}</p>` : ''}
      ${p.variations.length ? `<fieldset><legend>Choose one</legend>${p.variations.map(v => `
        <label class="opt"><input type="radio" name="var" value="${v.id}" ${v === def ? 'checked' : ''}><span>${esc(v.name)}</span><em>${v.delta ? (v.delta > 0 ? '+' : '−') + money(Math.abs(v.delta)) : ''}</em></label>`).join('')}</fieldset>` : ''}
      ${p.addons.length ? `<fieldset><legend>Extras</legend>${p.addons.map(a => `
        <label class="opt"><span>${esc(a.name)}</span><em>+${money(a.price)}</em>
        ${a.max > 1 ? `<select name="add" data-id="${a.id}" aria-label="${esc(a.name)} quantity">${Array.from({ length: a.max + 1 }, (_, i) => `<option>${i}</option>`).join('')}</select>`
                    : `<input type="checkbox" name="add" data-id="${a.id}">`}</label>`).join('')}</fieldset>` : ''}
      <label for="onotes">Note for the kitchen (optional)</label><input id="onotes" maxlength="200" placeholder="e.g. no onions">
      <div class="sheet-foot"><div class="qty"><button type="button" data-q="-1" aria-label="Fewer">−</button><output id="oq">1</output><button type="button" data-q="1" aria-label="More">+</button></div>
      <button class="btn" type="submit" id="oadd"></button></div></form>`;
    const f = optDlg.querySelector('#optf'); let qty = 1;
    const pick = () => {
      const v = p.variations.find(x => x.id === +(f.querySelector('input[name=var]:checked') || {}).value) || null;
      const addons = [...f.querySelectorAll('[name=add]')].map(el => {
        const a = p.addons.find(x => x.id === +el.dataset.id); const q = el.type === 'checkbox' ? +el.checked : +el.value;
        return q ? { id: a.id, name: a.name, price: a.price, qty: q } : null;
      }).filter(Boolean);
      return { v, addons };
    };
    const total = () => { const { v, addons } = pick(); return (p.price + (v ? v.delta : 0) + addons.reduce((a, x) => a + x.price * x.qty, 0)) * qty; };
    const paint = () => { f.querySelector('#oq').textContent = qty; f.querySelector('#oadd').textContent = 'Add to order · ' + money(total()); };
    f.addEventListener('change', paint);
    f.addEventListener('click', e => {
      if (e.target.matches('[data-close]')) optDlg.close();
      const q = e.target.closest('[data-q]'); if (q) { qty = Math.max(1, Math.min(50, qty + +q.dataset.q)); paint(); }
    });
    f.addEventListener('submit', e => {
      e.preventDefault(); const { v, addons } = pick();
      addLine({ pid: p.id, name: p.name, unit: p.price + (v ? v.delta : 0), vid: v ? v.id : null, vname: v ? v.name : null, addons, qty, notes: f.querySelector('#onotes').value.trim() });
      optDlg.close(); toast('Added ' + p.name);
    });
    paint(); optDlg.showModal();
  }

  // ---------- cart view ----------
  bar.addEventListener('click', showCart);
  function showCart() {
    cartDlg.dataset.view = 'cart';
    if (!lines.length) { cartDlg.innerHTML = `<div class="sheet-in"><div class="sheet-head"><h2 id="cart-title">Your order</h2><button class="x" data-close aria-label="Close">×</button></div>
      <div class="empty"><b>Your order is empty</b>Add something from the menu.</div></div>`; wire(); if (!cartDlg.open) cartDlg.showModal(); return; }
    cartDlg.innerHTML = `<div class="sheet-in"><div class="sheet-head"><h2 id="cart-title">Your order${store && store.table ? ' · ' + esc(store.table.label) : ''}</h2><button class="x" data-close aria-label="Close">×</button></div>
      ${closed() ? `<div class="msg error">${esc(store.restaurant.name)} is closed right now, so you can't place an order yet.</div>` : ''}
      <ul class="lines">${lines.map((l, i) => `<li class="line"><div><b>${esc(l.name)}</b>
        <small>${[l.vname, ...l.addons.map(a => (a.qty > 1 ? a.qty + '× ' : '') + a.name), l.notes && '“' + l.notes + '”'].filter(Boolean).map(esc).join(' · ')}</small></div>
        <div class="qty"><button data-i="${i}" data-d="-1" aria-label="Remove one ${esc(l.name)}">−</button><output>${l.qty}</output><button data-i="${i}" data-d="1" aria-label="Add one ${esc(l.name)}">+</button></div>
        <span class="lt">${money(lineTotal(l))}</span></li>`).join('')}</ul>
      <div class="sum"><span>Subtotal</span><b>${money(subtotal())}</b></div>
      <div class="sheet-foot"><button class="btn ghost" data-close type="button">Keep browsing</button>
      <button class="btn" id="tocheckout" ${closed() || !canOrder() ? 'disabled' : ''}>Checkout</button></div>
      ${!canOrder() ? `<p class="sub">${store.table ? 'Table ordering is currently unavailable. Please ask restaurant staff for help.' : 'This restaurant isn\'t taking online orders yet.'}</p>` : ''}</div>`;
    wire(); if (!cartDlg.open) cartDlg.showModal();
  }
  function wire() {
    cartDlg.querySelectorAll('[data-close]').forEach(b => b.onclick = () => cartDlg.close());
    cartDlg.querySelectorAll('[data-d]').forEach(b => b.onclick = () => {
      const l = lines[+b.dataset.i]; l.qty += +b.dataset.d;
      if (l.qty < 1) lines.splice(+b.dataset.i, 1); else l.qty = Math.min(50, l.qty);
      save();
    });
    const c = cartDlg.querySelector('#tocheckout'); if (c) c.onclick = showCheckout;
  }

  // ---------- checkout ----------
  async function showCheckout() {
    cartDlg.dataset.view = 'checkout';
    const r = store.restaurant, zones = store.zones || [];
    const types = store.table ? [] : [r.pickup && ['pickup', 'Pickup'], r.delivery && ['delivery', 'Delivery']].filter(Boolean);
    const me = await api('auth/me.php'); const user = me.user && me.user.user_type === 'customer' ? me.user : null;
    let rewards=null;if(user){try{const rw=await api('public/loyalty.php?slug='+encodeURIComponent(slug));if(rw.ok&&rw.enabled)rewards=rw;}catch(_){} }
    const redeemStep=rewards?Number(rewards.redemption_points):0,redeemValue=rewards?Number(rewards.redemption_value):0;
    const redeemCount=rewards?Math.max(0,Math.min(Math.floor(Number(rewards.points)/redeemStep),Math.floor(subtotal()/redeemValue))):0;
    cartDlg.innerHTML = `<form class="sheet-in" id="cof" novalidate>
      <div class="sheet-head"><h2 id="cart-title">Checkout</h2><button type="button" class="x" data-close aria-label="Close">×</button></div>
      ${user ? '<p class="sub checkout-account-note">Your saved account details are filled in. You can edit them if needed.</p>' : ''}
      ${store.table ? `<p class="sub">Dine-in · ${esc(store.table.label)}. We'll bring your order to your table.</p>` :
        `<fieldset><legend>How would you like it?</legend><div class="seg">${types.map(([v, t], i) => `<label><input type="radio" name="order_type" value="${v}" ${i === 0 ? 'checked' : ''}><span>${t}</span></label>`).join('')}</div><div class="err" data-err="order_type"></div></fieldset>`}
      <div id="delivery" hidden>
        ${zones.length ? `<label for="zone_id">Delivery area</label><select id="zone_id" name="zone_id"><option value="">Choose your area</option>${zones.map(z => `<option value="${z.id}">${esc(z.name)} — ${money(z.fee)}${z.eta ? ' · ~' + z.eta + ' min' : ''}</option>`).join('')}</select><div class="err" data-err="zone_id"></div>` : ''}
        <label for="address">Delivery address</label><textarea id="address" name="address" rows="2" placeholder="Building, street, landmark">${esc(user ? (user.default_delivery_address || '') : recall('address'))}</textarea><div class="err" data-err="address"></div>
      </div>
      <label for="name">Your name</label><input id="name" name="name" autocomplete="name" value="${esc(user ? user.name : recall('name'))}"><div class="err" data-err="name"></div>
      <label for="phone">Phone</label><input id="phone" name="phone" type="tel" autocomplete="tel" placeholder="07XX XXX XXX" value="${esc(user && user.phone ? user.phone : recall('phone'))}"><div class="err" data-err="phone"></div>
      <label for="email">Email (optional)</label><input id="email" name="email" type="email" autocomplete="email" value="${esc(user ? user.email : recall('email'))}"><div class="err" data-err="email"></div>
      <label for="payment_method">Payment method</label><select id="payment_method" name="payment_method"><option value="cash">Cash on ${store.table ? 'service' : 'delivery or collection'}</option>${r.mpesa ? '<option value="mpesa">M-Pesa · Send a payment prompt</option>' : ''}</select><div class="pay-method-note" id="paymentMethodNote">${r.mpesa ? 'You can choose cash or pay securely with an M-Pesa prompt.' : 'Cash is available at delivery or collection.'}</div>
      ${rewards?`<label class="checkout-rewards" ${redeemCount<1?'data-no-reward="1"':''}><input type="checkbox" id="redeemRewards" ${redeemCount<1?'disabled':''}><span><b>Use ${redeemCount*redeemStep} points</b><small>${redeemCount>0?`Save ${money(redeemCount*redeemValue)} from this order`:`${Number(rewards.points)} points available · Earn more to unlock a reward`}</small></span></label>`:''}
      <label for="instructions">Anything we should know? (optional)</label><textarea id="instructions" name="instructions" rows="2"></textarea>
      <div class="sum"><span>Subtotal</span><b>${money(subtotal())}</b></div>
      ${rewards?'<div class="sum" id="rewardDiscountRow" hidden><span>Points reward</span><b id="rewardDiscount"></b></div>':''}
      <div class="sum" id="feeRow" hidden><span>Delivery</span><b id="fee"></b></div>
      <div class="sum big"><span>Total</span><b id="tot"></b></div>
      <p class="sub" id="paymentInstructions">You pay in cash when you ${store.table ? 'get your order' : 'receive your order'}.</p>
      <div class="sheet-foot"><button type="button" class="btn ghost" id="back">Back</button><button class="btn" type="submit">Place order</button></div>
      <div class="msg" id="cmsg"></div></form>`;
    const f = cartDlg.querySelector('#cof');
    const typeNow = () => store.table ? 'qr_table' : (f.querySelector('input[name=order_type]:checked') || {}).value;
    const paint = () => {
      const del = typeNow() === 'delivery'; f.querySelector('#delivery').hidden = !del;
      const z = zones.find(x => x.id === +(f.zone_id ? f.zone_id.value : 0)); const fee = del && z ? z.fee : 0;
      f.querySelector('#feeRow').hidden = !del; f.querySelector('#fee').textContent = del ? (z || !zones.length ? money(fee) : 'Choose your area') : '';
      const useReward=!!(f.querySelector('#redeemRewards')?.checked),discount=useReward?redeemCount*redeemValue:0;
      if(f.querySelector('#rewardDiscountRow')){f.querySelector('#rewardDiscountRow').hidden=!useReward;f.querySelector('#rewardDiscount').textContent='−'+money(discount);}
      f.querySelector('#tot').textContent = money(subtotal() + fee - discount);
      const mpesa=f.payment_method?.value==='mpesa';
      f.querySelector('#paymentInstructions').textContent=mpesa?'After you place the order, an M-Pesa prompt will be sent to the phone number above. The order is paid only after Safaricom confirms it.':'You pay in cash when you '+(store.table?'get your order':'receive your order')+'.';
      f.querySelector('#paymentMethodNote').textContent=mpesa?'Make sure the phone number above is registered with M-Pesa.':'Choose M-Pesa to receive a payment prompt, or keep cash.';
    };
    f.addEventListener('change', paint); paint();
    f.querySelector('[data-close]').onclick = () => cartDlg.close();
    f.querySelector('#back').onclick = showCart;
    f.addEventListener('submit', async e => {
      e.preventDefault();
      const btn = f.querySelector('button[type=submit]'), msg = f.querySelector('#cmsg'); btn.disabled = true; msg.className = 'msg'; showFieldErrors(f);
      const body = { slug, order_type: typeNow(), name: f.name.value, phone: f.phone.value, email: f.email.value, payment_method: f.payment_method.value,
        address: f.address ? f.address.value : '', zone_id: f.zone_id ? +f.zone_id.value : 0, instructions: f.instructions.value,
        loyalty_points:f.querySelector('#redeemRewards')?.checked?redeemCount*redeemStep:0,
        items: lines.map(l => ({ product_id: l.pid, variation_id: l.vid, qty: l.qty, addons: l.addons.map(a => ({ id: a.id, qty: a.qty })), notes: l.notes })) };
      const res = await api('orders/create.php', { method: 'POST', body });
      btn.disabled = false;
      if (res.ok) { remember('name', f.name.value); remember('phone', f.phone.value); if (f.address) remember('address', f.address.value); lines = []; try { localStorage.removeItem(KEY); } catch (x) {} location.href = res.track_url; return; }
      msg.className = 'msg error'; msg.textContent = res.error || 'Something went wrong.'; showFieldErrors(f, res.fields);
      if (res.status === 409) { const r2 = await api('public/restaurant.php?slug=' + encodeURIComponent(slug)); if (r2.ok) store = r2; }
    });
  }

  document.addEventListener('store:ready', e => {
    store = e.detail; load(); render();
    try {
      if (localStorage.getItem('dq_reorder_open') === slug) {
        localStorage.removeItem('dq_reorder_open');
        showCart();
      }
    } catch (x) {}
  });
})();
