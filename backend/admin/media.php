<?php
require_once __DIR__ . '/_layout.php';
require_admin();
ensure_media_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'save_item');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete_item' && $id > 0) {
        $statement = db()->prepare('DELETE FROM media_items WHERE id=?');
        $statement->execute([$id]);
        redirect('media.php?ok=' . rawurlencode('Mídia removida.'));
    }
    if ($action === 'delete_category' && $id > 0) {
        $statement = db()->prepare('DELETE FROM media_categories WHERE id=?');
        $statement->execute([$id]);
        redirect('media.php?ok=' . rawurlencode('Categoria de mídia removida.'));
    }
    if ($action === 'save_category') {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            redirect('media.php?category_new=1&error=' . rawurlencode('Informe o nome da categoria.'));
        }
        $values = [$name, slugify(trim((string) ($_POST['slug'] ?? '')) ?: $name), trim((string) ($_POST['description'] ?? '')), (int) ($_POST['sort_order'] ?? 0), isset($_POST['is_active']) ? 1 : 0];
        try {
            if ($id > 0) {
                $values[] = $id;
                $statement = db()->prepare('UPDATE media_categories SET name=?, slug=?, description=?, sort_order=?, is_active=? WHERE id=?');
            } else {
                $statement = db()->prepare('INSERT INTO media_categories (name, slug, description, sort_order, is_active) VALUES (?, ?, ?, ?, ?)');
            }
            $statement->execute($values);
            redirect('media.php?ok=' . rawurlencode('Categoria de mídia salva.'));
        } catch (PDOException) {
            redirect('media.php?category_new=1&error=' . rawurlencode('Não foi possível salvar a categoria. Verifique o slug.'));
        }
    }

    $title = trim((string) ($_POST['title'] ?? ''));
    $type = in_array($_POST['type'] ?? '', ['video', 'audio'], true) ? (string) $_POST['type'] : 'video';
    $sourceUrl = trim((string) ($_POST['source_url'] ?? ''));
    try {
        $uploadedUrl = store_audio_upload();
        if ($uploadedUrl !== null) {
            $sourceUrl = $uploadedUrl;
        }
        if ($title === '' || $sourceUrl === '') {
            throw new RuntimeException('Informe o título e o link ou arquivo da mídia.');
        }
        if (!filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Informe um endereço válido para a mídia.');
        }
        if ($type === 'video' && !preg_match('~(?:youtube\.com|youtu\.be)~i', $sourceUrl)) {
            throw new RuntimeException('Para vídeos, use um link do YouTube.');
        }
        $publishedAt = trim((string) ($_POST['published_at'] ?? ''));
        $publishedAt = $publishedAt !== '' ? str_replace('T', ' ', $publishedAt) . ':00' : null;
        $values = [
            (int) ($_POST['category_id'] ?? 0) ?: null,
            $type,
            $title,
            slugify(trim((string) ($_POST['slug'] ?? '')) ?: $title),
            trim((string) ($_POST['description'] ?? '')),
            $sourceUrl,
            trim((string) ($_POST['thumbnail_url'] ?? '')),
            trim((string) ($_POST['duration_label'] ?? '')),
            isset($_POST['is_premium']) ? 1 : 0,
            isset($_POST['is_published']) ? 1 : 0,
            (int) ($_POST['sort_order'] ?? 0),
            $publishedAt,
        ];
        if ($id > 0) {
            $values[] = $id;
            $statement = db()->prepare('UPDATE media_items SET category_id=?, type=?, title=?, slug=?, description=?, source_url=?, thumbnail_url=?, duration_label=?, is_premium=?, is_published=?, sort_order=?, published_at=? WHERE id=?');
        } else {
            $statement = db()->prepare('INSERT INTO media_items (category_id, type, title, slug, description, source_url, thumbnail_url, duration_label, is_premium, is_published, sort_order, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        }
        $statement->execute($values);
        redirect('media.php?ok=' . rawurlencode('Mídia salva.'));
    } catch (RuntimeException $error) {
        redirect('media.php?new=1&error=' . rawurlencode($error->getMessage()));
    } catch (PDOException) {
        redirect('media.php?new=1&error=' . rawurlencode('Não foi possível salvar. Verifique se o slug já existe.'));
    }
}

$editing = null;
if (!empty($_GET['edit'])) {
    $statement = db()->prepare('SELECT * FROM media_items WHERE id=?');
    $statement->execute([(int) $_GET['edit']]);
    $editing = $statement->fetch() ?: null;
}
$editingCategory = null;
if (!empty($_GET['category_edit'])) {
    $statement = db()->prepare('SELECT * FROM media_categories WHERE id=?');
    $statement->execute([(int) $_GET['category_edit']]);
    $editingCategory = $statement->fetch() ?: null;
}
$showItemForm = isset($_GET['new']) || $editing;
$showCategoryForm = isset($_GET['category_new']) || $editingCategory;
$categories = db()->query('SELECT c.*, COUNT(m.id) AS media_count FROM media_categories c LEFT JOIN media_items m ON m.category_id=c.id GROUP BY c.id ORDER BY c.sort_order, c.name')->fetchAll();
$items = db()->query('SELECT m.*, c.name AS category_name FROM media_items m LEFT JOIN media_categories c ON c.id=m.category_id ORDER BY m.created_at DESC')->fetchAll();
admin_header('Vídeos e áudios'); notice();
?>
<div class="page-title"><p class="muted">Organize o conteúdo da aba Vídeos do aplicativo.</p><div class="actions"><a class="button" href="media.php?new=1">Nova mídia</a><a class="button secondary" href="media.php?category_new=1">Nova categoria</a></div></div>
<?php if ($showCategoryForm): ?><section class="card" style="margin-bottom:18px"><h2><?= $editingCategory ? 'Editar categoria' : 'Nova categoria de mídia' ?></h2><form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_category"><input type="hidden" name="id" value="<?= (int) ($editingCategory['id'] ?? 0) ?>">
<div><label>Nome</label><input name="name" required value="<?= e($editingCategory['name'] ?? '') ?>"></div><div><label>Slug</label><input name="slug" value="<?= e($editingCategory['slug'] ?? '') ?>" placeholder="gerado automaticamente"></div><div class="full"><label>Descrição</label><input name="description" maxlength="500" value="<?= e($editingCategory['description'] ?? '') ?>"></div><div><label>Ordem</label><input name="sort_order" type="number" value="<?= (int) ($editingCategory['sort_order'] ?? 0) ?>"></div><label class="check"><input type="checkbox" name="is_active" <?= !isset($editingCategory['is_active']) || $editingCategory['is_active'] ? 'checked' : '' ?>> Ativa no aplicativo</label><div class="full actions"><button class="button">Salvar categoria</button><a class="button secondary" href="media.php">Cancelar</a></div></form></section><?php endif; ?>
<?php if ($showItemForm): ?><section class="card" style="margin-bottom:18px"><h2><?= $editing ? 'Editar mídia' : 'Nova mídia' ?></h2><form method="post" enctype="multipart/form-data" class="form-grid"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_item"><input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
<div><label>Tipo</label><select name="type"><option value="video" <?= ($editing['type'] ?? 'video') === 'video' ? 'selected' : '' ?>>Vídeo do YouTube</option><option value="audio" <?= ($editing['type'] ?? '') === 'audio' ? 'selected' : '' ?>>Áudio</option></select></div><div><label>Categoria</label><select name="category_id"><option value="">Sem categoria</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) ($editing['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></div>
<div><label>Título</label><input name="title" required value="<?= e($editing['title'] ?? '') ?>"></div><div><label>Slug</label><input name="slug" value="<?= e($editing['slug'] ?? '') ?>" placeholder="gerado automaticamente"></div><div class="full"><label>Descrição</label><textarea name="description"><?= e($editing['description'] ?? '') ?></textarea></div>
<div class="full"><label>Link do YouTube ou URL do áudio</label><input name="source_url" type="url" value="<?= e($editing['source_url'] ?? '') ?>" placeholder="https://..."><small class="muted">Para vídeo, cole o link do YouTube. Para áudio, use este campo ou envie um arquivo abaixo.</small></div><div class="full" data-audio-converter><label>Enviar arquivo de áudio</label><input data-audio-file name="audio_file" type="file" accept="audio/*,.mp3,.m4a,.aac,.wav,.ogg,.webm"><div class="audio-tools"><select data-audio-quality aria-label="Qualidade do MP3"><option value="48k">Econômica · 48 kbps</option><option value="64k" selected>Recomendada · 64 kbps</option><option value="96k">Alta · 96 kbps</option></select><button data-audio-convert class="button secondary" type="button">Converter e comprimir para MP3</button></div><span data-audio-status class="audio-status">A conversão acontece neste navegador. O arquivo só é enviado quando você salvar.</span></div>
<div><label>URL da capa</label><input name="thumbnail_url" type="url" value="<?= e($editing['thumbnail_url'] ?? '') ?>" placeholder="https://..."></div><div><label>Duração exibida</label><input name="duration_label" value="<?= e($editing['duration_label'] ?? '') ?>" placeholder="Ex.: 08:35"></div><div><label>Ordem</label><input name="sort_order" type="number" value="<?= (int) ($editing['sort_order'] ?? 0) ?>"></div><div><label>Publicar a partir de</label><input name="published_at" type="datetime-local" value="<?= !empty($editing['published_at']) ? e(date('Y-m-d\TH:i', strtotime($editing['published_at']))) : '' ?>"></div><label class="check"><input type="checkbox" name="is_published" <?= !empty($editing['is_published']) ? 'checked' : '' ?>> Publicada</label><label class="check"><input type="checkbox" name="is_premium" <?= !empty($editing['is_premium']) ? 'checked' : '' ?>> Conteúdo Premium</label><div class="full actions"><button class="button">Salvar mídia</button><a class="button secondary" href="media.php">Cancelar</a></div></form></section><?php endif; ?>
<section style="margin-bottom:24px"><h2>Conteúdos</h2><div class="table-wrap"><table><thead><tr><th>Conteúdo</th><th>Tipo</th><th>Categoria</th><th>Acesso</th><th>Status</th><th>Ações</th></tr></thead><tbody><?php if (!$items): ?><tr><td colspan="6" class="muted">Nenhum vídeo ou áudio cadastrado.</td></tr><?php endif; ?><?php foreach ($items as $item): ?><tr><td><strong><?= e($item['title']) ?></strong><br><span class="muted"><?= e($item['slug']) ?></span></td><td><span class="pill"><?= $item['type'] === 'video' ? 'Vídeo' : 'Áudio' ?></span></td><td><?= e($item['category_name'] ?? 'Sem categoria') ?></td><td><?= $item['is_premium'] ? 'Premium' : 'Gratuita' ?></td><td><?= $item['is_published'] ? 'Publicada' : 'Rascunho' ?></td><td><div class="actions"><a class="button secondary" href="?edit=<?= (int) $item['id'] ?>">Editar</a><form method="post" onsubmit="return confirm('Excluir esta mídia?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_item"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button class="button danger">Excluir</button></form></div></td></tr><?php endforeach; ?></tbody></table></div></section>
<section><h2>Categorias de vídeos e áudios</h2><div class="table-wrap"><table><thead><tr><th>Categoria</th><th>Slug</th><th>Conteúdos</th><th>Status</th><th>Ações</th></tr></thead><tbody><?php if (!$categories): ?><tr><td colspan="5" class="muted">Nenhuma categoria de mídia cadastrada.</td></tr><?php endif; ?><?php foreach ($categories as $category): ?><tr><td><strong><?= e($category['name']) ?></strong></td><td><?= e($category['slug']) ?></td><td><?= (int) $category['media_count'] ?></td><td><?= $category['is_active'] ? 'Ativa' : 'Oculta' ?></td><td><div class="actions"><a class="button secondary" href="?category_edit=<?= (int) $category['id'] ?>">Editar</a><form method="post" onsubmit="return confirm('Excluir esta categoria? As mídias ficarão sem categoria.')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_category"><input type="hidden" name="id" value="<?= (int) $category['id'] ?>"><button class="button danger">Excluir</button></form></div></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php admin_footer(); ?>
