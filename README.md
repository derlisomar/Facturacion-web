# Sistema de Facturación Web (PHP MVC + PostgreSQL)

Facturación sobre hojas preimpresas, multiempresa, con control de stock solo para productos físicos y seguimiento de cobros.
El análisis y las decisiones de diseño están en [docs/ANALISIS.md](docs/ANALISIS.md).

## Requisitos

- PHP 8.0 o superior con las extensiones `pdo_pgsql`, `mbstring` y `zip` (la última para descargar Excel; en XAMPP quitar el `;` de `;extension=zip` en php.ini)
- PostgreSQL 12 o superior
- Apache con `mod_rewrite` (o cualquier servidor apuntando a `public/`)

## Instalación nueva (sin datos anteriores)

```bash
createdb facturacion
psql -d facturacion -f database/schema.sql
psql -d facturacion -f database/seed.sql      # empresas de ejemplo y usuario admin / admin123
cp config/config.example.php config/config.php # completar los datos de la base
```

Configurar el servidor para que la carpeta pública sea `public/` (DocumentRoot). Si el hosting no lo permite, subir todo y dejar `config/` y `app/` fuera del acceso web. Si no hay `mod_rewrite`, poner `'pretty_urls' => false` en `config.php`.

Después de entrar como `admin`:

1. **Empresa** → completar razón social, RUC, color, y cargar el **timbrado** real (número, vigencia, establecimiento, punto de expedición, rango de números).
2. **Usuarios** → cambiar la contraseña del admin y crear los usuarios con sus empresas.
3. **Empresa → Imprimir prueba** → ajustar el formato de impresión hasta que encaje en la hoja preimpresa.

Para la instalación con una sola empresa, dejar una sola fila en `empresa`: el selector de empresa no aparece.

## Migrar desde el sistema Java

```bash
createdb facturacion
pg_restore -d facturacion --no-owner backup_del_java
psql -d facturacion -c "ALTER SCHEMA public RENAME TO legacy; CREATE SCHEMA public;"
psql -d facturacion -f database/schema.sql
psql -d facturacion -f database/migracion_desde_java.sql
```

La migración imprime al final consultas de verificación (totales por empresa, facturas cuyo total no coincide con el detalle, facturas sin cliente). Comparalas con el sistema Java antes de dejarlo. Los supuestos que usa están al inicio del archivo.

Los usuarios entran con su contraseña de siempre; en el primer ingreso se guarda cifrada.

Una vez verificado, el esquema `legacy` se puede borrar: `DROP SCHEMA legacy CASCADE;`

## Estructura

```
public/          index.php (controlador frontal), .htaccess, assets/
app/Core/        Router, Controller, Model, Database, Auth, Csrf, View
app/Controllers/ Auth, Dashboard, Empresas, Clientes, Productos, Facturas, Seguimiento, Reportes, Usuarios
app/Models/      Usuario, Empresa, Timbrado, Cliente, Producto, Factura, Cobro, Reporte
app/Views/       layout.php y una carpeta por módulo
app/rutas.php    todas las URL del sistema
database/        schema.sql, seed.sql, migracion_desde_java.sql
```

Para agregar una pantalla: una ruta en `app/rutas.php`, un método en el controlador, la consulta en el modelo y la vista en `app/Views/`.

## Panel y centro de reportes

- **Inicio**: panel de rendimiento con los números del día, la situación actual (por cobrar, vencido, stock bajo) y los gráficos del mes (ApexCharts).
- **Reportes**: centro de reportes. Cada informe se ve en pantalla y se descarga en Excel (.xlsx) o PDF, sin librerías externas (`app/Helpers/Exportador.php`). Con "Todas" las empresas, cada una sale en su propia hoja o sección. Para agregar un informe: una entrada en `CentroReportes::grupos()` y su consulta en `CentroReportes::filas()`.

## Impresión

**Configuración → Diseño de impresión** es el diseñador visual: se sube la imagen escaneada de la hoja preimpresa y se arrastra cada campo a su lugar (o se escriben X / Y en mm). Cada campo puede tener alineación, ancho máximo, tamaño de letra y negrita, y se puede ocultar. La imagen es solo una guía en pantalla y se guarda en `public/uploads/fondos/` (esa carpeta debe tener permiso de escritura).


`facturas/imprimir` arma la hoja con cada dato posicionado en milímetros según `empresa.formato_impresion` (JSON editable en la pantalla Empresa). En el diálogo de impresión del navegador: márgenes **Ninguno**, escala **100 %**, sin encabezados ni pies de página.

- `desplazamiento.x / y`: corrimiento general en mm (para corregir la impresora sin tocar cada campo).
- Hay dos plantillas (`Empresa::plantillas()`), medidas sobre los escaneos de las hojas preimpresas: Agente de Transporte
  y Centro de Copias, las dos en hoja oficio de 216 x 330 mm con original, duplicado y triplicado
  (`copias: [0, 110, 220]` y `[0, 104.5, 210.7]`). En el diseñador, «Cargar plantilla…» trae cualquiera de las dos
  como punto de partida, por ejemplo para otra empresa.
- El diseñador muestra de fondo el escaneo de la plantilla (`public/assets/plantillas/<plantilla>.jpg`) mientras la
  empresa no suba su propia imagen. Para una empresa nueva con otra hoja: subir el escaneo, cargar la plantilla más
  parecida, arrastrar los campos y guardar.
- `copias`: desplazamiento vertical en mm de cada copia dentro de la misma hoja. En el diseñador, el duplicado y el triplicado se arrastran enteros (o con las flechas ↑ ↓ después de tocarlos).
- `detalle.columnas.descripcion.multilinea`: si la descripción no entra en el ancho, sigue en el renglón de abajo y los ítems siguientes bajan; lo que no entra en `max_filas` no se imprime y la vista previa avisa.
- `marca_contado_fecha`: qué casilla se marca con X en las facturas «contado con fecha de pago». Por defecto `contado`, porque el sistema no maneja crédito.

