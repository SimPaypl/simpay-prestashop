$(document).ready(() => {
    const $tabs = $('#simpay-auth-tabs [data-simpay-auth]');
    const $panels = $('[data-simpay-auth-panel]');
    const $input = $('input[name="form[auth_method]"]');

    if (!$tabs.length || !$input.length) {
        return;
    }

    const show = (method) => {
        $tabs.each(function () {
            $(this).toggleClass('active', $(this).data('simpay-auth') === method);
        });
        $panels.each(function () {
            $(this).toggleClass('d-none', $(this).data('simpay-auth-panel') !== method);
        });
        $input.val(method);
    };

    $tabs.on('click', function (e) {
        e.preventDefault();
        show($(this).data('simpay-auth'));
    });

    // Validation errors on credential fields must stay visible
    const $manual = $('[data-simpay-auth-panel="manual"]');
    const hasManualErrors = $manual.find('.is-invalid, .invalid-feedback, .form-control-feedback, .has-danger').length > 0;

    show(hasManualErrors ? 'manual' : ($input.val() === 'manual' ? 'manual' : 'oauth'));
});
