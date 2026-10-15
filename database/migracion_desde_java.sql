-- =====================================================================
-- Migración de datos del sistema Java (esquema viejo) a la base nueva.
--
-- Pasos (ver README.md):
--   1. createdb facturacion
--   2. pg_restore -d facturacion --no-owner backup_del_java
--   3. psql -d facturacion -c "ALTER SCHEMA public RENAME TO legacy; CREATE SCHEMA public;"
--   4. psql -d facturacion -f database/schema.sql
--   5. psql -d facturacion -f database/migracion_desde_java.sql
--   6. Revisar las consultas de verificación del final.
--
-- Supuestos (a confirmar con los datos reales):
--   * factura.fa_enti es 'COPIAS' o 'AGENTE'. Si viene vacío o distinto, va a COPIAS.
--   * detfactura.fac_cod apunta a factura.fa_id.
--   * factura.fa_tipoop = 2 es "contado con fecha de pago".
--   * En detfactura, det_iva / det_iva5 / det_ivaexc distinto de 0 indica la tasa de la línea (10 / 5 / exenta).
--   * producto.pro_iva guarda 10, 5 o 0. Otro valor se toma como 10.
--   * Un producto con pro_stock > 0 es un producto físico; el resto se migra como servicio.
--     Después de migrar, revisá en Productos cuáles son físicos.
--   * El sistema viejo no guarda si una factura con fecha de pago ya fue cobrada:
--     todas quedan pendientes en Seguimiento hasta que registres el cobro.
-- =====================================================================

BEGIN;

-- ---------- Empresas (desde config) ----------
INSERT INTO empresa (codigo, razon_social, nombre_fantasia, ruc, actividad, direccion, telefono, email, ciudad, color)
SELECT e.codigo,
       COALESCE(NULLIF(trim(c.conf_denominacion), ''), e.nombre),
       e.nombre,
       COALESCE(NULLIF(trim(c.conf_ruc), ''), '0000000-0'),
       c.conf_actividad, c.conf_direccion, c.conf_telefono, c.conf_email, c.conf_ciudad, e.color
FROM (VALUES ('COPIAS', 'Centro de Copias', '#059669', 1),
             ('AGENTE', 'Agente de Transporte', '#ea580c', 2)) AS e(codigo, nombre, color, orden)
LEFT JOIN (SELECT * FROM legacy.config ORDER BY conf_id LIMIT 1) c ON true
ORDER BY e.orden;

-- Convierte texto de fecha del sistema viejo (dd/mm/aaaa o aaaa-mm-dd) a date.
CREATE FUNCTION pg_temp.a_fecha(t text) RETURNS date LANGUAGE sql IMMUTABLE AS $$
    SELECT CASE
        WHEN t ~ '^\d{4}-\d{2}-\d{2}' THEN to_date(substr(t, 1, 10), 'YYYY-MM-DD')
        WHEN t ~ '^\d{1,2}/\d{1,2}/\d{4}' THEN to_date(t, 'DD/MM/YYYY')
        ELSE NULL END
$$;

-- Timbrado actual (desde config), activo para facturar desde hoy
INSERT INTO timbrado (empresa_id, numero, vigencia_desde, vigencia_hasta, establecimiento, punto_expedicion, numero_desde, numero_hasta, activo)
SELECT e.id,
       COALESCE(NULLIF(trim(c.conf_timbrado), ''), 'SIN-TIMBRADO'),
       COALESCE(pg_temp.a_fecha(c.conf_timbradoini), date_trunc('year', current_date)::date),
       COALESCE(pg_temp.a_fecha(c.conf_timbradoven), (date_trunc('year', current_date) + interval '1 year - 1 day')::date),
       '001', '001', 1, 9999999, true
FROM empresa e
LEFT JOIN (SELECT * FROM legacy.config ORDER BY conf_id LIMIT 1) c ON true;

