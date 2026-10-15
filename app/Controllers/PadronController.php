<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Padron;

class PadronController extends Controller
{
    public function index(): void
    {
        $this->requiereEmpresa();
        $instalado = Padron::instalado();
        $q = trim((string) $this->entrada('q', ''));
        $estado = (string) $this->entrada('estado', '');
        $pagina = max(1, (int) $this->entrada('pagina', 1));
        $resultado = $instalado ? Padron::listar($q, $estado, $pagina) : ['filas' => [], 'total' => 0];
        $this->vista('padron/index', [
            'titulo'    => 'Padrón RUC (DNIT)',
            'instalado' => $instalado,
            'cantidad'  => $instalado ? Padron::contar() : 0,
            'filas'     => $resultado['filas'],
            'total'     => $resultado['total'],
            'pagina'    => $pagina,
            'busqueda'  => $q,
            'estado'    => $estado,
        ]);
    }

    public function importar(): void
    {
        $this->requiereEmpresa();
        $this->requiereRol('ADMIN');
        $this->verificarCsrf();
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $archivos = $_FILES['archivos'] ?? null;
        if (!$archivos || !is_array($archivos['name']) || ($archivos['name'][0] ?? '') === '') {
            // Si el envío supera post_max_size, PHP descarta todo y $_FILES llega vacío.
            flash('error', 'No llegó ningún archivo. Si elegiste varios archivos grandes, subilos de a uno o aumentá upload_max_filesize y post_max_size en php.ini.');
            $this->redirigir('padron');
        }
        $antes = Padron::contar();
        $leidas = $ignoradas = 0;
        $errores = [];
        foreach ($archivos['name'] as $i => $nombre) {
            if ($archivos['error'][$i] !== UPLOAD_ERR_OK) {
                $errores[] = $nombre . ': ' . ($archivos['error'][$i] === UPLOAD_ERR_INI_SIZE || $archivos['error'][$i] === UPLOAD_ERR_FORM_SIZE
                    ? 'supera el tamaño permitido (upload_max_filesize en php.ini)' : 'no se pudo subir');
                continue;
            }
            $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
            if (!in_array($ext, ['txt', 'csv', 'zip'], true)) {
                $errores[] = $nombre . ': solo se aceptan .txt, .csv o .zip';
                continue;
            }
            try {
                [$l, $ig] = Padron::importar($archivos['tmp_name'][$i], $ext === 'zip');
                $leidas += $l;
                $ignoradas += $ig;
            } catch (\Throwable $ex) {
                $errores[] = $nombre . ': ' . $ex->getMessage();
            }
        }
        $despues = Padron::contar();
        if ($leidas) {
            flash('ok', sprintf('Padrón actualizado: %s registros leídos, %s nuevos. Total en el padrón: %s.',
                number_format($leidas, 0, ',', '.'), number_format($despues - $antes, 0, ',', '.'), number_format($despues, 0, ',', '.')));
        }
        if ($errores || !$leidas) {
            flash('error', $errores ? implode(' · ', $errores) : 'No se encontró ningún RUC válido en los archivos.');
        }
        $this->redirigir('padron');
    }

    /** JSON: cliente existente con ese RUC y coincidencias del padrón. */
    public function ruc(): void
    {
        $this->requiereEmpresa();
        if (!Auth::puedeFacturar()) {
            $this->json(['error' => 'Sin permiso.'], 403);
        }
        $this->json(Padron::consultar((string) $this->entrada('q', '')));
    }
}
