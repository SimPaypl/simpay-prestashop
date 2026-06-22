(function () {
  function getCleanCode(value) {
    return (value || '').replace(/[^0-9]/g, '').slice(0, 6);
  }

  function formatCode(value) {
    var clean = getCleanCode(value);
    return clean.length > 3 ? clean.slice(0, 3) + ' ' + clean.slice(3) : clean;
  }

  function getMsg(form, key, fallback) {
    return form.getAttribute(key) || fallback;
  }

  function setMessage(form, message) {
    var el = form.querySelector('[data-simpay-blik-message]');
    if (!el) return;
    el.textContent = message || '';
    el.classList.toggle('d-none', !message);
  }

  function setLoading(form, isLoading) {
    var loader = form.querySelector('[data-simpay-blik-loader]');
    var submit = document.querySelector('#payment-confirmation button');
    if (loader) loader.style.display = isLoading ? 'inline-flex' : 'none';
    if (submit) submit.disabled = !!isLoading;

    if (isLoading) {
      setMessage(form, getMsg(form, 'data-simpay-msg-loading', 'Processing your data, please wait...'));
    }
  }

  function areTermsAccepted() {
    var checkboxes = document.querySelectorAll('#conditions-to-approve input[type="checkbox"]');
    if (!checkboxes.length) return true;
    return Array.prototype.every.call(checkboxes, function (cb) { return cb.checked; });
  }

  function getSelectedAdditionalInfo() {
    var selected = document.querySelector('input[name="payment-option"]:checked');
    if (!selected) return null;
    return document.querySelector('#' + selected.id + '-additional-information');
  }

  function getBlikFormFromSelected() {
    var info = getSelectedAdditionalInfo();
    if (!info) return null;
    var wrapper = info.querySelector('[data-payment-type="blik"]');
    if (!wrapper) return null;
    return wrapper.querySelector('[data-simpay-blik-form]');
  }

  // ==================== OneClick ====================

  function isOneClickEnabled(form) {
    return form.getAttribute('data-simpay-oneclick-enabled') === '1';
  }

  function showSection(form, section) {
    var oneclickSection = form.querySelector('[data-simpay-oneclick-section]');
    var codeSection = form.querySelector('[data-simpay-code-section]');
    var switchToOneclick = form.querySelector('[data-simpay-switch-to-oneclick]');

    if (section === 'oneclick') {
      if (oneclickSection) oneclickSection.style.display = '';
      if (codeSection) codeSection.style.display = 'none';
      setMessage(form, '');
    } else {
      if (oneclickSection) oneclickSection.style.display = 'none';
      if (codeSection) codeSection.style.display = '';
      if (switchToOneclick) switchToOneclick.style.display = '';
    }

    // Hide bank modal when switching
    var bankModal = form.querySelector('[data-simpay-bank-modal]');
    if (bankModal) bankModal.style.display = 'none';
  }

  function checkOneClickAvailability(form) {
    if (!isOneClickEnabled(form)) return;

    var data = new URLSearchParams();
    data.set('action', 'check_oneclick');
    data.set('token', form.querySelector('input[name="token"]').value || '');

    fetch(form.action, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: data.toString(),
      credentials: 'same-origin',
    })
      .then(function (res) { return res.json().catch(function () { return null; }); })
      .then(function (json) {
        if (json && json.success && json.data && json.data.oneclick_available) {
          showSection(form, 'oneclick');
        }
      })
      .catch(function () { /* silently ignore */ });
  }

  function submitOneClick(form) {
    if (!areTermsAccepted()) {
      setMessage(form, getMsg(form, 'data-simpay-msg-terms', 'Please accept the terms before continuing.'));
      return;
    }

    setMessage(form, '');
    setLoading(form, true);

    var data = new URLSearchParams();
    data.set('action', 'oneclick');
    data.set('token', form.querySelector('input[name="token"]').value || '');
    data.set('cart_id', form.querySelector('input[name="cart_id"]').value || '');

    fetch(form.action, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: data.toString(),
      credentials: 'same-origin',
    })
      .then(function (res) { return res.json().catch(function () { return null; }); })
      .then(function (json) {
        if (!json) {
          setLoading(form, false);
          setMessage(form, getMsg(form, 'data-simpay-msg-failed', 'BLIK payment failed. Try again.'));
          return;
        }

        if (json.success) {
          // OneClick accepted, poll for confirmation
          toggleOtherPayments(true);
          setMessage(form, getMsg(form, 'data-simpay-msg-oneclick-wait', 'Confirm the payment in your banking app.'));
          pollStatus(form, json.data.transaction_id);
          return;
        }


        // Other error
        setLoading(form, false);
        setMessage(form, json.message || getMsg(form, 'data-simpay-msg-failed', 'BLIK payment failed. Try again.'));
      })
      .catch(function () {
        setLoading(form, false);
        setMessage(form, getMsg(form, 'data-simpay-msg-failed', 'BLIK payment failed. Try again.'));
      });
  }

  // ==================== Polling ====================

  function pollStatus(form, transactionId) {
    var interval = 1000;
    var timeout = 60000;
    var started = Date.now();
    var timer = null;

    function tick() {
      var data = new URLSearchParams();
      data.set('action', 'status');
      data.set('token', form.querySelector('input[name="token"]').value || '');
      data.set('cart_id', form.querySelector('input[name="cart_id"]').value || '');
      data.set('transaction_id', transactionId);

      fetch(form.action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: data.toString(),
        credentials: 'same-origin',
      })
        .then(function (res) { return res.json().catch(function () { return null; }); })
        .then(function (json) {
          if (!json || !json.success || !json.data) {
            return;
          }

          if (json.data.status === 'paid') {
            setMessage(form, getMsg(form, 'data-simpay-msg-success', 'Payment confirmed. Redirecting to order confirmation…'));
            if (json.data.order_confirm_url) {
              window.location.href = json.data.order_confirm_url;
            }
            clearInterval(timer);
            return;
          }

          if (json.data.status === 'rejected') {
            setLoading(form, false);
            setMessage(form, getMsg(form, 'data-simpay-msg-rejected', 'Payment rejected. Please enter a new BLIK code.'));
            showSection(form, 'code');
            clearInterval(timer);
            return;
          }

          if (json.data.status === 'expired') {
            setLoading(form, false);
            setMessage(form, getMsg(form, 'data-simpay-msg-expired', 'BLIK code expired. Please enter a new code.'));
            showSection(form, 'code');
            clearInterval(timer);
            return;
          }
        });

      if (Date.now() - started > timeout) {
        setLoading(form, false);
        setMessage(form, getMsg(form, 'data-simpay-msg-expired', 'BLIK code expired. Please enter a new code.'));
        showSection(form, 'code');
        clearInterval(timer);
      }
    }

    setLoading(form, true);
    setMessage(form, getMsg(form, 'data-simpay-msg-wait', 'Waiting for confirmation in your banking app…'));

    timer = setInterval(tick, interval);
    tick();
  }

  // ==================== Standard BLIK ====================

  function toggleOtherPayments(disabled) {
    document.querySelectorAll('input[name="payment-option"]').forEach(function (el) {
      if (!el.checked) {
        el.disabled = !!disabled;
      }
    });
  }

  function submitBlik(form) {
    var codeInput = form.querySelector('[data-simpay-blik-code]');
    if (!codeInput) return;

    if (!areTermsAccepted()) {
      setMessage(form, getMsg(form, 'data-simpay-msg-terms', 'Please accept the terms before continuing.'));
      return;
    }

    var clean = getCleanCode(codeInput.value);
    if (!clean) {
      setMessage(form, getMsg(form, 'data-simpay-msg-empty', 'Enter the BLIK code'));
      codeInput.focus();
      return;
    }
    if (!/^\d{6}$/.test(clean)) {
      setMessage(form, getMsg(form, 'data-simpay-msg-invalid', 'Invalid BLIK code'));
      codeInput.focus();
      return;
    }

    setMessage(form, '');
    setLoading(form, true);

    var data = new URLSearchParams(new FormData(form));
    data.set('blik_code', clean);

    var keepLoading = false;

    fetch(form.action, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: data.toString(),
      credentials: 'same-origin',
    })
      .then(function (res) { return res.json().catch(function () { return null; }); })
      .then(function (json) {
        toggleOtherPayments(true);
        if (json && json.success) {
          if (json.data && json.data.confirm_required && json.data.transaction_id) {
            keepLoading = true;
            pollStatus(form, json.data.transaction_id);
            return;
          }
          if (json.data && json.data.redirect_url) {
            window.location.href = json.data.redirect_url;
            return;
          }
        }
        setMessage(form, (json && json.message) ? json.message : getMsg(form, 'data-simpay-msg-failed', 'BLIK payment failed. Try again.'));
      })
      .catch(function () {
        setMessage(form, getMsg(form, 'data-simpay-msg-failed', 'BLIK payment failed. Try again.'));
      })
      .finally(function () {
        if (!keepLoading) {
          setLoading(form, false);
        }
      });
  }

  // ==================== UI: Move buttons ====================

  var originalButtonParent = null;
  var originalButtonNext = null;
  var originalTermsParent = null;
  var originalTermsNext = null;

  function moveMainButtonIntoWidget(form) {
    var slot = form.querySelector('[data-simpay-main-button-slot]');
    var btnWrap = document.querySelector('#payment-confirmation');
    if (!slot || !btnWrap) return;

    if (!originalButtonParent) {
      originalButtonParent = btnWrap.parentNode;
      originalButtonNext = btnWrap.nextSibling;
    }

    slot.appendChild(btnWrap);
  }

  function restoreMainButton() {
    var btnWrap = document.querySelector('#payment-confirmation');
    if (!btnWrap || !originalButtonParent) return;

    if (originalButtonNext) {
      originalButtonParent.insertBefore(btnWrap, originalButtonNext);
    } else {
      originalButtonParent.appendChild(btnWrap);
    }
  }

  function moveTermsIntoWidget(form) {
    var slot = form.querySelector('[data-simpay-terms-slot]');
    var terms = document.querySelector('#conditions-to-approve');
    if (!slot || !terms) return;
    if (!originalTermsParent) {
      originalTermsParent = terms.parentNode;
      originalTermsNext = terms.nextSibling;
    }

    slot.appendChild(terms);
  }

  function restoreTerms() {
    var terms = document.querySelector('#conditions-to-approve');
    if (!terms || !originalTermsParent) return;

    if (originalTermsNext) {
      originalTermsParent.insertBefore(terms, originalTermsNext);
    } else {
      originalTermsParent.appendChild(terms);
    }
  }

  function onPaymentOptionChange() {
    var form = getBlikFormFromSelected();
    if (form) {
      moveMainButtonIntoWidget(form);
      moveTermsIntoWidget(form);
      // Check OneClick availability when BLIK is selected
      checkOneClickAvailability(form);
    } else {
      restoreMainButton();
      restoreTerms();
    }
  }

  // ==================== Event bindings ====================

  function bindOneClickEvents(form) {
    if (form.dataset.simpayOneClickBound === '1') return;
    form.dataset.simpayOneClickBound = '1';

    // OneClick button
    var oneclickBtn = form.querySelector('[data-simpay-oneclick-btn]');
    if (oneclickBtn) {
      oneclickBtn.addEventListener('click', function (e) {
        e.preventDefault();
        submitOneClick(form);
      });
    }

    // Switch to code
    var switchToCode = form.querySelector('[data-simpay-switch-to-code]');
    if (switchToCode) {
      switchToCode.addEventListener('click', function (e) {
        e.preventDefault();
        showSection(form, 'code');
        setMessage(form, '');
      });
    }

    // Switch to oneclick
    var switchToOneclick = form.querySelector('[data-simpay-switch-to-oneclick]');
    if (switchToOneclick) {
      switchToOneclick.addEventListener('click', function (e) {
        e.preventDefault();
        showSection(form, 'oneclick');
        setMessage(form, '');
      });
    }
  }

  function bindMainButton() {
    var confirmation = document.querySelector('#payment-confirmation');
    if (!confirmation) return;

    if (confirmation.dataset.simpayBlikBound === '1') return;
    confirmation.dataset.simpayBlikBound = '1';

    confirmation.addEventListener('click', function (e) {
      var form = getBlikFormFromSelected();
      if (!form) return;

      // If oneclick section is visible, block default and trigger oneclick instead
      var oneclickSection = form.querySelector('[data-simpay-oneclick-section]');
      if (oneclickSection && oneclickSection.style.display !== 'none') {
        e.preventDefault();
        e.stopPropagation();
        submitOneClick(form);
        return;
      }

      e.preventDefault();
      e.stopPropagation();
      submitBlik(form);
    }, true);
  }

  function initBlik() {
    bindMainButton();

    document.querySelectorAll('[data-simpay-blik-form]').forEach(function (form) {
      bindOneClickEvents(form);
    });

    document.querySelectorAll('input[name="payment-option"]').forEach(function (el) {
      if (!el.dataset.simpayBlikBound) {
        el.dataset.simpayBlikBound = '1';
        el.addEventListener('change', onPaymentOptionChange);
      }
    });

    onPaymentOptionChange();
  }

  document.addEventListener('input', function (e) {
    if (!e.target || !e.target.matches('[data-simpay-blik-code]')) return;
    e.target.value = formatCode(e.target.value);
  });

  document.addEventListener('DOMContentLoaded', function () {
    initBlik();

    if (typeof prestashop !== 'undefined' && prestashop.on) {
      prestashop.on('updatedCheckout', function () {
        initBlik();
      });
    }
  });
})();