-- Usuario administrador de PRUEBA
--   usuario: prueba     contraseña: 12345
-- Ejecutar en la base "facturacion" (pgAdmin > Query Tool > F5).
-- Si ya existe, le vuelve a poner la contraseña 12345 y lo activa.
-- Borralo o desactivalo antes de usar el sistema en serio.

INSERT INTO usuario (nombre, usuario, password_hash, rol, activo)
VALUES ('Usuario de prueba', 'prueba', '$2y$10$DQIeR1nF0nrTwDhxDxhf8u80g6J0OgNCFPTXCFOJOaEe4koXgAQjG', 'ADMIN', true)
ON CONFLICT (usuario) DO UPDATE
SET password_hash = EXCLUDED.password_hash, rol = 'ADMIN', activo = true;

INSERT INTO usuario_empresa (usuario_id, empresa_id)
SELECT u.id, e.id FROM usuario u CROSS JOIN empresa e
WHERE u.usuario = 'prueba'
ON CONFLICT DO NOTHING;
