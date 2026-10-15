<h1 class="titulo"><?= e($titulo) ?></h1>
<form method="post" action="<?= url('clientes/guardar') ?>" class="tarjeta formulario">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e($cliente['id']) ?>">
    <div class="fila">
        <label>RUC / CI *<input name="ruc" id="ruc" value="<?= e($cliente['ruc']) ?>" required maxlength="20" placeholder="80012345-6" autocomplete="off"></label>
        <label class="grande">Razón social / Nombre *<input name="razon_social" id="razon_social" value="<?= e($cliente['razon_social']) ?>" required maxlength="150"></label>
    </div>
    <div class="rp-aviso" id="ruc-padron" hidden></div>
    <label>Dirección<input name="direccion" value="<?= e($cliente['direccion']) ?>" maxlength="200"></label>
    <div class="fila">
        <label>Teléfono<input name="telefono" value="<?= e($cliente['telefono']) ?>" maxlength="50"></label>
        <label>Celular<input name="celular" value="<?= e($cliente['celular']) ?>" maxlength="50"></label>
        <label>Correo<input type="email" name="email" value="<?= e($cliente['email']) ?>" maxlength="100"></label>
    </div>
    <?php if ($cliente['id']): ?>
        <label class="check"><input type="checkbox" name="activo" value="1" <?= $cliente['activo'] ? 'checked' : '' ?>> Activo</label>
    <?php endif; ?>
    <div class="botones">
        <a href="<?= url('clientes') ?>" class="btn">Cancelar</a>
        <button class="btn btn-primario">Guardar</button>
    </div>
</form>
<?php if (!$cliente['id']): ?>
<script src="<?= asset('js/ruc-padron.js') ?>"></script>
<script>
RucPadron({
    ruc: document.getElementById('ruc'), nombre: document.getElementById('razon_social'), aviso: document.getElementById('ruc-padron'),
    url: <?= json_encode(url('padron/ruc')) ?>, textoExistente: 'Abrir su ficha',
    alExistente: (c) => { window.location = <?= json_encode(url('clientes/editar')) ?> + (<?= json_encode(url('clientes/editar')) ?>.includes('?') ? '&' : '?') + 'id=' + c.id; },
});
</script>
<?php endif; ?>
