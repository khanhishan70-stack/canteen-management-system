<?php
/**
 * footer.php  -  Bottom of every page.
 */
?>
</main>

<footer class="site-footer">
  <div class="container">
    <p><strong><?= e(APP_NAME) ?></strong> &mdash; Diploma DBMS project.</p>
    <p class="muted">Payment on this site is a <strong>simulation only</strong>. No real money is ever charged.</p>
  </div>
</footer>

<script src="<?= e(url('assets/js/main.js')) ?>"></script>
<?php
/* The staff phone and the chef phone poll for pop-up messages.
   No other page needs this script. */
$_footer_user = current_user();
if ($_footer_user !== null && in_array($_footer_user['role'], ['manager', 'chef'], true)): ?>
<script src="<?= e(url('assets/js/notifications.js')) ?>"
        data-notify-url="<?= e(url('api/notifications.php')) ?>"></script>
<?php endif; ?>
</body>
</html>
