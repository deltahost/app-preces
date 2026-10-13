<?php
require_once __DIR__ . '/_layout.php';
require_admin();
ensure_media_schema();
ensure_prayer_audio_schema();
$metrics = [
    'Categorias ativas' => (int) db()->query('SELECT COUNT(*) FROM categories WHERE is_active = 1')->fetchColumn(),
    'Orações publicadas' => (int) db()->query('SELECT COUNT(*) FROM prayers WHERE is_published = 1')->fetchColumn(),
    'Mídias publicadas' => (int) db()->query('SELECT COUNT(*) FROM media_items WHERE is_published = 1')->fetchColumn(),
    'Conteúdos Premium' => (int) db()->query('SELECT COUNT(*) FROM prayers WHERE is_premium = 1')->fetchColumn(),
];
admin_header('Resumo');
?>
<p>Olá, <?= e((string) ($_SESSION['admin_name'] ?? 'Administrador')) ?>. Gerencie o conteúdo que aparecerá no aplicativo.</p>
<div class="grid"><?php foreach ($metrics as $label => $value): ?><section class="card"><div class="metric"><?= $value ?></div><div class="muted"><?= e($label) ?></div></section><?php endforeach; ?></div>
<section class="card" style="margin-top:16px"><h2>Ações rápidas</h2><div class="actions"><a class="button" href="prayers.php?new=1">Nova oração</a><a class="button secondary" href="categories.php?new=1">Nova categoria</a><a class="button secondary" href="media.php?new=1">Novo vídeo ou áudio</a></div></section>
<?php admin_footer(); ?>