-- ---------- Usuarios ----------
-- La contraseña vieja queda marcada como 'legacy:' y se convierte a hash seguro en el primer ingreso.
INSERT INTO usuario (id, nombre, usuario, email, cedula, password_hash, rol)
SELECT u.usu_id,
       COALESCE(NULLIF(trim(u.usu_nombre), ''), 'Usuario ' || u.usu_id),
       CASE WHEN count(*) OVER (PARTITION BY lower(trim(u.usu_alias))) > 1 OR NULLIF(trim(u.usu_alias), '') IS NULL
            THEN COALESCE(NULLIF(trim(u.usu_alias), ''), 'usuario') || u.usu_id
            ELSE trim(u.usu_alias) END,
       CASE WHEN count(*) OVER (PARTITION BY lower(trim(u.usu_correo))) = 1 THEN NULLIF(trim(u.usu_correo), '') END,
       u.usu_cedula,
       'legacy:' || COALESCE(trim(u.usu_pass), ''),
       CASE WHEN upper(u.usu_rol) LIKE 'ADMIN%' THEN 'ADMIN' ELSE 'FACTURADOR' END
FROM legacy.usuario u;

SELECT setval('usuario_id_seq', GREATEST((SELECT MAX(id) FROM usuario), 1));

INSERT INTO usuario_empresa (usuario_id, empresa_id)
SELECT u.id, e.id FROM usuario u CROSS JOIN empresa e;

-- ---------- Clientes (se unifican RUC repetidos, se conserva el id más bajo) ----------
INSERT INTO cliente (id, ruc, razon_social, direccion, telefono, celular)
SELECT DISTINCT ON (ruc_norm)
       c.cliente_id,
       ruc_norm,
       COALESCE(NULLIF(trim(c.cliente_razon), ''), 'SIN NOMBRE'),
       NULLIF(trim(c.cliente_direccion), ''),
       NULLIF(trim(c.cliente_telefono), ''),
       NULLIF(trim(c.cliente_celular), '')
FROM (SELECT *, COALESCE(NULLIF(upper(trim(cliente_ruc)), ''), 'SIN-RUC-' || cliente_id) AS ruc_norm
      FROM legacy.cliente) c
ORDER BY ruc_norm, c.cliente_id;
SELECT setval('cliente_id_seq', GREATEST((SELECT MAX(id) FROM cliente), 1));

-- Clientes que solo existen en facturas (RUC facturado que no está en la tabla cliente)
INSERT INTO cliente (ruc, razon_social)
SELECT DISTINCT ON (upper(trim(f.fa_ruc))) upper(trim(f.fa_ruc)), COALESCE(NULLIF(trim(f.fa_nombre), ''), 'SIN NOMBRE')
FROM legacy.factura f
WHERE NULLIF(trim(f.fa_ruc), '') IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM cliente c WHERE c.ruc = upper(trim(f.fa_ruc)))
ORDER BY upper(trim(f.fa_ruc)), f.fa_id DESC;

-- ---------- Categorías (desde marca y pro_marca) ----------
INSERT INTO categoria (descripcion)
SELECT DISTINCT d FROM (
    SELECT upper(trim(ma_desc)) AS d FROM legacy.marca
    UNION SELECT upper(trim(pro_marca)) FROM legacy.producto
) x WHERE d IS NOT NULL AND d <> '';

-- ---------- Productos ----------
-- Cada producto va a la empresa donde se facturó; si nunca se facturó, a todas las empresas.
CREATE TEMP TABLE producto_empresa AS
SELECT DISTINCT p.pro_id, e.id AS empresa_id
FROM legacy.producto p
JOIN legacy.detfactura d ON trim(d.det_codcorto) = trim(p.pro_codcorto)
JOIN legacy.factura f ON f.fa_id = d.fac_cod
JOIN empresa e ON e.codigo = COALESCE(NULLIF(upper(trim(f.fa_enti)), ''), 'COPIAS');

INSERT INTO producto_empresa
SELECT p.pro_id, e.id FROM legacy.producto p CROSS JOIN empresa e
WHERE NOT EXISTS (SELECT 1 FROM producto_empresa pe WHERE pe.pro_id = p.pro_id);

-- Columna temporal para enlazar con el producto viejo; se elimina al final.
ALTER TABLE producto ADD COLUMN legacy_id integer;

