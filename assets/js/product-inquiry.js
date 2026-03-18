(function () {
  'use strict';

  const modal = document.getElementById('gc-crm-product-modal');
  if (!modal) return;

  const openModal = () => modal.setAttribute('aria-hidden', 'false');
  const closeModal = () => modal.setAttribute('aria-hidden', 'true');

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

          Object.entries(map).forEach(([name, value]) => {
            modal.querySelectorAll(`[name="${name}"]`).forEach((input) => {
              input.value = value;
            });
          });
        } catch (e) {
          // noop
        }
      }

      openModal();
    });
  });

  modal.querySelectorAll('[data-gc-close]').forEach((el) => el.addEventListener('click', closeModal));
})();
