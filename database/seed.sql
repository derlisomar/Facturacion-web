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