INSERT INTO producto (empresa_id, categoria_id, codigo, codigo_corto, descripcion, tipo, precio_venta, iva, stock_actual, legacy_id)
SELECT pe.empresa_id,
       cat.id,
       p.pro_codigo::text,
       CASE WHEN count(*) OVER (PARTITION BY pe.empresa_id, trim(p.pro_codcorto)) > 1
            THEN trim(p.pro_codcorto) || '-' || p.pro_id
            ELSE NULLIF(trim(p.pro_codcorto), '') END,
       COALESCE(NULLIF(trim(p.pro_descrip), ''), 'PRODUCTO ' || p.pro_id),
       CASE WHEN COALESCE(p.pro_stock, 0) > 0 THEN 'P' ELSE 'S' END,
       GREATEST(COALESCE(p.pro_preciovta, 0), 0),
       CASE WHEN p.pro_iva IN (0, 5, 10) THEN p.pro_iva ELSE 10 END,
       CASE WHEN COALESCE(p.pro_stock, 0) > 0 THEN p.pro_stock ELSE 0 END,
       p.pro_id
FROM legacy.producto p
JOIN producto_empresa pe ON pe.pro_id = p.pro_id
LEFT JOIN categoria cat ON cat.descripcion = upper(trim(p.pro_marca));

-- Saldo inicial de stock como movimiento, para que el historial cuadre
INSERT INTO movimiento_stock (producto_id, tipo, cantidad, stock_resultante, observacion)
SELECT id, 'ENTRADA', stock_actual, stock_actual, 'Saldo migrado del sistema anterior'
FROM producto WHERE tipo = 'P' AND stock_actual > 0;

-- ---------- Facturas ----------
-- Las facturas viejas no tienen timbrado: se agrupan en timbrados "HISTÓRICO" inactivos.
-- Si un mismo número se repite en una empresa (cambio de timbrado), cada repetición va a otra serie.
CREATE TEMP TABLE factura_vieja AS
SELECT f.*,
       e.id AS empresa_id,
       COALESCE(f.fa_nrofac, 0) AS nro,
       row_number() OVER (PARTITION BY e.id, COALESCE(f.fa_nrofac, 0) ORDER BY f.fa_fecha, f.fa_id) AS serie
FROM legacy.factura f
JOIN empresa e ON e.codigo = COALESCE(NULLIF(upper(trim(f.fa_enti)), ''), 'COPIAS');

INSERT INTO timbrado (empresa_id, numero, vigencia_desde, vigencia_hasta, numero_desde, numero_hasta, activo)
SELECT empresa_id, 'HISTORICO-' || serie,
       COALESCE(min(fa_fecha), DATE '2000-01-01'), COALESCE(max(fa_fecha), DATE '2000-01-01'),
       0, 9999999, false
FROM factura_vieja GROUP BY empresa_id, serie;

INSERT INTO factura (id, empresa_id, timbrado_id, numero, numero_completo, fecha, cliente_id, ruc_cliente,
                     razon_social_cliente, direccion_cliente, telefono_cliente, condicion, metodo_pago, fecha_pago,
                     total, monto_letras, observacion, estado, motivo_anulacion)
SELECT f.fa_id, f.empresa_id, t.id, f.nro,
       '001-001-' || lpad(f.nro::text, 7, '0'),
       COALESCE(f.fa_fecha, DATE '2000-01-01'),
       c.id, upper(trim(f.fa_ruc)), f.fa_nombre, c.direccion, COALESCE(c.telefono, c.celular),
       CASE WHEN f.fa_tipoop = 2 THEN 'CONTADO_FECHA' ELSE 'CONTADO' END,
       CASE WHEN upper(f.fa_metodopago) LIKE 'TRANSF%' THEN 'TRANSFERENCIA'
            WHEN upper(f.fa_metodopago) LIKE 'EFEC%' THEN 'EFECTIVO'
            WHEN COALESCE(f.fa_tipoop, 0) <> 2 THEN 'EFECTIVO' END,
       CASE WHEN f.fa_tipoop = 2 THEN COALESCE(f.fa_fechapago, f.fa_fecha, DATE '2000-01-01') END,
       COALESCE(f.fa_montototal, 0), f.fa_letras, f.fa_obs,
       CASE WHEN f.fa_anulado = 1 THEN 'ANULADA' ELSE 'EMITIDA' END,
       CASE WHEN f.fa_anulado = 1 THEN 'Anulada en el sistema anterior' END
