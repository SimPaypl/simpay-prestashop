$(function () {
    const SELECTOR_METHOD = 'input[name="simpay_method_choice"]';
    const SELECTOR_TERMS  = 'input[name="conditions_to_approve[terms-and-conditions]"]';
    const SELECTOR_BTN    = '#payment-confirmation button';
    const SELECTOR_PAYOPT = 'input[name="payment-option"]';

    // Helpers
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

    // 1) Change on SimPay radio -> update active + validate
    $(document).on('change', SELECTOR_METHOD, function () {
        updateActive($(this));
        validateSimpayBeforeSubmit();
    });

    // 2) Change on terms checkbox -> validate (but only affects button if SimPay active)
    $(document).on('change', SELECTOR_TERMS, function () {
        validateSimpayBeforeSubmit();
    });

    // 3) Change main payment method -> validate (important!)
    $(document).on('change', SELECTOR_PAYOPT, function () {
        // When switching to SimPay, set active style for prechecked tile
        const id = getSelectedPaymentOptionId();
        if (id) {
            const $form = $('#pay-with-' + id + '-form');
            const $checked = $form.find(SELECTOR_METHOD + ':checked').first();
            if ($checked.length) updateActive($checked);
        }
        validateSimpayBeforeSubmit();
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
    })();
});
