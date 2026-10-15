<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Producto;

class ProductosController extends Controller
{
    public function index(): void
    {
        $empresaId = $this->requiereEmpresa();
        $busqueda = (string) $this->entrada('q', '');
        $tipo = (string) $this->entrada('tipo', '');
        $pagina = max(1, (int) $this->entrada('pagina', 1));
        $resultado = Producto::listar($empresaId, $busqueda, $tipo, $pagina);
        $this->vista('productos/index', [
            'titulo'    => 'Productos y servicios',
            'productos' => $resultado['filas'],
            'total'     => $resultado['total'],
            'pagina'    => $pagina,
            'busqueda'  => $busqueda,
            'tipo'      => $tipo,
        ]);
    }

    public function formulario(): void
    {
        $empresaId = $this->requiereEmpresa();
        $this->requiereRol('ADMIN', 'FACTURADOR');
        $id = (int) $this->entrada('id', 0);
        $producto = $id ? Producto::buscar($id, $empresaId) : null;
        $this->vista('productos/form', [
            'titulo'     => $producto ? 'Editar producto' : 'Nuevo producto o servicio',
            'producto'   => $producto ?? ['id' => null, 'codigo_corto' => Producto::siguienteCodigo($empresaId), 'descripcion' => '',
                                          'tipo' => Producto::SERVICIO, 'precio_venta' => 0,
                                          'iva' => 10, 'stock_actual' => 0, 'stock_minimo' => 0, 'activo' => true],
        ]);
    }

    public function guardar(): void
    {
        $empresaId = $this->requiereEmpresa();
        $this->requiereRol('ADMIN', 'FACTURADOR');
        $this->verificarCsrf();
        $id = (int) $this->entrada('id', 0) ?: null;
        $tipo = $this->entrada('tipo') === Producto::PRODUCTO ? Producto::PRODUCTO : Producto::SERVICIO;
        $iva = (int) $this->entrada('iva', 10);
        $datos = [
            'codigo_corto'  => strtoupper(trim((string) $this->entrada('codigo_corto', ''))),
            'descripcion'   => mb_strtoupper((string) $this->entrada('descripcion', '')),
            'tipo'          => $tipo,
            'precio_venta'  => entero($this->entrada('precio_venta', 0)),
            'iva'           => in_array($iva, [0, 5, 10], true) ? $iva : 10,
            'stock_minimo'  => $tipo === Producto::PRODUCTO ? decimal($this->entrada('stock_minimo', 0)) : 0,
            'stock_inicial' => $tipo === Producto::PRODUCTO ? decimal($this->entrada('stock_inicial', 0)) : 0,
            'activo'        => $id === null || !empty($_POST['activo']),
            'usuario_id'    => Auth::id(),
        ];
        if ($datos['descripcion'] === '') {
            flash('error', 'La descripción es obligatoria.');
            $this->redirigir($id ? 'productos/editar' : 'productos/nuevo', $id ? ['id' => $id] : []);
        }
        try {
            Producto::guardar($empresaId, $datos, $id);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23505') {
                throw $e;
            }
            flash('error', 'El código ' . $datos['codigo_corto'] . ' ya lo usa otro producto de esta empresa. El siguiente libre es ' . Producto::siguienteCodigo($empresaId) . '.');
            $this->redirigir($id ? 'productos/editar' : 'productos/nuevo', $id ? ['id' => $id] : []);
        }
        flash('ok', 'Producto guardado.');
        $this->redirigir('productos');
    }

    /** Desactiva varios productos a la vez (por ejemplo los que la migración copió en la empresa equivocada). */
    public function desactivar(): void
    {
        $empresaId = $this->requiereEmpresa();
        $this->requiereRol('ADMIN', 'FACTURADOR');
        $this->verificarCsrf();
        $n = Producto::desactivarVarios($empresaId, (array) ($_POST['ids'] ?? []));
        flash('ok', $n ? "Se ocultaron $n producto(s) de esta empresa. Ya no aparecen al facturar." : 'No se eligió ningún producto.');
        $this->redirigir('productos');
    }

    public function buscar(): void
    {
        $empresaId = $this->requiereEmpresa();
        $this->json(Producto::autocompletar($empresaId, (string) $this->entrada('q', '')));
    }

    /** Historial de movimientos de stock de un producto. */
    public function kardex(): void
    {
        $empresaId = $this->requiereEmpresa();
        $producto = Producto::buscar((int) $this->entrada('id', 0), $empresaId);
        if ($producto === null || $producto['tipo'] !== Producto::PRODUCTO) {
            flash('error', 'Ese ítem es un servicio y no lleva stock.');
            $this->redirigir('productos');
        }
        $this->vista('productos/kardex', [
            'titulo'      => 'Stock: ' . $producto['descripcion'],
            'producto'    => $producto,
            'movimientos' => Producto::movimientos((int) $producto['id']),
        ]);
    }

    public function ajustarStock(): void
    {
        $empresaId = $this->requiereEmpresa();
        $this->requiereRol('ADMIN', 'FACTURADOR');
        $this->verificarCsrf();
        $id = (int) $this->entrada('id', 0);
        $tipo = $this->entrada('tipo') === 'AJUSTE' ? 'AJUSTE' : 'ENTRADA';
        $cantidad = decimal($this->entrada('cantidad', 0));
        if ($tipo === 'ENTRADA' && $cantidad <= 0 || $cantidad == 0) {
            flash('error', 'Ingresá una cantidad válida (en ajustes puede ser negativa).');
        } else {
            try {
                Producto::ajustarStock($id, $empresaId, $cantidad, $tipo, Auth::id(), (string) $this->entrada('observacion', ''));
                flash('ok', 'Stock actualizado.');
            } catch (\DomainException $e) {
                flash('error', $e->getMessage());
            }
        }
        $this->redirigir('productos/stock', ['id' => $id]);
    }
}
