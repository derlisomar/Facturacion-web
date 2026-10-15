-- =====================================================================
-- INSTALACIÓN COMPLETA: base nueva y vacía del Sistema de Facturación
--
-- 1. En pgAdmin: clic derecho en "Databases" > Create > Database > nombre: facturacion
-- 2. Seleccioná la base "facturacion" > Tools > Query Tool
-- 3. Abrí este archivo (o pegá todo el contenido) y ejecutalo con F5
--
-- Crea las tablas, las dos empresas, un timbrado de ejemplo y el usuario:
--     usuario: admin    contraseña: admin123
-- Entrá con ese usuario, creá el tuyo en Configuración > Usuarios
-- y cambiá la contraseña del admin (o desactivalo).
-- =====================================================================

-- Sistema de Facturación Web - estructura de base de datos
-- Compatible con PostgreSQL 12 o superior.
-- Montos en guaraníes (enteros, sin decimales).

BEGIN;

CREATE TABLE empresa (
    id                      serial PRIMARY KEY,
    codigo                  varchar(20)  NOT NULL UNIQUE,      -- 'COPIAS', 'AGENTE'
    razon_social            varchar(150) NOT NULL,
    nombre_fantasia         varchar(150),
    ruc                     varchar(20)  NOT NULL,
    actividad               varchar(200),
    direccion               varchar(200),
    telefono                varchar(50),
    email                   varchar(100),
    ciudad                  varchar(80),
    color                   varchar(7)   NOT NULL DEFAULT '#6d28d9',
    permite_stock_negativo  boolean      NOT NULL DEFAULT false,
    formato_impresion       text,                             -- JSON con posiciones en mm
    activo                  boolean      NOT NULL DEFAULT true,
    creado_en               timestamp    NOT NULL DEFAULT now()
);

CREATE TABLE timbrado (
    id                serial PRIMARY KEY,
    empresa_id        integer     NOT NULL REFERENCES empresa(id),
    numero            varchar(20) NOT NULL,
    vigencia_desde    date        NOT NULL,
    vigencia_hasta    date        NOT NULL,
    establecimiento   char(3)     NOT NULL DEFAULT '001',
    punto_expedicion  char(3)     NOT NULL DEFAULT '001',
    numero_desde      integer     NOT NULL DEFAULT 1,
    numero_hasta      integer     NOT NULL DEFAULT 9999999,
    activo            boolean     NOT NULL DEFAULT true,
    CHECK (vigencia_hasta >= vigencia_desde),
    CHECK (numero_hasta >= numero_desde)
);
CREATE INDEX timbrado_empresa_idx ON timbrado(empresa_id, activo);

CREATE TABLE usuario (
    id                  serial PRIMARY KEY,
    nombre              varchar(100) NOT NULL,
    usuario             varchar(40)  NOT NULL UNIQUE,
    email               varchar(100) UNIQUE,
    cedula              varchar(20),
    password_hash       varchar(255) NOT NULL,
    rol                 varchar(15)  NOT NULL DEFAULT 'FACTURADOR'
                        CHECK (rol IN ('ADMIN', 'FACTURADOR', 'CONSULTA')),
    activo              boolean      NOT NULL DEFAULT true,
    ultimo_acceso       timestamp,
    creado_en           timestamp    NOT NULL DEFAULT now()
);

CREATE TABLE usuario_empresa (
    usuario_id  integer NOT NULL REFERENCES usuario(id) ON DELETE CASCADE,
    empresa_id  integer NOT NULL REFERENCES empresa(id) ON DELETE CASCADE,
    PRIMARY KEY (usuario_id, empresa_id)
);

CREATE TABLE cliente (
    id            serial PRIMARY KEY,
    ruc           varchar(20)  NOT NULL UNIQUE,   -- '80012345-6' o cédula
    razon_social  varchar(150) NOT NULL,
    direccion     varchar(200),
    telefono      varchar(50),
    celular       varchar(50),
    email         varchar(100),
    activo        boolean      NOT NULL DEFAULT true,
    creado_en     timestamp    NOT NULL DEFAULT now()
);
CREATE INDEX cliente_razon_idx ON cliente (lower(razon_social));

