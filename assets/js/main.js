/* =====================================================================
   Campus Canteen - main.js
   A small helper script (no framework). Everything here also works
   without JavaScript, except the QR picture and the kitchen auto
   refresh.
   ===================================================================== */
(function () {
  'use strict';

  /* ------------------------------------------------------------------
   * 1. "Are you sure?" box before important actions
 *    Any form with data-confirm="message" asks first.
   * ------------------------------------------------------------------ */
  document.addEventListener('submit', function (event) {
    var form = event.target;
    var message = form.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
      event.preventDefault();
    }
  });

  /* ------------------------------------------------------------------
   * 2. Stop double clicks
   *    A form with data-submit-once only sends once, even if the button
   *    is clicked twice.
   *
   *    NOTE: the button is NOT disabled on purpose. A disabled button
   *    would not send its name/value, and this project uses the button
   *    value on the server (for example action=pay or action=fail).
   * ------------------------------------------------------------------ */
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form.hasAttribute('data-submit-once')) {
      return;
    }
    if (form.getAttribute('data-sending') === '1') {
      event.preventDefault(); // a second click does nothing
      return;
    }
    form.setAttribute('data-sending', '1');

    var button = event.submitter || form.querySelector('button[type="submit"]');
    if (button) {
      button.textContent = 'Working...';
    }
  });

  /* ------------------------------------------------------------------
   * 3. Draw the QR pickup code
   *    The picture is created in the browser by assets/js/qrcode.js
   *    (open source, MIT licence). Nothing is sent to the internet.
   *    The text under the picture stays visible, so staff can also
   *    type the code in by hand.
   *
   *    EVERY element with a data-code attribute gets a QR picture,
   *    so devices.php can show several QR codes on one page.
   * ------------------------------------------------------------------ */
  var boxes = document.querySelectorAll('[data-code]');
  if (boxes.length && typeof window.qrcode === 'function') {
    for (var i = 0; i < boxes.length; i++) {
      (function (box) {
        var text = box.getAttribute('data-code') || '';
        if (!text) { return; }
        try {
          var qr = window.qrcode(0, 'M'); // 0 = choose size automatically
          qr.addData(text);
          qr.make();
          box.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 2, scalable: true });
          var svg = box.querySelector('svg');
          if (svg) {
            svg.setAttribute('style', 'width:100%;height:100%;display:block;');
          }
        } catch (error) {
          /* Keep the text-only version, the code below the box is enough. */
        }
      })(boxes[i]);
    }
  }

  /* ------------------------------------------------------------------
   * 4. Kitchen screen: refresh itself so new orders show up on their
   *    own, and keep the scroll position after refreshing.
   * ------------------------------------------------------------------ */
  var kitchen = document.querySelector('[data-autorefresh]');
  if (kitchen) {
    var seconds = parseInt(kitchen.getAttribute('data-autorefresh'), 10) || 15;

    var saved = sessionStorage.getItem('canteenScroll');
    if (saved) {
      window.scrollTo(0, parseInt(saved, 10) || 0);
      sessionStorage.removeItem('canteenScroll');
    }

    window.setTimeout(function () {
      sessionStorage.setItem('canteenScroll', String(window.scrollY));
      window.location.reload();
    }, seconds * 1000);
  }

  /* ------------------------------------------------------------------
   * 5. Hide flash messages after a few seconds.
   * ------------------------------------------------------------------ */
  var alertBox = document.querySelector('.alert');
  if (alertBox) {
    window.setTimeout(function () {
      alertBox.style.transition = 'opacity .5s';
      alertBox.style.opacity = '0';
      window.setTimeout(function () { alertBox.remove(); }, 500);
    }, 6000);
  }
})();
