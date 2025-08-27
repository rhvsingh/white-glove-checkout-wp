;(function () {
    if (typeof window === "undefined" || typeof wc === "undefined" || !wc.wcBlocksRegistry) {
        return
    }

    const data = window.WGC_DATA || {}
    if (!data.active) return

    const { registerPaymentMethod } = wc.wcBlocksRegistry
    const { createElement, useState, useEffect, useMemo } = wp.element
    const { __ } = wp.i18n

    const Label = ({ components }) => {
        return createElement(components.PaymentMethodLabel, { text: data.title })
    }

    const Content = ({ eventRegistration, emitResponse }) => {
        const { onPaymentSetup, onCheckoutValidation } = eventRegistration
        const [serviceDetails, setServiceDetails] = useState("")

        // Memoize description paragraphs
        const descriptionParagraphs = useMemo(() => {
            return data.description
                .split("\n")
                .filter((line) => line.trim())
                .map((line, index) =>
                    createElement(
                        "p",
                        {
                            key: index,
                            className: "wgc-description-line",
                        },
                        line.trim()
                    )
                )
        }, [data.description])

        // Payment setup validation
        useEffect(() => {
            if (!onPaymentSetup) return

            const unsubscribe = onPaymentSetup(() => {
                if (!serviceDetails.trim()) {
                    return {
                        type: emitResponse.responseTypes.ERROR,
                        message: (window.WGC_DATA && window.WGC_DATA.i18n && window.WGC_DATA.i18n.validation_details_req) || __("Please provide White Glove Service Details.", "white-glove-checkout"),
                        messageContext: "wc/checkout/payments",
                    }
                }
                return {
                    type: emitResponse.responseTypes.SUCCESS,
                    meta: {
                        paymentMethodData: {
                            [(window.WGC_DATA && window.WGC_DATA.fields && window.WGC_DATA.fields.details) || 'wgc_details']: serviceDetails.trim(),
                            [(window.WGC_DATA && window.WGC_DATA.fields && window.WGC_DATA.fields.detailsAlt) || 'wgc-blocks-details']: serviceDetails.trim(), // fallback key
                        },
                    },
                }
            })
            return unsubscribe
        }, [onPaymentSetup, serviceDetails, emitResponse.responseTypes])

        // Shipping visibility toggle - optimized
        useEffect(() => {
            let currentState = null
            let debounceTimer = null

            // Cache DOM selectors
            const selectors = Object.assign({
                paymentMethods: 'input[name="radio-control-wc-payment-method-options"]',
                shippingMethods: ".wc-block-components-shipping-methods",
                shippingTotal: ".wc-block-components-totals-shipping",
                shippingHeading: ".wc-block-components-shipping-rates-control__package:not(.wc-block-components-panel)",
            }, (window.WGC_DATA && window.WGC_DATA.selectors) || {})

            const toggleShipping = (isWhiteGlove) => {
                if (isWhiteGlove === currentState) return
                currentState = isWhiteGlove

                const elements = {
                    methods: document.querySelector(selectors.shippingMethods),
                    total: document.querySelector(selectors.shippingTotal),
                    heading: document.querySelector(selectors.shippingHeading),
                }

                if (isWhiteGlove) {
                    // Hide shipping elements
                    Object.values(elements).forEach((el) => {
                        if (el) el.style.display = "none"
                    })

                    // Add custom message if not already present
                    if (elements.total && !elements.total.parentNode.querySelector(".wgc-shipping-message")) {
                        const message = document.createElement("div")
                        message.className = "wgc-shipping-message"
                        message.textContent = __(
                            "White Glove delivery will be finalized after your order.",
                            "white-glove-checkout"
                        )
                        elements.total.parentNode.insertBefore(message, elements.total.nextSibling)
                    }
                } else {
                    // Restore shipping elements
                    Object.values(elements).forEach((el) => {
                        if (el) el.style.display = ""
                    })
                    // Remove custom messages
                    document.querySelectorAll(".wgc-shipping-message").forEach((el) => el.remove())
                }
            }

            const handlePaymentChange = () => {
                const selectedMethod = document.querySelector(`${selectors.paymentMethods}:checked`)
                const id = (window.WGC_DATA && window.WGC_DATA.id) || 'wgc'
                toggleShipping(selectedMethod?.value === id)
            }

            const debouncedHandler = () => {
                clearTimeout(debounceTimer)
                debounceTimer = setTimeout(handlePaymentChange, 150)
            }

            const attachListeners = () => {
                document.querySelectorAll(selectors.paymentMethods).forEach((input) => {
                    input.removeEventListener("change", handlePaymentChange)
                    input.addEventListener("change", handlePaymentChange)
                })
            }

            // Initial setup
            attachListeners()
            handlePaymentChange()

            // Optimized observer - only watches for payment method changes
            const observer = new MutationObserver((mutations) => {
                const hasPaymentMethodChange = mutations.some((mutation) =>
                    Array.from(mutation.addedNodes).some(
                        (node) =>
                            node.nodeType === 1 &&
                            (node.matches?.(selectors.paymentMethods) || node.querySelector?.(selectors.paymentMethods))
                    )
                )

                if (hasPaymentMethodChange) {
                    attachListeners()
                    debouncedHandler()
                }
            })

            const checkoutContainer = document.querySelector((window.WGC_DATA && window.WGC_DATA.selectors && window.WGC_DATA.selectors.checkoutContainer) || ".wc-block-checkout") || document.body
            observer.observe(checkoutContainer, { childList: true, subtree: true })

            // Cleanup
            return () => {
                document.querySelectorAll(selectors.paymentMethods).forEach((input) => {
                    input.removeEventListener("change", handlePaymentChange)
                })
                observer.disconnect()
                clearTimeout(debounceTimer)

                // Reset UI state
                const elements = {
                    methods: document.querySelector(selectors.shippingMethods),
                    total: document.querySelector(selectors.shippingTotal),
                    heading: document.querySelector(selectors.shippingHeading),
                }

                Object.values(elements).forEach((el) => {
                    if (el) el.style.display = ""
                })
                document.querySelectorAll(".wgc-shipping-message").forEach((el) => el.remove())
            }
        }, [])

        // Checkout validation
        useEffect(() => {
            if (!onCheckoutValidation) return

            const unsubscribe = onCheckoutValidation(() => ({
                type: emitResponse.responseTypes.SUCCESS,
                meta: {
                    paymentMethodData: {
                        wgc_details: serviceDetails,
                    },
                },
            }))
            return unsubscribe
        }, [serviceDetails, onCheckoutValidation, emitResponse])

        return createElement(
            "div",
            { className: "wgc-payment-content", style: { margin: "15px 0" } },

            // Description
            createElement(
                "div",
                {
                    className: "wgc-description",
                    style: {
                        backgroundColor: "#f9f9f9",
                        border: "1px solid #ddd",
                        borderRadius: "4px",
                        padding: "12px",
                        marginBottom: "15px",
                        fontSize: "14px",
                        lineHeight: "1.5",
                    },
                },
                descriptionParagraphs
            ),

            // Service details input
            createElement(
                "div",
                {
                    className: "wgc-service-details",
                    style: { marginBottom: "10px" },
                },
                [
                    createElement(
                        "label",
                        {
                            key: "label",
                            htmlFor: "wgc-service-details",
                            className: "wgc-details-label",
                            style: {
                                display: "block",
                                marginBottom: "5px",
                                fontWeight: "bold",
                                fontSize: "14px",
                            },
                        },
                        [
                            ((window.WGC_DATA && window.WGC_DATA.i18n && window.WGC_DATA.i18n.details_label) || __("White Glove Service Details", "white-glove-checkout")) + " ",
                            createElement("span", { key: "required", style: { color: "red" } }, "*"),
                        ]
                    ),

                    createElement("textarea", {
                        key: "textarea",
                        id: `${((window.WGC_DATA && window.WGC_DATA.fields && window.WGC_DATA.fields.details) || 'wgc_details')}_blocks`,
                        className: "wgc-details-textarea",
                        name: ((window.WGC_DATA && window.WGC_DATA.fields && window.WGC_DATA.fields.details) || 'wgc_details'),
                        rows: 4,
                        required: true,
                        placeholder: ((window.WGC_DATA && window.WGC_DATA.i18n && window.WGC_DATA.i18n.details_placeholder) || __(
                            "Kindly share any details that will help us deliver a seamless experience.",
                            "white-glove-checkout"
                        )),
                        value: serviceDetails,
                        onChange: (e) => setServiceDetails(e.target.value),
                    }),
                ]
            )
        )
    }

    // Register payment method
    registerPaymentMethod({
        name: "wgc",
        label: createElement(Label),
        content: createElement(Content),
        edit: createElement(Content),
        canMakePayment: () => true,
        ariaLabel: __("White Glove (No Payment)", "white-glove-checkout"),
        supports: {
            features: ["products"],
        },
    })
})()
