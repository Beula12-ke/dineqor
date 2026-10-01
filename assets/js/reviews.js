(() => {
  document.querySelectorAll('.customer-review-form').forEach(form => {
    form.addEventListener('submit', async event => {
      event.preventDefault();
      showFieldErrors(form);
      const message = form.querySelector('[data-review-message]');
      const button = form.querySelector('[type="submit"]');
      const values = formData(form);
      button.disabled = true; message.textContent = '';
      const result = await api('customer/reviews.php', { method: 'POST', body: {
        order_id: Number(form.dataset.orderId), rating: Number(values.rating), title: values.title, comment: values.comment
      }});
      button.disabled = false;
      if (!result.ok) {
        message.className = 'msg error'; message.textContent = result.error || 'Could not save your review.';
        showFieldErrors(form, result.fields || {}); return;
      }
      const summary = document.createElement('div');
      summary.className = 'customer-review-readonly';
      const stars = document.createElement('div');
      stars.className = 'customer-review-rating';
      stars.setAttribute('aria-label', values.rating + ' out of 5 stars');
      stars.append(document.createTextNode('★'.repeat(Number(values.rating)) + '☆'.repeat(5 - Number(values.rating))));
      const score = document.createElement('span');
      score.textContent = values.rating + ' / 5';
      stars.append(score);
      summary.append(stars);
      if (values.title.trim()) {
        const title = document.createElement('h3');
        title.textContent = values.title.trim();
        summary.append(title);
      }
      if (values.comment.trim()) {
        const comment = document.createElement('p');
        comment.textContent = values.comment.trim();
        summary.append(comment);
      }
      const submitted = document.createElement('small');
      submitted.textContent = 'Your review has been submitted.';
      summary.append(submitted);
      form.replaceWith(summary);
    });
  });
})();
