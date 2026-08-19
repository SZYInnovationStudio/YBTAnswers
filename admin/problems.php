<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin_layout.php';
require_once dirname(__DIR__) . '/includes/tree_cache.php';

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'problem_delete') {
            $id = int_input($_POST['id'] ?? 0);
            db()->prepare('UPDATE problems SET deleted_at = NOW() WHERE id = :id')->execute(['id' => $id]);
            TreeCache::clear();
            flash('success', '题目已移入回收站。');
        } elseif ($action === 'problem_restore') {
            $id = int_input($_POST['id'] ?? 0);
            $chk = db()->prepare('SELECT p.id FROM problems p LEFT JOIN chapters c ON c.id = p.chapter_id WHERE p.id = :id AND c.id IS NULL LIMIT 1');
            $chk->execute(['id' => $id]);
            if ($chk->fetch()) {
                flash('error', '该题目所属章节不存在，无法恢复。请先删除后重新添加题目。');
            } else {
                db()->prepare('UPDATE problems SET deleted_at = NULL WHERE id = :id')->execute(['id' => $id]);
                TreeCache::clear();
                flash('success', '题目已恢复。');
            }
        } elseif ($action === 'problem_purge') {
            $id = int_input($_POST['id'] ?? 0);
            db()->prepare('DELETE FROM problems WHERE id = :id')->execute(['id' => $id]);
            TreeCache::clear();
            flash('success', '题目已彻底删除。');
        } elseif ($action === 'trash_empty') {
            db()->exec('DELETE FROM problems WHERE deleted_at IS NOT NULL');
            TreeCache::clear();
            flash('success', '回收站已清空。');
        }
    } catch (Throwable $ex) {
        app_log('题目管理操作失败: ' . $ex->getMessage(), 'error');
        flash('error', '操作失败，请查看日志。');
    }
    redirect(url('admin/problems.php?' . http_build_query($_GET)));
}

$keyword = str_input((string) ($_GET['q'] ?? ''));
$view = ($_GET['view'] ?? 'list') === 'trash' ? 'trash' : 'list';
$chapterFilter = int_input($_GET['chapter'] ?? 0);
$perPage = 20;
$page = max(1, int_input($_GET['page'] ?? 1, 1));

$where = $view === 'trash' ? 'p.deleted_at IS NOT NULL' : 'p.deleted_at IS NULL';
$params = [];

if ($keyword !== '') {
    $where .= ' AND (p.pid LIKE :k1 OR p.title LIKE :k2)';
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $keyword) . '%';
    $params['k1'] = $like;
    $params['k2'] = $like;
}
if ($chapterFilter > 0 && $view === 'list') {
    $where .= ' AND p.chapter_id = :chapter_id';
    $params['chapter_id'] = $chapterFilter;
}

try {
    $countStmt = db()->prepare("SELECT COUNT(*) FROM problems p WHERE $where");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $pager = paginate($total, $perPage, $page);

    $listStmt = db()->prepare(
        "SELECT p.id, p.pid, p.title, p.is_answer_manual, p.answer_code, p.updated_at, c.name AS chapter_name
         FROM problems p
         LEFT JOIN chapters c ON c.id = p.chapter_id
         WHERE $where
         ORDER BY p.pid ASC
         LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $key => $value) {
        $listStmt->bindValue($key, $value);
    }
    $listStmt->bindValue('limit', $perPage, PDO::PARAM_INT);
    $listStmt->bindValue('offset', $pager['offset'], PDO::PARAM_INT);
    $listStmt->execute();
    $problems = $listStmt->fetchAll();

    $chapters = db()->query(
        'SELECT ch.id, ch.name, sp.name AS subpart_name
         FROM chapters ch JOIN subparts sp ON sp.id = ch.subpart_id
         ORDER BY sp.sort_order, ch.sort_order, ch.id'
    )->fetchAll();
} catch (Throwable $ex) {
    app_log('题目列表查询失败: ' . $ex->getMessage(), 'error');
    $problems = [];
    $chapters = [];
    $pager = ['pages' => 1, 'page' => 1, 'total' => 0];
}

ob_start();
?>
<div class="admin-page-header">
  <h1>题目管理</h1>
  <div class="flex gap-8">
    <?php if ($view === 'trash'): ?>
      <a class="btn" href="<?= e(url('admin/problems.php')) ?>">返回列表</a>
      <?php if ($total > 0): ?>
      <form method="post" data-confirm="确定清空回收站吗？此操作不可恢复。">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="trash_empty">
        <button type="submit" class="btn btn--danger">清空回收站</button>
      </form>
      <?php endif; ?>
    <?php else: ?>
      <a class="btn" href="<?= e(url('admin/problems.php?view=trash')) ?>">回收站</a>
      <a class="btn btn--primary" href="<?= e(url('admin/problem_edit.php')) ?>">+ 添加题目</a>
    <?php endif; ?>
  </div>
