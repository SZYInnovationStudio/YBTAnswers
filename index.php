<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';

guard_installed();

$perPage = 20;
$page = max(1, int_input($_GET['page'] ?? 1, 1));
$chapterId = int_input($_GET['chapter'] ?? 0);
$subpartId = int_input($_GET['subpart'] ?? 0);

$where = 'p.deleted_at IS NULL';
$params = [];
$breadcrumb = [['label' => '首页', 'href' => url('index.php')]];
$pageTitle = '全部题目';
$activeChapterId = null;

try {
    if ($chapterId > 0) {
        $stmt = db()->prepare(
            'SELECT c.id, c.name, sp.id AS subpart_id, sp.name AS subpart_name, pt.name AS part_name
             FROM chapters c
             JOIN subparts sp ON sp.id = c.subpart_id
             JOIN parts pt ON pt.id = sp.part_id
             WHERE c.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $chapterId]);
        $chapter = $stmt->fetch();
        if ($chapter) {
            $where .= ' AND p.chapter_id = :chapter_id';
            $params['chapter_id'] = $chapterId;
            $activeChapterId = $chapterId;
            $pageTitle = $chapter['name'];
            $breadcrumb[] = ['label' => $chapter['part_name'], 'href' => null];
            $breadcrumb[] = ['label' => $chapter['subpart_name'], 'href' => url('index.php?subpart=' . (int) $chapter['subpart_id'])];
            $breadcrumb[] = ['label' => $chapter['name'], 'href' => null];
        }
    } elseif ($subpartId > 0) {
        $stmt = db()->prepare(
            'SELECT sp.id, sp.name, pt.name AS part_name
             FROM subparts sp JOIN parts pt ON pt.id = sp.part_id
             WHERE sp.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $subpartId]);
        $subpart = $stmt->fetch();
        if ($subpart) {
            $where .= ' AND c.subpart_id = :subpart_id';
            $params['subpart_id'] = $subpartId;
            $pageTitle = $subpart['name'];
            $breadcrumb[] = ['label' => $subpart['part_name'], 'href' => null];
            $breadcrumb[] = ['label' => $subpart['name'], 'href' => null];
        }
    }

    $countStmt = db()->prepare("SELECT COUNT(*) FROM problems p JOIN chapters c ON c.id = p.chapter_id WHERE $where");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $pager = paginate($total, $perPage, $page);

    $listStmt = db()->prepare(
        "SELECT p.pid, p.title, p.time_limit, p.memory_limit, p.answer_code, c.name AS chapter_name
         FROM problems p
         JOIN chapters c ON c.id = p.chapter_id
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

    $stats = db()->query(
        'SELECT
            (SELECT COUNT(*) FROM problems WHERE deleted_at IS NULL) AS problem_count,
            (SELECT COUNT(*) FROM chapters) AS chapter_count,
            (SELECT COUNT(*) FROM problems WHERE deleted_at IS NULL AND answer_code <> "") AS answered_count'
    )->fetch();
} catch (Throwable $ex) {
    app_log('首页查询失败: ' . $ex->getMessage(), 'error');
    $problems = [];
    $pager = ['pages' => 1, 'page' => 1, 'total' => 0];
    $stats = ['problem_count' => 0, 'chapter_count' => 0, 'answered_count' => 0];
    $breadcrumb = [['label' => '首页', 'href' => url('index.php')]];
}

ob_start();
?>
<section class="hero">
  <h1 class="hero__title">信息学奥赛一本通 <span class="hero__title-accent">答案网</span></h1>
  <p class="hero__desc">收录 ybt.ssoier.cn 全部题目与参考解答，支持题号 / 标题搜索、数学公式渲染与代码高亮。公益非盈利，答案由 AI 生成，仅供学习参考。</p>
  <form class="hero__search" action="<?= e(url('search.php')) ?>" method="get" role="search" data-hero-search>
    <svg class="hero__search-icon" width="17" height="17" viewBox="0 0 16 16" fill="none" aria-hidden="true">
      <circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.5"/>
      <path d="M11 11l3.5 3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
    </svg>
    <input type="text" name="q" class="hero__search-input" placeholder="输入题号（如 1000）或题目名称…" aria-label="搜索题目" maxlength="60">
    <button type="submit" class="btn btn--accent hero__search-btn">搜索</button>
  </form>
