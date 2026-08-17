<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin_layout.php';

Auth::requireLogin();

try {
    $stats = db()->query(
        'SELECT
            (SELECT COUNT(*) FROM problems WHERE deleted_at IS NULL) AS problem_count,
            (SELECT COUNT(*) FROM problems WHERE deleted_at IS NOT NULL) AS trash_count,
            (SELECT COUNT(*) FROM chapters) AS chapter_count,
            (SELECT COUNT(*) FROM subparts) AS subpart_count,
            (SELECT COUNT(*) FROM parts) AS part_count,
            (SELECT COUNT(*) FROM problems WHERE deleted_at IS NULL AND answer_code <> "") AS answered_count,
            (SELECT COUNT(*) FROM problems WHERE deleted_at IS NULL AND is_answer_manual = 1) AS manual_count'
    )->fetch();

    $recent = db()->query(
        'SELECT pid, title, is_answer_manual, updated_at
         FROM problems WHERE deleted_at IS NULL
         ORDER BY updated_at DESC LIMIT 8'
    )->fetchAll();
} catch (Throwable $ex) {
    app_log('仪表盘查询失败: ' . $ex->getMessage(), 'error');
    $stats = ['problem_count' => 0, 'trash_count' => 0, 'chapter_count' => 0, 'subpart_count' => 0, 'part_count' => 0, 'answered_count' => 0, 'manual_count' => 0];
    $recent = [];
}

$apiKeyConfigured = false;
try {
    $apiKeyConfigured = setting_get('deepseek_api_key') !== '';
} catch (Throwable $ex) {
    $apiKeyConfigured = false;
}

ob_start();
?>
<div class="admin-page-header">
  <h1>仪表盘</h1>
  <span class="text-muted" style="font-size: 13.5px;">欢迎，<?= e((string) ($_SESSION['admin_username'] ?? 'admin')) ?></span>
</div>

<div class="dash-stats">
  <div class="dash-stat">
    <div class="dash-stat__label">收录题目</div>
    <div class="dash-stat__value"><?= (int) $stats['problem_count'] ?></div>
    <div class="dash-stat__sub">回收站 <?= (int) $stats['trash_count'] ?> 题</div>
  </div>
  <div class="dash-stat">
    <div class="dash-stat__label">已有答案</div>
    <div class="dash-stat__value"><?= (int) $stats['answered_count'] ?></div>
    <div class="dash-stat__sub">其中人工校对 <?= (int) $stats['manual_count'] ?> 题</div>
  </div>
  <div class="dash-stat">
    <div class="dash-stat__label">章节数</div>
    <div class="dash-stat__value"><?= (int) $stats['chapter_count'] ?></div>
    <div class="dash-stat__sub"><?= (int) $stats['part_count'] ?> 大部分 · <?= (int) $stats['subpart_count'] ?> 小部分</div>
  </div>
  <div class="dash-stat">
    <div class="dash-stat__label">AI API</div>
    <div class="dash-stat__value" style="font-size: 20px; padding-top: 6px;">
      <?php if ($apiKeyConfigured): ?>
        <span class="badge badge--accent">已配置</span>
      <?php else: ?>
        <span class="badge badge--warning">未配置</span>
      <?php endif; ?>
    </div>
    <div class="dash-stat__sub"><a href="<?= e(url('admin/settings.php')) ?>">前往设置</a></div>
  </div>
</div>

<div class="card">
  <div class="flex-between" style="margin-bottom: 12px;">
    <h2 class="section-title mb-0">最近更新</h2>
    <div class="flex gap-8">
      <a class="btn btn--sm" href="<?= e(url('admin/fetch.php')) ?>">一键抓取</a>
      <a class="btn btn--sm btn--primary" href="<?= e(url('admin/problem_edit.php')) ?>">添加题目</a>
    </div>
  </div>

  <?php if (empty($recent)): ?>
    <div class="empty-state" style="padding: 32px 16px;">
      <p class="empty-state__title">还没有题目</p>
      <p class="empty-state__desc">使用「一键抓取」从原网站快速导入题目并自动生成答案。</p>
      <a class="btn btn--accent" href="<?= e(url('admin/fetch.php')) ?>">开始抓取</a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th style="width: 80px;">题号</th>
            <th>标题</th>
            <th style="width: 110px;">答案状态</th>
            <th style="width: 150px;">更新时间</th>
            <th style="width: 90px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $row): ?>
          <tr>
            <td><span class="admin-table__pid"><?= e((string) $row['pid']) ?></span></td>
            <td><?= e((string) $row['title']) ?></td>
            <td>
              <?php if ((int) $row['is_answer_manual'] === 1): ?>
                <span class="badge badge--accent">人工校对</span>
              <?php else: ?>
                <span class="badge badge--neutral">AI 生成</span>
              <?php endif; ?>
            </td>
            <td class="text-muted"><?= e(format_datetime((string) $row['updated_at'])) ?></td>
            <td>
              <a class="btn btn--sm btn--ghost" href="<?= e(url('problem.php?pid=' . urlencode((string) $row['pid']))) ?>" target="_blank">查看</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();

render_admin_layout([
    'pageTitle' => '仪表盘',
    'content' => $content,
    'active' => 'dashboard',
]);
