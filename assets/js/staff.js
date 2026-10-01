(() => {
  const form = document.getElementById('staffForm');
  if (!form) return;
  const list = document.getElementById('teamList');
  const count = document.getElementById('teamCount');
  const msg = document.getElementById('staffFormMsg');
  const resetDialog = document.getElementById('resetPasswordDialog');
  const resetForm = document.getElementById('resetPasswordForm');
  const resetMsg = document.getElementById('resetPasswordMsg');
  let resetStaffId = 0;
  let csrf = '';
  const roles = ['manager', 'cashier', 'chef', 'waiter', 'delivery', 'inventory'];
  const title = value => value.charAt(0).toUpperCase() + value.slice(1);

  async function load() {
    list.setAttribute('aria-busy', 'true');
    try {
      const data = await api('restaurant/staff.php');
      if (!data.ok) throw new Error(data.error || 'Could not load team members.');
      csrf = data.csrf;
      const people = data.staff || [];
      count.textContent = `${people.length} ${people.length === 1 ? 'team member' : 'team members'}`;
      if (!people.length) {
        list.innerHTML = '<div class="inventory-empty"><span>♧</span><b>Your team starts here</b><p>Add a team member above to give them access to this restaurant.</p></div>';
        return;
      }
      list.innerHTML = people.map(person => {
        const expiry = person.password_expires_at ? new Date(String(person.password_expires_at).replace(' ', 'T')) : null;
        const due = expiry && !Number.isNaN(expiry.getTime()) ? expiry.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : 'Not set';
        const passwordStatus = Number(person.password_expired) ? 'Password expired · reset needed' : `Password expires ${due}`;
        return `<article class="team-row" data-id="${Number(person.id)}">
        <div class="team-avatar" aria-hidden="true">${esc((person.full_name || person.email || '?').trim().charAt(0).toUpperCase())}</div>
        <div class="team-person"><b>${esc(person.full_name || 'Team member')}</b><small>${esc(person.email)}${person.phone ? ` · ${esc(person.phone)}` : ''}</small><small class="team-password-status ${Number(person.password_expired) ? 'expired' : ''}">${esc(passwordStatus)}</small></div>
        <label class="team-control"><span>Role</span><select class="team-role" aria-label="Role for ${esc(person.full_name || person.email)}">${roles.map(role => `<option value="${role}" ${person.role === role ? 'selected' : ''}>${title(role)}</option>`).join('')}</select></label>
        <label class="team-control"><span>Access</span><select class="team-status" aria-label="Access for ${esc(person.full_name || person.email)}"><option value="active" ${person.status === 'active' ? 'selected' : ''}>Active</option><option value="disabled" ${person.status === 'disabled' ? 'selected' : ''}>Disabled</option></select></label>
        <div class="team-actions"><button class="btn sm ghost team-reset" type="button">Set password</button><button class="btn sm ghost team-save" type="button">Save role</button></div>
      </article>`;
      }).join('');
    } catch (error) {
      list.innerHTML = `<div class="inventory-empty"><b>Team list unavailable</b><p>${esc(error.message || 'Please try again.')}</p><button class="btn sm ghost" type="button" id="retryTeam">Try again</button></div>`;
      document.getElementById('retryTeam')?.addEventListener('click', load);
    } finally { list.setAttribute('aria-busy', 'false'); }
  }

  form.addEventListener('submit', async event => {
    event.preventDefault();
    const button = form.querySelector('[type="submit"]');
    const values = formData(form);
    button.disabled = true;
    msg.className = 'msg'; msg.textContent = '';
    showFieldErrors(form);
    try {
      const result = await api('restaurant/staff.php', { method: 'POST', body: { ...values, action: 'add' } });
      button.disabled = false;
      if (!result.ok) {
        msg.className = 'msg error'; msg.textContent = result.error || 'Could not add team member.';
        showFieldErrors(form, result.fields || {}); return;
      }
      msg.className = 'msg ok'; msg.textContent = result.message || 'Team member added.';
      form.reset(); await load();
    } catch {
      button.disabled = false; msg.className = 'msg error'; msg.textContent = 'Could not reach the server. Try again.';
    }
  });

  list.addEventListener('click', async event => {
    const reset = event.target.closest('.team-reset');
    if (reset) {
      const row = reset.closest('.team-row');
      resetStaffId = Number(row.dataset.id);
      document.getElementById('resetPasswordPerson').textContent = `Set a 90-day password for ${row.querySelector('.team-person b').textContent}`;
      resetForm.reset(); showFieldErrors(resetForm); resetMsg.className = 'msg'; resetMsg.textContent = '';
      resetDialog.showModal(); document.getElementById('resetTemporaryPassword').focus(); return;
    }
    const button = event.target.closest('.team-save');
    if (!button) return;
    const row = button.closest('.team-row');
    button.disabled = true;
    const result = await api('restaurant/staff.php', { method: 'POST', body: {
      action: 'update', id: Number(row.dataset.id),
      role: row.querySelector('.team-role').value, status: row.querySelector('.team-status').value,
    }});
    button.disabled = false;
    if (result.ok) { button.textContent = 'Saved'; setTimeout(() => { button.textContent = 'Save'; }, 1400); }
    else { button.textContent = result.error || 'Try again'; setTimeout(() => { button.textContent = 'Save'; }, 1800); }
  });

  document.getElementById('cancelResetPassword').addEventListener('click', () => resetDialog.close());
  resetForm.addEventListener('submit', async event => {
    event.preventDefault();
    const button = resetForm.querySelector('[type="submit"]');
    button.disabled = true; resetMsg.className = 'msg'; resetMsg.textContent = ''; showFieldErrors(resetForm);
    try {
      const result = await api('restaurant/staff.php', { method: 'POST', body: { action: 'reset_password', id: resetStaffId, password: resetForm.elements.password.value } });
      if (!result.ok) { resetMsg.className = 'msg error'; resetMsg.textContent = result.error || 'Could not set the password.'; showFieldErrors(resetForm, result.fields || {}); button.disabled = false; return; }
      resetMsg.className = 'msg ok'; resetMsg.textContent = result.message || 'Password updated.';
      setTimeout(() => { resetDialog.close(); resetForm.reset(); button.disabled = false; load(); }, 1100);
    } catch {
      resetMsg.className = 'msg error'; resetMsg.textContent = 'Could not reach the server. Try again.'; button.disabled = false;
    }
  });
  document.getElementById('refreshTeam').addEventListener('click', load);
  load();
})();