</div>

<form class="toolbar" method="get" action="<?= e(url('admin/problems.php')) ?>">
  <?php if ($view === 'trash'): ?><input type="hidden" name="view" value="trash"><?php endif; ?>
  <input class="form-input" type="text" name="q" value="<?= e($keyword) ?>" placeholder="搜索题号或标题…" aria-label="搜索">
  <?php if ($view === 'list'): ?>
  <select class="form-select" name="chapter" aria-label="按章节筛选">
    <option value="0">全部章节</option>
    <?php foreach ($chapters as $chapter): ?>
      <option value="<?= (int) $chapter['id'] ?>" <?= $chapterFilter === (int) $chapter['id'] ? 'selected' : '' ?>>
        <?= e($chapter['subpart_name'] . ' / ' . $chapter['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <?php endif; ?>
  <button type="submit" class="btn">筛选</button>
  <span class="toolbar__spacer"></span>
  <span class="text-muted" style="font-size: 13.5px;">共 <?= (int) $pager['total'] ?> 题</span>
</form>

<div class="table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th style="width: 80px;">题号</th>
        <th>标题</th>
        <?php if ($view === 'list'): ?><th style="width: 160px;">章节</th><?php endif; ?>
        <th style="width: 110px;">答案状态</th>
        <th style="width: 140px;">更新时间</th>
        <th style="width: 250px; text-align: right;">操作</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($problems)): ?>
        <tr><td class="admin-table__empty" colspan="6">暂无数据</td></tr>
      <?php endif; ?>
      <?php foreach ($problems as $problem): ?>
      <tr>
        <td><span class="admin-table__pid"><?= e((string) $problem['pid']) ?></span></td>
        <td><?= e((string) $problem['title']) ?></td>
        <?php if ($view === 'list'): ?>
        <td class="text-muted" style="font-size: 13px;"><?= e((string) ($problem['chapter_name'] ?? '—')) ?></td>
        <?php endif; ?>
        <td>
          <?php if (trim((string) $problem['answer_code']) === ''): ?>
            <span class="badge badge--neutral">暂无</span>
          <?php elseif ((int) $problem['is_answer_manual'] === 1): ?>
            <span class="badge badge--accent">人工校对</span>
          <?php else: ?>
            <span class="badge badge--neutral">AI 生成</span>
          <?php endif; ?>
        </td>
        <td class="text-muted"><?= e(format_datetime((string) $problem['updated_at'])) ?></td>
        <td>
          <div class="admin-table__actions">
            <?php if ($view === 'list'): ?>
              <a class="btn btn--sm" href="<?= e(url('admin/problem_edit.php?id=' . (int) $problem['id'])) ?>">编辑</a>
              <button type="button" class="btn btn--sm" data-regenerate="<?= (int) $problem['id'] ?>" data-manual="<?= (int) $problem['is_answer_manual'] ?>">重新生成</button>
              <a class="btn btn--sm btn--ghost" href="<?= e(url('problem.php?pid=' . urlencode((string) $problem['pid']))) ?>" target="_blank">预览</a>
              <form method="post" data-confirm="确定将题目 <?= e((string) $problem['pid']) ?> 移入回收站吗？" style="display: inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="problem_delete">
                <input type="hidden" name="id" value="<?= (int) $problem['id'] ?>">
                <button type="submit" class="btn btn--sm btn--danger">删除</button>
              </form>
            <?php else: ?>
              <form method="post" style="display: inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="problem_restore">
                <input type="hidden" name="id" value="<?= (int) $problem['id'] ?>">
                <button type="submit" class="btn btn--sm">恢复</button>
              </form>
              <form method="post" data-confirm="彻底删除后不可恢复，确定吗？" style="display: inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="problem_purge">
                <input type="hidden" name="id" value="<?= (int) $problem['id'] ?>">
                <button type="submit" class="btn btn--sm btn--danger">彻底删除</button>
              </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php
$query = $_GET;
unset($query['page']);
$pattern = url('admin/problems.php?' . http_build_query($query) . ($query ? '&' : '') . 'page={page}');
echo pagination_links($pager['pages'], $pager['page'], $pattern);
?>
<?php
$content = ob_get_clean();

render_admin_layout([
    'pageTitle' => $view === 'trash' ? '回收站' : '题目管理',
    'content' => $content,
    'active' => 'problems',
]);
