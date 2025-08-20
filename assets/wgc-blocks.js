;(function () {
    if (typeof window === 'undefined' || typeof wc === 'undefined' || !wc.wcBlocksRegistry) {
        return;
    }
    var data = window.WGC_DATA || {};
    var isActive = !!data.active;
    
    if (!isActive) {
        return;
    }
    
    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
    const { createElement, useState, useEffect } = window.wp.element;
    const { __ } = window.wp.i18n;
    const { useSelect, useDispatch } = window.wp.data;

    const Label = (props) => {
        return createElement('span', { 
            className: 'wc-block-components-payment-method-label',
            style: { fontWeight: 'bold' }
        }, __('White Glove (No Payment)', 'white-glove-checkout'));
    };

    const Content = (props) => {
        const [details, setDetails] = useState('');
        const { setPaymentMethodData } = useDispatch('wc/store/payment');
        
        // Hide other payment methods when this is selected
        useEffect(() => {
            if (props.activePaymentMethod === 'wgc') {
                // Hide shipping section
                const shippingSection = document.querySelector('.wc-block-components-shipping-rates-control');
                if (shippingSection) {
                    shippingSection.style.display = 'none';
                }
                
                // Add "Calculated later" message for shipping
                let calculatedMsg = document.getElementById('wgc-shipping-message');
                if (!calculatedMsg) {
                    calculatedMsg = document.createElement('div');
                    calculatedMsg.id = 'wgc-shipping-message';
                    calculatedMsg.innerHTML = '<p><strong>Shipping:</strong> Calculated later</p>';
                    calculatedMsg.style.cssText = 'margin: 10px 0; padding: 10px; background: #f0f0f0; border-radius: 4px;';
                    
                    const checkoutForm = document.querySelector('.wc-block-checkout__main');
                    if (checkoutForm) {
                        checkoutForm.insertBefore(calculatedMsg, checkoutForm.firstChild);
                    }
                }
            } else {
                // Show shipping section when other methods selected
                const shippingSection = document.querySelector('.wc-block-components-shipping-rates-control');
                if (shippingSection) {
                    shippingSection.style.display = 'block';
                }
                
                // Remove calculated message
                const calculatedMsg = document.getElementById('wgc-shipping-message');
                if (calculatedMsg) {
                    calculatedMsg.remove();
                }
            }
        }, [props.activePaymentMethod]);
        
        // Update payment data when details change
        useEffect(() => {
            if (props.activePaymentMethod === 'wgc') {
                setPaymentMethodData({
                    wgc_details: details
                });
            }
        }, [details, props.activePaymentMethod, setPaymentMethodData]);
        
        return createElement('div', { 
            className: 'wgc-payment-content',
            style: { margin: '15px 0' }
        },
            createElement('p', { 
                style: { marginBottom: '15px', color: '#666' }
            }, __('Place order without payment. We will contact you to finalize service.', 'white-glove-checkout')),
            
            createElement('div', { 
                className: 'wgc-details-field',
                style: { marginBottom: '10px' }
            },
                createElement('label', { 
                    htmlFor: 'wgc_details_blocks',
                    className: 'wgc-details-label',
                    style: { 
                        display: 'block', 
                        marginBottom: '5px', 
                        fontWeight: 'bold',
                        fontSize: '14px'
                    }
                }, __('White Glove Service Details', 'white-glove-checkout') + ' ', 
                    createElement('span', { style: { color: 'red' } }, '*')
                ),
                createElement('textarea', {
                    id: 'wgc_details_blocks',
                    name: 'wgc_details',
                    rows: 4,
                    required: true,
                    placeholder: __('Please add any helpful details for our team.', 'white-glove-checkout'),
                    value: details,
                    onChange: (e) => setDetails(e.target.value),
                    className: 'wgc-details-textarea',
                    style: {
                        width: '100%',
                        padding: '8px',
                        border: '1px solid #ddd',
                        borderRadius: '4px',
                        fontSize: '14px',
                        fontFamily: 'inherit'
                    }
                })
            )
        );
    };

    registerPaymentMethod({
        name: 'wgc',
        label: createElement(Label),
        content: createElement(Content),
        edit: createElement(Content),
        canMakePayment: () => true,
        ariaLabel: __('White Glove (No Payment)', 'white-glove-checkout'),
        supports: {
            features: ['products']
        }
    });
})();
