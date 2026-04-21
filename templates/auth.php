<?php
declare(strict_types=1);
$canonical = rtrim(\Stain\Config::get('APP_URL', 'http://localhost:8080'), '/') . '/auth';
$hideAuthPopup = true;
ob_start();
?>
<section class="auth-wrap">
  <?php $authFormUid = 'page'; require __DIR__ . '/partials/auth_forms.php'; ?>
</section>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
