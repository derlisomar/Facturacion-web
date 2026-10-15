<?php
namespace App\Models;

use App\Core\Model;

class Empresa extends Model
{
    public static function buscar(int $id): ?array
    {
        return self::uno('SELECT * FROM empresa WHERE id = ?', [$id]);
    }

    public static function listarActivas(): array
    {
        return self::todos('SELECT * FROM empresa WHERE activo ORDER BY id');
    }

    public static function listar(): array
    {
        return self::todos('SELECT * FROM empresa ORDER BY id');
    }

    public static function actualizar(int $id, array $d): void
    {
        self::ejecutar(
            'UPDATE empresa SET razon_social = :razon_social, nombre_fantasia = :nombre_fantasia, ruc = :ruc,
             actividad = :actividad, direccion = :direccion, telefono = :telefono, email = :email, ciudad = :ciudad,
             color = :color, permite_stock_negativo = :neg, formato_impresion = :formato WHERE id = :id',
            [
                'razon_social'    => $d['razon_social'],
                'nombre_fantasia' => $d['nombre_fantasia'] ?: null,
                'ruc'             => $d['ruc'],
                'actividad'       => $d['actividad'] ?: null,
                'direccion'       => $d['direccion'] ?: null,
                'telefono'        => $d['telefono'] ?: null,
                'email'           => $d['email'] ?: null,
                'ciudad'          => $d['ciudad'] ?: null,
                'color'           => $d['color'],
                'neg'             => $d['permite_stock_negativo'] ? 'true' : 'false',
                'formato'         => $d['formato_impresion'],
                'id'              => $id,
            ]
        );
    }

    /**
     * Posiciones de impresión sobre la hoja preimpresa, en milímetros desde la esquina superior izquierda.
     * Se guarda por empresa en empresa.formato_impresion; este es el valor por defecto.
     * Medido del PDF que genera el sistema Java (hoja de 229 x 324 mm con original, duplicado y triplicado).
     * Las columnas exenta / 5% y la casilla "crédito" no aparecen en ese PDF: calibrarlas con "Imprimir prueba".
     * Cada campo admite además: alinear (izquierda|centro|derecha), ancho (mm), fuente (pt), negrita, visible
     * y en_copias (lista 1 = original, 2 = duplicado, 3 = triplicado; sin ella sale en todas).
     */
    /**
     * Posiciones de impresión por defecto. Cada empresa usa su propia hoja preimpresa:
     * la de Centro de Copias se tomó de su factura impresa por el sistema Java (copias2.pdf).
     */
    public static function formatoPorDefecto(?array $empresa = null): array
    {
        return self::plantillas()[self::plantillaDe($empresa)]['formato'];
    }

    /** Qué plantilla le corresponde a la empresa: la de copias si su nombre dice "copia". */
    public static function plantillaDe(?array $empresa): string
    {
        $nombre = mb_strtoupper(($empresa['nombre_fantasia'] ?? '') . ' ' . ($empresa['razon_social'] ?? ''));
        return str_contains($nombre, 'COPIA') ? 'copias' : 'agente';
    }

