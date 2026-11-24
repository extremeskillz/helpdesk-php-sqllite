document.addEventListener('click', function (e) {
  const trigger = e.target.closest('[data-confirm]');
  if (trigger && !confirm(trigger.getAttribute('data-confirm') || 'Are you sure?')) e.preventDefault();
});

/* ------------------------------------------------------------------
   v1.4 – Email template editor (Jodit, MIT licence)
   Loads public/vendor/jodit/ if present, otherwise jsDelivr. If neither
   can be loaded the fields stay as plain HTML textareas.
------------------------------------------------------------------- */
(function () {
  const areas = Array.prototype.slice.call(document.querySelectorAll('textarea.rich-email-template'));
  if (!areas.length) return;
  window.hdEditors = window.hdEditors || {};

  function insertToken(textarea, token) {
    const ed = window.hdEditors[textarea.id];
    if (ed && ed.selection && typeof ed.selection.insertHTML === 'function') {
      ed.selection.insertHTML(token.replace(/&/g, '&amp;').replace(/</g, '&lt;'));
      return;
    }
    const start = textarea.selectionStart == null ? textarea.value.length : textarea.selectionStart;
    const end = textarea.selectionEnd == null ? start : textarea.selectionEnd;
    textarea.value = textarea.value.slice(0, start) + token + textarea.value.slice(end);
    textarea.focus();
    textarea.selectionStart = textarea.selectionEnd = start + token.length;
  }

  document.querySelectorAll('.ph-bar').forEach(function (bar) {
    bar.addEventListener('click', function (e) {
      const btn = e.target.closest('button[data-token]');
      if (!btn) return;
      const ta = document.getElementById(bar.getAttribute('data-for'));
      if (ta) insertToken(ta, btn.getAttribute('data-token'));
    });
  });

  function loadAsset(base, done, fail) {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = base + 'jodit.min.css';
    document.head.appendChild(link);
    const script = document.createElement('script');
    script.src = base + 'jodit.min.js';
    script.onload = function () { window.Jodit ? done() : fail(); };
    script.onerror = fail;
    document.head.appendChild(script);
  }

  function init() {
    const dark = document.documentElement.getAttribute('data-theme') === 'dark' || document.body.getAttribute('data-theme') === 'dark';
    areas.forEach(function (ta) {
      try {
        const ed = window.Jodit.make(ta, {
          height: 380,
          theme: dark ? 'dark' : 'default',
          toolbarAdaptive: true,
          toolbarSticky: false,
          showCharsCounter: false,
          showWordsCounter: false,
          showXPathInStatusbar: false,
          askBeforePasteHTML: false,
          askBeforePasteFromWord: false,
          defaultActionOnPaste: 'insert_clear_html',
          enter: 'P',
          link: { openInNewTabCheckbox: true },
          buttons: ['bold', 'italic', 'underline', 'strikethrough', '|', 'ul', 'ol', '|', 'font', 'fontsize', 'paragraph', 'brush', '|', 'align', '|', 'link', 'image', 'table', 'hr', '|', 'undo', 'redo', '|', 'eraser', 'source', 'fullsize']
        });
        window.hdEditors[ta.id] = ed;
        if (ta.form) {
          ta.form.addEventListener('submit', function () {
            try { if (typeof ed.synchronizeValues === 'function') ed.synchronizeValues(); ta.value = ed.value; } catch (err) { /* keep textarea value */ }
          });
        }
      } catch (err) { /* leave this textarea as plain HTML */ }
    });
  }

  const sources = ['public/vendor/jodit/', 'https://cdn.jsdelivr.net/npm/jodit@4/es2021/'];
  (function next(i) {
    if (i >= sources.length) return;
    loadAsset(sources[i], init, function () { next(i + 1); });
  })(0);
})();

/* v1.4 – Warn administrators if the SQLite file is publicly reachable (Settings page only). */
(function () {
  const box = document.getElementById('db-exposure-warning');
  if (!box || !window.fetch) return;
  fetch(box.getAttribute('data-check-url'), { method: 'HEAD', cache: 'no-store', credentials: 'omit' })
    .then(function (r) { if (r.ok) box.hidden = false; })
    .catch(function () { /* unreachable = good */ });
})();
