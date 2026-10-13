<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'Método não permitido.'], 405);
}

$baseSelect = 'SELECT p.id, p.title, p.slug, p.excerpt, p.body, p.image_url, p.is_premium, p.is_featured, p.sort_order, p.published_at, p.updated_at, c.name AS category_name, c.slug AS category_slug FROM prayers p LEFT JOIN categories c ON c.id=p.category_id';
$publicWhere = ' p.is_published=1 AND (p.published_at IS NULL OR p.published_at <= NOW())';

if (!empty($_GET['id']) || !empty($_GET['slug'])) {
    if (!empty($_GET['id'])) {
        $statement = db()->prepare($baseSelect . ' WHERE' . $publicWhere . ' AND p.id=? LIMIT 1');
        $statement->execute([(int) $_GET['id']]);
    } else {
        $statement = db()->prepare($baseSelect . ' WHERE' . $publicWhere . ' AND p.slug=? LIMIT 1');
        $statement->execute([(string) $_GET['slug']]);
    }
    $prayer = $statement->fetch();
    json_response($prayer ?: ['error' => 'Oração não encontrada.'], $prayer ? 200 : 404);
}

$limit = max(1, min(100, (int) ($_GET['limit'] ?? 30)));
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;
$where = [$publicWhere];
$params = [];
if (!empty($_GET['category'])) {
    $where[] = 'c.slug = ?';
    $params[] = (string) $_GET['category'];
}
if (isset($_GET['featured'])) {
    $where[] = 'p.is_featured = ?';
    $params[] = filter_var($_GET['featured'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
}
$sql = $baseSelect . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY p.sort_order, p.published_at DESC, p.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
$statement = db()->prepare($sql);
$statement->execute($params);
json_response(['items' => $statement->fetchAll(), 'page' => $page, 'limit' => $limit]);

