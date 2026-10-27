(() => {
  const menuToggle = document.querySelector('[data-menu-toggle]');
  const mainNav = document.querySelector('[data-main-nav]');

  if (menuToggle && mainNav) {
    menuToggle.addEventListener('click', () => {
      const open = mainNav.classList.toggle('open');
      menuToggle.setAttribute('aria-expanded', String(open));
    });
  }

  document.querySelectorAll('[data-dismiss-flash]').forEach((button) => {
    button.addEventListener('click', () => {
      const flash = button.closest('.flash');
      if (flash) {
        flash.style.opacity = '0';
        flash.style.transform = 'translateY(-8px)';
        window.setTimeout(() => flash.remove(), 180);
      }
    });
  });

  document.querySelectorAll('[data-quantity-down], [data-quantity-up]').forEach((button) => {
    button.addEventListener('click', () => {
      const control = button.closest('.quantity-control');
      const input = control?.querySelector('[data-quantity-input]');
      if (!input) return;

      const min = Number(input.min || 1);
      const max = Number(input.max || 999);
      const next = Number(input.value || min) + (button.matches('[data-quantity-up]') ? 1 : -1);
      input.value = String(Math.max(min, Math.min(max, next)));
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
  });

  document.querySelectorAll('[data-quantity-input]').forEach((input) => {
    input.addEventListener('change', () => {
      const min = Number(input.min || 1);
      const max = Number(input.max || 999);
      input.value = String(Math.max(min, Math.min(max, Number(input.value || min))));
    });
  });

  const productSearch = document.querySelector('[data-product-search]');
  const productCards = [...document.querySelectorAll('[data-product-card]')];
  const resultCount = document.querySelector('[data-result-count]');
  const emptyCatalog = document.querySelector('[data-empty-catalog]');

  if (productSearch) {
    const filterProducts = () => {
      const term = productSearch.value.trim().toLowerCase();
      let visible = 0;

      productCards.forEach((card) => {
        const show = term.split(/\s+/).every(word => card.dataset.name?.includes(word));
        card.hidden = !show;
        if (show) visible += 1;
      });

      document.querySelectorAll('[data-filter-category]').forEach((link) => {
        const target = new URL(link.href);
        if (term) target.searchParams.set('q', productSearch.value.trim());
        else target.searchParams.delete('q');
        link.href = target.href;
      });
      if (resultCount) resultCount.textContent = String(visible);
      if (emptyCatalog) emptyCatalog.hidden = visible > 0;
    };
    productSearch.addEventListener('input', filterProducts);
    filterProducts();
  }

  const summary = document.querySelector('[data-checkout-summary]');
  const coupon = document.querySelector('[data-coupon]');
  if (summary && coupon) {
    const formatMoney = (value) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(value);
    const roundMoney = (value) => Math.round((value + Number.EPSILON) * 100) / 100;
    coupon.addEventListener('input', () => {
      const code = coupon.value.trim().toUpperCase();
      const valid = code === '' || code === 'GALAXY10';
      coupon.setCustomValidity(valid ? '' : 'Use GALAXY10 or leave the offer code empty.');
      const subtotal = Number(summary.dataset.subtotal);
      const discount = code === 'GALAXY10' ? roundMoney(subtotal * 0.1) : 0;
      const taxable = subtotal - discount;
      const tax = roundMoney(taxable * Number(summary.dataset.taxRate) / 100);
      const shipping = taxable >= Number(summary.dataset.freeShipping) ? 0 : Number(summary.dataset.shippingFee);
      summary.querySelector('[data-summary-shipping]').textContent = shipping === 0 ? 'Free' : formatMoney(shipping);
      summary.querySelector('[data-summary-tax]').textContent = formatMoney(tax);
      summary.querySelector('[data-summary-total]').textContent = formatMoney(roundMoney(taxable + tax + shipping));
      summary.querySelector('[data-discount-row]').hidden = discount === 0;
      summary.querySelector('[data-summary-discount]').textContent = '−' + formatMoney(discount);
    });
  }

  document.querySelectorAll('[data-order-action-form]').forEach((form) => {
    const select = form.querySelector('[data-order-status]');
    const label = form.querySelector('[data-cancel-reason]');
    const reason = label?.querySelector('textarea');
    if (!select || !label || !reason) return;
    const validate = () => reason.setCustomValidity(select.value === 'cancelled' && !reason.value.trim() ? 'Please enter a reason for cancellation.' : '');
    const sync = () => {
      const cancelling = select.value === 'cancelled';
      label.hidden = !cancelling;
      reason.required = cancelling;
      reason.disabled = !cancelling;
      validate();
    };
    select.addEventListener('change', () => { sync(); if (!label.hidden) reason.focus(); });
    reason.addEventListener('input', validate);
    sync();
  });

  document.querySelectorAll('[data-reason-form]').forEach((form) => {
    const reason = form.querySelector('[name=reason]');
    const validate = () => reason.setCustomValidity(reason.value.trim().length < 5 ? 'Please explain the reason in at least 5 characters.' : '');
    reason.addEventListener('input', validate);
    validate();
  });

  const brandingForm = document.querySelector('[data-branding-form]');
  const brandingPreview = document.querySelector('[data-brand-preview]');
  if (brandingForm && brandingPreview) {
    brandingForm.querySelector('[data-brand-name]')?.addEventListener('input', (event) => {
      brandingPreview.querySelector('[data-brand-name-preview]').textContent = event.target.value;
      brandingPreview.querySelector('.brand-text').textContent = event.target.value;
    });
    brandingForm.querySelectorAll('[data-brand-color]').forEach((input) => {
      input.addEventListener('input', () => brandingPreview.style.setProperty(input.dataset.brandColor, input.value));
    });
  }

  document.querySelectorAll('[data-photo-input]').forEach((input) => {
    const preview = input.closest('.photo-controls')?.querySelector('[data-photo-preview]');
    let previewUrl;
    const reset = input.closest('form')?.querySelector('[name="default_photo"]');
    reset?.addEventListener('change', () => {
      if (!reset.checked) return;
      input.value = '';
      input.setCustomValidity('');
      if (previewUrl) URL.revokeObjectURL(previewUrl);
      if (preview) { preview.hidden = true; preview.removeAttribute('src'); }
    });
    input.addEventListener('change', () => {
      if (previewUrl) URL.revokeObjectURL(previewUrl);
      if (preview) { preview.hidden = true; preview.removeAttribute('src'); }
      const file = input.files?.[0];
      const valid = !file || (['image/jpeg', 'image/png', 'image/webp'].includes(file.type) && file.size <= 2 * 1024 * 1024);
      input.setCustomValidity(valid ? '' : 'Choose a JPG, PNG, or WebP photo up to 2 MB.');
      if (file && valid && preview) {
        previewUrl = URL.createObjectURL(file);
        preview.src = previewUrl;
        preview.hidden = false;
        // Selecting a new photo takes precedence over returning to the default.
        const reset = input.closest('form')?.querySelector('[name="default_photo"]');
        if (reset) reset.checked = false;
      }
      input.reportValidity();
    });
  });

  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', () => {
      const button = form.querySelector('button[type="submit"].button-primary');
      if (button && !button.dataset.submitting) {
        button.dataset.submitting = 'true';
        button.classList.add('is-submitting');
        window.setTimeout(() => button.classList.remove('is-submitting'), 1500);
      }
    });
  });
})();

