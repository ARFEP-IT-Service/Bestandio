// Bestandio – small progressive enhancements. Everything works without JavaScript.
'use strict';

document.documentElement.classList.add('js');

// Service worker: only makes the app installable and caches static files (see sw.js).
// Browsers allow it on HTTPS and localhost only.
(function () {
  var url = document.documentElement.getAttribute('data-sw');
  if (url && 'serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(url).catch(function () { /* not installable, app still works */ });
    });
  }
}());

document.addEventListener('DOMContentLoaded', function () {
  // Language select submits on change.
  document.querySelectorAll('select[data-autosubmit]').forEach(function (select) {
    select.addEventListener('change', function () {
      select.form.submit();
    });
  });

  // Print button on the label page.
  document.querySelectorAll('[data-print]').forEach(function (button) {
    button.addEventListener('click', function () {
      window.print();
    });
  });

  // "Select all" checkbox for the label selection in the device list.
  document.querySelectorAll('input[data-select-all]').forEach(function (all) {
    all.addEventListener('change', function () {
      var name = all.getAttribute('data-select-all');
      document.querySelectorAll('input[type="checkbox"][name="' + name + '"]').forEach(function (box) {
        box.checked = all.checked;
      });
    });
  });

  // Phone "More" sheet: close on a tap outside or with Escape.
  var more = document.querySelector('details[data-more]');
  if (more) {
    document.addEventListener('click', function (event) {
      if (more.open && !more.contains(event.target)) {
        more.open = false;
      }
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && more.open) {
        more.open = false;
        more.querySelector('summary').focus();
      }
    });
  }

  // Ask before destructive actions.
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.getAttribute('data-confirm'))) {
        event.preventDefault();
      }
    });
  });
});
