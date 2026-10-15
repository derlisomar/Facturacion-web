<h1 class="titulo"><?= e($titulo) ?></h1>
<form method="post" action="<?= url('usuarios/guardar') ?>" class="tarjeta formulario">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e($usuario['id']) ?>">
    <div class="fila">
        <label class="grande">Nombre *<input name="nombre" value="<?= e($usuario['nombre']) ?>" required maxlength="100"></label>
        <label>Usuario *<input name="usuario" value="<?= e($usuario['usuario']) ?>" required maxlength="40"></label>
    </div>
    <div class="fila">
        <label>Correo<input type="email" name="email" value="<?= e($usuario['email']) ?>" maxlength="100"></label>
        <label>Cédula<input name="cedula" value="<?= e($usuario['cedula']) ?>" maxlength="20"></label>
        <label><?= $usuario['id'] ? 'Nueva contraseña (vacío = no cambia)' : 'Contraseña *' ?>
            <input type="password" name="clave" autocomplete="new-password" <?= $usuario['id'] ? '' : 'required' ?> minlength="6"></label>
    </div>
    <div class="fila">
        <label>Rol
            <select name="rol">
                <?php foreach (['ADMIN' => 'Administrador', 'FACTURADOR' => 'Facturador', 'CONSULTA' => 'Solo consulta'] as $v => $t): ?>
                    <option value="<?= $v ?>" <?= $usuario['rol'] === $v ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <fieldset>
            <legend>Empresas que puede usar</legend>
            <?php foreach ($empresas as $emp): ?>
                <label class="check"><input type="checkbox" name="empresas[]" value="<?= (int) $emp['id'] ?>"
                    <?= in_array((int) $emp['id'], $asignadas, true) ? 'checked' : '' ?>> <?= e($emp['nombre_fantasia'] ?: $emp['razon_social']) ?></label>
            <?php endforeach; ?>
            <small class="gris">El administrador ve todas las empresas.</small>
        </fieldset>
    </div>
    <?php if ($usuario['id']): ?>
        <label class="check"><input type="checkbox" name="activo" value="1" <?= $usuario['activo'] ? 'checked' : '' ?>> Activo</label>
    <?php endif; ?>
    <div class="botones">
        <a href="<?= url('usuarios') ?>" class="btn">Cancelar</a>
        <button class="btn btn-primario">Guardar</button>
    </div>
</form>
