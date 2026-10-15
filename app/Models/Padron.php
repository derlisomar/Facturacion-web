<?php
namespace App\Models;

use App\Core\Model;

/**
 * Padrón de RUC de la DNIT (tabla padron_ruc). Lee los archivos ruc0 a ruc9 (texto con separador | ; , o
 * tabulación; .txt, .csv o .zip). Orden de columnas: RUC, razón social, DV, RUC anterior, estado.
 * Mismo criterio que el importador de dbstock.
 */
class Padron extends Model
{
    /** La tabla se agrega con database/actualizar_padron_ruc.sql en bases instaladas antes. */
    public static function instalado(): bool
    {
        return (int) self::valor("SELECT CASE WHEN to_regclass('padron_ruc') IS NULL THEN 0 ELSE 1 END") === 1;
    }

    public static function contar(): int
    {
        return (int) self::valor('SELECT COUNT(*) FROM padron_ruc');
    }

    /**
     * Busca por RUC (con o sin guion y dígito verificador). Devuelve el cliente ya cargado, si existe,
     * y hasta 8 coincidencias del padrón que todavía no son clientes.
     */
    public static function consultar(string $q): array
    {
        $q = trim($q);
        $ruc = $q;
        $dv = null;
        if (preg_match('/^(\d{1,12})\s*-\s*(\d?)$/', $q, $m)) {
            $ruc = $m[1];
            $dv = $m[2] !== '' ? $m[2] : null;
        }
        if (!preg_match('/^\d{3,12}$/', $ruc) || !self::instalado()) {
            return ['existente' => null, 'padron' => [], 'cargado' => false];
        }
        $existente = self::uno(
            "SELECT id, ruc, razon_social, direccion, telefono FROM cliente
             WHERE ruc = :q OR ruc = :ruc OR ruc LIKE :pref ORDER BY (ruc = :q) DESC LIMIT 1",
            ['q' => $q, 'ruc' => $ruc, 'pref' => $ruc . '-%']
        );
        $padron = [];
        foreach (self::todos('SELECT ruc, dv, nombre, estado FROM padron_ruc WHERE ruc LIKE ? ORDER BY ruc LIMIT 8', [$ruc . '%']) as $p) {
            if ($dv !== null && $p['dv'] !== null && $p['dv'] !== $dv) {
                continue;
            }
            $padron[] = [
                'ruc'    => $p['ruc'] . ($p['dv'] !== null ? '-' . $p['dv'] : ''),
                'nombre' => self::nombreFactura($p['nombre']),
                'original' => $p['nombre'],
                'estado' => $p['estado'],
            ];
        }
        $cargado = (int) self::valor('SELECT CASE WHEN EXISTS (SELECT 1 FROM padron_ruc) THEN 1 ELSE 0 END') === 1;
        return ['existente' => $existente, 'padron' => $padron, 'cargado' => $cargado];
    }

    /** Las personas vienen como "APELLIDOS, NOMBRES": en la factura va "NOMBRES APELLIDOS". */
    public static function nombreFactura(string $nombre): string
    {
        $partes = array_map('trim', explode(',', $nombre, 2));
        if (count($partes) === 2 && $partes[0] !== '' && $partes[1] !== '') {
            return $partes[1] . ' ' . $partes[0];
        }
        return $nombre;
    }

    public static function listar(string $q, string $estado, int $pagina, int $porPagina = 25): array
    {
        $where = [];
        $params = [];
        if ($q !== '') {
            if (preg_match('/^\d[\d\-]*$/', $q)) {
                $where[] = 'ruc LIKE ?';
                $params[] = explode('-', $q)[0] . '%';
            } else {
                foreach (preg_split('/\s+/', $q) as $palabra) {
                    $where[] = 'nombre ILIKE ?';
                    $params[] = '%' . str_replace(['%', '_'], ['\%', '\_'], $palabra) . '%';
                }
            }
        }
        if ($estado !== '') {
            $where[] = 'estado = ?';
            $params[] = $estado;
        }
        $sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total = $where ? (int) self::valor("SELECT COUNT(*) FROM padron_ruc $sql", $params) : self::contar();
        $filas = self::todos("SELECT * FROM padron_ruc $sql ORDER BY ruc LIMIT $porPagina OFFSET " . (($pagina - 1) * $porPagina), $params);
        return ['filas' => $filas, 'total' => $total];
    }

