// "Book a table" widget for the storefront. Listens for store:ready (dispatched by restaurant.js) so it
// never needs to touch that file. Availability and validation are all re-checked on the server at submit time.
(() => {
  let slug = null;

  function fabHtml() {
    let fab = document.getElementById('resFab');
    if (!fab) {
      fab = document.createElement('button'); fab.id = 'resFab'; fab.className = 'cart-fab res-fab'; fab.type = 'button';
      fab.textContent = '📅 Book a table'; fab.onclick = openDrawer;
      document.body.appendChild(fab);
    }
    fab.classList.add('show');
  }

  function drawerHtml() {
    let d = document.getElementById('resDrawer');
    if (d) return d;
    const today = new Date().toISOString().slice(0, 10);
    d = document.createElement('div'); d.id = 'resDrawer'; d.className = 'cart-drawer';
    d.innerHTML = `<div class="cart-panel"><div class="row-head"><h2>Book a table</h2><button class="btn sm ghost" id="resClose" type="button">Close</button></div>
      <form id="resForm" novalidate>
        <label for="rName">Name</label><input id="rName" name="name" required><div class="err" data-err="name"></div>
        <label for="rPhone">Phone</label><input id="rPhone" name="phone" type="tel" required><div class="err" data-err="phone"></div>
        <label for="rEmail">Email (optional)</label><input id="rEmail" name="email" type="email"><div class="err" data-err="email"></div>
        <label for="rDate">Date</label><input id="rDate" name="date" type="date" min="${today}" required><div class="err" data-err="date"></div>
        <label for="rTime">Time</label><input id="rTime" name="time" type="time" required><div class="err" data-err="time"></div>
        <label for="rGuests">Guests</label><input id="rGuests" name="guests" type="number" min="1" max="30" value="2" required><div class="err" data-err="guests"></div>
        <label for="rNote">Special request (optional)</label><textarea id="rNote" name="special_request" rows="2"></textarea>
        <div class="msg" id="resMsg"></div>
        <button class="btn" type="submit">Request reservation</button>
      </form></div>`;
    document.body.appendChild(d);
    d.addEventListener('click', e => { if (e.target === d) closeDrawer(); });
    document.getElementById('resClose').onclick = closeDrawer;
    document.getElementById('resForm').addEventListener('submit', submit);
    return d;
  }

  function openDrawer() { drawerHtml(); document.getElementById('resDrawer').classList.add('open'); }
  function closeDrawer() { const d = document.getElementById('resDrawer'); if (d) d.classList.remove('open'); }

  async function submit(e) {
    e.preventDefault();
    const f = e.target, btn = f.querySelector('button[type=submit]'), msg = document.getElementById('resMsg');
    btn.disabled = true; msg.className = 'msg'; showFieldErrors(f);
    const body = { slug, name: f.name.value, phone: f.phone.value, email: f.email.value, date: f.date.value,
                   time: f.time.value, guests: +f.guests.value, special_request: f.special_request.value };
    const r = await api('public/reservations-create.php', { method: 'POST', body });
    btn.disabled = false;
    if (!r.ok) { msg.className = 'msg error'; msg.textContent = r.error || 'Something went wrong.'; showFieldErrors(f, r.fields); return; }
    msg.className = 'msg ok'; msg.textContent = r.message;
    setTimeout(closeDrawer, 1800);
    f.reset();
  }

  document.addEventListener('store:ready', e => { slug = e.detail.restaurant.slug; fabHtml(); });
})();