    public static function plantillas(): array
    {
        // Hojas oficio (216 x 330 mm) con original, duplicado y triplicado. Medidas tomadas de los
        // escaneos de las hojas preimpresas de cada empresa; "y" es el borde superior del texto (8 pt).
        $agente = [
            'pagina'  => ['ancho' => 216, 'alto' => 330],
            'desplazamiento' => ['x' => 0, 'y' => 0],   // corrimiento general para calibrar la impresora
            'fuente'  => 8,                             // tamaño en puntos
            'formato_fecha' => 'd/m/y',                 // como en el Java: 15/09/26
            'copias'  => [0, 110, 220],                  // desplazamiento vertical de cada copia (original, duplicado, triplicado)
            'marca_contado_fecha' => 'contado',         // "contado con fecha de pago" se imprime como contado
            'campos'  => [
                'fecha'          => ['x' => 79.0,  'y' => 7.3],
                'razon_social'   => ['x' => 100.5, 'y' => 11.3],
                'ruc'            => ['x' => 77.5,  'y' => 15.1],
                'direccion'      => ['x' => 81.5,  'y' => 19.4],
                'telefono'       => ['x' => 77.0,  'y' => 23.5],
                'fecha_pago'     => ['x' => 133.0, 'y' => 28.0, 'visible' => false],
                'contado'        => ['x' => 110.4, 'y' => 28.0, 'negrita' => true],
                'credito'        => ['x' => 127.2, 'y' => 28.0, 'negrita' => true],
                'subtotal_exenta'=> ['x' => 166.0, 'y' => 95.0, 'alinear' => 'derecha'],
                'subtotal_5'     => ['x' => 186.0, 'y' => 95.0, 'alinear' => 'derecha'],
                'subtotal_10'    => ['x' => 205.5, 'y' => 95.0, 'alinear' => 'derecha'],
                'total_letras'   => ['x' => 44.1,  'y' => 96.0],
                'total'          => ['x' => 205.0, 'y' => 100.2, 'alinear' => 'derecha'],
                'iva5'           => ['x' => 45.5,  'y' => 100.2],
                'iva10'          => ['x' => 79.0,  'y' => 100.2],
                'iva_total'      => ['x' => 120.0, 'y' => 100.2],
                // Observación de la venta (quién pidió la factura): por defecto apagada y solo en el triplicado.
                'observacion'    => ['x' => 27.2,  'y' => 82.6, 'ancho' => 100, 'multilinea' => true, 'visible' => false, 'en_copias' => [3]],
            ],
            'detalle' => [
                'y'         => 43.0,   // primera fila
                'alto_fila' => 9.9,
                'max_filas' => 5,
                'columnas'  => [
                    'cantidad'    => ['x' => 16.2],
                    'descripcion' => ['x' => 27.2, 'ancho' => 100, 'multilinea' => true],
                    'precio'      => ['x' => 146.5, 'alinear' => 'derecha'],
                    'exenta'      => ['x' => 166.0, 'alinear' => 'derecha'],
                    'iva5'        => ['x' => 186.0, 'alinear' => 'derecha'],
                    'iva10'       => ['x' => 205.5, 'alinear' => 'derecha'],
                ],
            ],
        ];

        $copias = [
            'pagina'  => ['ancho' => 216, 'alto' => 330],
            'desplazamiento' => ['x' => 0, 'y' => 0],
            'fuente'  => 8,
            'formato_fecha' => 'd/m/y',
            'copias'  => [0, 104.5, 210.7],
            'marca_contado_fecha' => 'contado',
            'campos'  => [
                'razon_social'   => ['x' => 27.5,  'y' => 25.6],
                'direccion'      => ['x' => 27.5,  'y' => 29.8],
                'telefono'       => ['x' => 141.5, 'y' => 29.4],
                'ruc'            => ['x' => 174.0, 'y' => 29.2],
                'fecha'          => ['x' => 83.5,  'y' => 34.0],
                'fecha_pago'     => ['x' => 110,   'y' => 34.0, 'visible' => false],
                'contado'        => ['x' => 153.5, 'y' => 25.8, 'negrita' => true],
                'credito'        => ['x' => 165.5, 'y' => 25.8, 'negrita' => true],
                'subtotal_exenta'=> ['x' => 174.5, 'y' => 85.9, 'alinear' => 'derecha'],
                'subtotal_5'     => ['x' => 187.5, 'y' => 85.9, 'alinear' => 'derecha'],
                'subtotal_10'    => ['x' => 206.0, 'y' => 85.9, 'alinear' => 'derecha'],
                'total_letras'   => ['x' => 43.7,  'y' => 90.8],
                'total'          => ['x' => 206.0, 'y' => 90.8, 'alinear' => 'derecha'],
                'iva5'           => ['x' => 58.9,  'y' => 96.2],
                'iva10'          => ['x' => 103.0, 'y' => 96.2],
                'iva_total'      => ['x' => 172.2, 'y' => 96.2],
                'observacion'    => ['x' => 62.8,  'y' => 78.2, 'ancho' => 74, 'multilinea' => true, 'visible' => false, 'en_copias' => [3]],
            ],
            'detalle' => [
                'y'         => 45.7,
                'alto_fila' => 6.5,
                'max_filas' => 6,
                'columnas'  => [
                    'cantidad'    => ['x' => 38.0],
                    'descripcion' => ['x' => 62.8, 'ancho' => 74, 'multilinea' => true],
                    'precio'      => ['x' => 153.5, 'alinear' => 'derecha'],
                    'exenta'      => ['x' => 174.5, 'alinear' => 'derecha'],
                    'iva5'        => ['x' => 187.5, 'alinear' => 'derecha'],
                    'iva10'       => ['x' => 206.0, 'alinear' => 'derecha'],
                ],
            ],
        ];

        return [
            'copias' => ['nombre' => 'Hoja de Centro de Copias', 'formato' => $copias],
            'agente' => ['nombre' => 'Hoja de Agente de Transporte', 'formato' => $agente],
        ];
    }

    public static function guardarFormato(int $id, string $json): void
    {
        self::ejecutar('UPDATE empresa SET formato_impresion = ? WHERE id = ?', [$json, $id]);
    }

    /**
     * Imagen escaneada de la hoja preimpresa que se usa de fondo en el diseñador (no se imprime).
     * Si la empresa no subió una propia, se usa la de su plantilla (public/assets/plantillas/<plantilla>.jpg).
     */
    public static function fondo(int $id, bool $soloPropia = false): ?string
    {
        foreach (['jpg', 'png', 'webp'] as $ext) {
            if (is_file(self::carpetaFondos() . "/empresa-$id.$ext")) {
                return "uploads/fondos/empresa-$id.$ext";
            }
        }
        if (!$soloPropia && ($empresa = self::buscar($id))) {
            $ruta = 'assets/plantillas/' . self::plantillaDe($empresa) . '.jpg';
            if (is_file(BASE_PATH . '/public/' . $ruta)) {
                return $ruta;
            }
        }
        return null;
    }

    public static function carpetaFondos(): string
    {
        return BASE_PATH . '/public/uploads/fondos';
    }

    public static function formato(array $empresa): array
    {
        $guardado = json_decode((string) ($empresa['formato_impresion'] ?? ''), true);
        if (!is_array($guardado)) {
            return self::formatoPorDefecto($empresa);
        }
        $formato = array_replace_recursive(self::formatoPorDefecto($empresa), $guardado);
        if (isset($guardado['copias']) && is_array($guardado['copias'])) {
            $formato['copias'] = array_values($guardado['copias']); // una lista se reemplaza entera, no se mezcla
        }
        foreach ($guardado['campos'] ?? [] as $clave => $pos) {
            if (is_array($pos) && isset($pos['en_copias']) && is_array($pos['en_copias'])) {
                $formato['campos'][$clave]['en_copias'] = array_values(array_map('intval', $pos['en_copias']));
            }
        }
        return $formato;
    }
}
