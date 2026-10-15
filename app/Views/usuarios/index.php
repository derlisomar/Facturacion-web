<div class="encabezado">
    <h1 class="titulo">Usuarios</h1>
    <a href="<?= url('usuarios/nuevo') ?>" class="btn btn-primario"><i class="fas fa-plus"></i> Nuevo usuario</a>
</div>
<div class="tarjeta">
    <table class="tabla">
        <thead><tr><th>Nombre</th><th>Usuario</th><th>Correo</th><th>Rol</th><th>Último acceso</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($usuarios as $u): ?>
            <tr class="<?= $u['activo'] ? '' : 'inactivo' ?>">
                <td><?= e($u['nombre']) ?></td><td><?= e($u['usuario']) ?></td><td><?= e($u['email']) ?></td>
                <td><span class="etiqueta gris"><?= e($u['rol']) ?></span></td>
                <td><?= $u['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acceso'])) : '—' ?></td>
                <td class="der"><a href="<?= url('usuarios/editar', ['id' => $u['id']]) ?>"><i class="fas fa-pen"></i></a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