CREATE TABLE categoria (
    id           serial PRIMARY KEY,
    descripcion  varchar(100) NOT NULL UNIQUE
);

CREATE TABLE producto (
    id             serial PRIMARY KEY,
    empresa_id     integer      NOT NULL REFERENCES empresa(id),
    categoria_id   integer      REFERENCES categoria(id),
    codigo         varchar(30),
    codigo_corto   varchar(30),
    descripcion    varchar(200) NOT NULL,
    tipo           char(1)      NOT NULL DEFAULT 'S' CHECK (tipo IN ('P', 'S')), -- P=producto físico, S=servicio
    precio_venta   bigint       NOT NULL DEFAULT 0 CHECK (precio_venta >= 0),
    iva            smallint     NOT NULL DEFAULT 10 CHECK (iva IN (0, 5, 10)),
    stock_actual   numeric(12,2) NOT NULL DEFAULT 0,
    stock_minimo   numeric(12,2) NOT NULL DEFAULT 0,
    activo         boolean      NOT NULL DEFAULT true,
    creado_en      timestamp    NOT NULL DEFAULT now(),
    UNIQUE (empresa_id, codigo_corto)
);
CREATE INDEX producto_empresa_idx ON producto (empresa_id, activo);

CREATE TABLE factura (
    id                     serial PRIMARY KEY,
    empresa_id             integer      NOT NULL REFERENCES empresa(id),
    timbrado_id            integer      NOT NULL REFERENCES timbrado(id),
    numero                 integer      NOT NULL,
    numero_completo        varchar(20)  NOT NULL,          -- 001-001-0000123
    fecha                  date         NOT NULL,
    cliente_id             integer      REFERENCES cliente(id),
    ruc_cliente            varchar(20),                     -- lo que salió impreso
    razon_social_cliente   varchar(150),
    direccion_cliente      varchar(200),
    telefono_cliente       varchar(50),
    condicion              varchar(15)  NOT NULL DEFAULT 'CONTADO'
                           CHECK (condicion IN ('CONTADO', 'CONTADO_FECHA')),
    metodo_pago            varchar(15)  CHECK (metodo_pago IN ('EFECTIVO', 'TRANSFERENCIA')),
    fecha_pago             date,
    total_exenta           bigint       NOT NULL DEFAULT 0,
    total_gravada5         bigint       NOT NULL DEFAULT 0,
    total_gravada10        bigint       NOT NULL DEFAULT 0,
    total_iva5             bigint       NOT NULL DEFAULT 0,
    total_iva10            bigint       NOT NULL DEFAULT 0,
    total                  bigint       NOT NULL DEFAULT 0,
    monto_letras           varchar(300),
    observacion            varchar(300),
    estado                 varchar(10)  NOT NULL DEFAULT 'EMITIDA' CHECK (estado IN ('EMITIDA', 'ANULADA')),
    motivo_anulacion       varchar(200),
    anulado_en             timestamp,
    usuario_id             integer      REFERENCES usuario(id),
    creado_en              timestamp    NOT NULL DEFAULT now(),
    UNIQUE (timbrado_id, numero),
    CHECK (condicion <> 'CONTADO_FECHA' OR fecha_pago IS NOT NULL OR estado = 'ANULADA')
);
CREATE INDEX factura_empresa_fecha_idx ON factura (empresa_id, fecha);
CREATE INDEX factura_cliente_idx ON factura (cliente_id);
CREATE INDEX factura_seguimiento_idx ON factura (empresa_id, fecha_pago) WHERE condicion = 'CONTADO_FECHA' AND estado = 'EMITIDA';

CREATE TABLE factura_detalle (
    id               serial PRIMARY KEY,
    factura_id       integer       NOT NULL REFERENCES factura(id) ON DELETE CASCADE,
    producto_id      integer       REFERENCES producto(id),
    descripcion      varchar(500)  NOT NULL,
    cantidad         numeric(12,2) NOT NULL CHECK (cantidad > 0),
    precio_unitario  bigint        NOT NULL,
    iva              smallint      NOT NULL CHECK (iva IN (0, 5, 10)),
    subtotal         bigint        NOT NULL
);
CREATE INDEX factura_detalle_factura_idx ON factura_detalle (factura_id);

