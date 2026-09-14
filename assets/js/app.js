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

  if (productSearch && productCards.length) {
    productSearch.addEventListener('input', () => {
      const term = productSearch.value.trim().toLowerCase();
      let visible = 0;

      productCards.forEach((card) => {
        const show = card.dataset.name?.includes(term);
        card.hidden = !show;
        if (show) visible += 1;
      });

      if (resultCount) resultCount.textContent = String(visible);
      if (emptyCatalog) emptyCatalog.hidden = visible > 0;
    });
  }

  document.querySelectorAll('[data-demo-login]').forEach((button) => {
    button.addEventListener('click', () => {
      const form = button.closest('.auth-form-wrap')?.querySelector('form');
      if (!form) return;
      const email = form.querySelector('[name="email"]');
      const password = form.querySelector('[name="password"]');
      if (email && password) {
        email.value = button.dataset.email || '';
        password.value = button.dataset.password || '';
        email.focus();
      }
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
