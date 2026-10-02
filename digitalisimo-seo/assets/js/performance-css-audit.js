(function () {
  'use strict';
  var config = window.digitalisimoCssAudit;
  if (!config || !window.fetch || !window.CSSRule) return;
  var width = Math.round(window.innerWidth);
  var height = Math.round(window.innerHeight);
  if (width < 240 || height < 240 || width > 4096 || height > 4096) return;
  document.cookie = 'digitalisimo_css_view=' + width + '.' + height + '; Path=/; Max-Age=86400; SameSite=Lax';

  // Un cambio de tamaño invalida la geometría utilizada al servir esta página.
  window.addEventListener('resize', function () {
    document.querySelectorAll('link[data-digitalisimo-css-deferred]').forEach(function (link) {
      link.media = 'all';
      link.onload = null;
    });
  }, { once: true });

  function belowFirstViewport(node) {
    if (!node.getClientRects().length) return false;
    if (node.getBoundingClientRect().top <= height + 200) return false;
    for (var parent = node.parentElement; parent && parent !== document.body; parent = parent.parentElement) {
      var style = window.getComputedStyle(parent);
      if (/^(fixed|sticky)$/.test(style.position)) return false;
      if (/^(flex|inline-flex|grid|inline-grid)$/.test(style.display) && parent.getBoundingClientRect().top <= height + 200) return false;
    }
    return true;
  }

  function selectorIsBelow(selector) {
    // Los estados interactivos se amplían: así un hover/focus futuro no se clasifica como ausente.
    if (/:has\(|:not\(|::(?!before\b|after\b|marker\b|placeholder\b|selection\b|first-letter\b|first-line\b)/i.test(selector)) return false;
    var broad = selector.replace(/::(?:before|after|marker|placeholder|selection|first-letter|first-line)\b/gi, '')
      .replace(/:(?:hover|focus|focus-visible|focus-within|active|visited|checked|disabled|enabled|target)\b/gi, '');
    var matches;
    try { matches = document.querySelectorAll(broad); } catch (error) { return false; }
    if (!matches.length || matches.length > 300) return false;
    for (var i = 0; i < matches.length; i++) if (!belowFirstViewport(matches[i])) return false;
    return true;
  }

  function rulesAreBelow(rules, count) {
    for (var i = 0; i < rules.length; i++) {
      if (++count.total > 150) return false;
      var rule = rules[i];
      if (rule.type === CSSRule.STYLE_RULE) {
        count.styles++;
        if (!selectorIsBelow(rule.selectorText)) return false;
      } else if (rule.type === CSSRule.MEDIA_RULE || rule.type === CSSRule.SUPPORTS_RULE) {
        if (!rulesAreBelow(rule.cssRules, count)) return false;
      } else return false; // @font-face, @import, keyframes, capas y reglas desconocidas siguen bloqueantes.
    }
    return true;
  }

  function audit() {
    var rows = [];
    var budget = 400;
    var criticalWidgets = [];
    document.querySelectorAll('[data-widget_type]').forEach(function (node) {
      if (criticalWidgets.length < 100 && node.getBoundingClientRect().top <= height + 200) {
        var type = node.getAttribute('data-widget_type');
        if (type && criticalWidgets.indexOf(type) === -1) criticalWidgets.push(type);
      }
    });
    var templates = [];
    document.querySelectorAll('[data-elementor-type]').forEach(function (node) {
      var type = node.getAttribute('data-elementor-type');
      if (type && templates.indexOf(type) === -1 && templates.length < 20) templates.push(type);
    });
    document.querySelectorAll('link[data-digitalisimo-css-handle]:not([data-digitalisimo-css-deferred])').forEach(function (link) {
      if (rows.length >= 100) return;
      var safe = false;
      try {
        var count = { total: 0, styles: 0 };
        if (budget > 0 && link.sheet) safe = rulesAreBelow(link.sheet.cssRules, count) && count.styles > 0;
        budget -= count.total;
      } catch (error) { /* CSSOM inaccesible: bloqueante. */ }
      rows.push({ handle: link.getAttribute('data-digitalisimo-css-handle'), href: link.href, safe: safe ? 1 : 0 });
    });
    if (!rows.length) return;
    var body = new URLSearchParams({
      action: 'digitalisimo_css_audit', nonce: config.nonce, page: config.page,
      view: width + '.' + height, rows: JSON.stringify(rows),
      critical_widgets: JSON.stringify(criticalWidgets), templates: JSON.stringify(templates)
    });
    fetch(config.ajax, { method: 'POST', body: body, credentials: 'same-origin', keepalive: true }).catch(function () {});
  }

  window.addEventListener('load', function () {
    var ready = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();
    ready.then(function () { requestAnimationFrame(function () { requestAnimationFrame(audit); }); });
  }, { once: true });
}());
