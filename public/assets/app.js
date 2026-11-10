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

  // Tab bar: tapping the link of the page you are on does not reload, it scrolls to the top.
  document.querySelectorAll('.tabbar a[aria-current="page"]').forEach(function (link) {
    link.addEventListener('click', function (event) {
      if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
        return;
      }
      if (link.pathname !== window.location.pathname || link.search !== window.location.search) {
        return;
      }
      event.preventDefault();
      if (more) {
        more.open = false;
      }
      var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
    });
  });

  // Forms with data-dirty-guard: warn before leaving with unsaved changes, not when submitting.
  var formState = function (form) {
    return Array.prototype.map.call(form.elements, function (field) {
      if (!field.name) {
        return '';
      }
      return field.type === 'checkbox' || field.type === 'radio' ? String(field.checked) : field.value;
    }).join('\u0000');
  };
  var guarded = [];
  var submitting = false;
  // After a failed save (422) or a duplicate question (409) the form holds input sent back by the
  // server that is not saved: it counts as changed from the start.
  var unsaved = document.body.hasAttribute('data-unsaved');
  document.querySelectorAll('form[data-dirty-guard]').forEach(function (form) {
    var entry = { form: form, initial: unsaved ? null : formState(form), touched: unsaved };
    var touch = function () {
      entry.touched = true;
    };
    form.addEventListener('input', touch);
    form.addEventListener('change', touch);
    guarded.push(entry);
  });
  if (guarded.length) {
    // Bubbling listener on the document: runs after the form's own handlers (e.g. data-confirm).
    document.addEventListener('submit', function (event) {
      if (!event.defaultPrevented && event.target.hasAttribute('data-dirty-guard')) {
        submitting = true;
      }
    });
    window.addEventListener('pageshow', function () {
      submitting = false;
    });
    window.addEventListener('beforeunload', function (event) {
      var dirty = !submitting && guarded.some(function (entry) {
        return entry.touched && formState(entry.form) !== entry.initial;
      });
      if (dirty) {
        event.preventDefault();
        event.returnValue = '';
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
