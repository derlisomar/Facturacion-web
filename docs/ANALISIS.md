# Sistema de Facturación Web (PHP MVC) — Análisis y diseño

Migración del sistema de escritorio en Java a una aplicación web en PHP con patrón Modelo‑Vista‑Controlador, manteniendo PostgreSQL.

---

## 1. Lo que hay hoy

**Sistema Java (capturas)**

- Menú: Copias Fact., Agente Fact., Consulta, Seguimiento, Clientes, Usuarios, Categorías, Productos, Config.
- Hay **una pantalla de facturación por empresa** (Centro de Copias y Agente de Transporte), copiadas una de la otra.
- Al facturar se exige el **número de factura** (hoja preimpresa) y la condición: *Contado* (efectivo o transferencia) o *Contado con fecha de pago*.
- IVA 10 %, 5 % y exenta. Montos en guaraníes, sin decimales.

**Base de datos actual (`fac_13-10`)** — el archivo trae **solo la estructura, sin datos**.

| Problema | Consecuencia |
|---|---|
| No hay claves foráneas (`factura` no apunta a `cliente`, `detfactura` no apunta a `factura`) | Se pueden borrar clientes con facturas, quedan detalles huérfanos |
| La empresa se guarda como texto (`fa_enti = 'AGENTE' / 'COPIAS'`) | Agregar o renombrar una empresa obliga a tocar código; el otro cliente con una sola empresa necesita otra versión |
| `config` es una sola fila con timbrado, RUC, etc. | No sirve para dos empresas con timbrados distintos |
| El número de factura no tiene restricción de unicidad | Se puede cargar dos veces la misma factura |
| Cliente copiado dentro de la factura (`fa_ruc`, `fa_nombre`) sin `cliente_id` | No hay relación real; los reportes por cliente cruzan por RUC |
| `tmpdetfactura` (carrito temporal en la BD) | Con varios usuarios web a la vez se mezclan los ítems |
| `usu_pass varchar(15)` en texto plano | Cualquiera con acceso a la BD ve las contraseñas |
| El producto no distingue servicio de producto físico | No se puede controlar stock solo de lo que corresponde |
| IVA guardado como `double precision` | Errores de redondeo en totales |

**Proyecto PHP que ya empezaste (`Facturacion/`)** — es un tablero de consulta sobre la BD del Java. Se aprovechan el diseño visual (CSS, login, colores) y las consultas de reportes. Dos cosas a corregir ya mismo:

1. `modelos/conexion.php` tiene usuario y contraseña de la base **escritos en el código, incluido el servidor remoto**. Conviene cambiar esa contraseña y pasar las credenciales a un archivo de configuración fuera de la carpeta pública (así lo hace el esqueleto nuevo).
2. El login compara contraseñas en texto plano.

---

## 2. Decisiones principales

| Tema | Decisión | Por qué |
|---|---|---|
| Base de datos | **PostgreSQL** (compatible con la versión 12 que ya usás) | Ya la conocés, tu hosting la tiene, y maneja bien transacciones y bloqueos que la facturación necesita. No hay razón para cambiar. |
| Framework | **PHP puro con MVC propio y liviano** (sin Composer obligatorio) | Corre en cualquier hosting compartido, es fácil de mantener y sigue el estilo que ya usás. Si más adelante querés Laravel, la estructura de datos sirve igual. |
| Multiempresa | Tabla **`empresa`** y todo lo fiscal colgando de ella | La misma instalación sirve para 1 o N empresas. Con una sola empresa, el selector simplemente no aparece. |
| Una sola pantalla de facturación | El usuario elige la empresa activa y la pantalla se adapta | Elimina el código duplicado Copias/Agente. |
| Contraseñas | `password_hash()` / `password_verify()` | Estándar de PHP. Las contraseñas viejas se convierten solas la primera vez que cada usuario entra. |
| Carrito | En el navegador (JS) y se guarda todo en **una sola transacción** al confirmar | Elimina `tmpdetfactura` y los cruces entre usuarios. |
| Montos | `bigint` en guaraníes, sin decimales | Sin errores de redondeo. |

---

## 3. Modelo de datos nuevo

```
empresa ─┬─< timbrado ─< factura >─ cliente
         │                 │
         ├─< producto ─────┼─< factura_detalle
         │       │         │
         │       └─< movimiento_stock
         │                 └─< cobro
         └─< usuario_empresa >─ usuario
categoria ─< producto
```

### Tablas

**`empresa`** — reemplaza a `config`. Razón social, nombre de fantasía, RUC, actividad, dirección, teléfono, ciudad, color y título para la pantalla, `formato_impresion` (JSON con posiciones de cada campo en la hoja), activo.

