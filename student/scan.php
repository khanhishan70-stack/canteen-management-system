<?php
/**
 * student/scan.php  -  CAMERA payment scanner (Student System).
 *
 * WHAT THIS PAGE IS
 *   The student presses "Scan & pay", the phone camera opens, and the
 *   student points it at the payment QR code shown on the ordering
 *   screen. The picture is read inside the browser (assets/js/jsqr.js,
 *   open source, offline) and the demo payment page opens on its own.
 *
 *   It is exactly the same QR + demo payment as before - this page only
 *   replaces the phone's own camera app, so the whole flow stays inside
 *   this website (nice for the presentation).
 *
 *   No real money, no card details. The QR only holds a random link
 *   token, exactly like on student/payment.php.
 *
 * WHY THE PAGE MUST BE OPENED WITH https:// ON A PHONE
 *   Phones only allow a web page to use the camera when the address is
 *   secure (https:// or localhost). XAMPP already speaks https on port
 *   443, so on a phone use:  https://<pc-ip>/canteen/student/scan.php
 *   The certificate is self-made, so the phone asks once - tap
 *   "Advanced -> proceed". On this computer http://localhost/... works
 *   too, because the browser trusts localhost.
 *
 * IF THE CAMERA CANNOT BE USED
 *   Nothing breaks: the payment link under the QR picture (and the
 *   "Pay on this device" button on student/payment.php) still work.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$me = require_role('student');

/** Where the camera sends the student after a good scan (the demo pay page). */
$pay_path = 'pay.php';

$page_title = 'Scan & pay';
require __DIR__ . '/../includes/header.php';
?>

<div class="notice-sim">
  <h3>&#9888; Simulation only &mdash; no real payment</h3>
  <p style="margin:0">
    The camera only reads a <strong>secret link</strong> from the QR picture and opens the
    <strong>demo</strong> payment page. No card details are asked for, and no real money is ever charged.
  </p>
</div>

<div class="card narrow" style="text-align:center">
  <p class="portal-tag">&#128247; Scan &amp; pay</p>
  <h1>Point the camera at the QR</h1>
  <p class="muted">Open the payment QR code on your other screen
    (or ask at the counter) and hold this phone in front of it.</p>

  <div class="scan-box" id="scan-box"
       data-pay-path="<?= e($pay_path) ?>"
       data-site-base="<?= e(url('')) ?>">
    <video id="scan-video" class="scan-video" playsinline muted autoplay></video>
    <span class="scan-corner tl"></span>
    <span class="scan-corner tr"></span>
    <span class="scan-corner bl"></span>
    <span class="scan-corner br"></span>
    <span class="scan-line"></span>
  </div>

  <p class="scan-status" id="scan-status">Starting the camera&hellip;</p>

  <!-- No camera / permission refused? The printed link still works. -->
  <form id="scan-manual" class="scan-manual">
    <label for="manual-link" class="center">No camera? Paste the payment link</label>
    <input type="text" id="manual-link" name="manual_link"
           placeholder="http://.../canteen/pay.php?token=..." autocomplete="off"
           inputmode="url" style="text-align:center">
    <div class="form-actions" style="justify-content:center">
      <button class="btn btn-success" type="submit">Open payment page</button>
    </div>
  </form>

  <p class="muted small" style="margin-top:14px">
    The camera stays on this website only &mdash; nothing is sent to the internet.
  </p>
</div>

<div class="card narrow">
  <h3>&#128161; How to test it</h3>
  <ol class="muted small" style="padding-left:20px;margin:0">
    <li>On your laptop open <strong>My Orders</strong> &rarr; an order waiting for payment
      &rarr; it shows the big payment QR code.</li>
    <li>On this phone press <strong>Start camera</strong> above and point it at that QR.</li>
    <li>The demo payment page opens &rarr; press
      <strong>Confirm demo payment</strong> and the laptop screen turns to &ldquo;Paid&rdquo; by itself.</li>
  </ol>
</div>

<script src="<?= e(url('assets/js/jsqr.js')) ?>"></script>
<script src="<?= e(url('assets/js/scanner.js')) ?>"></script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
