<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';

guard_installed();

$pid = trim((string) ($_GET['pid'] ?? ''));

if (!preg_match('/^\d{3,5}$/', $pid)) {
    http_response_code(404);
    render_layout([
        'pageTitle' => '题目不存在',
        'content' => '<div class="empty-state"><div class="empty-state__icon" aria-hidden="true">🔍</div>'
            . '<p class="empty-state__title">题目不存在</p>'
            . '<p class="empty-state__desc">题号无效或该题目尚未收录。</p>'
            . '<a class="btn" href="' . e(url('index.php')) . '">返回首页</a></div>',
        'sidebar' => true,
    ]);
    exit;
}

try {
    $stmt = db()->prepare(
        'SELECT p.*, c.name AS chapter_name, c.id AS chapter_id,
                sp.id AS subpart_id, sp.name AS subpart_name, pt.name AS part_name
         FROM problems p
         JOIN chapters c ON c.id = p.chapter_id
         JOIN subparts sp ON sp.id = c.subpart_id
         JOIN parts pt ON pt.id = sp.part_id
         WHERE p.pid = :pid AND p.deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute(['pid' => $pid]);
    $problem = $stmt->fetch();
} catch (Throwable $ex) {
    app_log('题目查询失败: ' . $ex->getMessage(), 'error');
    $problem = false;
}

if (!$problem) {
    http_response_code(404);
    render_layout([
        'pageTitle' => '题目不存在',
        'content' => '<div class="empty-state"><div class="empty-state__icon" aria-hidden="true">🔍</div>'
            . '<p class="empty-state__title">题目 ' . e($pid) . ' 不存在</p>'
            . '<p class="empty-state__desc">该题目尚未收录，管理员可在后台通过一键抓取添加。</p>'
            . '<a class="btn" href="' . e(url('index.php')) . '">返回首页</a></div>',
        'sidebar' => true,
    ]);
    exit;
}

function render_problem_content(string $content): string
{
    $content = trim($content);
    if ($content === '') {
        return '<p class="text-muted">（无）</p>';
    }
    return '<div class="problem-content">' . sanitize_html($content) . '</div>';
}

function normalize_sample(string $text): string
{
    $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
    $lines = array_map(static fn(string $line): string => rtrim($line), $lines);
    while (count($lines) > 0 && trim($lines[0]) === '') {
        array_shift($lines);
    }
    while (count($lines) > 0 && trim($lines[count($lines) - 1]) === '') {
        array_pop($lines);
    }
    $indents = [];
    foreach ($lines as $line) {
        if (trim($line) === '') {
            continue;
        }
        $indents[] = strspn($line, " \t");
    }
    if ($indents !== []) {
        $strip = min($indents);
        if ($strip > 0) {
            $lines = array_map(
                static fn(string $line): string => $line === '' ? $line : substr($line, $strip),
                $lines
            );
        }
    }
    return implode("\n", $lines);
}

$sourceUrl = $problem['source_url'] ?: SOURCE_SITE . '/problem_show.php?pid=' . urlencode($pid);
$hasAnswer = trim((string) $problem['answer_code']) !== '';
$isManual = (int) $problem['is_answer_manual'] === 1;

ob_start();
?>
<nav class="breadcrumb" aria-label="面包屑">
  <a href="<?= e(url('index.php')) ?>">首页</a>
  <span class="breadcrumb__sep">/</span>
  <span><?= e((string) $problem['part_name']) ?></span>
  <span class="breadcrumb__sep">/</span>
  <a href="<?= e(url('index.php?subpart=' . (int) $problem['subpart_id'])) ?>"><?= e((string) $problem['subpart_name']) ?></a>
  <span class="breadcrumb__sep">/</span>
  <a href="<?= e(url('index.php?chapter=' . (int) $problem['chapter_id'])) ?>"><?= e((string) $problem['chapter_name']) ?></a>
</nav>

<div class="problem-detail">
  <div class="problem-detail__main">

    <header class="card problem-hero">
      <div class="problem-hero__row">
        <span class="problem-pid-chip"><?= e($pid) ?></span>
        <?php if ($isManual): ?>
          <span class="badge badge--accent">人工校对</span>
        <?php endif; ?>
        <a class="problem-hero__source" href="<?= e($sourceUrl) ?>" target="_blank" rel="noopener">
          原题链接
          <svg width="12" height="12" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M6 3h7v7M13 3L7 9M11 8v4a1 1 0 01-1 1H4a1 1 0 01-1-1V5a1 1 0 011-1h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
      </div>
      <h1 class="problem-heading"><?= e((string) $problem['title']) ?></h1>
      <div class="problem-hero__meta">
        <?php if ($problem['time_limit']): ?>
          <span class="limit-chip">时间限制<b><?= e((string) $problem['time_limit']) ?></b></span>
        <?php endif; ?>
        <?php if ($problem['memory_limit']): ?>
          <span class="limit-chip">内存限制<b><?= e((string) $problem['memory_limit']) ?></b></span>
        <?php endif; ?>
        <span class="limit-chip limit-chip--chapter"><?= e((string) $problem['chapter_name']) ?></span>
      </div>
    </header>

    <section class="card problem-card" aria-labelledby="sec-desc">
      <h2 class="problem-section__title" id="sec-desc">题目描述</h2>
      <?= render_problem_content((string) $problem['description']) ?>
    </section>

    <?php if (trim((string) $problem['input_desc']) !== ''): ?>
    <section class="card problem-card" aria-labelledby="sec-input">
      <h2 class="problem-section__title" id="sec-input">输入说明</h2>
      <?= render_problem_content((string) $problem['input_desc']) ?>
    </section>
    <?php endif; ?>

    <?php if (trim((string) $problem['output_desc']) !== ''): ?>
    <section class="card problem-card" aria-labelledby="sec-output">
      <h2 class="problem-section__title" id="sec-output">输出说明</h2>
      <?= render_problem_content((string) $problem['output_desc']) ?>
    </section>
    <?php endif; ?>

    <?php if (trim((string) $problem['input_sample']) !== ''): ?>
    <section class="card problem-card" aria-labelledby="sec-input-sample">
      <h2 class="problem-section__title" id="sec-input-sample">输入样例</h2>
      <pre class="code-block"><button type="button" class="copy-btn" aria-label="复制输入样例">复制</button><code><?= e(normalize_sample((string) $problem['input_sample'])) ?></code></pre>
    </section>
    <?php endif; ?>

    <?php if (trim((string) $problem['output_sample']) !== ''): ?>
    <section class="card problem-card" aria-labelledby="sec-output-sample">
      <h2 class="problem-section__title" id="sec-output-sample">输出样例</h2>
      <pre class="code-block"><button type="button" class="copy-btn" aria-label="复制输出样例">复制</button><code><?= e(normalize_sample((string) $problem['output_sample'])) ?></code></pre>
    </section>
    <?php endif; ?>

    <section class="answer-panel" aria-labelledby="sec-answer">
      <div class="answer-panel__header">
        <h2 class="answer-panel__title" id="sec-answer">
          参考代码
          <?php if ($isManual): ?>
            <span class="badge badge--accent">人工校对</span>
          <?php else: ?>
            <span class="badge badge--neutral">AI 生成</span>
          <?php endif; ?>
        </h2>
        <?php if ($hasAnswer): ?>
          <button type="button" class="btn btn--sm" data-copy-target="#answerCode">复制代码</button>
        <?php endif; ?>
      </div>
      <?php if ($hasAnswer): ?>
        <div class="answer-panel__disclaimer" role="note">
          由 AI 生成，仅供参考，可能存在错误。请以原题评测结果为准。
        </div>
        <div class="answer-panel__body">
          <div class="answer-collapsed" id="answerBody">
            <pre class="code-block"><button type="button" class="copy-btn" data-copy-target="#answerCode" aria-label="复制代码">复制</button><code id="answerCode" class="language-cpp" data-lazy><?= e((string) $problem['answer_code']) ?></code></pre>
          </div>
        </div>
        <div class="answer-expand-row">
          <button type="button" class="btn btn--ghost" id="answerExpandBtn" aria-expanded="false">展开完整代码</button>
        </div>
      <?php else: ?>
        <div class="answer-panel__body">
          <p class="answer-panel__empty">该题目暂无参考代码，管理员可在后台生成或手动添加。</p>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <aside class="problem-detail__aside">
    <div class="meta-card">
      <h2 class="meta-card__title">题目信息</h2>
      <ul class="meta-list">
        <li><span class="meta-list__label">题号</span><span class="meta-list__value"><?= e($pid) ?></span></li>
        <li><span class="meta-list__label">所属章节</span><span class="meta-list__value meta-list__value--plain"><?= e((string) $problem['chapter_name']) ?></span></li>
        <li><span class="meta-list__label">答案状态</span><span class="meta-list__value meta-list__value--plain"><?= $hasAnswer ? ($isManual ? '人工校对' : 'AI 生成') : '暂无' ?></span></li>
        <li><span class="meta-list__label">更新时间</span><span class="meta-list__value"><?= e(format_datetime((string) $problem['updated_at'])) ?></span></li>
      </ul>
    </div>
    <div class="meta-card">
      <h2 class="meta-card__title">溯源</h2>
      <p class="meta-card__desc">题目内容来自原网站，可前往原题页面核对。</p>
      <a class="btn meta-card__action" href="<?= e($sourceUrl) ?>" target="_blank" rel="noopener">
        在原网站查看
        <svg width="13" height="13" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M6 3h7v7M13 3L7 9M11 8v4a1 1 0 01-1 1H4a1 1 0 01-1-1V5a1 1 0 011-1h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </a>
    </div>
  </aside>
</div>
<?php
$content = ob_get_clean();

render_layout([
    'pageTitle' => $pid . '：' . $problem['title'],
    'description' => mb_substr(strip_tags((string) $problem['description']), 0, 120),
    'content' => $content,
    'activeNav' => 'problems',
    'activeChapterId' => (int) $problem['chapter_id'],
    'activePid' => $pid,
]);
