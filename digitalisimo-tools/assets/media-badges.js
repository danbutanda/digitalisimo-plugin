(function () {
  'use strict';

  if (!window.wp || !wp.media || !wp.media.model || !wp.media.model.Attachment) return;

  function markOptimized() {
    document.querySelectorAll('.attachments .attachment[data-id]').forEach(function (item) {
      if (item.dataset.digitalisimoWebpChecked === '1') return;
      var model = wp.media.model.Attachment.get(item.getAttribute('data-id'));
      if (!model || !model.has('digitalisimoOptimized')) return;
      item.dataset.digitalisimoWebpChecked = '1';
      if (!model.get('digitalisimoOptimized')) return;
      var preview = item.querySelector('.attachment-preview');
      if (!preview) return;
      var badge = document.createElement('span');
      badge.className = 'digitalisimo-webp-badge';
      badge.textContent = 'Optimizada · WebP';
      preview.appendChild(badge);
    });
  }

  var scheduled = false;
  function scheduleScan() {
    if (scheduled) return;
    scheduled = true;
    window.requestAnimationFrame(function () {
      scheduled = false;
      markOptimized();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scheduleScan);
  } else {
    scheduleScan();
  }
  new MutationObserver(scheduleScan).observe(document.body, { childList: true, subtree: true });
})();
