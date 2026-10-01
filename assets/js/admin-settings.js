(() => {
  const platformForm = document.getElementById('platformSettingsForm');
  const accountForm = document.getElementById('adminAccountForm');
  if (!platformForm || !accountForm) return;

  const fill = (form, values) => Object.entries(values).forEach(([key, value]) => {
    const field = form.elements.namedItem(key);
    if (field) field.value = value ?? '';
  });
  const save = async (form, action, messageId) => {
    showFieldErrors(form);
    const msg = document.getElementById(messageId);
    msg.textContent = 'Saving…';
    const response = await api('admin/settings.php', { method: 'POST', body: { action, ...formData(form) } });
    if (!response.ok) {
      showFieldErrors(form, response.fields || {});
      msg.textContent = response.error || 'Could not save settings.';
      return;
    }
    msg.textContent = response.message || 'Saved.';
    if (action === 'save_account') {
      form.elements.current_password.value = '';
      form.elements.new_password.value = '';
      form.elements.confirm_password.value = '';
    }
    toast(response.message || 'Saved.');
  };

  platformForm.addEventListener('submit', event => {
    event.preventDefault();
    save(platformForm, 'save_platform', 'platformSettingsMessage');
  });
  accountForm.addEventListener('submit', event => {
    event.preventDefault();
    save(accountForm, 'save_account', 'adminAccountMessage');
  });

  api('admin/settings.php').then(response => {
    if (!response.ok) {
      document.getElementById('platformSettingsMessage').textContent = response.error || 'Could not load platform settings.';
      return;
    }
    fill(platformForm, response.settings || {});
  }).catch(() => {
    document.getElementById('platformSettingsMessage').textContent = 'Could not connect to the server.';
  });
})();
