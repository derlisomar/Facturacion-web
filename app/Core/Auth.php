<?php
namespace App\Core;

use App\Models\Empresa;
use App\Models\Usuario;

class Auth
{
    public static function intentar(string $login, string $clave): bool
    {
        $usuario = Usuario::buscarParaLogin($login);
        if ($usuario === null || !$usuario['activo']) {
            return false;
        }

        $hash = $usuario['password_hash'];
        if (str_starts_with($hash, 'legacy:')) {
            // Contraseña migrada del sistema Java (texto plano): se valida y se convierte a hash.
            $valida = hash_equals(trim(substr($hash, 7)), $clave);
            if ($valida) {
                Usuario::cambiarClave((int) $usuario['id'], $clave);
            }
        } else {
            $valida = password_verify($clave, $hash);
            if ($valida && password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                Usuario::cambiarClave((int) $usuario['id'], $clave);
            }
        }
        if (!$valida) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['usuario'] = [
            'id'     => (int) $usuario['id'],
            'nombre' => $usuario['nombre'],
            'rol'    => $usuario['rol'],
        ];
        $_SESSION['empresas'] = array_map('intval', array_column(Usuario::empresas((int) $usuario['id'], $usuario['rol']), 'id'));
        unset($_SESSION['empresa']);
        if (count($_SESSION['empresas']) === 1) {
            self::seleccionarEmpresa($_SESSION['empresas'][0]);
        }
        Usuario::registrarAcceso((int) $usuario['id']);
        return true;
    }

    public static function salir(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public static function check(): bool
    {
        return isset($_SESSION['usuario']);
    }

    public static function usuario(): ?array
    {
        return $_SESSION['usuario'] ?? null;
    }

    public static function id(): ?int
    {
        return $_SESSION['usuario']['id'] ?? null;
    }

    public static function rol(): ?string
    {
        return $_SESSION['usuario']['rol'] ?? null;
    }

    public static function esAdmin(): bool
    {
        return self::rol() === 'ADMIN';
    }

    public static function puedeFacturar(): bool
    {
        return in_array(self::rol(), ['ADMIN', 'FACTURADOR'], true);
    }

    public static function empresasPermitidas(): array
    {
        return $_SESSION['empresas'] ?? [];
    }

    public static function seleccionarEmpresa(int $empresaId): bool
    {
        if (!in_array($empresaId, self::empresasPermitidas(), true)) {
            return false;
        }
        $empresa = Empresa::buscar($empresaId);
        if ($empresa === null) {
            return false;
        }
        $_SESSION['empresa'] = [
            'id'     => (int) $empresa['id'],
            'codigo' => $empresa['codigo'],
            'nombre' => $empresa['nombre_fantasia'] ?: $empresa['razon_social'],
            'color'  => $empresa['color'],
        ];
        return true;
    }

    public static function empresa(): ?array
    {
        return $_SESSION['empresa'] ?? null;
    }

    public static function empresaId(): ?int
    {
        return $_SESSION['empresa']['id'] ?? null;
    }
}
