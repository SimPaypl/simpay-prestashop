$(function () {
    const SELECTOR_METHOD = 'input[name="simpay_method_choice"]';
    const SELECTOR_TERMS  = 'input[name="conditions_to_approve[terms-and-conditions]"]';
    const SELECTOR_BTN    = '#payment-confirmation button';
    const SELECTOR_PAYOPT = 'input[name="payment-option"]';

    // ── Commission notice ──────────────────────────────────────────────
    const $notice = $('#simpay-commission-notice');
    let commissionMap = {};
    let cartTotal = 0;
    let msgGeneric = '';
    let msgFee = '';

    if ($notice.length) {
        try { commissionMap = JSON.parse($notice.attr('data-commission-map') || '{}'); } catch(e) {}
        cartTotal = parseFloat($notice.attr('data-cart-total') || '0');
        msgGeneric = $notice.attr('data-msg-generic') || '';
        msgFee = $notice.attr('data-msg-fee') || '';

        // Move notice to bottom of payment section (after all payment options)
        var $paymentOptions = $('#payment-option-forms, .payment-options, #checkout-payment-step .content');
        if ($paymentOptions.length) {
            $paymentOptions.last().after($notice);
        }
    }

    function formatAmount(amount) {
        return amount.toFixed(2).replace('.', ',');
    }

    function updateCommissionNotice() {
        if (!$notice.length) return;

        var selectedMethod = null;
        var isSimpayMain = false;

        // Check if a SimPay separate method is selected (payment-option radio for simpay method)
        var payOptId = getSelectedPaymentOptionId();
        if (payOptId) {
            // Check if this is a separate SimPay method (has data-module-name="simpay" or similar)
            var $option = $('#' + payOptId);
            var $form = $('#pay-with-' + payOptId + '-form, #' + payOptId + '-additional-information');

            // Separate method: look for hidden input with method value
            var $methodInput = $form.find('input[name="method"]');
            if ($methodInput.length) {
                selectedMethod = $methodInput.val();
            }

            // Fallback: check simpay_method_choice hidden input (e.g. BLIK widget)
            if (!selectedMethod) {
                var $choiceInput = $form.find('input[name="simpay_method_choice"][type="hidden"]');
                if ($choiceInput.length) {
                    selectedMethod = $choiceInput.val();
                }
            }

            // Check URL for method param in the main payment form (not additional info)
            if (!selectedMethod) {
                var $mainForm = $('#pay-with-' + payOptId + '-form');
                var action = $mainForm.find('form').attr('action') || '';
                if (!action) {
                    action = $option.closest('.payment-option').find('form').attr('action') || '';
                }
                var match = action.match(/[?&]method=([^&]+)/);
                if (match) {
                    selectedMethod = decodeURIComponent(match[1]);
                }
            }

            // Check if it's the main SimPay gateway (has payment grid)
            if ($form.find('.simpay-payment-channels').length > 0 || $form.find('[data-simpay-form]').length > 0) {
                isSimpayMain = true;
                // Check if a method is selected inside the grid
                var $gridChoice = $form.find(SELECTOR_METHOD + ':checked');
                if ($gridChoice.length) {
                    selectedMethod = $gridChoice.val();
                } else {
                    selectedMethod = null;
                }
            }

            // Check if this payment option belongs to simpay module
            var isSimpay = $form.find('.simpay-wrapper, .simpay-blik-wrapper').length > 0
                || $form.find('[data-simpay-form]').length > 0
                || selectedMethod !== null
                || isSimpayMain;

            if (!isSimpay) {
                $notice.hide();
                return;
            }
        } else {
            $notice.hide();
            return;
        }

        // Show the notice
        var $text = $('#simpay-commission-text');

        if (selectedMethod && commissionMap[selectedMethod] && commissionMap[selectedMethod] > 0) {
            // Specific method selected → show exact fee
            var percent = commissionMap[selectedMethod];
            var amount = Math.round(cartTotal * percent) / 100;
            $text.html(msgFee + ': <strong>' + formatAmount(amount) + ' PLN</strong> <small>(' + percent.toFixed(2).replace('.', ',') + '%)</small>');
            $notice.show();
        } else if (isSimpayMain && !selectedMethod) {
            // Main gateway, no method selected → generic message
            $text.html(msgGeneric);
            $notice.show();
        } else if (selectedMethod && (!commissionMap[selectedMethod] || commissionMap[selectedMethod] === 0)) {
            // Method with 0% commission
            $notice.hide();
        } else {
            // Separate method without specific commission data → generic
            $text.html(msgGeneric);
            $notice.show();
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────────
    function getSubmitBtn() {
        return $(SELECTOR_BTN).first();
    }

    function getTermsCheckbox() {
        return $(SELECTOR_TERMS).first();
    }

    function getSelectedPaymentOptionId() {
        const $checked = $(SELECTOR_PAYOPT + ':checked').first();
        return $checked.length ? $checked.attr('id') : null;
    }

    function isSimpaySelectedAsMainPayment() {
        // The container id depends on PS theme, but this is the standard pattern:
        // #pay-with-payment-option-X-form exists for selected option.
        const id = getSelectedPaymentOptionId();
        if (!id) return false;

        const $form = $('#pay-with-' + id + '-form');
        // If our SimPay payment channels exists inside the selected payment form, treat as active.
        return $form.find('.simpay-payment-channels').length > 0;
    }

    function simpayGridVisible() {
        // Visible grid inside currently active payment form
        const id = getSelectedPaymentOptionId();
        if (!id) return false;
        const $form = $('#pay-with-' + id + '-form');
        return $form.find('.simpay-wrapper:visible').length > 0;
    }

    function hasChoice() {
        const id = getSelectedPaymentOptionId();
        if (!id) return false;
        const $form = $('#pay-with-' + id + '-form');
        return $form.find(SELECTOR_METHOD + ':checked').length > 0;
    }

    function updateActive($input) {
        const $grid = $input.closest('.simpay-payment-channels');
        if (!$grid.length) return;

        $grid.find('.simpay-payment-channels__item')
            .removeClass('simpay-payment-channels__item--active');

        $input.closest('.simpay-payment-channels__item')
            .addClass('simpay-payment-channels__item--active');
    }

    function toggleSubmit(canSubmit) {
        const $btn = getSubmitBtn();
        if (!$btn.length) return;

        // Do not fight PrestaShop if it already disabled the button for other reasons.
        // We only disable when SimPay needs a choice and it's missing.
        if (canSubmit) {
            // Enable only if PS hasn't disabled it for something else.
            // If you want to force enable, remove the guard below.
            $btn.prop('disabled', false).removeClass('disabled');
        } else {
            $btn.prop('disabled', true).addClass('disabled');
        }
    }

    function showError(show) {
        const id = getSelectedPaymentOptionId();
        if (!id) return;
        const $form = $('#pay-with-' + id + '-form');
        const $err = $form.find('.simpay-error');
        if ($err.length) $err.toggle(!!show);
    }

    function validateSimpayBeforeSubmit() {
        // If SimPay isn't selected as the main payment, do nothing.
        if (!isSimpaySelectedAsMainPayment()) return;

        const termsOk = (function () {
            const $terms = getTermsCheckbox();
            // If shop doesn't require terms (custom setups), treat as ok
            if (!$terms.length) return true;
            return $terms.is(':checked');
        })();

        // If grid isn't visible (e.g. showPaymentMethods = false), we do not require choice
        const choiceRequired = simpayGridVisible();
        const choiceOk = !choiceRequired || hasChoice();

        const ok = termsOk && choiceOk;

        showError(choiceRequired && !choiceOk);
        toggleSubmit(ok);
    }

    // --- Bindings ---

    // 1) Change on SimPay radio -> update active + validate + commission
    $(document).on('change', SELECTOR_METHOD, function () {
        updateActive($(this));
        validateSimpayBeforeSubmit();
        updateCommissionNotice();
    });

    // 2) Change on terms checkbox -> validate (but only affects button if SimPay active)
    $(document).on('change', SELECTOR_TERMS, function () {
        validateSimpayBeforeSubmit();
    });

    // 3) Change main payment method -> validate + commission (important!)
    $(document).on('change', SELECTOR_PAYOPT, function () {
        // When switching to SimPay, set active style for prechecked tile
        const id = getSelectedPaymentOptionId();
        if (id) {
            const $form = $('#pay-with-' + id + '-form');
            const $checked = $form.find(SELECTOR_METHOD + ':checked').first();
            if ($checked.length) updateActive($checked);
        }
        validateSimpayBeforeSubmit();
        updateCommissionNotice();
    });

    // Initial sync on load
    (function init() {
        const id = getSelectedPaymentOptionId();
        if (id) {
            const $form = $('#pay-with-' + id + '-form');
            const $checked = $form.find(SELECTOR_METHOD + ':checked').first();
            if ($checked.length) updateActive($checked);
        }
        validateSimpayBeforeSubmit();
        updateCommissionNotice();
    })();
});