(() => {
  const input = document.querySelector('#header-search');
  const suggestions = document.querySelector('#search-suggestions');
  let timer, request;
  input?.addEventListener('input', () => {
    clearTimeout(timer); request?.abort(); suggestions.replaceChildren(); suggestions.hidden = true;
    const query = input.value.trim();
    if (!query) return;
    timer = setTimeout(async () => {
      request = new AbortController();
      try {
        const response = await fetch('/search?q=' + encodeURIComponent(query), { signal: request.signal });
        const data = await response.json();
        if (input.value.trim() !== query) return;
        for (const item of data.items) {
          const li = document.createElement('li'), link = document.createElement('a');
          link.href = item.url; link.textContent = `${item.name} · ${item.price}`; li.append(link); suggestions.append(li);
        }
        if (!data.items.length) { const li = document.createElement('li'); li.textContent = 'No matching gear'; suggestions.append(li); }
        suggestions.hidden = false;
      } catch (_) { suggestions.hidden = true; }
    }, 180);
  });
  input?.addEventListener('keydown', event => { if (event.key === 'Escape') suggestions.hidden = true; if (event.key === 'ArrowDown') { event.preventDefault(); suggestions.querySelector('a')?.focus(); } });
  document.addEventListener('click', event => { if (!event.target.closest('.header-search')) suggestions.hidden = true; });
  const drawer = document.querySelector('#cart-drawer');
  document.querySelector('[data-close-cart]')?.addEventListener('click', () => drawer.close());
  function displayCart(data) {
    const content = drawer.querySelector('[data-cart-content]'); content.replaceChildren();
    for (const item of data.items) { const line = document.createElement('p'); line.textContent = `${item.name} × ${item.quantity}`; content.append(line); }
    const total = document.createElement('strong'); total.textContent = `Subtotal: ${data.subtotal}`; content.append(total);
    document.querySelectorAll('.cart-count').forEach(el => { el.textContent = data.count; });
    if (!drawer.open) drawer.showModal();
  }
  document.querySelector('.cart-link')?.addEventListener('click', async event => {
    if (!drawer.showModal) return;
    event.preventDefault();
    try { const response = await fetch('/cart-data'); if (!response.ok) throw Error(); displayCart(await response.json()); }
    catch (_) { window.location.href = '/cart'; }
  });
  document.querySelectorAll('form').forEach(form => {
    const action = form.querySelector('[name="action"]')?.value;
    if (!['add_to_cart', 'wishlist'].includes(action) || !drawer.showModal) return;
    form.addEventListener('submit', async event => {
      event.preventDefault(); const button = form.querySelector('button'); button.disabled = true;
      try {
        const response = await fetch(form.getAttribute('action') || window.location.href, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
        if (!response.ok || response.redirected) throw Error('Please refresh and sign in again.');
        const data = await response.json();
        if (action === 'wishlist') { button.textContent = data.saved ? 'Saved to wishlist' : 'Add to wishlist'; button.setAttribute('aria-pressed', String(data.saved)); }
        else displayCart(data);
      } catch (error) { alert(error.message || 'Could not save. Please try again.'); }
      finally { button.disabled = false; }
    });
  });
})();
