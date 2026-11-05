(function () {
  'use strict';
  var initialized = new WeakSet();
  function load(widget) {
    if (widget.dataset.digiGoogleLoaded) return;
    widget.dataset.digiGoogleLoaded = '1';
    var body = new URLSearchParams();
    body.set('action', 'digitalisimo_elements_google_reviews');
    body.set('place_id', widget.dataset.placeId || '');
    body.set('signature', widget.dataset.signature || '');
    body.set('limit', widget.dataset.limit || '5');
    fetch(widget.dataset.endpoint, { method: 'POST', credentials: 'same-origin', body: body })
      .then(function (response) { return response.ok ? response.json() : null; })
      .then(function (result) {
        if (!result || !result.success || !result.data || !result.data.html) return;
        var target = widget.querySelector('.digi-google-reviews__results');
        if (target) target.innerHTML = result.data.html;
      })
      .catch(function () { /* El enlace directo a Google Maps permanece disponible. */ });
  }
  function init(root) {
    (root || document).querySelectorAll('[data-digi-google-reviews]').forEach(function (widget) {
      if (initialized.has(widget)) return;
      initialized.add(widget);
      if (!('IntersectionObserver' in window)) { load(widget); return; }
      var observer = new IntersectionObserver(function (entries) {
        if (!entries[0].isIntersecting) return;
        observer.disconnect();
        load(widget);
      }, { rootMargin: '200px' });
      observer.observe(widget);
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(document); }); else init(document);
  if (window.elementorFrontend && window.elementorFrontend.hooks) window.elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-google-reviews.default', function ($scope) { init($scope[0] || document); });
})();
