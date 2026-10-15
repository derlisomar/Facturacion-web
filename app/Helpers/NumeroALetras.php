<?php
namespace App\Helpers;

/** Convierte un monto entero en letras: 1250000 -> "UN MILLÓN DOSCIENTOS CINCUENTA MIL" */
class NumeroALetras
{
    private const UNIDADES = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
        'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE',
        'VEINTE', 'VEINTIUNO', 'VEINTIDÓS', 'VEINTITRÉS', 'VEINTICUATRO', 'VEINTICINCO', 'VEINTISÉIS',
        'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE'];
    private const DECENAS = ['', '', '', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
    private const CENTENAS = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS',
        'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

    public static function convertir(int $numero, string $moneda = 'GUARANIES'): string
    {
        if ($numero === 0) {
            return trim('CERO ' . $moneda);
        }
        $texto = self::millones($numero);
        return trim(preg_replace('/\s+/', ' ', trim($texto)) . ' ' . $moneda);
    }

    private static function millones(int $n): string
    {
        $millones = intdiv($n, 1000000);
        $resto = $n % 1000000;
        if ($millones === 0) {
            return self::miles($resto);
        }
        $texto = $millones === 1 ? 'UN MILLÓN' : self::apocopar(self::miles($millones)) . ' MILLONES';
        return $texto . ' ' . self::miles($resto);
    }

    private static function miles(int $n): string
    {
        $miles = intdiv($n, 1000);
        $resto = $n % 1000;
        if ($miles === 0) {
            return self::centenas($resto);
        }
        $texto = $miles === 1 ? 'MIL' : self::apocopar(self::centenas($miles)) . ' MIL';
        return $texto . ' ' . self::centenas($resto);
    }

    private static function centenas(int $n): string
    {
        if ($n === 100) {
            return 'CIEN';
        }
        $c = intdiv($n, 100);
        $resto = $n % 100;
        return trim(self::CENTENAS[$c] . ' ' . self::decenas($resto));
    }

    private static function decenas(int $n): string
    {
        if ($n < 30) {
            return self::UNIDADES[$n];
        }
        $d = intdiv($n, 10);
        $u = $n % 10;
        return self::DECENAS[$d] . ($u ? ' Y ' . self::UNIDADES[$u] : '');
    }

    /** "VEINTIUNO MIL" -> "VEINTIÚN MIL", "UNO MIL" -> "UN MIL" */
    private static function apocopar(string $texto): string
    {
        $texto = preg_replace('/VEINTIUNO$/', 'VEINTIÚN', $texto);
        return preg_replace('/UNO$/', 'UN', $texto);
    }
}
