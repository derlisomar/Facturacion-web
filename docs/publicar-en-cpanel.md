# Publicar en cPanel (subdominio facturaweb)

En los ejemplos, `tudominio.com` es tu dominio y `usuario` es tu usuario de cPanel. Cambialos por los tuyos.

## 0. Antes de empezar: ¿tu hosting tiene PostgreSQL?

En la portada de cPanel, buscá **"Bases de datos PostgreSQL"** (PostgreSQL Databases).

- **Si aparece**, seguí esta guía tal cual.
- **Si solo aparece "Bases de datos MySQL"**, el hosting no trae PostgreSQL y hay dos caminos:
  - Pedirle a tu proveedor que lo active. Muchos lo hacen sin costo.
  - Usar una base PostgreSQL externa (Neon o Supabase) y hacer el paso 2 allá, en lugar de en cPanel. Algunos hostings compartidos bloquean las conexiones salientes al puerto 5432. Si después de configurar sale un error de conexión, pedile a tu proveedor que lo habilite.

## 1. Crear el subdominio apuntando a `public`

1. En cPanel, abrí **Dominios** (en versiones viejas se llama **Subdominios**).
2. Elegí **Crear un nuevo dominio** y escribí `facturaweb.tudominio.com`.
3. Destildá **"Compartir la raíz del documento"**.
4. En **Raíz del documento**, escribí `facturaweb/public` y tocá **Enviar**.

Apuntar a `public` es importante, porque así las carpetas `app/` y `config/`, donde está la clave de la base, no quedan visibles desde internet.

## 2. Crear la base de datos

1. En **Bases de datos PostgreSQL**, creá una base llamada `facturacion`. cPanel le agrega tu prefijo y queda, por ejemplo, `usuario_facturacion`.
2. Creá un usuario, por ejemplo `usuario_fac`, con una clave fuerte. Anotá el nombre completo de la base, el usuario y la clave.
3. En **Agregar usuario a base de datos**, agregá ese usuario a esa base.
4. Abrí **phpPgAdmin** desde cPanel, entrá a la base `usuario_facturacion` y abrí la pestaña **SQL**.
5. Pegá todo el contenido de `facturacion-web/database/nube.sql`, o subí el archivo, y ejecutalo.
   - Eso crea las tablas, las dos empresas con sus timbrados y el usuario `omar` / `14512`.
   - Para usar los datos que ya tenés en la PC, en su lugar hacé un Backup en pgAdmin y restauralo en esta base. Después ejecutá `database/usuario_omar.sql`.

## 3. Subir los archivos

1. Abrí **Administrador de archivos** y entrá a tu carpeta principal (`/home/usuario`), no a `public_html`.
2. Subí `facturacion-web.zip`, seleccionalo y tocá **Extraer**. Se crea la carpeta `facturacion-web`.
3. Renombrá esa carpeta a `facturaweb`. Así coincide con la raíz del paso 1, que es `/home/usuario/facturaweb/public`.
4. Revisá que el archivo oculto `public/.htaccess` haya quedado dentro. Para verlo, en **Configuración** del Administrador tildá **"Mostrar archivos ocultos"**.

## 4. Configurar

1. En `facturaweb/config/`, copiá `config.example.php` con el nombre `config.php`.
2. Abrí `config.php` con **Editar** y completalo:

```php
'url'         => 'https://facturaweb.tudominio.com',
'pretty_urls' => true,
'debug'       => false,
...
'host'    => 'localhost',
'port'    => 5432,
'nombre'  => 'usuario_facturacion',   // nombre completo con prefijo
'usuario' => 'usuario_fac',
'clave'   => 'la clave que creaste',
'sslmode' => '',                      // si la base es Neon o Supabase: 'require' y el host que te dan
```

## 5. Versión de PHP y extensiones

1. En **MultiPHP Manager**, elegí `facturaweb.tudominio.com` y poné **PHP 8.1 o superior**.
2. En **Seleccionar versión de PHP** → **Extensiones**, tildá `pdo_pgsql`, `pgsql`, `mbstring` y `zip`.
   - Esa pantalla puede no estar, porque depende del hosting. Si falta, preguntale al proveedor si `pdo_pgsql` está activo.
3. Para importar los archivos de la DNIT, en **Editor INI MultiPHP** subí `upload_max_filesize` y `post_max_size` a `64M`.

## 6. Certificado (https)

En **SSL/TLS Status**, tildá el subdominio y tocá **Run AutoSSL**. Tarda unos minutos.

## 7. Permisos

La carpeta `facturaweb/public/uploads` tiene que poder escribirse, porque ahí van los logos y fondos de factura. Dale permisos `755`. Si al subir un logo da error, probá con `775`.

## 8. Probar

1. Entrá a `https://facturaweb.tudominio.com`.
2. Ingresá con **omar / 14512**.
3. Cambiá la contraseña en **Configuración > Usuarios**.

## Si algo falla

| Lo que ves | Qué revisar |
|---|---|
| Página en blanco o "Error 500" | Pasos 4 y 5: datos de la base en `config.php` y que `pdo_pgsql` esté activo. Por un momento podés poner `'debug' => true` para ver el error, y después volvé a `false`. |
| "could not find driver" | Falta la extensión `pdo_pgsql` (paso 5). |
| "password authentication failed" | El usuario o la clave de la base, o que falte el prefijo `usuario_`. |
| Inicio abre pero los demás menús dan 404 | Falta `public/.htaccess` (paso 3.4). Si no se arregla, poné `'pretty_urls' => false`. |
| Se ve el listado de carpetas o el código | La raíz del subdominio no apunta a `facturaweb/public` (paso 1). |
