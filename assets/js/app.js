// Shared client helper. All data comes from PHP APIs; the server decides who you are.
// An empty BASE is intentional when the app is deployed at the domain root.
const BASE = typeof window.BASE === 'string' ? window.BASE : '/dineqor';
let _csrf = null;

async function getCsrf() {
  if (_csrf) return _csrf;
  const r = await fetch(BASE + '/api/auth/csrf.php', { credentials: 'same-origin' });
  _csrf = (await r.json()).csrf;
  return _csrf;
}

async function api(path, { method = 'GET', body = null } = {}) {
  const opts = { method, credentials: 'same-origin', headers: {} };
  if (method !== 'GET') {
    opts.headers['Content-Type'] = 'application/json';
    opts.headers['X-CSRF-Token'] = await getCsrf();
    opts.body = JSON.stringify(body || {});
  }
  const res = await fetch(BASE + '/api/' + path, opts);
  let data = {};
  try { data = await res.json(); } catch (e) { data = { ok: false, error: 'Something went wrong. Please try again.' }; }
  if (data.csrf) _csrf = data.csrf;
  data.status = res.status;
  if ((res.status === 401 || res.status === 428) && data.redirect && location.pathname !== new URL(data.redirect, location.origin).pathname) location.href = data.redirect;
  return data;
}

// Client-side redirect helper. Real protection is server-side (page_guard + API checks).
async function requireRole(types) {
  const r = await api('auth/me.php');
  if (!r.user || (types && !types.includes(r.user.user_type))) {
    location.href = BASE + '/auth/login.php';
    return null;
  }
  return r.user;
}

async function logout() {
  await api('auth/logout.php', { method: 'POST' });
  _csrf = null;
  location.href = BASE + '/auth/login.php';
}

function showFieldErrors(form, fields = {}) {
  form.querySelectorAll('.err').forEach(e => e.textContent = '');
  Object.entries(fields).forEach(([k, msg]) => {
    const el = form.querySelector('[data-err="' + k + '"]');
    if (el) el.textContent = msg;
  });
}

function formData(form) { return Object.fromEntries(new FormData(form).entries()); }

// ---- shared UI helpers ----
function esc(v) {
  return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
function debounce(fn, ms = 250) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; }
function toast(text) {
  const el = document.createElement('div');
  el.className = 'toast'; el.setAttribute('role', 'status'); el.textContent = text;
  document.body.appendChild(el);
  setTimeout(() => el.remove(), 2800);
}
function money(n) { return 'KSh ' + Number(n).toLocaleString('en-KE', { maximumFractionDigits: 0 }); }
