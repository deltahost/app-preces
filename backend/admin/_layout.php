<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';

function admin_header(string $title): void
{
    $appName = e((string) config('app', 'name', 'Preces e Orações'));
    $current = basename($_SERVER['PHP_SELF'] ?? '');
    ?>
    <!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($title) ?> · <?= $appName ?></title><link rel="stylesheet" href="assets/admin.css"><link rel="stylesheet" href="assets/audio-converter.css"></head><body>
    <header class="top"><a class="brand" href="index.php">✦ <?= $appName ?></a>
    <?php if (!empty($_SESSION['admin_id'])): ?><nav><a class="<?= $current === 'index.php' ? 'active' : '' ?>" href="index.php">Resumo</a><a class="<?= $current === 'categories.php' ? 'active' : '' ?>" href="categories.php">Categorias</a><a class="<?= $current === 'prayers.php' ? 'active' : '' ?>" href="prayers.php">Orações</a><a class="<?= $current === 'media.php' ? 'active' : '' ?>" href="media.php">Mídias</a><a class="<?= $current === 'settings.php' ? 'active' : '' ?>" href="settings.php">Configurações</a><form method="post" action="logout.php"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="link" type="submit">Sair</button></form></nav><?php endif; ?>
    </header><main class="container"><div class="page-title"><h1><?= e($title) ?></h1></div>
    <?php
}

function admin_footer(): void
{
    ?></main><footer>Painel de conteúdo · Preces e Orações</footer><script src="assets/ffmpeg/ffmpeg.js"></script><script src="assets/audio-converter.js"></script></body></html><?php
}

function notice(): void
{
    if (!empty($_SESSION['flash_error'])) {
        echo '<div class="notice error">' . e((string) $_SESSION['flash_error']) . '</div>';
        unset($_SESSION['flash_error']);
    }
    if (!empty($_GET['ok'])) {
        echo '<div class="notice success">' . e((string) $_GET['ok']) . '</div>';
    }
    if (!empty($_GET['error'])) {
        echo '<div class="notice error">' . e((string) $_GET['error']) . '</div>';
    }
}
