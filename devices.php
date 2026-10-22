<?php
/**
 * devices.php  -  DEMO SIGN-BOARD (no login needed).
 *
 * Shows one QR picture per device, so everybody on the team can
 * simply SCAN their own code with the phone camera and land
 * directly on their system:
 *
 *   - Student phone  -> student system (pay by QR, track order)
 *   - Staff phone    -> staff system (order board, verify pickup)
 *   - Chef phone     -> chef kitchen screen
 *   - Laptop         -> student system for ordering the food
 *
 * The links always use THIS computer's WiFi (LAN) address, so any
 * phone on the same WiFi can open them. "localhost" would not work
 * on a phone (there it means the phone itself).
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

/**
 * Find this computer's address on the local network.
 * (We "connect" a dummy UDP socket to 8.8.8.8 - no data is ever
 *  sent, Windows only tells us which local address would be used.)
 */
function demo_lan_ip(): string
{
    $socket = @stream_socket_client('udp://8.8.8.8:53', $errno, $errstr, 1);
    if ($socket) {
        /* false = the LOCAL address of this computer, e.g. "192.168.0.118:50001" */
        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        if ($name) {
            $ip = explode(':', $name)[0];
            if (filter_var($ip, FILTER_VALIDATE_IP) && strpos($ip, '127.') !== 0) {
                return $ip;
            }
        }
    }
    return gethostbyname(gethostname());
}

$ip   = demo_lan_ip();
$base = 'http://' . $ip . '/canteen/';

$devices = [
    [
        'icon' => '&#127860;',
        'who'  => 'Student phone',
        'note' => 'Scan the payment QR and pay (demo), follow the order, show the pickup QR at the counter.',
        'link' => 'student/',
    ],
    [
        'icon' => '&#129302;',
        'who'  => 'Laptop (ordering)',
        'note' => 'The ordering screen. The student picks the food here and the payment QR appears here.',
        'link' => 'student/',
    ],
    [
        'icon' => '&#128188;',
        'who'  => 'Staff phone',
        'note' => 'See every order, check payments, send orders to the chef, verify pickup codes, reports.',
        'link' => 'staff/',
    ],
    [
        'icon' => '&#127859;',
        'who'  => 'Chef phone',
        'note' => 'The kitchen screen: Accept, Preparing, Ready - one big button for each step.',
        'link' => 'chef/',
    ],
];

$page_title = 'Demo devices';
require __DIR__ . '/includes/header.php';
?>

<div class="notice-sim">
  <h3>&#128241; Scan-in page for the demo</h3>
  <p style="margin:0">
    Every phone must be connected to the <strong>same WiFi</strong> as this computer.
    This computer's WiFi address is:
    <code style="font-size:1.1rem"><?= e($ip) ?></code>
  </p>
</div>

<div class="hero">
  <h1>&#128241;&#128187; One QR code per device</h1>
  <p>Scan the code for your job with the phone camera &mdash; it opens your system straight away.</p>
</div>

<div class="steps">
  <?php foreach ($devices as $d): ?>
    <div class="step" style="text-align:center">
      <div class="step-num"><?= e($d['icon']) ?></div>
      <strong><?= e($d['who']) ?></strong>
      <div id="qrcode" class="qr-image" data-code="<?= e($base . $d['link']) ?>"></div>
      <p class="muted small" style="word-break:break-all"><code><?= e($base . $d['link']) ?></code></p>
      <p class="muted small"><?= e($d['note']) ?></p>
    </div>
  <?php endforeach; ?>
</div>

<div class="card" style="margin-top:20px">
  <h3>&#9888; Simulation only</h3>
  <p class="muted small">This is a diploma project. The payment is a demo &mdash;
    no real money, no bank, no card details. The QR pictures contain only these
    local links, nothing personal.</p>
</div>

<script src="<?= e(url('assets/js/qrcode.js')) ?>"></script>
<?php require __DIR__ . '/includes/footer.php';
