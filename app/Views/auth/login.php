<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión · <?= e(config('app.nombre')) ?></title>
    <link rel="icon" type="image/png" href="<?= asset('img/favicon.png') ?>">
    <script>
        (function () {
            try {
                var t = localStorage.getItem('theme');
                if (t !== 'dark' && t !== 'light') { t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
                document.documentElement.setAttribute('data-theme', t);
            } catch (e) { document.documentElement.setAttribute('data-theme', 'light'); }
        })();
    </script>
    <link rel="stylesheet" href="<?= asset('css/login.css') ?>">
</head>
<body>
<div class="lg">
    <main class="lg-form">
        <button type="button" class="lg-tema" id="lg-tema" aria-label="Cambiar entre modo claro y oscuro">
            <svg class="luna" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
            <svg class="sol" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
        </button>

        <div class="lg-box">
            <img class="lg-logo on-light" src="<?= asset('img/logo-claro.png') ?>" alt="Dobi Soluciones Informáticas">
            <img class="lg-logo on-dark" src="<?= asset('img/logo-oscuro.png') ?>" alt="Dobi Soluciones Informáticas">

            <h1>Iniciar sesión</h1>
            <p class="lg-sub">Ingresá tu usuario y contraseña para entrar al sistema.</p>

            <?php if ($error): ?>
                <div class="lg-err" role="alert"><p><?= e($error) ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?= url('login') ?>">
                <?= csrf_field() ?>
                <div class="lg-f">
                    <label for="login">Usuario o correo</label>
                    <div class="lg-in">
                        <input type="text" id="login" name="login" value="<?= e($login) ?>" placeholder="tu usuario o tu correo" autocomplete="username" autofocus required>
                    </div>
                </div>
                <div class="lg-f">
                    <label for="clave">Contraseña</label>
                    <div class="lg-in">
                        <input type="password" id="clave" name="clave" placeholder="••••••••••" autocomplete="current-password" required>
                        <button type="button" class="lg-ojo" id="lg-ojo" aria-label="Mostrar u ocultar la contraseña">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="lg-btn">Entrar al sistema</button>
            </form>

            <p class="lg-ayuda">¿Necesitás acceso o olvidaste tu clave? Pedíselo al administrador del sistema.</p>
        </div>
        <div class="lg-pie">© <?= date('Y') ?> Dobi Soluciones Informáticas</div>
    </main>

    <aside class="lg-hero" aria-hidden="true">
        <div class="lg-hero-in">
            <h2>Facturación para tus empresas</h2>
            <p>Facturas, clientes, stock y cobros en un solo lugar, hecho en Paraguay.</p>
            <div class="mapa in">
                <?php readfile(__DIR__ . '/mapa.svg'); ?>
            </div>
        </div>
    </aside>
</div>
<script>
    (function () {
        var raiz = document.documentElement;
        document.getElementById('lg-tema').addEventListener('click', function () {
            var t = raiz.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            raiz.setAttribute('data-theme', t);
            try { localStorage.setItem('theme', t); } catch (e) {}
        });
        var ojo = document.getElementById('lg-ojo'), clave = document.getElementById('clave');
        ojo.addEventListener('click', function () { clave.type = clave.type === 'password' ? 'text' : 'password'; });
    })();
</script>
</body>
</html>
