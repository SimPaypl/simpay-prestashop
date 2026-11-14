document.addEventListener('DOMContentLoaded', function () {
  var form = document.querySelector('[data-simpay-refund-form]');
  var block = document.getElementById('simpay_order_payments_block');

  var currency = (form && form.getAttribute('data-currency')) || 'PLN';
  var locale = ((form && form.getAttribute('data-locale')) || document.documentElement.lang || 'pl-PL').replace('_', '-');

  function formatMoney(value) {
    try {
      return new Intl.NumberFormat(locale, { style: 'currency', currency: currency }).format(value);
    } catch (e) {
      return value.toFixed(2) + ' ' + currency;
    }
  }

  // Static amounts rendered by Smarty -> locale-aware format
  if (block) {
    block.querySelectorAll('[data-simpay-money]').forEach(function (el) {
      var value = parseFloat(el.getAttribute('data-simpay-money'));
      if (!isNaN(value)) el.textContent = formatMoney(value);
    });
  }

  if (!form) return;

  var qtyInputs = Array.prototype.slice.call(form.querySelectorAll('[data-simpay-refund-qty]'));
  var shipping = form.querySelector('[data-simpay-refund-shipping]');
  var totalEl = form.querySelector('[data-simpay-refund-total]');
  var submitBtn = form.querySelector('[data-simpay-refund-submit]');
  var submitAmount = form.querySelector('[data-simpay-refund-submit-amount]');
  var selectAllBtn = form.querySelector('[data-simpay-refund-select-all]');
  var remainingMinor = Math.round(parseFloat(form.getAttribute('data-remaining') || '0') * 100);
  var submitting = false;

  function toMinor(value) {
    return Math.round(parseFloat(value || '0') * 100);
  }

  function clampQty(input) {
    var max = parseInt(input.max, 10) || 0;
    var qty = parseInt(input.value, 10);
    if (isNaN(qty) || qty < 0) qty = 0;
    if (qty > max) qty = max;
    input.value = String(qty);
    return qty;
  }

  function isEverythingSelected() {
    var all = qtyInputs.every(function (input) { return clampQty(input) === (parseInt(input.max, 10) || 0); });
    return all && (!shipping || shipping.checked);
  }

  // Mirrors SimPayRefundService::requestItemsRefund() - the server recomputes it anyway
  function recalc() {
    var totalMinor = 0;

    qtyInputs.forEach(function (input) {
      var lineMinor = toMinor(input.getAttribute('data-unit')) * clampQty(input);
      totalMinor += lineMinor;
      var row = input.closest('tr');
      row.classList.toggle('table-active', lineMinor > 0);
      row.querySelector('[data-simpay-line-total]').textContent = lineMinor > 0 ? formatMoney(lineMinor / 100) : '–';
    });

    if (shipping) {
      var shippingMinor = shipping.checked ? toMinor(shipping.getAttribute('data-unit')) : 0;
      totalMinor += shippingMinor;
      var shippingRow = shipping.closest('tr');
      shippingRow.classList.toggle('table-active', shippingMinor > 0);
      shippingRow.querySelector('[data-simpay-line-total]').textContent = shippingMinor > 0 ? formatMoney(shippingMinor / 100) : '–';
    }

    if (totalMinor > 0 && (isEverythingSelected() || totalMinor > remainingMinor)) {
      totalMinor = remainingMinor;
    }

    var hasSelection = totalMinor > 0;
    var label = hasSelection ? formatMoney(totalMinor / 100) : '–';

    totalEl.textContent = label;
    submitAmount.textContent = hasSelection ? label : '';
    submitBtn.disabled = !hasSelection || submitting;
    form.setAttribute('data-total-label', label);

    if (selectAllBtn) selectAllBtn.disabled = isEverythingSelected();
  }

  qtyInputs.forEach(function (input) {
    input.addEventListener('input', recalc);
    input.addEventListener('change', recalc);
  });

  if (shipping) shipping.addEventListener('change', recalc);

  if (selectAllBtn) {
    selectAllBtn.addEventListener('click', function () {
      qtyInputs.forEach(function (input) { input.value = input.max; });
      if (shipping) shipping.checked = true;
      recalc();
    });
  }

  form.addEventListener('submit', function (e) {
    if (submitting) {
      e.preventDefault();
      return;
    }

    var message = (form.getAttribute('data-msg-confirm') || 'Refund %amount%?')
      .replace('%amount%', form.getAttribute('data-total-label') || '');

    if (!window.confirm(message)) {
      e.preventDefault();
      return;
    }

    // keep the clicked submit's name/value in the POST, then lock the button
    submitting = true;
    window.setTimeout(function () { submitBtn.disabled = true; }, 0);
  });

  recalc();
});
