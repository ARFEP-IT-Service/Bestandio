// Bestandio – page change cross-fade (CSS @view-transition). The browser skips the fade when it
// cannot run it (e.g. a page without it, or a quick second navigation) and rejects its promises;
// that is harmless, so they are marked as handled. Loaded in <head> because "pagereveal" fires
// before deferred scripts run.
'use strict';

(function () {
  var quiet = function (event) {
    var vt = event.viewTransition;
    if (vt) {
      vt.ready.catch(function () { /* fade skipped */ });
      vt.finished.catch(function () { /* fade skipped */ });
      vt.updateCallbackDone.catch(function () { /* fade skipped */ });
    }
  };
  window.addEventListener('pageswap', quiet);
  window.addEventListener('pagereveal', quiet);
}());