CREATE TABLE cobro (
    id           serial PRIMARY KEY,
    factura_id   integer     NOT NULL REFERENCES factura(id),
    fecha        date        NOT NULL DEFAULT current_date,
    monto        bigint      NOT NULL CHECK (monto > 0),
    metodo_pago  varchar(15) NOT NULL CHECK (metodo_pago IN ('EFECTIVO', 'TRANSFERENCIA')),
    referencia   varchar(100),
    usuario_id   integer     REFERENCES usuario(id),
    creado_en    timestamp   NOT NULL DEFAULT now()
);
CREATE INDEX cobro_factura_idx ON cobro (factura_id);

CREATE TABLE movimiento_stock (
    id                serial PRIMARY KEY,
    producto_id       integer       NOT NULL REFERENCES producto(id),
    fecha             timestamp     NOT NULL DEFAULT now(),
    tipo              varchar(15)   NOT NULL CHECK (tipo IN ('ENTRADA', 'SALIDA_VENTA', 'ANULACION', 'AJUSTE')),
    cantidad          numeric(12,2) NOT NULL,          -- positivo entra, negativo sale
    stock_resultante  numeric(12,2) NOT NULL,
    factura_id        integer       REFERENCES factura(id),
    usuario_id        integer       REFERENCES usuario(id),
    observacion       varchar(200)
);
CREATE INDEX movimiento_stock_producto_idx ON movimiento_stock (producto_id, fecha);

-- Saldo pendiente por factura (para el seguimiento de cobros)
CREATE VIEW v_factura_saldo AS
SELECT f.id AS factura_id,
       f.total,
       COALESCE(SUM(c.monto), 0) AS cobrado,
       f.total - COALESCE(SUM(c.monto), 0) AS saldo
FROM factura f
LEFT JOIN cobro c ON c.factura_id = f.id
GROUP BY f.id, f.total;

-- Padrón público de RUC de la DNIT: solo consulta, para completar el nombre al cargar un cliente.
-- No son clientes. Se carga desde Registros → Padrón RUC (archivos ruc0 a ruc9 de la DNIT).
CREATE TABLE padron_ruc (
    ruc     varchar(15)  PRIMARY KEY,
    dv      varchar(2),
    nombre  varchar(255) NOT NULL,
    estado  varchar(40)
);
CREATE INDEX padron_ruc_prefijo_idx ON padron_ruc (ruc varchar_pattern_ops);

COMMIT;

-- Datos iniciales de ejemplo. Ajustá RUC, timbrados y rangos con los datos reales.
-- Usuario inicial: admin / admin123  (cambiá la contraseña al entrar)

BEGIN;

INSERT INTO empresa (codigo, razon_social, nombre_fantasia, ruc, actividad, ciudad, color) VALUES
  ('COPIAS', 'Centro de Copias', 'Centro de Copias', '0000000-0', 'Fotocopias e impresiones', 'Asunción', '#059669'),
  ('AGENTE', 'Agente de Transporte', 'Agente de Transporte', '0000000-0', 'Agente de transporte', 'Asunción', '#ea580c');

INSERT INTO timbrado (empresa_id, numero, vigencia_desde, vigencia_hasta, establecimiento, punto_expedicion, numero_desde, numero_hasta)
SELECT id, '12345678', DATE '2026-01-01', DATE '2026-12-31', '001', '001', 1, 9999999 FROM empresa;

INSERT INTO usuario (nombre, usuario, email, password_hash, rol) VALUES
  ('Administrador', 'admin', NULL, '$2y$10$9Jv3Al2Km6IY8EREx1hk1.B3SF3jLZFCCw8Y3EViRvPWUCMY8m3gS', 'ADMIN');

INSERT INTO usuario_empresa (usuario_id, empresa_id)
SELECT u.id, e.id FROM usuario u CROSS JOIN empresa e WHERE u.usuario = 'admin';

INSERT INTO categoria (descripcion) VALUES ('General');

COMMIT;
