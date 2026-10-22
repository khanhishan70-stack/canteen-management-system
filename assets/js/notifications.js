/* =====================================================================
   Campus Canteen - notifications.js
   Runs ONLY on the staff phone and the chef phone.

   Every POLL_SECONDS it asks api/notifications.php for new messages.
   Anything unread is shown as a pop-up toast, a short beep is played
   and (where the browser allows it) a system notification is raised.

   There is no WebSocket and no framework - plain polling works on
   every phone browser and on any local Wi-Fi, which is all a demo
   needs. Two devices logged in with the same role both ring.
   ===================================================================== */
(function () {
  'use strict';

  var script  = document.currentScript;
  var url     = (script && script.getAttribute('data-notify-url')) || 'api/notifications.php';
  var POLL_MS = 5000;          // ask the server every 5 seconds
  var TOAST_MS = 7000;         // keep a pop-up on screen for 7 seconds
  var SEEN_MAX = 60;           // remember this many titles to skip repeats

  var seen = Object.create(null);
  var seenList = [];
  var busy = false;

  /* ------------------------------------------------------------------
   * 1. Ask the server once, show whatever comes back.
   * ------------------------------------------------------------------ */
  function poll() {
    if (busy || document.hidden) {
      return;                  // never stack requests, skip hidden tabs
    }
    busy = true;

    fetch(url, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
      cache: 'no-store'
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data && data.ok && Array.isArray(data.notifications)) {
          // Newest first on screen.
          data.notifications.slice().reverse().forEach(showNotification);
        }
      })
      .catch(function () { /* server briefly away - try again later */ })
      .then(function () { busy = false; });
  }

  /* ------------------------------------------------------------------
   * 2. Show ONE pop-up.
   * ------------------------------------------------------------------ */
  function showNotification(n) {
    if (!n || !n.title) { return; }

    // Same message twice inside a short time? Skip it.
    var key = n.title + '|' + (n.message || '');
    if (seen[key]) { return; }
    seen[key] = 1;
    seenList.push(key);
    if (seenList.length > SEEN_MAX) {
      delete seen[seenList.shift()];
    }

    toast(n);
    beep();
    systemNotification(n);
  }

  /* ------------------------------------------------------------------
   * 3. The toast box itself.
   * ------------------------------------------------------------------ */
  function toast(n) {
    var box = document.createElement('div');
    box.className = 'toast-pop';
    box.setAttribute('role', 'alert');

    var head = document.createElement('div');
    head.className = 'toast-head';
    head.textContent = n.title;

    var body = document.createElement('div');
    body.className = 'toast-body';
    body.textContent = n.message || '';

    var time = document.createElement('div');
    time.className = 'toast-time';
    time.textContent = n.at || '';

    box.appendChild(head);
    box.appendChild(body);
    box.appendChild(time);

    // A click opens the matching order when we know its number.
    if (n.order_id) {
      box.style.cursor = 'pointer';
      box.addEventListener('click', function () {
        var base = document.querySelector('script[data-notify-url]');
        var root = '';
        if (base) {
          var src = base.getAttribute('src') || '';
          root = src.replace(/assets\/js\/notifications\.js.*$/, '');
        }
        var target = root + (box.getAttribute('data-role') === 'chef'
          ? 'chef/kitchen.php'
          : 'manager/dashboard.php');
        window.location.href = target;
      });
      box.setAttribute('data-role', isChef() ? 'chef' : 'manager');
    }

    document.body.appendChild(box);

    // Slide in, then leave.
    window.setTimeout(function () { box.classList.add('toast-out'); }, TOAST_MS);
    window.setTimeout(function () {
      if (box.parentNode) { box.parentNode.removeChild(box); }
    }, TOAST_MS + 600);
  }

  function isChef() {
    var nav = document.querySelector('.nav-system');
    return !!nav && nav.textContent.indexOf('Chef') !== -1;
  }

  /* ------------------------------------------------------------------
   * 4. A short beep made with the Web Audio API.
   *    No sound file is needed, so the project stays offline.
   * ------------------------------------------------------------------ */
  function beep() {
    try {
      var Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) { return; }
      var ctx = new Ctx();
      if (ctx.state === 'suspended' && ctx.resume) { ctx.resume(); }

      var osc  = ctx.createOscillator();
      var gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.value = 880;                 // pleasant high note
      gain.gain.setValueAtTime(0.0001, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.25, ctx.currentTime + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.45);

      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start();
      osc.stop(ctx.currentTime + 0.5);
      osc.onended = function () { ctx.close(); };
    } catch (err) { /* audio is a bonus, never a blocker */ }
  }

  /* ------------------------------------------------------------------
   * 5. The phone's own notification banner, when the user allows it.
   * ------------------------------------------------------------------ */
  function systemNotification(n) {
    try {
      if (!('Notification' in window)) { return; }
      if (Notification.permission === 'granted') {
        fire(n);
      } else if (Notification.permission !== 'denied') {
        Notification.requestPermission().then(function (p) {
          if (p === 'granted') { fire(n); }
        });
      }
    } catch (err) { /* ignore */ }
  }

  function fire(n) {
    try {
      var note = new Notification(n.title, {
        body: n.message || '',
        tag: 'canteen-' + (n.order_id || Date.now()),
        silent: true            // the beep above already plays
      });
      note.onclick = function () {
        window.focus();
        note.close();
      };
    } catch (err) { /* ignore */ }
  }

  /* ------------------------------------------------------------------
   * 6. Start: first pull right away, then every POLL_MS.
   *    Ask for permission as soon as the page opens, so the very first
   *    order of the demo already shows a system banner.
   * ------------------------------------------------------------------ */
  if ('Notification' in window && Notification.permission === 'default') {
    try { Notification.requestPermission(); } catch (err) { /* ignore */ }
  }

  poll();
  window.setInterval(poll, POLL_MS);

  // When the phone screen wakes up, check immediately.
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) { poll(); }
  });
})();
