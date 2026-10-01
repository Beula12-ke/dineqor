(() => {
  const form = document.getElementById('profileForm');
  const button = form.querySelector('[type="submit"]');
  const message = document.getElementById('profileMsg');
  form.addEventListener('submit', async event => {
    event.preventDefault();
    showFieldErrors(form);
    message.textContent = '';
    const values = formData(form);
    if (values.new_password !== values.confirm_password) {
      showFieldErrors(form, { confirm_password: 'The new passwords do not match.' });
      return;
    }
    button.disabled = true;
    const result = await api('customer/profile.php', { method: 'POST', body: {
      full_name: values.full_name, email: values.email, phone: values.phone,
      default_delivery_address: values.default_delivery_address,
      current_password: values.current_password, new_password: values.new_password
    }});
    button.disabled = false;
    if (!result.ok) {
      message.className = 'msg error';
      message.textContent = result.error || 'We couldn’t save your details.';
      showFieldErrors(form, result.fields || {});
      return;
    }
    message.className = 'msg ok';
    message.textContent = result.message || 'Your details have been saved.';
    form.elements.current_password.value = '';
    form.elements.new_password.value = '';
    form.elements.confirm_password.value = '';
  });
})();