</section>

<section class="stats" aria-label="题库统计">
  <div class="stat-card">
    <div class="stat-card__value"><?= (int) $stats['problem_count'] ?></div>
    <div class="stat-card__label">收录题目</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__value"><?= (int) $stats['chapter_count'] ?></div>
    <div class="stat-card__label">章节分类</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__value"><?= (int) $stats['answered_count'] ?></div>
    <div class="stat-card__label">已有答案</div>
  </div>
</section>

<section id="catalog">
  <nav class="breadcrumb" aria-label="面包屑">
    <?php foreach ($breadcrumb as $i => $crumb): ?>
      <?php if ($i > 0): ?><span class="breadcrumb__sep">/</span><?php endif; ?>
      <?php if ($crumb['href']): ?>
        <a href="<?= e($crumb['href']) ?>"><?= e($crumb['label']) ?></a>
      <?php else: ?>
        <span aria-current="page"><?= e($crumb['label']) ?></span>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>

  <div class="flex-between" style="margin-bottom: 14px;">
    <h2 class="section-title mb-0"><?= e($pageTitle) ?></h2>
    <span class="text-muted" style="font-size: 13.5px;">共 <?= (int) $pager['total'] ?> 题</span>
  </div>

  <?php if (empty($problems)): ?>
    <div class="problem-list">
      <div class="empty-state">
        <div class="empty-state__icon" aria-hidden="true">📭</div>
        <p class="empty-state__title">这里还没有题目</p>
        <p class="empty-state__desc">请管理员登录后台，通过「一键抓取」添加题目。</p>
        <a class="btn" href="<?= e(url('admin/login.php')) ?>">进入后台</a>
      </div>
    </div>
  <?php else: ?>
    <div class="problem-list" role="list">
      <?php foreach ($problems as $problem): ?>
        <a class="problem-item" role="listitem" href="<?= e(url('problem.php?pid=' . urlencode((string) $problem['pid']))) ?>">
          <span class="problem-item__pid"><?= e((string) $problem['pid']) ?></span>
          <span class="problem-item__body">
            <span class="problem-item__title"><?= e((string) $problem['title']) ?></span>
            <span class="problem-item__meta"><?= e((string) $problem['chapter_name']) ?></span>
          </span>
          <span class="problem-item__limits">
            <?php if ($problem['time_limit']): ?><?= e((string) $problem['time_limit']) ?><?php endif; ?>
            <?php if ($problem['memory_limit']): ?><br><?= e((string) $problem['memory_limit']) ?><?php endif; ?>
          </span>
          <span class="problem-item__status">
            <?php if ($problem['answer_code'] !== '' && $problem['answer_code'] !== null): ?>
              <span class="badge badge--accent">有答案</span>
            <?php else: ?>
              <span class="badge badge--neutral">待生成</span>
            <?php endif; ?>
          </span>
        </a>
      <?php endforeach; ?>
    </div>

    <?php
    $query = $_GET;
    unset($query['page']);
    $pattern = url('index.php?' . http_build_query($query) . ($query ? '&' : '') . 'page={page}');
    echo pagination_links($pager['pages'], $pager['page'], $pattern);
    ?>
  <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var form = document.querySelector('[data-hero-search]');
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
    'pageTitle' => $pageTitle === '全部题目' ? '首页' : $pageTitle,
    'content' => $content,
    'activeNav' => $chapterId || $subpartId ? 'problems' : 'home',
    'activeChapterId' => $activeChapterId,
]);
