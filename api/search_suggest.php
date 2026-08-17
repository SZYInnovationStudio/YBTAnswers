<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!is_installed()) {
    json_response(['items' => []]);
}

$keyword = trim((string) ($_GET['q'] ?? ''));
$keyword = mb_substr($keyword, 0, 60);

if ($keyword === '') {
    json_response(['items' => []]);
}

try {
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $keyword) . '%';
    $stmt = db()->prepare(
        'SELECT pid, title FROM problems
         WHERE deleted_at IS NULL AND (pid LIKE :k1 OR title LIKE :k2)
         ORDER BY pid ASC LIMIT 5'
    );
    $stmt->execute(['k1' => $like, 'k2' => $like]);
    $rows = $stmt->fetchAll();
} catch (Throwable $ex) {
    app_log('搜索建议失败: ' . $ex->getMessage(), 'error');
    $rows = [];
}

json_response(['items' => $rows]);
