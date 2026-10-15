-- Usuario administrador de Derlis
--   usuario: omar     contraseña: 14512
-- Ejecutar en la base del sistema (pgAdmin > Query Tool > F5), en la PC o en la nube.
-- Si ya existe, le vuelve a poner esa contraseña, lo deja como ADMIN y activo.
-- Queda con acceso a todas las empresas.

INSERT INTO usuario (nombre, usuario, password_hash, rol, activo)
VALUES ('Omar', 'omar', '$2y$10$maQCVk1n9lpJBbGhGOpL/uOwXgqUsmar3LJ0cIQtkg3RRJYQyZ3ue', 'ADMIN', true)
ON CONFLICT (usuario) DO UPDATE
SET password_hash = EXCLUDED.password_hash, rol = 'ADMIN', activo = true;

INSERT INTO usuario_empresa (usuario_id, empresa_id)
SELECT u.id, e.id FROM usuario u CROSS JOIN empresa e
WHERE u.usuario = 'omar'
ON CONFLICT DO NOTHING;
