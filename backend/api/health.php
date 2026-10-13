<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
try {
    db()->query('SELECT 1');
    json_response(['ok' => true, 'service' => 'preces-content-api']);
} catch (Throwable) {
    json_response(['ok' => false, 'service' => 'preces-content-api'], 503);
}