**`timbrado`** — un timbrado por empresa y por punto de expedición, con su vigencia:
`empresa_id, numero, vigencia_desde, vigencia_hasta, establecimiento ('001'), punto_expedicion ('001'), numero_desde, numero_hasta, activo`.
Así, al vencer un timbrado se carga el nuevo sin tocar las facturas viejas.

**`usuario`** — `nombre, usuario, email, password_hash, rol, activo, ultimo_acceso`.
**`usuario_empresa`** — qué empresas puede usar cada usuario.

**`cliente`** — compartido entre las empresas de la instalación (la misma persona puede comprar copias y contratar transporte): `ruc, dv, razon_social, direccion, telefono, celular, email, activo`. RUC único.

**`categoria`** — reemplaza `marca` / Categorías del menú.

**`producto`** — por empresa:
`empresa_id, codigo, codigo_corto, descripcion, categoria_id, tipo ('P' producto | 'S' servicio), precio_venta, iva (10 | 5 | 0), stock_actual, stock_minimo, activo`.
**Solo los de tipo `P` controlan stock**; a los servicios el sistema nunca les descuenta ni les exige existencia.

**`movimiento_stock`** — historial (kardex): `producto_id, fecha, tipo (ENTRADA, SALIDA_VENTA, ANULACION, AJUSTE), cantidad (+/−), stock_resultante, factura_id, usuario_id, observacion`. El stock actual se actualiza en la misma transacción, así siempre podés explicar de dónde salió cada número.

**`factura`** — cabecera:
`empresa_id, timbrado_id, numero (entero), numero_completo ('001-001-0000123'), fecha, cliente_id` + copia de `ruc_cliente` y `razon_social_cliente` (lo que salió impreso), `condicion`, `metodo_pago`, `fecha_pago`, `total_exenta, total_gravada5, total_gravada10, total_iva5, total_iva10, total`, `monto_letras`, `observacion`, `estado ('EMITIDA' | 'ANULADA')`, `motivo_anulacion`, `usuario_id`, `creado_en`.
Restricción **`UNIQUE (timbrado_id, numero)`**: es imposible cargar dos veces el mismo número.

**`factura_detalle`** — `factura_id, producto_id, descripcion, cantidad, precio_unitario, iva, subtotal`. Se copian descripción y precio porque si mañana cambia el precio del producto, la factura vieja no debe cambiar.

**`cobro`** — pagos de las facturas con fecha de pago: `factura_id, fecha, monto, metodo_pago, referencia, usuario_id`. Permite pagos parciales. El saldo es `total − suma(cobros)`.

### Condiciones de venta

| En pantalla | `condicion` | `metodo_pago` | `fecha_pago` | Queda en seguimiento |
|---|---|---|---|---|
| Contado – Efectivo | `CONTADO` | `EFECTIVO` | — | No (se registra cobrada) |
| Contado – Transferencia | `CONTADO` | `TRANSFERENCIA` | — | No (se registra cobrada) |
| Contado con fecha de pago | `CONTADO_FECHA` | se completa al cobrar | obligatoria | **Sí**, hasta que el saldo sea 0 |

Si en algún momento necesitás *Crédito* (cuotas) entra como otra condición sin cambiar tablas.

---

## 4. Flujo de facturación

1. El usuario entra y elige empresa (si tiene más de una). La pantalla muestra el nombre y color de esa empresa.
2. **Número de factura**: el sistema propone el siguiente (último + 1 del timbrado activo) pero el campo es **editable y obligatorio**, porque manda la hoja que está en la impresora. Al guardar se valida:
   - que esté dentro del rango del timbrado (`numero_desde`–`numero_hasta`),
   - que la fecha esté dentro de la vigencia del timbrado,
   - que no exista ya (la base lo bloquea aunque dos usuarios lo intenten al mismo tiempo).
3. Busca el cliente por RUC o nombre (o lo crea ahí mismo).
4. Agrega productos/servicios. Si es producto físico muestra el stock y no deja vender más de lo que hay (configurable por empresa si querés permitir stock negativo).
5. Elige condición: contado efectivo, contado transferencia o contado con fecha de pago (pide la fecha).
6. Confirmar → **una transacción**: graba cabecera, detalle, descuenta stock de los productos físicos, registra el cobro si es contado. Si algo falla, no se graba nada.
7. Se abre la vista de impresión.

**Hoja arruinada en la impresora**: se registra ese número como *anulado* (sin detalle) para que no quede un hueco en la numeración, y se factura de nuevo con la hoja siguiente.