**Observación en la factura:** en Diseño de impresión, el campo «Observación (quién pidió)» viene apagado. Al tildarlo se imprime lo escrito en Observación de la venta, por defecto solo en el triplicado (se elige en qué copias sale). Su Y se mide desde el borde de esa copia.

**Descripción larga:** al facturar, la descripción de cada ítem crece hacia abajo y admite hasta 500 letras. En bases instaladas antes, ejecutar una vez `database/actualizar_descripcion_larga.sql` (si no, sigue el límite de 200).
- `detalle.max_filas`: cuántos renglones tiene la hoja; si la factura tiene más, el sistema avisa.

## Seguridad

- Contraseñas con `password_hash`; tokens CSRF en todos los formularios; consultas preparadas; salida escapada con `e()`.
- `config/config.php` no se versiona (está en `.gitignore`).

## Padrón de RUC de la DNIT

**Registros → Padrón RUC** importa los archivos `ruc0` a `ruc9` que publica la DNIT en
[Listado de RUC con sus equivalencias](https://www.dnit.gov.py/web/portal-institucional/listado-de-ruc-con-sus-equivalencias)
(.zip tal como vienen, o .txt/.csv). Volver a importar actualiza sin duplicar. Solo el administrador puede importar.
Con el padrón cargado, al escribir un RUC en un cliente nuevo (o en la pantalla de venta) se completa el nombre solo.

- Base ya instalada antes de esta versión: ejecutar una vez `database/actualizar_padron_ruc.sql` en pgAdmin.
- Los archivos de la DNIT son grandes. En `php.ini` de XAMPP conviene subir `upload_max_filesize = 64M`,
  `post_max_size = 256M` y `max_execution_time = 600`, y reiniciar Apache. Para los .zip hace falta `extension=zip`.

## Pantalla de venta

**Facturar** funciona como un punto de venta: los productos de la empresa activa aparecen en tarjetas con precio y stock (F2 filtra), carrito con cantidades y, a la derecha,
el resumen con el cliente (F4), la condición, el método de pago y el total. Si el cliente buscado no existe, la lista
ofrece «No es cliente. Agregar como cliente», y si se intenta guardar con un nombre o RUC que no es cliente, se abre
el alta con los datos ya puestos (y el nombre del padrón cuando el RUC figura ahí). En transferencias se puede anotar
el número de comprobante, que queda guardado en el cobro.


Cada empresa tiene su propio catálogo y al facturar solo se ven sus productos. La migración desde el sistema Java copia
en las dos empresas los productos que nunca se facturaron; en **Productos** se pueden marcar los que no corresponden y
usar «Ocultar de esta empresa» (quedan inactivos, no se borran).

Cada producto tiene un solo **código** (único dentro de la empresa). Al cargar uno nuevo se sugiere el siguiente
al último código numérico usado en esa empresa, y se puede cambiar. La columna `codigo` antigua sigue en la base
pero el sistema ya no la usa.

**Número de factura**: no se puede repetir dentro de una empresa (Copias y Agente llevan numeraciones separadas).
Mientras se escribe el número se avisa si ya fue usado (por una factura emitida o una hoja anulada), y el servidor
rechaza el guardado aunque el número se haya usado con otro timbrado. El número sugerido salta los ya usados.

## Mensajes e impresión

Los avisos y confirmaciones usan SweetAlert2 (incluido en `public/assets/vendor/sweetalert2`, funciona sin internet).
Al guardar una factura, se imprime desde la misma pantalla con un marco oculto: no queda ninguna pestaña abierta.
«Reimprimir» en la ficha de la factura funciona igual.

## Subir a la nube

La base es PostgreSQL común, así que sirve cualquier proveedor (Neon, Supabase, Render, Railway o un VPS).
El sistema (PHP 8 con `pdo_pgsql`) va en un hosting PHP apuntando a la carpeta `public/`.

**Base nueva y vacía:** crear la base en el proveedor y ejecutar `database/nube.sql` completo. Crea las tablas,
las dos empresas con sus timbrados (tomados de las hojas preimpresas) y el usuario `omar` / `14512`. No crea el
usuario `admin`.

**Llevar los datos que ya están en la PC:**
1. En pgAdmin, clic derecho en la base local > Backup… (formato Custom).
2. Crear la base vacía en el proveedor, conectarla en pgAdmin y hacer clic derecho > Restore… con ese archivo.
3. Ejecutar `database/usuario_omar.sql` en la base de la nube.

**cPanel:** guía paso a paso en `docs/publicar-en-cpanel.md`.

**Configuración:** en `config/config.php` poner los datos que da el proveedor (host, puerto, base, usuario, clave)
y `'sslmode' => 'require'`. Poner `'debug' => false`.

## Usuario omar

`database/usuario_omar.sql` crea (o repara) el usuario administrador `omar` con contraseña `14512` y acceso a las
dos empresas. Sirve tanto en la base de la PC como en la de la nube. Conviene cambiar la contraseña desde
Configuración > Usuarios cuando el sistema esté en internet.

## Celular

En pantallas chicas el menú lateral se abre con ☰ y abajo queda una barra fija con Inicio, Productos,
el botón + en el medio (despliega Nueva factura y Nuevo cliente), Seguimiento y Reportes. Las tablas anchas se desplazan de costado dentro de su tarjeta.

- Íconos (Font Awesome) y letra (Outfit) se sirven desde el propio sistema (`public/assets/vendor/`), no desde internet. Así la barra de abajo no se mueve ni pierde los íconos al tocar.
