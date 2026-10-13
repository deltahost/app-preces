<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'Método não permitido.'], 405);
}
$statement = db()->query('SELECT id, name, slug, description, icon, color, sort_order, updated_at FROM categories WHERE is_active = 1 ORDER BY sort_order, name');
json_response(['items' => $statement->fetchAll()]);

