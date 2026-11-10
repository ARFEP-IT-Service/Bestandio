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
  var live = q('data-scan-live');
  var frame = q('data-scan-frame');
  var menu = q('data-scan-menu');
  var menuToggle = q('data-scan-menu-toggle');
  var canvas = document.createElement('canvas');
  var ctx = canvas.getContext('2d', { willReadFrequently: true });
  var crop = document.createElement('canvas');
  var cropCtx = crop.getContext('2d', { willReadFrequently: true });
  var stream = null;
  var timer = null;
  var detectorPromise = null;

  function show(el, on) { el.hidden = !on; }

  // Keep header and tab bar visible: the camera fills only the space between them.
  function fitLive() {
    var top = document.querySelector('.topbar');
    var bar = document.querySelector('.tabbar');
    live.style.top = (top ? Math.max(0, top.getBoundingClientRect().bottom) : 0) + 'px';
    live.style.bottom = (bar && getComputedStyle(bar).display !== 'none' ? Math.max(0, window.innerHeight - bar.getBoundingClientRect().top) : 0) + 'px';
  }

  function setMenu(open) {
    show(menu, open);
    menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) {
      menu.querySelector('button').focus();
    }
  }

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

  // Copies the part of the camera image that lies inside the on-screen frame into `crop`.
  // The video is shown with object-fit: cover, so screen and camera pixels differ by one scale and offset.
  function cropToFrame() {
    var vw = video.videoWidth;
    var vh = video.videoHeight;
    var box = video.getBoundingClientRect();
    var f = frame.getBoundingClientRect();
    var scale = Math.max(box.width / vw, box.height / vh);
    var x = Math.max(0, (f.left - box.left - (box.width - vw * scale) / 2) / scale);
    var y = Math.max(0, (f.top - box.top - (box.height - vh * scale) / 2) / scale);
    var w = Math.min(vw - x, f.width / scale);
    var h = Math.min(vh - y, f.height / scale);
    var out = Math.min(1, 800 / Math.max(w, h));
    crop.width = Math.round(w * out);
    crop.height = Math.round(h * out);
    cropCtx.drawImage(video, x, y, w, h, 0, 0, crop.width, crop.height);
  }

  async function tick() {
    if (!stream || video.readyState < 2 || !video.videoWidth) {
      return;
    }
    try {
      var det = await getDetector();
      cropToFrame();
      var text = await det.detect(crop, crop.width, crop.height);
      if (text) {
        submit(text);
      }
    } catch (e) { /* keep scanning */ }
  }

  async function start() {
    if (stream) {
      return;
    }
    show(q('data-scan-error'), false);
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
    } catch (e) {
      show(q('data-scan-error'), true);
      return;
    }
    video.srcObject = stream;
    window.scrollTo(0, 0);
    show(live, true);
    document.documentElement.classList.add('scan-open');
    fitLive();
    stopBtn.focus();
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
    setMenu(false);
    if (!live.hidden) {
      show(live, false);
      document.documentElement.classList.remove('scan-open');
      startBtn.focus();
    }
  }

  // Camera only in a secure context (HTTPS or localhost).
  if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    show(q('data-scan-insecure'), true);
    show(startBtn, false);
  } else {
    startBtn.addEventListener('click', start);
    stopBtn.addEventListener('click', stop);
    menuToggle.addEventListener('click', function () { setMenu(menu.hidden); });
    // Other ways: close the camera first, then hand over to the photo picker or the code field.
    q('data-scan-use-photo').addEventListener('click', function () {
      stop();
      q('data-scan-photo').click();
    });
    q('data-scan-use-manual').addEventListener('click', function () {
      stop();
      input.focus();
      input.scrollIntoView({ block: 'center' });
    });
    live.addEventListener('click', function (event) {
      if (!menu.hidden && !menu.contains(event.target) && !menuToggle.contains(event.target)) {
        setMenu(false);
      }
    });
    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape' || live.hidden) {
        return;
      }
      if (!menu.hidden) {
        setMenu(false);
        menuToggle.focus();
      } else {
        stop();
      }
    });
    // Never leave the camera running in the background.
    window.addEventListener('pagehide', stop);
    window.addEventListener('resize', function () {
      if (!live.hidden) {
        fitLive();
      }
    });
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
