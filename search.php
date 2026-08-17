<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';

guard_installed();

$keyword = str_input((string) ($_GET['q'] ?? ''));
$keyword = mb_substr($keyword, 0, 60);

if ($keyword !== '' && preg_match('/^\d{3,5}$/', $keyword)) {
    redirect(url('problem.php?pid=' . urlencode($keyword)));
}

$perPage = 20;
$page = max(1, int_input($_GET['page'] ?? 1, 1));
$problems = [];
$pager = ['pages' => 1, 'page' => 1, 'total' => 0];

if ($keyword !== '') {
    try {
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $keyword) . '%';

        $countStmt = db()->prepare(
            'SELECT COUNT(*) FROM problems p
             WHERE p.deleted_at IS NULL AND (p.pid LIKE :k1 OR p.title LIKE :k2)'
        );
        $countStmt->execute(['k1' => $like, 'k2' => $like]);
        $total = (int) $countStmt->fetchColumn();
        $pager = paginate($total, $perPage, $page);

        $stmt = db()->prepare(
            'SELECT p.pid, p.title, p.time_limit, p.memory_limit, p.answer_code, c.name AS chapter_name
             FROM problems p
             JOIN chapters c ON c.id = p.chapter_id
             WHERE p.deleted_at IS NULL AND (p.pid LIKE :k1 OR p.title LIKE :k2)
             ORDER BY p.pid ASC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue('k1', $like);
        $stmt->bindValue('k2', $like);
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $pager['offset'], PDO::PARAM_INT);
        $stmt->execute();
        $problems = $stmt->fetchAll();
    } catch (Throwable $ex) {
        app_log('搜索失败: ' . $ex->getMessage(), 'error');
    }
}

ob_start();
?>
<div class="page-header">
  <h1 class="page-header__title">搜索</h1>
  <p class="page-header__desc">输入题号可直接跳转，输入标题关键字可模糊匹配。</p>
</div>

<form action="<?= e(url('search.php')) ?>" method="get" role="search" style="max-width: 520px; margin-bottom: 24px;" data-search-page-form>
  <div class="hero__search">
    <svg class="hero__search-icon" width="17" height="17" viewBox="0 0 16 16" fill="none" aria-hidden="true">
      <circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.5"/>
      <path d="M11 11l3.5 3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
    </svg>
    <input type="text" name="q" class="hero__search-input" value="<?= e($keyword) ?>"
           placeholder="输入题号（如 1000）或题目名称…" aria-label="搜索题目" maxlength="60">
    <button type="submit" class="btn btn--accent hero__search-btn">搜索</button>
  </div>
</form>

<?php if ($keyword === ''): ?>
  <div class="problem-list">
    <div class="empty-state">
      <div class="empty-state__icon" aria-hidden="true">🔎</div>
      <p class="empty-state__title">输入关键字开始搜索</p>
      <p class="empty-state__desc">支持题号精确匹配与标题模糊匹配。</p>
    </div>
  </div>
<?php elseif (empty($problems)): ?>
  <div class="problem-list">
    <div class="empty-state">
      <div class="empty-state__icon" aria-hidden="true">📭</div>
      <p class="empty-state__title">未找到与「<?= e($keyword) ?>」相关的题目</p>
      <p class="empty-state__desc">试试更换关键字，或直接输入 4 位题号。</p>
    </div>
  </div>
<?php else: ?>
  <p class="search-summary">共找到 <strong><?= (int) $pager['total'] ?></strong> 个与「<?= e($keyword) ?>」相关的题目</p>
  <div class="problem-list" role="list">
    <?php foreach ($problems as $problem): ?>
      <a class="problem-item" role="listitem" href="<?= e(url('problem.php?pid=' . urlencode((string) $problem['pid']))) ?>">
        <span class="problem-item__pid"><?= highlight_keyword((string) $problem['pid'], $keyword) ?></span>
        <span class="problem-item__body">
          <span class="problem-item__title"><?= highlight_keyword((string) $problem['title'], $keyword) ?></span>
          <span class="problem-item__meta"><?= e((string) $problem['chapter_name']) ?></span>
        </span>
        <span class="problem-item__limits">
          <?php if ($problem['time_limit']): ?><?= e((string) $problem['time_limit']) ?><?php endif; ?>
          <?php if ($problem['memory_limit']): ?><br><?= e((string) $problem['memory_limit']) ?><?php endif; ?>
        </span>
      </a>
    <?php endforeach; ?>
  </div>
  <?php
  echo pagination_links($pager['pages'], $pager['page'], url('search.php?q=' . urlencode($keyword) . '&page={page}'));
  ?>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var form = document.querySelector('[data-search-page-form]');
  if (!form) return;
  form.addEventListener('submit', function (event) {
    var value = form.querySelector('input[name="q"]').value.trim();
    if (/^\d{3,5}$/.test(value)) {
      event.preventDefault();
      window.location.href = '<?= e(url('problem.php')) ?>?pid=' + encodeURIComponent(value);
    }
  });
});
</script>
<?php
$content = ob_get_clean();

render_layout([
    'pageTitle' => $keyword !== '' ? '搜索：' . $keyword : '搜索',
    'content' => $content,
    'activeNav' => 'search',
]);
