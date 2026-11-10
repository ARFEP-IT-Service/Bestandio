// Bestandio – small progressive enhancements. Everything works without JavaScript.
'use strict';

document.documentElement.classList.add('js');

document.addEventListener('DOMContentLoaded', function () {
  // Language select submits on change.
  document.querySelectorAll('select[data-autosubmit]').forEach(function (select) {
    select.addEventListener('change', function () {
      select.form.submit();
    });
  });

  // Ask before destructive actions.
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.getAttribute('data-confirm'))) {
        event.preventDefault();
      }
    });
  });
});
