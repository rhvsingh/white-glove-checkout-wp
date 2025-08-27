// ANCHOR: White Glove Checkout Frontend Script

(function(){
  // SECTION: Hidden input sync for Blocks details
  document.addEventListener('change', function (e) {
    var data = window.WGC_FRONTEND || {}
    var detailsName = (data.fields && data.fields.details) || 'wgc_details'
    var altName = (data.fields && data.fields.detailsAlt) || 'wgc-blocks-details'
    if (e.target && e.target.id === detailsName + '_blocks') {
      var existing = document.querySelector('input[name="' + altName + '"]');
      if (existing) existing.remove();

      var hiddenInput = document.createElement('input');
      hiddenInput.type = 'hidden';
      hiddenInput.name = altName;
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

  $(document).on('change', (window.WGC_FRONTEND && window.WGC_FRONTEND.selectors && window.WGC_FRONTEND.selectors.paymentMethodRadios) || 'input[name="radio-control-wc-payment-method-options"]', function () {
        // Trigger checkout + shipping refresh
        $('body').trigger('update_checkout', { update_shipping_method: true });
        setTimeout(function () { $('body').trigger('wc_update_cart'); }, 200);
      });
    });
  }
})();
