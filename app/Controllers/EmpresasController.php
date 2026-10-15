<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Empresa;
use App\Models\Timbrado;
use App\Models\Usuario;

class EmpresasController extends Controller
{
    public function seleccionar(): void
    {
        $this->requiereLogin();
        $empresas = Usuario::empresas(Auth::id(), Auth::rol());
        $this->vista('empresas/seleccionar', ['titulo' => 'Elegir empresa', 'empresas' => $empresas], null);
    }

    public function activar(): void
    {
        $this->requiereLogin();
        $this->verificarCsrf();
        if (!Auth::seleccionarEmpresa((int) $this->entrada('empresa_id'))) {
            flash('error', 'No tenés acceso a esa empresa.');
            $this->redirigir('empresas/seleccionar');
        }
        $this->redirigir('inicio');
    }

    public function editar(): void
    {
        $this->requiereRol('ADMIN');
        $empresaId = $this->requiereEmpresa();
        $empresa = Empresa::buscar($empresaId);
        $this->vista('empresas/editar', [
            'titulo'     => 'Datos de la empresa',
            'empresa'    => $empresa,
            'formato'    => json_encode(Empresa::formato($empresa), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'timbrados'  => Timbrado::listarPorEmpresa($empresaId),
            'editando'   => isset($_GET['timbrado']) ? Timbrado::buscar((int) $_GET['timbrado'], $empresaId) : null,
        ]);
    }

    public function guardar(): void
    {
        $this->requiereRol('ADMIN');
        $empresaId = $this->requiereEmpresa();
        $this->verificarCsrf();

        $formato = (string) ($_POST['formato_impresion'] ?? '');
        if (!is_array(json_decode($formato, true))) {
            flash('error', 'El formato de impresión no es un JSON válido. No se guardaron los cambios.');
            $this->redirigir('empresas/editar');
        }
        $color = (string) $this->entrada('color', '#6d28d9');
        $datos = [
            'razon_social'           => (string) $this->entrada('razon_social', ''),
            'nombre_fantasia'        => (string) $this->entrada('nombre_fantasia', ''),
            'ruc'                    => (string) $this->entrada('ruc', ''),
            'actividad'              => (string) $this->entrada('actividad', ''),
            'direccion'              => (string) $this->entrada('direccion', ''),
            'telefono'               => (string) $this->entrada('telefono', ''),
            'email'                  => (string) $this->entrada('email', ''),
            'ciudad'                 => (string) $this->entrada('ciudad', ''),
            'color'                  => preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#6d28d9',
            'permite_stock_negativo' => !empty($_POST['permite_stock_negativo']),
            'formato_impresion'      => $formato,
        ];
        if ($datos['razon_social'] === '' || $datos['ruc'] === '') {
            flash('error', 'Razón social y RUC son obligatorios.');
            $this->redirigir('empresas/editar');
        }
        Empresa::actualizar($empresaId, $datos);
        Auth::seleccionarEmpresa($empresaId); // refresca nombre y color en la sesión
        flash('ok', 'Datos de la empresa guardados.');
        $this->redirigir('empresas/editar');
    }

    public function crearTimbrado(): void
    {
        $this->requiereRol('ADMIN');
        $empresaId = $this->requiereEmpresa();
        $this->verificarCsrf();
        $d = $this->datosTimbrado();
        $id = (int) $this->entrada('id', 0);
        $actual = $id ? Timbrado::buscar($id, $empresaId) : null;
        if ($id && !$actual) {
            flash('error', 'Ese timbrado no existe.');
            $this->redirigir('empresas/editar');
        }
        $error = $this->errorTimbrado($d, $actual);
        if ($error) {
            flash('error', $error);
            $this->redirigir('empresas/editar', $id ? ['timbrado' => $id] : []);
        }
        if ($actual) {
            Timbrado::actualizar($id, $empresaId, $d);
            flash('ok', 'Timbrado ' . $d['numero'] . ' actualizado.');
        } else {
            Timbrado::crear($empresaId, $d);
            flash('ok', 'Timbrado agregado.');
        }
        $this->redirigir('empresas/editar');
    }

    public function borrarTimbrado(): void
    {
        $this->requiereRol('ADMIN');
        $empresaId = $this->requiereEmpresa();
        $this->verificarCsrf();
        $t = Timbrado::buscar((int) $this->entrada('id'), $empresaId);
        if (!$t) {
            flash('error', 'Ese timbrado no existe.');
        } elseif ((int) $t['facturas'] > 0) {
            flash('error', 'El timbrado ' . $t['numero'] . ' ya tiene ' . (int) $t['facturas']
                . ' factura(s) emitida(s) y no se puede eliminar. Usá Desactivar para que no se use más.');
        } else {
            Timbrado::eliminar((int) $t['id'], $empresaId);
            flash('ok', 'Timbrado ' . $t['numero'] . ' eliminado.');
        }
        $this->redirigir('empresas/editar');
    }

    private function datosTimbrado(): array
    {
        return [
            'numero'           => trim((string) $this->entrada('numero', '')),
            'vigencia_desde'   => (string) $this->entrada('vigencia_desde', ''),
            'vigencia_hasta'   => (string) $this->entrada('vigencia_hasta', ''),
            'establecimiento'  => str_pad(trim((string) $this->entrada('establecimiento', '1')), 3, '0', STR_PAD_LEFT),
            'punto_expedicion' => str_pad(trim((string) $this->entrada('punto_expedicion', '1')), 3, '0', STR_PAD_LEFT),
            'numero_desde'     => entero($this->entrada('numero_desde', 1)),
            'numero_hasta'     => entero($this->entrada('numero_hasta', 9999999)),
        ];
    }

    /** Con facturas emitidas no se cambia el establecimiento ni el punto, y el rango tiene que seguir cubriendo lo usado. */
    private function errorTimbrado(array $d, ?array $actual): ?string
    {
        if ($d['numero'] === '' || $d['vigencia_desde'] === '' || $d['vigencia_hasta'] === ''
            || $d['vigencia_hasta'] < $d['vigencia_desde'] || $d['numero_hasta'] < $d['numero_desde']) {
            return 'Revisá los datos del timbrado (número, vigencia y rango).';
        }
        if ($actual && (int) $actual['facturas'] > 0) {
            if ($d['establecimiento'] !== $actual['establecimiento'] || $d['punto_expedicion'] !== $actual['punto_expedicion']) {
                return 'Este timbrado ya tiene facturas emitidas con ' . $actual['establecimiento'] . '-' . $actual['punto_expedicion']
                    . ', así que no se puede cambiar el establecimiento ni el punto. Para otro punto agregá un timbrado nuevo.';
            }
            if ($d['numero_desde'] > (int) $actual['primer_usado'] || $d['numero_hasta'] < (int) $actual['ultimo_usado']) {
                return 'El rango tiene que incluir las facturas ya emitidas (del ' . (int) $actual['primer_usado']
                    . ' al ' . (int) $actual['ultimo_usado'] . ').';
            }
        }
        return null;
    }

    public function estadoTimbrado(): void
    {
        $this->requiereRol('ADMIN');
        $empresaId = $this->requiereEmpresa();
        $this->verificarCsrf();
        Timbrado::cambiarActivo((int) $this->entrada('id'), $empresaId, $this->entrada('activo') === '1');
        $this->redirigir('empresas/editar');
    }

    /** Diseñador visual: se arrastra cada campo sobre la imagen de la hoja preimpresa. */
    public function diseno(): void
    {
        $this->requiereRol('ADMIN');
        $empresaId = $this->requiereEmpresa();
        $empresa = Empresa::buscar($empresaId);
        $this->vista('empresas/diseno', [
            'titulo'    => 'Diseño de impresión',
            'empresa'   => $empresa,
            'formato'   => Empresa::formato($empresa),
            'defecto'   => Empresa::formatoPorDefecto($empresa),
            'plantillas'=> Empresa::plantillas(),
            'fondo'     => Empresa::fondo($empresaId),
            'fondoPropio' => Empresa::fondo($empresaId, true) !== null,
        ]);
    }

    public function guardarDiseno(): void
    {
        $this->requiereRol('ADMIN');
        $empresaId = $this->requiereEmpresa();
        $this->verificarCsrf();
        $formato = json_decode((string) ($_POST['formato'] ?? ''), true);
        if (!is_array($formato) || !isset($formato['campos'], $formato['detalle'], $formato['pagina'])) {
            $this->json(['ok' => false, 'error' => 'El diseño recibido no es válido.'], 422);
        }
        Empresa::guardarFormato($empresaId, json_encode($formato, JSON_UNESCAPED_UNICODE));
        $this->json(['ok' => true]);
    }

    public function subirFondo(): void
    {
        $this->requiereRol('ADMIN');
        $empresaId = $this->requiereEmpresa();
        $this->verificarCsrf();
        $archivo = $_FILES['fondo'] ?? null;
        $info = ($archivo && $archivo['error'] === UPLOAD_ERR_OK) ? @getimagesize($archivo['tmp_name']) : false;
        $extensiones = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!$info || !isset($extensiones[$info['mime']])) {
            flash('error', 'Subí una imagen JPG o PNG de la hoja escaneada.');
        } elseif ($archivo['size'] > 8 * 1024 * 1024) {
            flash('error', 'La imagen pesa más de 8 MB. Escaneala con menos resolución (150 o 200 ppp alcanza).');
        } else {
            $carpeta = Empresa::carpetaFondos();
            if (!is_dir($carpeta)) {
                @mkdir($carpeta, 0775, true);
            }
            foreach (glob($carpeta . "/empresa-$empresaId.*") ?: [] as $viejo) {
                @unlink($viejo);
            }
            if (move_uploaded_file($archivo['tmp_name'], $carpeta . "/empresa-$empresaId." . $extensiones[$info['mime']])) {
                flash('ok', 'Imagen de la hoja cargada. Ahora arrastrá cada campo a su lugar.');
            } else {
                flash('error', 'No se pudo guardar la imagen. Revisá que la carpeta public/uploads/fondos tenga permiso de escritura.');
            }
        }
        $this->redirigir('empresas/diseno');
    }

    public function borrarFondo(): void
    {
        $this->requiereRol('ADMIN');
        $empresaId = $this->requiereEmpresa();
        $this->verificarCsrf();
        foreach (glob(Empresa::carpetaFondos() . "/empresa-$empresaId.*") ?: [] as $archivo) {
            @unlink($archivo);
        }
        flash('ok', 'Imagen de fondo quitada.');
        $this->redirigir('empresas/diseno');
    }
}
