<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin_layout.php';

Auth::requireLogin();

try {
    $chapters = db()->query(
        'SELECT ch.id, ch.name, sp.name AS subpart_name, pt.name AS part_name
         FROM chapters ch
         JOIN subparts sp ON sp.id = ch.subpart_id
         JOIN parts pt ON pt.id = sp.part_id
         ORDER BY pt.sort_order, sp.sort_order, ch.sort_order, ch.id'
    )->fetchAll();
} catch (Throwable $ex) {
    $chapters = [];
}

ob_start();
?>
<div class="admin-page-header">
  <h1>一键抓取</h1>
</div>

<?php if (empty($chapters)): ?>
  <div class="alert alert--warning" role="alert">
    尚未创建任何章节，请先在「章节管理」中添加章节，再抓取题目。
  </div>
<?php endif; ?>

<div class="card">
  <h2 style="font-size: 16px;">单个抓取</h2>
  <p class="text-muted" style="font-size: 13.5px;">输入原网站题目链接（如 https://ybt.ssoier.cn/problem_show.php?pid=1000）或直接输入题号。</p>
  <form id="fetchSingleForm">
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 0 16px;">
      <div class="form-group">
        <label class="form-label" for="fetchTarget">题目链接或题号</label>
        <input class="form-input" type="text" id="fetchTarget" name="target" required placeholder="https://ybt.ssoier.cn/problem_show.php?pid=1000">
      </div>
      <div class="form-group">
        <label class="form-label" for="fetchChapter">归属章节</label>
        <select class="form-select" id="fetchChapter" name="chapter_id" required>
          <option value="">请选择</option>
          <?php foreach ($chapters as $chapter): ?>
            <option value="<?= (int) $chapter['id'] ?>"><?= e($chapter['part_name'] . ' / ' . $chapter['subpart_name'] . ' / ' . $chapter['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="flex-between">
      <label class="form-checkbox">
        <input type="checkbox" name="generate" value="1">
        抓取后自动调用 AI 生成答案
      </label>
      <button type="submit" class="btn btn--accent">开始抓取</button>
    </div>
  </form>
</div>

<div class="card">
  <h2 style="font-size: 16px;">批量抓取</h2>
  <p class="text-muted" style="font-size: 13.5px;">支持题号范围（如 1000-1050）或题号列表（逗号分隔）。每题依次抓取，可随时停止。</p>
  <form id="fetchBatchForm">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0 16px;">
      <div class="form-group">
        <label class="form-label" for="batchMode">输入方式</label>
        <select class="form-select" id="batchMode" name="mode">
          <option value="range">题号范围</option>
          <option value="list">题号列表</option>
        </select>
      </div>
      <div class="form-group" id="batchRangeRow">
        <label class="form-label" for="pidFrom">题号范围</label>
        <div class="flex gap-8" style="align-items: center;">
          <input class="form-input" type="number" id="pidFrom" name="pid_from" min="1000" max="9999" placeholder="1000" style="width: 110px;">
          <span class="text-muted">至</span>
          <input class="form-input" type="number" name="pid_to" min="1000" max="9999" placeholder="1050" style="width: 110px;">
        </div>
      </div>
      <div class="form-group" id="batchListRow" hidden>
        <label class="form-label" for="pidList">题号列表</label>
        <input class="form-input" type="text" id="pidList" name="pid_list" placeholder="1000, 1001, 1005">
      </div>
      <div class="form-group">
        <label class="form-label" for="batchChapter">归属章节</label>
        <select class="form-select" id="batchChapter" name="chapter_id" required>
          <option value="">请选择</option>
          <?php foreach ($chapters as $chapter): ?>
            <option value="<?= (int) $chapter['id'] ?>"><?= e($chapter['part_name'] . ' / ' . $chapter['subpart_name'] . ' / ' . $chapter['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="flex-between">
      <label class="form-checkbox">
        <input type="checkbox" name="generate" value="1">
        每题抓取后自动生成答案
      </label>
      <button type="submit" class="btn btn--accent">开始批量抓取</button>
    </div>

    <div class="fetch-progress">
      <div class="progress" role="progressbar" aria-label="抓取进度">
        <div class="progress__bar" id="fetchProgressBar"></div>
      </div>
      <div class="fetch-progress__text">
        <span>进度</span>
        <span id="fetchProgressText">0 / 0</span>
      </div>
    </div>
  </form>
</div>

<div class="card">
  <h2 style="font-size: 16px;">抓取日志</h2>
  <div class="fetch-log" id="fetchLog" aria-live="polite">
    <div class="fetch-log__line fetch-log__line--info">等待任务开始…</div>
  </div>
</div>
<?php
$content = ob_get_clean();

render_admin_layout([
    'pageTitle' => '一键抓取',
    'content' => $content,
    'active' => 'fetch',
]);
