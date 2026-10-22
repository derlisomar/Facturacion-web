-- =====================================================================
-- DATOS INICIALES para empezar el sistema de cero.
-- Ejecutar DESPUÉS de schema.sql, en la misma base vacía.
--
-- Crea las dos empresas, la categoría "General" y el usuario administrador:
--     usuario: omar     contraseña: 14512
-- Al entrar:
--   1. Cambiá la contraseña en Configuración > Usuarios.
--   2. Cargá el timbrado de cada empresa en Configuración > Empresa.
-- =====================================================================

BEGIN;

INSERT INTO empresa (codigo, razon_social, nombre_fantasia, ruc, actividad, direccion, telefono, ciudad, color) VALUES
  ('COPIAS', 'Miguel Cantero Britez', 'Centro de Copias', '1211149-0',
   'Servicios relacionados con la impresión. Fotocopias, telecomunicaciones y autoservicios',
   'Puerto Ciudad del Este', '(061) 509 361', 'Ciudad del Este', '#059669'),
  ('AGENTE', 'Miguel Cantero Britos', 'Agente de Transporte', '1211149-0',
   'Agente de transportes', 'Puerto Ciudad del Este', '(061) 509 361', 'Ciudad del Este', '#ea580c');

INSERT INTO categoria (descripcion) VALUES ('General');

INSERT INTO usuario (nombre, usuario, password_hash, rol, activo)
VALUES ('Omar', 'omar', '$2y$10$maQCVk1n9lpJBbGhGOpL/uOwXgqUsmar3LJ0cIQtkg3RRJYQyZ3ue', 'ADMIN', true);

INSERT INTO usuario_empresa (usuario_id, empresa_id)
SELECT u.id, e.id FROM usuario u CROSS JOIN empresa e WHERE u.usuario = 'omar';

COMMIT;