    /** Interpreta una línea. Devuelve null si no es un registro (encabezado, línea vacía). */
    public static function leerLinea(string $linea): ?array
    {
        $linea = trim($linea, "\xEF\xBB\xBF \t\r\n");
        if ($linea === '') {
            return null;
        }
        if (!mb_check_encoding($linea, 'UTF-8')) {
            $linea = mb_convert_encoding($linea, 'UTF-8', 'ISO-8859-1');
        }
        $sep = '|';
        foreach (['|', ';', "\t", ','] as $s) {
            if (substr_count($linea, $s) >= 2) {
                $sep = $s;
                break;
            }
        }
        $p = array_map(fn($x) => trim($x, " \t\"'"), explode($sep, $linea));
        $ruc = $p[0] ?? '';
        if (!preg_match('/^\d{1,12}[A-Za-z]?$/', $ruc) || trim($p[1] ?? '') === '') {
            return null;
        }
        $dv = $p[2] ?? '';
        return [
            'ruc'    => $ruc,
            'dv'     => preg_match('/^\d$/', $dv) ? $dv : null,
            'nombre' => mb_substr(preg_replace('/\s+/', ' ', $p[1]), 0, 255),
            'estado' => ($p[4] ?? '') !== '' ? mb_substr($p[4], 0, 40) : null,
        ];
    }

    /** Líneas de un archivo; si es .zip, de todos sus archivos de texto. */
    public static function lineas(string $ruta, bool $esZip): \Generator
    {
        if ($esZip) {
            if (!class_exists(\ZipArchive::class)) {
                throw new \RuntimeException('Para leer archivos .zip hay que activar la extensión zip de PHP (en php.ini quitá el ; de ;extension=zip). También podés descomprimirlos y subir los .txt.');
            }
            $zip = new \ZipArchive();
            if ($zip->open($ruta) !== true) {
                throw new \RuntimeException('No se pudo abrir el archivo .zip.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $h = $zip->getStream($zip->getNameIndex($i));
                if (!$h) {
                    continue;
                }
                while (($l = fgets($h)) !== false) {
                    yield $l;
                }
                fclose($h);
            }
            $zip->close();
            return;
        }
        $h = fopen($ruta, 'rb');
        if (!$h) {
            throw new \RuntimeException('No se pudo abrir el archivo.');
        }
        while (($l = fgets($h)) !== false) {
            yield $l;
        }
        fclose($h);
    }

    /** Carga el archivo; si un RUC ya estaba, lo actualiza. Devuelve [leídas, ignoradas]. */
    public static function importar(string $ruta, bool $esZip): array
    {
        $lote = [];
        $leidas = $ignoradas = 0;
        foreach (self::lineas($ruta, $esZip) as $linea) {
            $r = self::leerLinea($linea);
            if (!$r) {
                $ignoradas++;
                continue;
            }
            $lote[$r['ruc']] = $r; // un RUC repetido en el mismo lote rompería el ON CONFLICT
            $leidas++;
            if (count($lote) >= 1000) {
                self::volcar($lote);
                $lote = [];
            }
        }
        self::volcar($lote);
        return [$leidas, $ignoradas];
    }

    private static function volcar(array $lote): void
    {
        if (!$lote) {
            return;
        }
        $valores = [];
        $params = [];
        foreach ($lote as $r) {
            $valores[] = '(?, ?, ?, ?)';
            array_push($params, $r['ruc'], $r['dv'], $r['nombre'], $r['estado']);
        }
        self::ejecutar(
            'INSERT INTO padron_ruc (ruc, dv, nombre, estado) VALUES ' . implode(',', $valores) . '
             ON CONFLICT (ruc) DO UPDATE SET dv = EXCLUDED.dv, nombre = EXCLUDED.nombre, estado = EXCLUDED.estado',
            $params
        );
    }
}
