document.addEventListener('DOMContentLoaded', function () {
  var type = document.querySelector('[data-simpay-refund-type]');
  var amount = document.querySelector('[data-simpay-refund-amount]');
  if (!type || !amount) return;

  function syncRefundAmount() {
    if (type.value === 'full') {
      amount.value = amount.max || '';
      amount.disabled = true;
    } else {
      amount.disabled = false;
      amount.value = '';
      amount.focus();
    }
  }

  type.addEventListener('change', syncRefundAmount);
  syncRefundAmount();
});
