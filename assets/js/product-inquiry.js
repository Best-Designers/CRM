(function () {
  'use strict';

  const modal = document.getElementById('gc-crm-product-modal');
  if (!modal) return;

  const openModal = () => modal.setAttribute('aria-hidden', 'false');
  const closeModal = () => modal.setAttribute('aria-hidden', 'true');

  const setHiddenValue = (name, value) => {
    let found = false;
    modal.querySelectorAll(`[name="${name}"]`).forEach((input) => {
      input.value = value;
      found = true;
    });

    if (!found) {
      const form = modal.querySelector('form');
      if (!form) return;
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = value;
      form.appendChild(input);
    }
  };

  document.querySelectorAll('.gc-crm-inquire-button').forEach((button) => {
    button.addEventListener('click', () => {
      const payload = button.getAttribute('data-gc-product');
      if (payload) {
        try {
          const product = JSON.parse(payload);
          const map = {
            gc_product_id: product.id,
            gc_product_name: product.name,
            gc_product_sku: product.sku,
            gc_product_url: product.url,
            gc_product_price: product.price,
            gc_source: 'woocommerce_product',
          };

          Object.entries(map).forEach(([name, value]) => setHiddenValue(name, value));
        } catch (e) {
          // noop
        }
      }

      openModal();
    });
  });

  modal.querySelectorAll('[data-gc-close]').forEach((el) => el.addEventListener('click', closeModal));
})();
