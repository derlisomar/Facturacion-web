<?php
namespace App\Models;

use App\Core\Model;

class Timbrado extends Model
{
    public static function listarPorEmpresa(int $empresaId): array
    {
        return self::todos(
            'SELECT t.*, (SELECT COUNT(*) FROM factura f WHERE f.timbrado_id = t.id) AS facturas,
                    (SELECT MIN(numero) FROM factura f WHERE f.timbrado_id = t.id) AS primer_usado,
                    (SELECT MAX(numero) FROM factura f WHERE f.timbrado_id = t.id) AS ultimo_usado
             FROM timbrado t WHERE t.empresa_id = ? ORDER BY t.activo DESC, t.vigencia_hasta DESC, t.id DESC',
            [$empresaId]
        );
    }

    public static function buscar(int $id, int $empresaId): ?array
    {
        return self::uno(
            'SELECT t.*, (SELECT COUNT(*) FROM factura f WHERE f.timbrado_id = t.id) AS facturas,
                    (SELECT MIN(numero) FROM factura f WHERE f.timbrado_id = t.id) AS primer_usado,
                    (SELECT MAX(numero) FROM factura f WHERE f.timbrado_id = t.id) AS ultimo_usado
             FROM timbrado t WHERE t.id = ? AND t.empresa_id = ?',
            [$id, $empresaId]
        );
    }

    /** Timbrado activo y vigente para la fecha dada. */
    public static function vigente(int $empresaId, string $fecha): ?array
    {
        return self::uno(
            'SELECT * FROM timbrado WHERE empresa_id = ? AND activo AND ? BETWEEN vigencia_desde AND vigencia_hasta
             ORDER BY vigencia_desde DESC LIMIT 1',
            [$empresaId, $fecha]
        );
    }

    public static function siguienteNumero(array $timbrado): int
    {
        $ultimo = self::valor('SELECT MAX(numero) FROM factura WHERE timbrado_id = ?', [$timbrado['id']]);
        $numero = $ultimo ? (int) $ultimo + 1 : (int) $timbrado['numero_desde'];
        // Si ese número ya lo usó otro timbrado de la misma empresa (mismo establecimiento y punto), se salta.
        while ($numero < (int) $timbrado['numero_hasta']
            && Factura::numeroUsado((int) $timbrado['empresa_id'], self::numeroCompleto($timbrado, $numero))) {
            $numero++;
        }
        return $numero;
    }

    public static function numeroCompleto(array $timbrado, int $numero): string
    {
        return $timbrado['establecimiento'] . '-' . $timbrado['punto_expedicion'] . '-' . str_pad((string) $numero, 7, '0', STR_PAD_LEFT);
    }

    public static function crear(int $empresaId, array $d): void
    {
        self::ejecutar(
            'INSERT INTO timbrado (empresa_id, numero, vigencia_desde, vigencia_hasta, establecimiento, punto_expedicion, numero_desde, numero_hasta)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$empresaId, $d['numero'], $d['vigencia_desde'], $d['vigencia_hasta'], $d['establecimiento'],
             $d['punto_expedicion'], $d['numero_desde'], $d['numero_hasta']]
        );
    }

    public static function cambiarActivo(int $id, int $empresaId, bool $activo): void
    {
        self::ejecutar('UPDATE timbrado SET activo = ? WHERE id = ? AND empresa_id = ?', [$activo ? 'true' : 'false', $id, $empresaId]);
    }

    public static function actualizar(int $id, int $empresaId, array $d): void
    {
        self::ejecutar(
            'UPDATE timbrado SET numero = ?, vigencia_desde = ?, vigencia_hasta = ?, establecimiento = ?, punto_expedicion = ?,
                    numero_desde = ?, numero_hasta = ?
             WHERE id = ? AND empresa_id = ?',
            [$d['numero'], $d['vigencia_desde'], $d['vigencia_hasta'], $d['establecimiento'], $d['punto_expedicion'],
             $d['numero_desde'], $d['numero_hasta'], $id, $empresaId]
        );
    }

    /** Solo se borra si no tiene facturas: las facturas emitidas necesitan su timbrado. */
    public static function eliminar(int $id, int $empresaId): void
    {
        self::ejecutar('DELETE FROM timbrado WHERE id = ? AND empresa_id = ?', [$id, $empresaId]);
    }
}
