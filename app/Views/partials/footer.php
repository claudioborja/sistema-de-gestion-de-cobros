<?php
$footerYear = (new DateTimeImmutable('now', new DateTimeZone('America/Guayaquil')))->format('Y');
$fixed = $fixed ?? false;
?>
<footer class="footer footer-center border-t border-base-300 bg-base-100 px-4 py-4 text-center text-sm text-base-content/70 sm:px-6 lg:px-8<?= $fixed ? ' app-page-footer' : '' ?>">
    <p class="inline-flex flex-wrap items-baseline justify-center gap-x-1"><span class="font-semibold text-base-content">Cobros</span><span>· © <?= esc($footerYear) ?> Sistema de gestión de cobranzas</span></p>
</footer>
