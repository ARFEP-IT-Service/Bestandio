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

  // Ask before destructive actions.
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.getAttribute('data-confirm'))) {
        event.preventDefault();
      }
    });
  });
});
