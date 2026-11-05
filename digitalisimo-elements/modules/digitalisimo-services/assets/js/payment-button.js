(function () {
  'use strict';
  // Botones de PayPal y Stripe propios (C9). Sin jQuery. PayPal: con datos que faltan no se envía y se muestran los
  // mensajes. Stripe: la acción `submit_stripe_form` del servidor crea la sesión de pago (las claves nunca llegan aquí) y
  // se abre su dirección; un error se muestra como texto.
  var REMOTE = 'digi-pay__message--remote';

  function message(form, text) {
    var box = document.createElement('div');
    box.className = 'elementor-message elementor-message-danger digi-pay__message ' + REMOTE;
    box.setAttribute('role', 'alert');
    box.textContent = text || '';
    form.appendChild(box);
  }

  function stripe(form, button) {
    var d = form.dataset;
    var doc = form.closest('[data-elementor-id]');
    var custom = 'yes' === d.digiPayCustom;
    var body = new URLSearchParams();
    body.append('action', d.digiPayAction);
    body.append('data[action]', d.digiPayAction);
    body.append('data[postId]', doc ? String(parseInt(doc.getAttribute('data-elementor-id'), 10)) : '');
    body.append('data[widgetId]', d.digiPayWidget || '');
    body.append('data[pageUrl]', document.URL);
    body.append('data[nonce]', d.digiPayNonce || '');
    var done = function () {
      if (button) { button.removeAttribute('aria-busy'); button.disabled = false; }
    };
    var fail = function (text) {
      done();
      message(form, custom || !text ? d.digiPayGlobal : text);
    };
    if (button) { button.setAttribute('aria-busy', 'true'); button.disabled = true; }
    fetch(d.digiPayUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() })
      .then(function (r) { return r.json(); })
      .then(function (response) {
        var code = response && response.response ? parseInt(response.response.code, 10) : 0;
        var result = {};
        try { result = response && response.body ? JSON.parse(response.body) : {}; } catch (e) { result = {}; }
        var remote = result && result.error && result.error.message ? String(result.error.message) : '';
        done();
        if (200 === code && result && /^https:\/\//i.test(String(result.url || ''))) {
          window.open(result.url, '_blank' === d.digiPayTarget ? '_blank' : '_self');
        } else if (401 === code || 403 === code) {
          message(form, custom ? d.digiPayPayment : (remote || d.digiPayPayment));
        } else {
          message(form, custom ? d.digiPayGlobal : (remote || d.digiPayGlobal));
        }
      })
      .catch(function () { fail(''); });
  }

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form || !form.matches || !form.matches('form[data-digi-pay]')) return;
    var kind = form.getAttribute('data-digi-pay');
    var errors = form.querySelectorAll('.digi-pay__message:not(.' + REMOTE + ')');
    if ('stripe' === kind) {
      event.preventDefault();
      if (document.body.classList.contains('elementor-editor-active')) return;
      form.querySelectorAll('.' + REMOTE).forEach(function (old) { old.remove(); });
      if (errors.length) {
        errors.forEach(function (e) { e.hidden = false; });
        return;
      }
      stripe(form, event.submitter || form.querySelector('.digi-pay__button'));
      return;
    }
    if (errors.length) {
      event.preventDefault();
      errors.forEach(function (e) { e.hidden = false; });
    }
  });
})();
