<?php
require_once __DIR__ . '/_layout.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'save');
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'delete' && $id > 0) {
        $statement = db()->prepare('DELETE FROM categories WHERE id = ?');
        $statement->execute([$id]);
        redirect('categories.php?ok=' . rawurlencode('Categoria removida.'));
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $slug = slugify(trim((string) ($_POST['slug'] ?? '')) ?: $name);
    if ($name === '') {
        redirect('categories.php?error=' . rawurlencode('Informe o nome da categoria.'));
    }
    $values = [$name, $slug, trim((string) ($_POST['description'] ?? '')), trim((string) ($_POST['icon'] ?? 'book-outline')), trim((string) ($_POST['color'] ?? '#7B5C45')), (int) ($_POST['sort_order'] ?? 0), isset($_POST['is_active']) ? 1 : 0];
    try {
        if ($id > 0) {
            $values[] = $id;
            $statement = db()->prepare('UPDATE categories SET name=?, slug=?, description=?, icon=?, color=?, sort_order=?, is_active=? WHERE id=?');
        } else {
            $statement = db()->prepare('INSERT INTO categories (name, slug, description, icon, color, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)');
        }
        $statement->execute($values);
        redirect('categories.php?ok=' . rawurlencode('Categoria salva.'));
    } catch (PDOException) {
        redirect('categories.php?error=' . rawurlencode('Não foi possível salvar. Verifique se o slug já existe.'));
    }
}

$editing = null;
if (!empty($_GET['edit'])) {
    $statement = db()->prepare('SELECT * FROM categories WHERE id = ?');
    $statement->execute([(int) $_GET['edit']]);
    $editing = $statement->fetch() ?: null;
}
$showForm = isset($_GET['new']) || $editing;
$categories = db()->query('SELECT c.*, COUNT(p.id) AS prayer_count FROM categories c LEFT JOIN prayers p ON p.category_id=c.id GROUP BY c.id ORDER BY c.sort_order, c.name')->fetchAll();
admin_header('Categorias'); notice();
?>
<div class="page-title"><p class="muted">Organize as orações exibidas no mosaico do aplicativo.</p><a class="button" href="categories.php?new=1">Nova categoria</a></div>
<?php if ($showForm): ?><section class="card" style="margin-bottom:18px"><h2><?= $editing ? 'Editar categoria' : 'Nova categoria' ?></h2><form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
<div><label>Nome</label><input name="name" required value="<?= e($editing['name'] ?? '') ?>"></div><div><label>Slug</label><input name="slug" value="<?= e($editing['slug'] ?? '') ?>" placeholder="gerado automaticamente"></div>
<div class="full"><label>Descrição</label><input name="description" value="<?= e($editing['description'] ?? '') ?>"></div><div><label>Ícone</label><input name="icon" value="<?= e($editing['icon'] ?? 'book-outline') ?>"></div><div><label>Cor</label><input name="color" type="color" value="<?= e($editing['color'] ?? '#7B5C45') ?>"></div><div><label>Ordem</label><input name="sort_order" type="number" value="<?= (int) ($editing['sort_order'] ?? 0) ?>"></div><label class="check"><input type="checkbox" name="is_active" <?= !isset($editing['is_active']) || $editing['is_active'] ? 'checked' : '' ?>> Ativa no aplicativo</label><div class="full actions"><button class="button" type="submit">Salvar</button><a class="button secondary" href="categories.php">Cancelar</a></div></form></section><?php endif; ?>
<div class="table-wrap"><table><thead><tr><th>Categoria</th><th>Slug</th><th>Orações</th><th>Status</th><th>Ações</th></tr></thead><tbody><?php foreach ($categories as $category): ?><tr><td><strong><?= e($category['name']) ?></strong><br><span class="muted"><?= e($category['icon']) ?></span></td><td><?= e($category['slug']) ?></td><td><?= (int) $category['prayer_count'] ?></td><td><span class="pill"><?= $category['is_active'] ? 'Ativa' : 'Oculta' ?></span></td><td><div class="actions"><a class="button secondary" href="?edit=<?= (int) $category['id'] ?>">Editar</a><form method="post" onsubmit="return confirm('Excluir esta categoria? As orações ficarão sem categoria.')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $category['id'] ?>"><button class="button danger">Excluir</button></form></div></td></tr><?php endforeach; ?></tbody></table></div>
<?php admin_footer(); ?>
