<?php
// Espera: $ruta, $pagina, $total, $porPagina, $params
$paginas = (int) ceil($total / $porPagina);
if ($paginas > 1): ?>
<nav class="paginacion">
    <?php if ($pagina > 1): ?><a href="<?= url($ruta, $params + ['pagina' => $pagina - 1]) ?>">&laquo; Anterior</a><?php endif; ?>
    <span>Página <?= $pagina ?> de <?= $paginas ?> (<?= $total ?> registros)</span>
    <?php if ($pagina < $paginas): ?><a href="<?= url($ruta, $params + ['pagina' => $pagina + 1]) ?>">Siguiente &raquo;</a><?php endif; ?>
</nav>
<?php endif;
