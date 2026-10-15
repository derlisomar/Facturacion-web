<?php

namespace App\Helpers;

use RuntimeException;
use ZipArchive;

/**
 * Genera archivos .xlsx y .pdf reales sin librerías externas.
 * Todo el formato vive acá: si más adelante se prefiere phpspreadsheet/dompdf,
 * alcanza con reemplazar estos dos métodos.
 *
 * Documento esperado:
 *  [
 *    'negocio' => 'Mi Negocio', 'titulo' => 'Ventas por día', 'subtitulo' => 'Del 01/10 al 12/10',
 *    'generado' => '12/10/2026 10:30', 'usuario' => 'Juan',
 *    'secciones' => [
 *      ['titulo' => 'Casa central',
 *       'columnas' => [['t' => 'Fecha', 'tipo' => 'texto|entero|dinero|cantidad|porc', 'total' => true|false]],
 *       'filas' => [[...], ...]],
 *    ],
 *  ]
 */
class Exportador
{
    // ====================================================================== XLSX

    public static function xlsx(array $doc): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo Excel.');
        }

        $secciones = $doc['secciones'] ?: [['titulo' => 'Sin datos', 'columnas' => [['t' => 'Sin datos', 'tipo' => 'texto']], 'filas' => []]];
        $nombres = [];
        $hojas = '';
        $rels = '';
        $tipos = '';
        $definidos = '';
        foreach ($secciones as $i => $s) {
            $n = $i + 1;
            $nombre = self::nombreHoja($s['hoja'] ?? $s['titulo'], $nombres);
            $nombres[] = $nombre;
            $zip->addFromString("xl/worksheets/sheet{$n}.xml", self::hoja($doc, $s));
            $hojas .= '<sheet name="'.self::x($nombre).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
            $rels .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
            $tipos .= '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $definidos .= '<definedName name="_xlnm.Print_Titles" localSheetId="'.$i.'">\''.str_replace("'", "''", $nombre).'\'!$5:$5</definedName>';
        }
        $ultimo = count($secciones) + 1;
        $rels .= '<Relationship Id="rId'.$ultimo.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'.$tipos.'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>'.self::x($doc['titulo']).'</dc:title><dc:creator>'.self::x($doc['negocio']).'</dc:creator></cp:coreProperties>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$hojas.'</sheets><definedNames>'.$definidos.'</definedNames></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>');
        $zip->addFromString('xl/styles.xml', self::estilos());
        $zip->close();

        $bin = file_get_contents($tmp);
        @unlink($tmp);

        return $bin;
    }

    private static function nombreHoja(string $t, array $usados): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', preg_replace('/[\[\]\:\*\?\/\\\\]/u', ' ', $t))) ?: 'Hoja';
        $t = mb_substr($t, 0, 31);
        $base = $t;
        $k = 2;
        while (in_array(mb_strtolower($t), array_map('mb_strtolower', $usados), true)) {
            $t = mb_substr($base, 0, 28).' '.$k++;
        }

        return $t;
    }

    private static function estilos(): string
    {
        $borde = '<border><left style="thin"><color rgb="FFE2E8F0"/></left><right style="thin"><color rgb="FFE2E8F0"/></right><top style="thin"><color rgb="FFE2E8F0"/></top><bottom style="thin"><color rgb="FFE2E8F0"/></bottom><diagonal/></border>';
        $bordeTotal = '<border><left/><right/><top style="medium"><color rgb="FF1E3A8A"/></top><bottom style="thin"><color rgb="FF1E3A8A"/></bottom><diagonal/></border>';
        // 0 normal, 1 negrita blanca, 2 titulo, 3 gris cursiva, 4 negrita
        $fuentes = '<font><sz val="10"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="16"/><color rgb="FF1E3A8A"/><name val="Calibri"/></font>'
            .'<font><i/><sz val="10"/><color rgb="FF64748B"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="10"/><color rgb="FF1E3A8A"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="12"/><color rgb="FF0F172A"/><name val="Calibri"/></font>';
        // 0 none, 1 gray125, 2 header, 3 stripe, 4 total
        $rellenos = '<fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF1E3A8A"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFDBEAFE"/><bgColor indexed="64"/></patternFill></fill>';
        $xf = function (int $fmt, int $fuente, int $relleno, int $borde, string $al = '') {
            return '<xf numFmtId="'.$fmt.'" fontId="'.$fuente.'" fillId="'.$relleno.'" borderId="'.$borde.'" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"'.($al ? ' applyAlignment="1">'.$al.'</xf>' : '/>');
        };
        $izq = '<alignment horizontal="left" vertical="center"/>';
        $der = '<alignment horizontal="right" vertical="center"/>';
        $cab = '<alignment horizontal="center" vertical="center" wrapText="1"/>';
        $xfs = [
            $xf(0, 0, 0, 0),                    // 0
            $xf(0, 2, 0, 0, $izq),              // 1 titulo
            $xf(0, 3, 0, 0, $izq),              // 2 subtitulo
            $xf(0, 1, 2, 1, $cab),              // 3 cabecera
            $xf(0, 0, 0, 1, $izq),              // 4 texto
            $xf(0, 0, 3, 1, $izq),              // 5 texto cebra
            $xf(3, 0, 0, 1, $der),              // 6 entero
            $xf(3, 0, 3, 1, $der),              // 7 entero cebra
            $xf(164, 0, 0, 1, $der),            // 8 cantidad
            $xf(164, 0, 3, 1, $der),            // 9
            $xf(165, 0, 0, 1, $der),            // 10 porc
            $xf(165, 0, 3, 1, $der),            // 11
            $xf(0, 4, 4, 2, $izq),              // 12 total texto
            $xf(3, 4, 4, 2, $der),              // 13 total entero
            $xf(164, 4, 4, 2, $der),            // 14 total cantidad
            $xf(165, 4, 4, 2, $der),            // 15 total porc
            $xf(0, 5, 0, 0, $izq),              // 16 negocio
        ];

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0.###"/><numFmt numFmtId="165" formatCode="0.0&quot;%&quot;"/></numFmts>'
            .'<fonts count="6">'.$fuentes.'</fonts><fills count="5">'.$rellenos.'</fills>'
            .'<borders count="3"><border><left/><right/><top/><bottom/><diagonal/></border>'.$borde.$bordeTotal.'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="'.count($xfs).'">'.implode('', $xfs).'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private static function hoja(array $doc, array $s): string
    {
        $cols = $s['columnas'];
        $nc = max(1, count($cols));
        $ult = self::letra($nc);
        $filas = $s['filas'];

        // anchos según contenido
        $anchos = [];
        foreach ($cols as $c => $col) {
            $m = mb_strlen($col['t']) + 2;
            foreach (array_slice($filas, 0, 300) as $f) {
                $v = $f[$c] ?? '';
                $l = is_numeric($v) ? 14 : mb_strlen((string) $v) + 2;
                $m = max($m, $l);
            }
            $anchos[] = min(max($m, 9), 48);
        }

        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $x .= '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>';
        $x .= '<sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
        $x .= '<sheetFormatPr defaultRowHeight="15"/><cols>';
        foreach ($anchos as $i => $w) {
            $x .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$w.'" customWidth="1"/>';
        }
        $x .= '</cols><sheetData>';

        $x .= '<row r="1" ht="24" customHeight="1">'.self::celdaTexto('A1', $doc['negocio'].' — '.$doc['titulo'], 1).'</row>';
        $x .= '<row r="2">'.self::celdaTexto('A2', trim($s['titulo'].'  ·  '.$doc['subtitulo'], ' ·'), 16).'</row>';
        $x .= '<row r="3">'.self::celdaTexto('A3', 'Generado el '.$doc['generado'].' por '.$doc['usuario'], 2).'</row>';
        $x .= '<row r="5" ht="28" customHeight="1">';
        foreach ($cols as $c => $col) {
            $x .= self::celdaTexto(self::letra($c + 1).'5', $col['t'], 3);
        }
        $x .= '</row>';

        $r = 5;
        foreach ($filas as $k => $f) {
            $r++;
            $z = $k % 2;
            $x .= '<row r="'.$r.'">';
            foreach ($cols as $c => $col) {
                $ref = self::letra($c + 1).$r;
                $v = $f[$c] ?? null;
                $tipo = $col['tipo'] ?? 'texto';
                if ($tipo === 'texto' || $v === null || $v === '' || ! is_numeric($v)) {
                    $x .= self::celdaTexto($ref, (string) ($v ?? ''), 4 + $z);
                } else {
                    $est = ['entero' => 6, 'dinero' => 6, 'cantidad' => 8, 'porc' => 10][$tipo] ?? 6;
                    $x .= '<c r="'.$ref.'" s="'.($est + $z).'"><v>'.(0 + $v).'</v></c>';
                }
            }
            $x .= '</row>';
        }

        $tieneTotales = array_filter(array_column($cols, "total")) !== [];
        if ($tieneTotales && $filas) {
            $r++;
            $x .= '<row r="'.$r.'" ht="20" customHeight="1">';
            foreach ($cols as $c => $col) {
                $ref = self::letra($c + 1).$r;
                if (! empty($col['total'])) {
                    $L = self::letra($c + 1);
                    $est = ['entero' => 13, 'dinero' => 13, 'cantidad' => 14, 'porc' => 13][$col['tipo'] ?? 'entero'] ?? 13;
                    $x .= '<c r="'.$ref.'" s="'.$est.'"><f>SUBTOTAL(9,'.$L.'6:'.$L.($r - 1).')</f></c>';
                } else {
                    $x .= self::celdaTexto($ref, $c === 0 ? 'TOTAL' : '', 12);
                }
            }
            $x .= '</row>';
        }
        $x .= '</sheetData>';
        if ($filas) {
            $x .= '<autoFilter ref="A5:'.$ult.(5 + count($filas)).'"/>';
        }
        $x .= '<mergeCells count="3"><mergeCell ref="A1:'.$ult.'1"/><mergeCell ref="A2:'.$ult.'2"/><mergeCell ref="A3:'.$ult.'3"/></mergeCells>';
        $x .= '<pageMargins left="0.4" right="0.4" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>';
        $x .= '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>';
        $x .= '<headerFooter><oddFooter>&amp;L'.self::x($doc['negocio']).'&amp;RPágina &amp;P de &amp;N</oddFooter></headerFooter>';

        return $x.'</worksheet>';
    }

    private static function celdaTexto(string $ref, string $v, int $estilo): string
    {
        return '<c r="'.$ref.'" s="'.$estilo.'" t="inlineStr"><is><t xml:space="preserve">'.self::x($v).'</t></is></c>';
    }

    private static function letra(int $n): string
    {
        $s = '';
        while ($n > 0) {
            $m = ($n - 1) % 26;
            $s = chr(65 + $m).$s;
            $n = intdiv($n - 1, 26);
        }

        return $s;
    }

    private static function x(string $v): string
    {
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v);

        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    // ====================================================================== PDF

    private const ANCHO_N = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584];

    private const ANCHO_B = [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584];

    private static function ancho(string $t, float $pt, bool $negrita = false): float
    {
        $t = strtr($t, ['á'=>'a','à'=>'a','â'=>'a','ä'=>'a','ã'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ó'=>'o','ò'=>'o','ô'=>'o','ö'=>'o','õ'=>'o','ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ñ'=>'n','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N','Ü'=>'U','·'=>'.','—'=>'-','–'=>'-','…'=>'.']);
        $tab = $negrita ? self::ANCHO_B : self::ANCHO_N;
        $w = 0;
        foreach (str_split($t) as $ch) {
            $o = ord($ch);
            $w += ($o >= 32 && $o <= 126) ? $tab[$o - 32] : 556;
        }

        return $w * $pt / 1000;
    }

    private static function recortar(string $t, float $max, float $pt, bool $negrita = false): string
    {
        if (self::ancho($t, $pt, $negrita) <= $max) {
            return $t;
        }
        while ($t !== '' && self::ancho($t.'...', $pt, $negrita) > $max) {
            $t = mb_substr($t, 0, -1);
        }

        return rtrim($t).'...';
    }

    private static function pdfTexto(string $t): string
    {
        $t = strtr($t, ['—' => '-', '–' => '-', '·' => '·', '…' => '...', '“' => '"', '”' => '"', '’' => "'", '✓' => '']);
        $c = @mb_convert_encoding($t, 'Windows-1252', 'UTF-8');
        $c = $c === false ? $t : $c;

        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $c);
    }

    private static function fmt($v, string $tipo): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        if (! is_numeric($v)) {
            return (string) $v;
        }

        return match ($tipo) {
            'entero', 'dinero' => number_format((float) $v, 0, ',', '.'),
            'cantidad' => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ','),
            'porc' => number_format((float) $v, 1, ',', '.').'%',
            default => (string) $v,
        };
    }

    public static function formato($v, string $tipo): string
    {
        return self::fmt($v, $tipo);
    }

    public static function pdf(array $doc): string
    {
        $W = 842.0;
        $H = 595.0;
        $mx = 30.0;
        $util = $W - 2 * $mx;
        $alto = 15.0;
        $pt = 8.0;
        $top = $H - 92;     // inicio de tabla
        $fin = 44.0;        // límite inferior

        $paginas = [];   // cada página: string de operadores
        $secciones = $doc['secciones'] ?: [['titulo' => 'Sin datos', 'columnas' => [['t' => 'Sin datos', 'tipo' => 'texto']], 'filas' => []]];

        foreach ($secciones as $s) {
            $cols = $s['columnas'];
            $tipos = array_map(fn ($c) => $c['tipo'] ?? 'texto', $cols);
            $filas = $s['filas'];

            // anchos naturales
            $nat = [];
            foreach ($cols as $c => $col) {
                $m = self::ancho($col['t'], $pt, true) + 10;
                foreach (array_slice($filas, 0, 400) as $f) {
                    $m = max($m, self::ancho(self::fmt($f[$c] ?? '', $tipos[$c]), $pt) + 10);
                }
                $nat[$c] = min($m, 260);
            }
            $suma = array_sum($nat);
            $esc = $suma > $util ? $util / $suma : 1.0;
            $anchoCols = array_map(fn ($w) => $suma > 0 ? ($suma < $util ? $w * min($util / $suma, 1.8) : $w * $esc) : $w, $nat);
            $tabW = array_sum($anchoCols);

            $totales = [];
            foreach ($cols as $c => $col) {
                if (! empty($col['total'])) {
                    $totales[$c] = array_sum(array_map(fn ($f) => (float) ($f[$c] ?? 0), $filas));
                }
            }

            $buf = null;
            $y = 0.0;
            $nuevaPagina = function () use (&$buf, &$y, &$paginas, $top, $s, $cols, $anchoCols, $mx, $pt, $alto, $tabW) {
                if ($buf !== null) {
                    $paginas[] = $buf;
                }
                $buf = '';
                $y = $top;
                // barra de sección
                $buf .= self::rect($mx, $y - 18, $tabW, 18, '0.95 0.96 0.98');
                $buf .= self::txt($mx + 8, $y - 13, $s['titulo'], 10, true, '0.12 0.23 0.54');
                $y -= 24;
                // cabecera
                $buf .= self::rect($mx, $y - $alto - 3, $tabW, $alto + 3, '0.12 0.23 0.54');
                $x = $mx;
                foreach ($cols as $c => $col) {
                    $w = $anchoCols[$c];
                    $t = self::recortar($col['t'], $w - 8, $pt, true);
                    $der = ($col['tipo'] ?? 'texto') !== 'texto';
                    $tx = $der ? $x + $w - 4 - self::ancho($t, $pt, true) : $x + 4;
                    $buf .= self::txt($tx, $y - 11, $t, $pt, true, '1 1 1');
                    $x += $w;
                }
                $y -= $alto + 3;
            };
            $nuevaPagina();

            foreach ($filas as $k => $f) {
                if ($y - $alto < $fin) {
                    $nuevaPagina();
                }
                if ($k % 2 === 1) {
                    $buf .= self::rect($mx, $y - $alto, $tabW, $alto, '0.95 0.96 0.98');
                }
                $x = $mx;
                foreach ($cols as $c => $col) {
                    $w = $anchoCols[$c];
                    $t = self::recortar(self::fmt($f[$c] ?? '', $tipos[$c]), $w - 8, $pt);
                    $der = $tipos[$c] !== 'texto' && is_numeric($f[$c] ?? null);
                    $tx = $der ? $x + $w - 4 - self::ancho($t, $pt) : $x + 4;
                    $buf .= self::txt($tx, $y - 10.5, $t, $pt, false, '0.1 0.13 0.2');
                    $x += $w;
                }
                $buf .= self::linea($mx, $y - $alto, $mx + $tabW, $y - $alto, '0.89 0.91 0.94');
                $y -= $alto;
            }

            if ($totales && $filas) {
                if ($y - $alto - 2 < $fin) {
                    $nuevaPagina();
                }
                $buf .= self::rect($mx, $y - $alto - 2, $tabW, $alto + 2, '0.86 0.92 1');
                $buf .= self::linea($mx, $y, $mx + $tabW, $y, '0.12 0.23 0.54', 1.2);
                $x = $mx;
                foreach ($cols as $c => $col) {
                    $w = $anchoCols[$c];
                    if (isset($totales[$c])) {
                        $t = self::recortar(self::fmt($totales[$c], $tipos[$c]), $w - 8, $pt, true);
                        $buf .= self::txt($x + $w - 4 - self::ancho($t, $pt, true), $y - 11, $t, $pt, true, '0.12 0.23 0.54');
                    } elseif ($c === 0) {
                        $buf .= self::txt($x + 4, $y - 11, 'TOTAL', $pt, true, '0.12 0.23 0.54');
                    }
                    $x += $w;
                }
                $y -= $alto + 2;
            }
            if (! $filas) {
                $buf .= self::txt($mx + 8, $y - 14, 'Sin datos para los filtros elegidos.', 9, false, '0.4 0.45 0.55');
            }
            $paginas[] = $buf;
            $buf = null;
        }

        // ensamblado
        $total = count($paginas);
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $kids = [];
        $n = 5;
        foreach ($paginas as $i => $cuerpo) {
            $cab = self::rect(0, $H - 62, $W, 62, '0.12 0.23 0.54')
                .self::txt($mx, $H - 28, $doc['negocio'], 15, true, '1 1 1')
                .self::txt($mx, $H - 46, $doc['titulo'], 11, false, '0.86 0.92 1')
                .self::txtDer($W - $mx, $H - 28, $doc['subtitulo'], 9, false, '0.86 0.92 1')
                .self::txtDer($W - $mx, $H - 46, 'Generado el '.$doc['generado'].' por '.$doc['usuario'], 8, false, '0.86 0.92 1');
            $pie = self::linea($mx, 34, $W - $mx, 34, '0.8 0.84 0.9')
                .self::txt($mx, 22, $doc['negocio'].' · '.$doc['titulo'], 7.5, false, '0.4 0.45 0.55')
                .self::txtDer($W - $mx, 22, 'Página '.($i + 1).' de '.$total, 7.5, false, '0.4 0.45 0.55');
            $contenido = gzcompress($cab.$cuerpo.$pie);
            $pid = $n++;
            $cid = $n++;
            $objs[$cid] = ['stream' => $contenido, 'dict' => '/Filter /FlateDecode'];
            $objs[$pid] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.$W.' '.$H.'] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$cid.' 0 R >>';
            $kids[] = $pid.' 0 R';
        }
        $objs[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$total.' >>';

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $off = [];
        ksort($objs);
        foreach ($objs as $id => $o) {
            $off[$id] = strlen($pdf);
            if (is_array($o)) {
                $pdf .= $id." 0 obj\n<< /Length ".strlen($o['stream']).' '.$o['dict']." >>\nstream\n".$o['stream']."\nendstream\nendobj\n";
            } else {
                $pdf .= $id." 0 obj\n".$o."\nendobj\n";
            }
        }
        $xref = strlen($pdf);
        $max = max(array_keys($objs)) + 1;
        $pdf .= "xref\n0 {$max}\n0000000000 65535 f \n";
        for ($i = 1; $i < $max; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $off[$i]);
        }
        $pdf .= "trailer\n<< /Size {$max} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private static function n(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') ?: '0';
    }

    private static function txt(float $x, float $y, string $t, float $pt, bool $b, string $color): string
    {
        return "BT {$color} rg /F".($b ? 2 : 1).' '.self::n($pt).' Tf '.self::n($x).' '.self::n($y).' Td ('.self::pdfTexto($t).") Tj ET\n";
    }

    private static function txtDer(float $xDer, float $y, string $t, float $pt, bool $b, string $color): string
    {
        return self::txt($xDer - self::ancho($t, $pt, $b), $y, $t, $pt, $b, $color);
    }

    private static function rect(float $x, float $y, float $w, float $h, string $color): string
    {
        return "{$color} rg ".self::n($x).' '.self::n($y).' '.self::n($w).' '.self::n($h)." re f\n";
    }

    private static function linea(float $x1, float $y1, float $x2, float $y2, string $color, float $grosor = 0.5): string
    {
        return "{$color} RG ".self::n($grosor).' w '.self::n($x1).' '.self::n($y1).' m '.self::n($x2).' '.self::n($y2)." l S\n";
    }
}
