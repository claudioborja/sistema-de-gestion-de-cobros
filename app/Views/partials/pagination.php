<?php
$pageSize = in_array((int) ($pageSize ?? 5), [5, 10, 25], true) ? (int) ($pageSize ?? 5) : 5;
$lastPage = max(1, (int) ceil($total / $pageSize));
?>
<footer class="pagination-bar">
    <p><span class="font-data"><?= $total ?></span> registros · Página <span class="font-data"><?= $page ?></span> de <span class="font-data"><?= $lastPage ?></span></p>
    <nav aria-label="Paginación">
        <div class="flex gap-2">
            <?php if ($page > 1): ?><a class="btn" rel="prev" href="<?= site_url($base) . '?' . esc(http_build_query(array_merge($params, ['page' => $page - 1])), 'attr') ?>">Anterior</a><?php endif ?>
            <?php if ($page * $pageSize < $total): ?><a class="btn" rel="next" href="<?= site_url($base) . '?' . esc(http_build_query(array_merge($params, ['page' => $page + 1])), 'attr') ?>">Siguiente</a><?php endif ?>
        </div>
    </nav>
</footer>
