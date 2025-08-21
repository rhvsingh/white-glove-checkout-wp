// ANCHOR: White Glove Checkout Frontend Script

(function(){
  // SECTION: Hidden input sync for Blocks details
  document.addEventListener('change', function (e) {
    if (e.target && e.target.id === 'wgc_details_blocks') {
      var existing = document.querySelector('input[name="wgc-blocks-details"]');
      if (existing) existing.remove();

      var hiddenInput = document.createElement('input');
      hiddenInput.type = 'hidden';
      hiddenInput.name = 'wgc-blocks-details';
      hiddenInput.value = e.target.value;
      var form = e.target.closest('form');
      if (form) form.appendChild(hiddenInput);
    }
  });

  // SECTION: Payment method change triggers
  if (typeof jQuery !== 'undefined') {
    jQuery(function ($) {
      // Clear persisted selection if it was WGC
      if (sessionStorage.getItem('wc_checkout_payment_method') === 'wgc') {
        sessionStorage.removeItem('wc_checkout_payment_method');
      }
      if (localStorage.getItem('wc_checkout_payment_method') === 'wgc') {
        localStorage.removeItem('wc_checkout_payment_method');
      }

      $(document).on('change', 'input[name="radio-control-wc-payment-method-options"]', function () {
        // Trigger checkout + shipping refresh
        $('body').trigger('update_checkout', { update_shipping_method: true });
        setTimeout(function () { $('body').trigger('wc_update_cart'); }, 200);
      });
    });
  }
})();
