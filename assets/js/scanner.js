/* =====================================================================
   Campus Canteen - scanner.js
   The CAMERA inside the Student System ("Scan & pay" page).

   The student points the camera at the payment QR code, this script
   reads the picture (jsQR, MIT licence - works offline, no server and
   no API key), and the browser opens the demo payment page.

   Only a payment link is followed:
     - the secret token is pulled out with a regular expression and the
       link is BUILT AGAIN on this phone's own address, so a QR made on
       "localhost" still opens on 192.168.x.x and http/https never mix;
     - a pickup code (9F3A-71C4-...) only shows a friendly tip;
     - anything else is refused - a strange QR never sends the phone
       to another website.
   ===================================================================== */
(function () {
  'use strict';

  var box    = document.getElementById('scan-box');
  var video  = document.getElementById('scan-video');
  var status = document.getElementById('scan-status');
  if (!box || !video || !status) { return; }

  var payPath  = box.getAttribute('data-pay-path') || 'pay.php';
  var siteBase = box.getAttribute('data-site-base') || '/';

  var stream = null;
  var timer  = null;
  var going  = false;   /* true as soon as we navigate away */
  var lastText = '';
  var canvas = document.createElement('canvas');
  var ctx = canvas.getContext('2d', { willReadFrequently: true });

  /* ------------------------------------------------------------------
   * Status line under the camera (green = good, red = problem).
   * ------------------------------------------------------------------ */
  function say(text, kind) {
    status.textContent = text;
    status.className = 'scan-status' + (kind ? ' ' + kind : '');
  }

  /* ------------------------------------------------------------------
   * Stop the camera (on success and when leaving the page), so the
   * little "camera on" dot in the phone browser goes away.
   * ------------------------------------------------------------------ */
  function stopCamera() {
    going = true;
    if (timer) { window.clearTimeout(timer); timer = null; }
    if (stream) {
      var tracks = stream.getTracks();
      for (var i = 0; i < tracks.length; i++) { tracks[i].stop(); }
      stream = null;
    }
  }

  /* ------------------------------------------------------------------
   * Decide what the camera just read.
   * ------------------------------------------------------------------ */
  function handle(text) {
    if (going || text === lastText) { return; }
    lastText = text;

    /* 1) Payment QR  ->  pay.php?token=32-hex-chars (the demo pay page) */
    var token = /pay\.php\?token=([a-f0-9]{32})/i.exec(text);
    if (token) {
      going = true;
      say('Payment code found - opening the payment page...', 'scan-ok');
      box.classList.add('scan-hit');
      window.setTimeout(function () {
        window.location.href = siteBase + payPath + '?token=' + token[1].toLowerCase();
      }, 350);
      return;
    }

    /* 2) Pickup QR (9F3A-71C4-B0D8-2E55) - that one is shown TO the staff */
    if (/^[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}$/i.test(text.replace(/\s/g, ''))) {
      say('That is a pickup code - show it to the canteen staff, it is not a payment code.', 'scan-warn');
      return;
    }

    /* 3) A link of THIS website is opened (device board, menu, ...) */
    if (/^https?:\/\//i.test(text)) {
      try {
        var found = new URL(text);
        var site  = new URL(siteBase, window.location.href);
        if (found.origin === site.origin &&
            found.pathname.indexOf(site.pathname) === 0) {
          going = true;
          say('Opening this link...', 'scan-ok');
          window.location.href = text;
          return;
        }
      } catch (ignore) { /* not a usable link */ }
    }

    /* 4) Everything else is refused. */
    say('That is not a payment QR code of this canteen. Try again.', 'scan-warn');
  }

  /* ------------------------------------------------------------------
   * Read one frame of the video, every ~200 ms (5 frames per second is
   * plenty for a QR code and keeps the phone cool).
   * ------------------------------------------------------------------ */
  function tick() {
    if (going) { return; }
    timer = window.setTimeout(tick, 200);

    if (video.readyState !== 4 /* HAVE_ENOUGH_DATA */ || !video.videoWidth) {
      return;
    }

    /* Draw smaller than full size - jsQR is much faster that way. */
    var scale = Math.min(1, 720 / video.videoWidth);
    canvas.width  = Math.round(video.videoWidth  * scale);
    canvas.height = Math.round(video.videoHeight * scale);
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    var frame;
    try {
      frame = ctx.getImageData(0, 0, canvas.width, canvas.height);
    } catch (ignore) {
      return; /* camera not ready yet */
    }

    var code = window.jsQR ? window.jsQR(frame.data, canvas.width, canvas.height) : null;
    if (code && code.data) { handle(String(code.data)); }
  }

  /* ------------------------------------------------------------------
   * Turn the camera on. Back camera first, any camera as a fallback
   * (laptops only have a front camera).
   * ------------------------------------------------------------------ */
  function start() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      say('This browser cannot open the camera. Use the payment link below instead.', 'scan-error');
      return;
    }
    if (!window.isSecureContext) {
      say('The camera needs a secure page: open this site with https:// ... (see README).', 'scan-error');
      return;
    }

    say('Starting the camera...');

    navigator.mediaDevices.getUserMedia({
      video: { facingMode: { ideal: 'environment' } },
      audio: false
    }).catch(function () {
      return navigator.mediaDevices.getUserMedia({ video: true, audio: false });
    }).then(function (media) {
      stream = media;
      video.srcObject = media;
      return video.play();
    }).then(function () {
      say('Point the camera at the payment QR code.');
      tick();
    }).catch(function (error) {
      var name = error && error.name ? error.name : '';
      if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
        say('Camera blocked. Allow the camera for this page and reload.', 'scan-error');
      } else if (name === 'NotFoundError' || name === 'OverconstrainedError') {
        say('No camera was found on this device. Use the payment link below instead.', 'scan-error');
      } else {
        say('The camera could not be started. Use the payment link below instead.', 'scan-error');
      }
    });
  }

  /* ------------------------------------------------------------------
   * Manual way out: paste the link printed under the QR picture.
   * ------------------------------------------------------------------ */
  var manual = document.getElementById('scan-manual');
  if (manual) {
    manual.addEventListener('submit', function (event) {
      event.preventDefault();
      var field = document.getElementById('manual-link');
      handle(field ? String(field.value || '').trim() : '');
    });
  }

  /* The camera is switched off the moment the page is left. */
  window.addEventListener('pagehide', stopCamera);

  start();
})();
