/* Razorpay subscription checkout for the Subscribe page. */
(function () {
  'use strict';
  var C = window.EPAPER_SUBSCRIBE;
  if (!C || typeof Razorpay === 'undefined') return;

  function post(url, data) {
    var body = new URLSearchParams(data).toString();
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'fetch' },
      credentials: 'same-origin',
      body: body
    }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); });
  }

  function start(button) {
    button.disabled = true;
    var original = button.textContent;
    button.textContent = 'Starting…';

    post(C.checkoutUrl, { plan: button.getAttribute('data-plan'), csrf: C.csrf })
      .then(function (res) {
        if (!res.ok) throw new Error(res.body && res.body.error ? res.body.error : 'Could not start checkout.');
        var d = res.body;
        var rzp = new Razorpay({
          key: d.key_id,
          subscription_id: d.subscription_id,
          name: d.name,
          description: d.description,
          prefill: d.prefill || C.prefill,
          theme: { color: '#C4161C' },
          handler: function (resp) {
            var form = document.getElementById('rzp-callback');
            form.querySelector('[name="razorpay_payment_id"]').value = resp.razorpay_payment_id || '';
            form.querySelector('[name="razorpay_subscription_id"]').value = resp.razorpay_subscription_id || '';
            form.querySelector('[name="razorpay_signature"]').value = resp.razorpay_signature || '';
            form.submit();
          },
          modal: {
            ondismiss: function () { button.disabled = false; button.textContent = original; }
          }
        });
        rzp.on('payment.failed', function (resp) {
          alert('Payment failed: ' + (resp.error && resp.error.description ? resp.error.description : 'please try again.'));
          button.disabled = false; button.textContent = original;
        });
        rzp.open();
      })
      .catch(function (err) {
        alert(err.message || 'Something went wrong. Please try again.');
        button.disabled = false; button.textContent = original;
      });
  }

  document.querySelectorAll('.js-subscribe').forEach(function (btn) {
    btn.addEventListener('click', function () { start(btn); });
  });
})();
