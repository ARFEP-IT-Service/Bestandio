// Bestandio – scan page. Uses the browser's BarcodeDetector; falls back to the bundled jsQR.
'use strict';

(function () {
  var root = document.querySelector('[data-scan]');
  if (!root) {
    return;
  }
  var q = function (name) { return root.querySelector('[' + name + ']'); };
  var form = q('data-scan-form');
  var input = form.querySelector('input[name="code"]');
  var video = q('data-scan-video');
  var startBtn = q('data-scan-start');
  var stopBtn = q('data-scan-stop');
  var status = q('data-scan-status');
  var canvas = document.createElement('canvas');
  var ctx = canvas.getContext('2d', { willReadFrequently: true });
  var stream = null;
  var timer = null;
  var detectorPromise = null;

  function show(el, on) { el.hidden = !on; }

  function submit(text) {
    stop();
    input.value = String(text).trim();
    form.submit();
  }

  function loadScript(src) {
    return new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = src;
      s.onload = resolve;
      s.onerror = reject;
      document.head.appendChild(s);
    });
  }

  // Returns an object with detect(source, width, height) -> Promise<string|null>.
  function getDetector() {
    if (detectorPromise) {
      return detectorPromise;
    }
    detectorPromise = (async function () {
      if ('BarcodeDetector' in window) {
        try {
          var formats = await window.BarcodeDetector.getSupportedFormats();
          if (formats.indexOf('qr_code') !== -1) {
            var bd = new window.BarcodeDetector({ formats: ['qr_code'] });
            return {
              name: 'BarcodeDetector',
              detect: async function (source) {
                var found = await bd.detect(source);
                return found.length ? found[0].rawValue : null;
              }
            };
          }
        } catch (e) { /* fall back to jsQR */ }
      }
      if (typeof window.jsQR !== 'function') {
        await loadScript(root.getAttribute('data-jsqr'));
      }
      return {
        name: 'jsQR',
        detect: async function (source, width, height) {
          var max = 1024;
          var scale = Math.min(1, max / Math.max(width, height));
          canvas.width = Math.round(width * scale);
          canvas.height = Math.round(height * scale);
          ctx.drawImage(source, 0, 0, canvas.width, canvas.height);
          var img = ctx.getImageData(0, 0, canvas.width, canvas.height);
          var res = window.jsQR(img.data, img.width, img.height, { inversionAttempts: 'attemptBoth' });
          return res ? res.data : null;
        }
      };
    })();
    return detectorPromise;
  }

  async function tick() {
    if (!stream || video.readyState < 2) {
      return;
    }
    try {
      var det = await getDetector();
      var text = await det.detect(video, video.videoWidth, video.videoHeight);
      if (text) {
        submit(text);
      }
    } catch (e) { /* keep scanning */ }
  }

  async function start() {
    show(q('data-scan-error'), false);
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
    } catch (e) {
      show(q('data-scan-error'), true);
      return;
    }
    video.srcObject = stream;
    show(video, true);
    show(status, true);
    show(startBtn, false);
    show(stopBtn, true);
    await video.play();
    getDetector();
    timer = window.setInterval(tick, 300);
  }

  function stop() {
    if (timer) {
      window.clearInterval(timer);
      timer = null;
    }
    if (stream) {
      stream.getTracks().forEach(function (t) { t.stop(); });
      stream = null;
    }
    video.srcObject = null;
    show(video, false);
    show(status, false);
    show(stopBtn, false);
    show(startBtn, true);
  }

  // Camera only in a secure context (HTTPS or localhost).
  if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    show(q('data-scan-insecure'), true);
    show(startBtn, false);
  } else {
    startBtn.addEventListener('click', start);
    stopBtn.addEventListener('click', stop);
  }

  q('data-scan-photo').addEventListener('change', async function (event) {
    var file = event.target.files && event.target.files[0];
    show(q('data-scan-nocode'), false);
    if (!file) {
      return;
    }
    try {
      var bitmap = await createImageBitmap(file);
      var det = await getDetector();
      var text = await det.detect(bitmap, bitmap.width, bitmap.height);
      if (text) {
        submit(text);
        return;
      }
    } catch (e) { /* reported below */ }
    show(q('data-scan-nocode'), true);
  });
}());
