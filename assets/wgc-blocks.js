;(function () {
    // Expect WGC_DATA from wp_localize_script
    if (typeof window === 'undefined' || typeof wc === 'undefined' || !wc.wcBlocksRegistry) {
        return;
    }
    var data = window.WGC_DATA || {};
    var isActive = !!data.active;
    // (removed temporary debug logs)
    // Frontend guard: do not register if disabled
    if (!isActive) {
        return;
    }
    // TEMP: diagnostic default label
    var label = data.label || 'White Glove (TEST)';
    wc.wcBlocksRegistry.registerPaymentMethod({
        // TEMP: diagnostic method key
        name: 'wgc-test',
        label: label,
        canMakePayment: function () { return true; },
        // Provide null directly per Blocks API requirements
        content: null,
        edit: null,
        ariaLabel: label,
        supports: { features: [] }
    });
    // (removed temporary debug logs)
})();
