<?php
// includes/layout/close.php — closes <main>, .app-main and .app; loads the scripts (classic, in this order).
// app.js is loaded with `defer` in head.php, so it runs after Bootstrap and SweetAlert2 below and can theme Swal.
?>
</main>
</div><!-- .app-main -->
</div><!-- .app -->
<?= vendorScriptTag('bootstrap', 'js/bootstrap.bundle.min.js') ?>
<?= vendorScriptTag('sweetalert2', 'sweetalert2.all.min.js') ?>
<script src="/assets/js/admin.js?v=<?= (int) @filemtime(PUBLIC_PATH . '/assets/js/admin.js') ?>"></script>
<?php if (NOTIFICATIONS_ENABLED): ?><script src="/assets/js/notifications.js?v=<?= (int) @filemtime(PUBLIC_PATH . '/assets/js/notifications.js') ?>"></script><?php endif; ?>
</body>
</html>
