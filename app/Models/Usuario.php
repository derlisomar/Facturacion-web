<?php
namespace App\Models;

use App\Core\Model;

class Usuario extends Model
{
    public static function buscarParaLogin(string $login): ?array
    {
        return self::uno('SELECT * FROM usuario WHERE lower(usuario) = lower(:l) OR lower(email) = lower(:l) LIMIT 1', ['l' => $login]);
    }

    public static function buscar(int $id): ?array
    {
        return self::uno('SELECT id, nombre, usuario, email, cedula, rol, activo, ultimo_acceso FROM usuario WHERE id = ?', [$id]);
    }

    public static function listar(): array
    {
        return self::todos('SELECT id, nombre, usuario, email, rol, activo, ultimo_acceso FROM usuario ORDER BY nombre');
    }

    /** Empresas que puede usar el usuario. El ADMIN ve todas. */
    public static function empresas(int $usuarioId, string $rol): array
    {
        if ($rol === 'ADMIN') {
            return self::todos('SELECT * FROM empresa WHERE activo ORDER BY id');
        }
        return self::todos(
            'SELECT e.* FROM empresa e JOIN usuario_empresa ue ON ue.empresa_id = e.id
             WHERE ue.usuario_id = ? AND e.activo ORDER BY e.id',
            [$usuarioId]
        );
    }

    public static function idsEmpresas(int $usuarioId): array
    {
        return array_map('intval', array_column(
            self::todos('SELECT empresa_id FROM usuario_empresa WHERE usuario_id = ?', [$usuarioId]),
            'empresa_id'
        ));
    }

    public static function guardar(array $d, ?int $id = null): int
    {
        $db = self::db();
        $db->beginTransaction();
        try {
            $params = [
                'nombre'  => $d['nombre'],
                'usuario' => $d['usuario'],
                'email'   => $d['email'] ?: null,
                'cedula'  => $d['cedula'] ?: null,
                'rol'     => $d['rol'],
                'activo'  => $d['activo'] ? 'true' : 'false',
            ];
            if ($id === null) {
                $params['hash'] = password_hash($d['clave'], PASSWORD_DEFAULT);
                $id = (int) self::valor(
                    'INSERT INTO usuario (nombre, usuario, email, cedula, rol, activo, password_hash)
                     VALUES (:nombre, :usuario, :email, :cedula, :rol, :activo, :hash) RETURNING id',
                    $params
                );
            } else {
                $params['id'] = $id;
                self::ejecutar(
                    'UPDATE usuario SET nombre = :nombre, usuario = :usuario, email = :email, cedula = :cedula,
                     rol = :rol, activo = :activo WHERE id = :id',
                    $params
                );
                if (!empty($d['clave'])) {
                    self::cambiarClave($id, $d['clave']);
                }
            }
            self::ejecutar('DELETE FROM usuario_empresa WHERE usuario_id = ?', [$id]);
            foreach ($d['empresas'] as $empresaId) {
                self::ejecutar('INSERT INTO usuario_empresa (usuario_id, empresa_id) VALUES (?, ?)', [$id, (int) $empresaId]);
            }
            $db->commit();
            return $id;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function cambiarClave(int $id, string $clave): void
    {
        self::ejecutar('UPDATE usuario SET password_hash = ? WHERE id = ?', [password_hash($clave, PASSWORD_DEFAULT), $id]);
    }

    public static function registrarAcceso(int $id): void
    {
        self::ejecutar('UPDATE usuario SET ultimo_acceso = now() WHERE id = ?', [$id]);
    }

    public static function existeUsuario(string $usuario, ?int $exceptoId = null): bool
    {
        return (bool) self::valor(
            'SELECT 1 FROM usuario WHERE lower(usuario) = lower(?) AND id <> ?',
            [$usuario, $exceptoId ?? 0]
        );
    }
}
