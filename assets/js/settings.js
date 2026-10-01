// Restaurant owners and permitted managers edit only their session-bound restaurant.
(() => {
  const form = document.getElementById('settingsForm');
  if (!form) return;
  const editor = document.getElementById('hoursEditor');
  const msg = document.getElementById('settingsMsg');
  const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  const setValue = (name, value) => { const el = form.elements.namedItem(name); if (el) el.value = value ?? ''; };
  const setChecked = (name, value) => { const el = form.elements.namedItem(name); if (el) el.checked = !!Number(value); };

  function periodRow(period = {}) {
    return `<div class="period-row"><label><span class="sr">Opens at</span><input type="time" class="period-open" value="${esc(period.open_time || '09:00')}" required></label><span class="period-separator">to</span><label><span class="sr">Closes at</span><input type="time" class="period-close" value="${esc(period.close_time || '17:00')}" required></label><button class="period-remove" type="button" aria-label="Remove opening period">×</button></div>`;
  }

  function drawHours(days) {
    const map = new Map((days || []).map(day => [Number(day.weekday), day]));
    editor.setAttribute('aria-busy', 'false');
    editor.innerHTML = DAYS.map((name, weekday) => {
      const day = map.get(weekday) || { is_closed: true, periods: [] };
      const periods = day.is_closed ? [] : (day.periods || []);
      return `<article class="hours-day" data-weekday="${weekday}"><div class="day-title"><b>${name}</b><label class="day-switch"><input class="day-open" type="checkbox" ${!day.is_closed ? 'checked' : ''}><span class="toggle"><i></i></span><span class="day-state">${day.is_closed ? 'Closed' : 'Open'}</span></label></div><div class="day-periods" ${day.is_closed ? 'hidden' : ''}>${periods.map(periodRow).join('')}</div><button class="period-add" type="button" ${day.is_closed || periods.length >= 3 ? 'hidden' : ''}>+ Add hours</button></article>`;
    }).join('');
  }

  async function load() {
    editor.setAttribute('aria-busy', 'true');
    try {
      const r = await api('restaurant/settings.php');
      if (r.status === 401 || r.status === 403) { location.href = BASE + '/auth/login.php'; return; }
      if (!r.ok) throw new Error(r.error || 'Could not load settings.');
      const values = r.restaurant || {};
      ['name','cuisine','description','website_url','phone','email','address','city','country','price_range','primary_color','secondary_color'].forEach(k => setValue(k, values[k]));
      ['delivery_enabled','pickup_enabled','dinein_enabled'].forEach(k => setChecked(k, values[k]));
      drawHours(r.hours);
    } catch (err) {
      editor.setAttribute('aria-busy', 'false');
      editor.innerHTML = `<div class="settings-load-error"><b>We couldn’t load your settings.</b><button class="link" id="retrySettings" type="button">Try again</button></div>`;
      document.getElementById('retrySettings').onclick = load;
    }
  }

  editor.addEventListener('change', event => {
    if (!event.target.matches('.day-open')) return;
    const day = event.target.closest('.hours-day');
    const open = event.target.checked;
    const periods = day.querySelector('.day-periods');
    if (open && !periods.querySelector('.period-row')) periods.innerHTML = periodRow();
    periods.hidden = !open;
    periods.querySelectorAll('input').forEach(input => { input.disabled = !open; });
    day.querySelector('.day-state').textContent = open ? 'Open' : 'Closed';
    day.querySelector('.period-add').hidden = !open || periods.querySelectorAll('.period-row').length >= 3;
  });

  editor.addEventListener('click', event => {
    const day = event.target.closest('.hours-day');
    if (!day) return;
    if (event.target.closest('.period-add')) {
      const periods = day.querySelector('.day-periods');
      if (periods.querySelectorAll('.period-row').length < 3) periods.insertAdjacentHTML('beforeend', periodRow({ open_time: '17:00', close_time: '22:00' }));
      day.querySelector('.period-add').hidden = periods.querySelectorAll('.period-row').length >= 3;
    }
    if (event.target.closest('.period-remove')) {
      const row = event.target.closest('.period-row');
      row.remove();
      const periods = day.querySelector('.day-periods');
      const checkbox = day.querySelector('.day-open');
      if (!periods.querySelector('.period-row')) { checkbox.checked = false; periods.hidden = true; day.querySelector('.day-state').textContent = 'Closed'; }
      day.querySelector('.period-add').hidden = !checkbox.checked || periods.querySelectorAll('.period-row').length >= 3;
    }
  });

  form.addEventListener('submit', async event => {
    event.preventDefault();
    showFieldErrors(form);
    msg.className = 'msg'; msg.textContent = '';
    const saveButtons = document.querySelectorAll('#settingsForm button[type="submit"], button[form="settingsForm"][type="submit"]');
    saveButtons.forEach(button => { button.disabled = true; });
    const data = Object.fromEntries(new FormData(form).entries());
    ['delivery_enabled','pickup_enabled','dinein_enabled'].forEach(key => { data[key] = form.elements.namedItem(key).checked; });
    data.hours = [...editor.querySelectorAll('.hours-day')].map(day => ({
      weekday: Number(day.dataset.weekday), is_closed: !day.querySelector('.day-open').checked,
      periods: [...day.querySelectorAll('.period-row')].map(row => ({
        open_time: row.querySelector('.period-open').value, close_time: row.querySelector('.period-close').value,
      })),
    }));
    const invalidDay = data.hours.find(day => !day.is_closed && (!day.periods.length || day.periods.some(p => !p.open_time || !p.close_time || p.open_time === p.close_time)));
    if (invalidDay) {
      msg.className = 'msg error'; msg.textContent = 'Add valid opening and closing times for every open day.';
      saveButtons.forEach(button => { button.disabled = false; }); return;
    }
    try {
      const r = await api('restaurant/settings.php', { method: 'POST', body: data });
      saveButtons.forEach(button => { button.disabled = false; });
      if (r.ok) { msg.className = 'msg ok'; msg.textContent = r.message || 'Settings saved.'; toast('Restaurant settings saved'); }
      else { msg.className = 'msg error'; msg.textContent = r.error || 'Could not save settings.'; showFieldErrors(form, r.fields || {}); }
    } catch (err) {
      saveButtons.forEach(button => { button.disabled = false; });
      msg.className = 'msg error'; msg.textContent = 'Could not reach the server. Check your connection and try again.';
    }
  });

  load();
})();
