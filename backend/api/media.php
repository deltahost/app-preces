<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'Método não permitido.'], 405);
}

$baseSelect = 'SELECT m.id, m.type, m.title, m.slug, m.description, m.source_url, m.thumbnail_url, m.duration_label, m.is_premium, m.sort_order, m.published_at, m.updated_at, c.name AS category_name, c.slug AS category_slug FROM media_items m LEFT JOIN media_categories c ON c.id=m.category_id';
$publicWhere = ' m.is_published=1 AND (m.published_at IS NULL OR m.published_at <= NOW())';

if (!empty($_GET['id']) || !empty($_GET['slug'])) {
    if (!empty($_GET['id'])) {
        $statement = db()->prepare($baseSelect . ' WHERE' . $publicWhere . ' AND m.id=? LIMIT 1');
        $statement->execute([(int) $_GET['id']]);
    } else {
        $statement = db()->prepare($baseSelect . ' WHERE' . $publicWhere . ' AND m.slug=? LIMIT 1');
        $statement->execute([(string) $_GET['slug']]);
    }
    $item = $statement->fetch();
    json_response($item ?: ['error' => 'Mídia não encontrada.'], $item ? 200 : 404);
}

$limit = max(1, min(100, (int) ($_GET['limit'] ?? 30)));
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;
$where = [$publicWhere];
$params = [];
if (!empty($_GET['type']) && in_array($_GET['type'], ['video', 'audio'], true)) {
    $where[] = 'm.type = ?';
    $params[] = (string) $_GET['type'];
}
if (!empty($_GET['category'])) {
    $where[] = 'c.slug = ?';
    $params[] = (string) $_GET['category'];
}
$sql = $baseSelect . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY m.sort_order, m.published_at DESC, m.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
$statement = db()->prepare($sql);
$statement->execute($params);
json_response(['items' => $statement->fetchAll(), 'page' => $page, 'limit' => $limit]);
