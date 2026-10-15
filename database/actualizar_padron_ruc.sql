-- Para una base ya instalada (por ejemplo FacturaCopiasNew): agrega la tabla del padrón de RUC de la DNIT.
-- Se puede ejecutar más de una vez sin problema. En pgAdmin: abrir Query Tool sobre la base, pegar y ejecutar.
CREATE TABLE IF NOT EXISTS padron_ruc (
    ruc     varchar(15)  PRIMARY KEY,
    dv      varchar(2),
    nombre  varchar(255) NOT NULL,
    estado  varchar(40)
);
CREATE INDEX IF NOT EXISTS padron_ruc_prefijo_idx ON padron_ruc (ruc varchar_pattern_ops);
