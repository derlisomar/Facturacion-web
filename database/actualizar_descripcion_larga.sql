-- Descripciones largas en el detalle de la factura (hasta 500 letras en lugar de 200).
-- Ejecutar una sola vez en bases instaladas antes de este cambio (en phpPgAdmin: pestaña SQL).
ALTER TABLE factura_detalle ALTER COLUMN descripcion TYPE varchar(500);