**Anular una factura**: pide motivo, la marca `ANULADA`, devuelve el stock de los productos físicos (movimiento `ANULACION`) y la saca del seguimiento. Nunca se borra.

---

## 5. Impresión sobre la hoja preimpresa

- Vista HTML separada con `@page { size: <ancho>mm <alto>mm; margin: 0 }` y cada dato posicionado en **milímetros** (`position:absolute; top: Xmm; left: Ymm`).
- Las posiciones viven en `empresa.formato_impresion` (JSON), así **cada empresa tiene su propio formato** y el otro cliente con una sola empresa usa el suyo sin tocar código.
- Campos: fecha, nombre/razón social, RUC, dirección, teléfono, condición (marca X en contado/crédito), filas del detalle (cantidad, descripción, precio, exenta/5 %/10 %), subtotales, total, total en letras, liquidación del IVA.
- **Calibración**: pantalla de configuración con desplazamiento general (corrimiento X/Y en mm) y botón “imprimir prueba” sobre una hoja en blanco para encajarla contra una preimpresa a contraluz.
- Al imprimir desde el navegador: márgenes *Ninguno*, escala 100 %, sin encabezados ni pies. Se deja anotado en la pantalla.
- Si la hoja tiene original y duplicado en la misma página, el JSON admite un segundo bloque desplazado.

---

## 6. Login y roles

| Rol | Puede |
|---|---|
| `ADMIN` | Todo: usuarios, empresas, timbrados, formato de impresión, anular facturas |
| `FACTURADOR` | Facturar, clientes, productos, registrar cobros |
| `CONSULTA` | Ver listados, seguimiento y reportes |

Seguridad incluida en el esqueleto: sesiones con `session_regenerate_id`, token CSRF en todos los formularios, consultas preparadas (PDO), escape de salida en las vistas, credenciales en `config/config.php` fuera de `public/`.

---

## 7. Estructura del proyecto

```
facturacion-web/
├── public/                 ← única carpeta expuesta en el servidor (DocumentRoot)
│   ├── index.php           ← controlador frontal: todas las peticiones pasan por acá
│   ├── .htaccess
│   └── assets/css, js, img
├── app/
│   ├── Core/               ← Router, Controller, Model, Database, Auth, Csrf, View
│   ├── Controllers/        ← Auth, Dashboard, Empresas, Clientes, Productos, Facturas, Seguimiento
│   ├── Models/             ← Usuario, Empresa, Timbrado, Cliente, Producto, Factura, Cobro
│   ├── Views/              ← layout + una carpeta por módulo
│   └── Helpers/            ← NumeroALetras, formatos de moneda y fecha
├── config/config.example.php
├── database/
│   ├── schema.sql          ← estructura nueva
│   ├── seed.sql            ← empresas, timbrados y usuario admin de ejemplo
│   └── migracion_desde_java.sql ← pasa los datos de la BD actual a la nueva
└── docs/ANALISIS.md
```

**Una instalación por cliente** (tu empresa con Copias + Agente, la otra empresa con una sola): mismo código, distinta base y distinto `config.php`. Las actualizaciones se copian igual a las dos.

---

## 8. Migración desde el sistema Java

`database/migracion_desde_java.sql` asume que restaurás el backup actual en el esquema `legacy` de la base nueva:

1. `empresa`: se crean *Centro de Copias* (`COPIAS`) y *Agente de Transporte* (`AGENTE`) con los datos de `config`.
2. `cliente`: se copian; los RUC repetidos se unifican.
3. `producto`: se copian con tipo `S` (servicio) por defecto; después marcás cuáles son físicos y cargás su stock inicial.
4. `factura` + `factura_detalle`: se enlazan con el cliente por RUC y con la empresa por `fa_enti`; `fa_tipoop = 2` pasa a `CONTADO_FECHA`; `fa_anulado = 1` pasa a `ANULADA`.
5. Usuarios: se copian con la contraseña vieja marcada para convertir al primer ingreso.

Supuestos que hay que confirmar con datos reales (el backup no trae filas): que `fa_enti` siempre sea `AGENTE` o `COPIAS`, que `detfactura.fac_cod` apunte a `factura.fa_id`, y a qué corresponde `pro_idee` en `producto`.

---

## 9. Orden de trabajo sugerido

1. Base nueva + migración de datos reales y verificación de totales contra el sistema Java.
2. Login, usuarios, empresas y timbrados.
3. Clientes y productos (con tipo y stock).
4. Pantalla de facturación + impresión calibrada en la impresora real.
5. Seguimiento de cobros y anulaciones.
6. Reportes (se reutilizan los del tablero PHP que ya tenés).
7. Uso en paralelo con el Java unos días antes de apagarlo.
