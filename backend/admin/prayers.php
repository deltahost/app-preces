<?php
require_once __DIR__ . '/_layout.php';
require_admin();
ensure_prayer_audio_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'save');
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'delete' && $id > 0) {
        $statement = db()->prepare('DELETE FROM prayers WHERE id = ?');
        $statement->execute([$id]);
        redirect('prayers.php?ok=' . rawurlencode('Oração removida.'));
    }

    $title = trim((string) ($_POST['title'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? ''));
    $slug = slugify(trim((string) ($_POST['slug'] ?? '')) ?: $title);
    if ($title === '' || $body === '') {
        redirect('prayers.php?error=' . rawurlencode('Informe o título e o texto da oração.'));
    }
    $publishedAt = trim((string) ($_POST['published_at'] ?? ''));
    $publishedAt = $publishedAt !== '' ? str_replace('T', ' ', $publishedAt) . ':00' : null;
    $audioUrl = trim((string) ($_POST['audio_url'] ?? ''));
    try {
        $uploadedUrl = store_audio_upload();
        if ($uploadedUrl !== null) {
            $audioUrl = $uploadedUrl;
        }
    } catch (RuntimeException $error) {
        redirect('prayers.php?' . ($id > 0 ? 'edit=' . $id . '&' : 'new=1&') . 'error=' . rawurlencode($error->getMessage()));
    }
    $values = [
        (int) ($_POST['category_id'] ?? 0) ?: null,
        $title,
        $slug,
        trim((string) ($_POST['excerpt'] ?? '')),
        $body,
        trim((string) ($_POST['image_url'] ?? '')),
        $audioUrl,
        isset($_POST['is_premium']) ? 1 : 0,
        isset($_POST['is_featured']) ? 1 : 0,
        isset($_POST['is_published']) ? 1 : 0,
        (int) ($_POST['sort_order'] ?? 0),
        $publishedAt,
    ];
    try {
        if ($id > 0) {
            $values[] = $id;
            $statement = db()->prepare('UPDATE prayers SET category_id=?, title=?, slug=?, excerpt=?, body=?, image_url=?, audio_url=?, is_premium=?, is_featured=?, is_published=?, sort_order=?, published_at=? WHERE id=?');
        } else {
            $statement = db()->prepare('INSERT INTO prayers (category_id, title, slug, excerpt, body, image_url, audio_url, is_premium, is_featured, is_published, sort_order, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        }
        $statement->execute($values);
        redirect('prayers.php?ok=' . rawurlencode('Oração salva.'));
    } catch (PDOException) {
        redirect('prayers.php?error=' . rawurlencode('Não foi possível salvar. Verifique se o slug já existe.'));
    }
}

$editing = null;
if (!empty($_GET['edit'])) {
    $statement = db()->prepare('SELECT * FROM prayers WHERE id = ?');
    $statement->execute([(int) $_GET['edit']]);
    $editing = $statement->fetch() ?: null;
}
$showForm = isset($_GET['new']) || $editing;
$categories = db()->query('SELECT id, name FROM categories ORDER BY sort_order, name')->fetchAll();
$prayers = db()->query('SELECT p.*, c.name AS category_name FROM prayers p LEFT JOIN categories c ON c.id=p.category_id ORDER BY p.created_at DESC')->fetchAll();
admin_header('Orações e preces'); notice();
?>
<div class="page-title"><p class="muted">Cadastre os textos que serão consultados no aplicativo.</p><a class="button" href="prayers.php?new=1">Nova oração</a></div>
<?php if ($showForm): ?><section class="card" style="margin-bottom:18px"><h2><?= $editing ? 'Editar oração' : 'Nova oração' ?></h2><form method="post" enctype="multipart/form-data" class="form-grid"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
<div><label>Título</label><input name="title" required value="<?= e($editing['title'] ?? '') ?>"></div><div><label>Slug</label><input name="slug" value="<?= e($editing['slug'] ?? '') ?>" placeholder="gerado automaticamente"></div>
<div><label>Categoria</label><select name="category_id"><option value="">Sem categoria</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) ($editing['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></div><div><label>Ordem</label><input name="sort_order" type="number" value="<?= (int) ($editing['sort_order'] ?? 0) ?>"></div>
<div class="full"><label>Resumo</label><input name="excerpt" maxlength="500" value="<?= e($editing['excerpt'] ?? '') ?>"></div><div class="full"><label>Texto completo</label><textarea name="body" required><?= e($editing['body'] ?? '') ?></textarea></div><div class="full"><label>URL da imagem</label><input name="image_url" type="url" value="<?= e($editing['image_url'] ?? '') ?>" placeholder="https://..."></div><div class="full"><label>Link do áudio personalizado</label><input name="audio_url" type="url" value="<?= e($editing['audio_url'] ?? '') ?>" placeholder="https://.../narracao.mp3"><small class="muted">Opcional. Cole um endereço existente ou envie um arquivo abaixo.</small></div><div class="full"><label>Enviar áudio da oração</label><input name="audio_file" type="file" accept="audio/mpeg,audio/mp4,audio/aac,audio/wav,audio/ogg,.mp3,.m4a,.aac,.wav,.ogg"><small class="muted">MP3, M4A, AAC, WAV ou OGG, até 25 MB. Um novo arquivo substitui o link informado.</small></div>
<div><label>Publicar a partir de</label><input name="published_at" type="datetime-local" value="<?= !empty($editing['published_at']) ? e(date('Y-m-d\TH:i', strtotime($editing['published_at']))) : '' ?>"></div><div></div>
<label class="check"><input type="checkbox" name="is_published" <?= !empty($editing['is_published']) ? 'checked' : '' ?>> Publicada</label><label class="check"><input type="checkbox" name="is_premium" <?= !empty($editing['is_premium']) ? 'checked' : '' ?>> Conteúdo Premium</label><label class="check"><input type="checkbox" name="is_featured" <?= !empty($editing['is_featured']) ? 'checked' : '' ?>> Destaque</label>
<div class="full actions"><button class="button" type="submit">Salvar oração</button><a class="button secondary" href="prayers.php">Cancelar</a></div></form></section><?php endif; ?>
<div class="table-wrap"><table><thead><tr><th>Oração</th><th>Categoria</th><th>Acesso</th><th>Status</th><th>Ações</th></tr></thead><tbody><?php foreach ($prayers as $prayer): ?><tr><td><strong><?= e($prayer['title']) ?></strong><br><span class="muted"><?= e($prayer['slug']) ?></span></td><td><?= e($prayer['category_name'] ?? 'Sem categoria') ?></td><td><span class="pill"><?= $prayer['is_premium'] ? 'Premium' : 'Gratuita' ?></span></td><td><span class="pill"><?= $prayer['is_published'] ? 'Publicada' : 'Rascunho' ?></span></td><td><div class="actions"><a class="button secondary" href="?edit=<?= (int) $prayer['id'] ?>">Editar</a><form method="post" onsubmit="return confirm('Excluir esta oração?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $prayer['id'] ?>"><button class="button danger">Excluir</button></form></div></td></tr><?php endforeach; ?></tbody></table></div>
<?php admin_footer(); ?>