FROM factura_vieja f
JOIN timbrado t ON t.empresa_id = f.empresa_id AND t.numero = 'HISTORICO-' || f.serie
LEFT JOIN cliente c ON c.ruc = upper(trim(f.fa_ruc));

INSERT INTO factura_detalle (factura_id, producto_id, descripcion, cantidad, precio_unitario, iva, subtotal)
SELECT d.fac_cod,
       (SELECT p.id FROM producto p JOIN legacy.producto lp ON lp.pro_id = p.legacy_id
         WHERE trim(lp.pro_codcorto) = trim(d.det_codcorto) AND p.empresa_id = f.empresa_id
         ORDER BY p.id LIMIT 1),
       COALESCE(NULLIF(trim(d.det_descpro), ''), 'SIN DESCRIPCIÓN'),
       GREATEST(COALESCE(d.det_cant, 1), 1),
       COALESCE(d.det_preciouni, 0),
       CASE WHEN COALESCE(d.det_iva, 0) <> 0 THEN 10
            WHEN COALESCE(d.det_iva5, 0) <> 0 THEN 5
            WHEN COALESCE(d.det_ivaexc, 0) <> 0 THEN 0
            ELSE 10 END,
       COALESCE(d.det_preciototal, 0)
FROM legacy.detfactura d
JOIN factura f ON f.id = d.fac_cod;

-- Totales por tasa recalculados desde el detalle
UPDATE factura f SET
    total_exenta    = x.exenta,
    total_gravada5  = x.g5,
    total_gravada10 = x.g10,
    total_iva5      = round(x.g5 / 21.0),
    total_iva10     = round(x.g10 / 11.0)
FROM (SELECT factura_id,
             COALESCE(SUM(subtotal) FILTER (WHERE iva = 0), 0)  AS exenta,
             COALESCE(SUM(subtotal) FILTER (WHERE iva = 5), 0)  AS g5,
             COALESCE(SUM(subtotal) FILTER (WHERE iva = 10), 0) AS g10
      FROM factura_detalle GROUP BY factura_id) x
WHERE x.factura_id = f.id;

-- Facturas contado emitidas: se registran como cobradas
INSERT INTO cobro (factura_id, fecha, monto, metodo_pago)
SELECT id, fecha, total, COALESCE(metodo_pago, 'EFECTIVO')
FROM factura WHERE condicion = 'CONTADO' AND estado = 'EMITIDA' AND total > 0;

ALTER TABLE producto DROP COLUMN legacy_id;

-- ---------- Ajustar secuencias ----------
SELECT setval('cliente_id_seq', GREATEST((SELECT MAX(id) FROM cliente), 1));
SELECT setval('factura_id_seq', GREATEST((SELECT MAX(id) FROM factura), 1));

COMMIT;

-- =====================================================================
-- VERIFICACIÓN (comparar con el sistema Java antes de dejarlo)
-- =====================================================================

-- 1) Cantidad y monto por empresa: deben coincidir con el sistema viejo
SELECT e.codigo, f.estado, COUNT(*) AS facturas, SUM(f.total) AS monto
FROM factura f JOIN empresa e ON e.id = f.empresa_id
GROUP BY e.codigo, f.estado ORDER BY 1, 2;

-- 2) Facturas cuyo total no coincide con la suma del detalle (revisar a mano)
SELECT f.id, f.numero_completo, f.total, COALESCE(SUM(d.subtotal), 0) AS suma_detalle
FROM factura f LEFT JOIN factura_detalle d ON d.factura_id = f.id
WHERE f.estado = 'EMITIDA'
GROUP BY f.id HAVING f.total <> COALESCE(SUM(d.subtotal), 0)
ORDER BY f.id LIMIT 50;

-- 3) Facturas sin cliente enlazado
SELECT id, numero_completo, ruc_cliente, razon_social_cliente FROM factura
WHERE cliente_id IS NULL AND estado = 'EMITIDA' LIMIT 50;

-- 4) Números repetidos que quedaron en otra serie histórica
SELECT t.numero AS serie, e.codigo, COUNT(*) FROM factura f
JOIN timbrado t ON t.id = f.timbrado_id JOIN empresa e ON e.id = f.empresa_id
WHERE t.numero LIKE 'HISTORICO-%' GROUP BY 1, 2 ORDER BY 2, 1;
