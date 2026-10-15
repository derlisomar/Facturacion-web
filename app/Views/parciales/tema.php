<?php /* Modo claro/oscuro (mismo criterio que dbstock: se guarda en localStorage.theme) y fuente Outfit. */ ?>
<script>
    (function () {
        try {
            var t = localStorage.getItem('theme');
            if (t !== 'dark' && t !== 'light') { t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
            document.documentElement.setAttribute('data-theme', t);
        } catch (e) { document.documentElement.setAttribute('data-theme', 'light'); }
    })();
</script>
<link rel="preload" href="<?= url_base() ?>/assets/vendor/outfit/outfit-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset('vendor/outfit/outfit.css') ?>">
