<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin_layout.php';
require_once dirname(__DIR__) . '/includes/tree_cache.php';
require_once dirname(__DIR__) . '/includes/api_client.php';

Auth::requireLogin();

$id = int_input($_GET['id'] ?? ($_POST['id'] ?? 0));
$isEdit = $id > 0;
$errors = [];

$problem = [
    'id' => 0,
    'pid' => '',
    'title' => '',
    'chapter_id' => 0,
    'time_limit' => '',
    'memory_limit' => '',
    'description' => '',
    'input_desc' => '',
    'output_desc' => '',
    'input_sample' => '',
    'output_sample' => '',
    'source_url' => '',
    'answer_code' => '',
    'is_answer_manual' => 0,
];

if ($isEdit) {
    try {
        $stmt = db()->prepare('SELECT * FROM problems WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $found = $stmt->fetch();
        if (!$found) {
            flash('error', '题目不存在。');
            redirect(url('admin/problems.php'));
        }
        $problem = $found;
    } catch (Throwable $ex) {
        app_log('加载题目失败: ' . $ex->getMessage(), 'error');
        flash('error', '加载题目失败。');
        redirect(url('admin/problems.php'));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $problem['pid'] = trim((string) ($_POST['pid'] ?? ''));
    $problem['title'] = str_input((string) ($_POST['title'] ?? ''));
    $problem['chapter_id'] = int_input($_POST['chapter_id'] ?? 0);
    $problem['time_limit'] = str_input((string) ($_POST['time_limit'] ?? ''));
    $problem['memory_limit'] = str_input((string) ($_POST['memory_limit'] ?? ''));
    $problem['description'] = str_input((string) ($_POST['description'] ?? ''));
    $problem['input_desc'] = str_input((string) ($_POST['input_desc'] ?? ''));
    $problem['output_desc'] = str_input((string) ($_POST['output_desc'] ?? ''));
    $problem['input_sample'] = str_input((string) ($_POST['input_sample'] ?? ''));
    $problem['output_sample'] = str_input((string) ($_POST['output_sample'] ?? ''));
    $problem['source_url'] = str_input((string) ($_POST['source_url'] ?? ''));
    $postedAnswer = str_input((string) ($_POST['answer_code'] ?? ''));
    $generate = !empty($_POST['generate_answer']);

    if (!preg_match('/^\d{3,5}$/', $problem['pid'])) {
        $errors[] = '题号必须为 3-5 位数字。';
    }
    if ($problem['title'] === '') {
        $errors[] = '标题不能为空。';
    }
    if ($problem['chapter_id'] <= 0) {
        $errors[] = '请选择所属章节。';
    }

    try {
        $dupStmt = db()->prepare('SELECT id FROM problems WHERE pid = :pid AND id <> :id LIMIT 1');
        $dupStmt->execute(['pid' => $problem['pid'], 'id' => $id]);
        if ($dupStmt->fetch()) {
            $errors[] = '题号 ' . $problem['pid'] . ' 已存在。';
        }
    } catch (Throwable $ex) {
        $errors[] = '校验题号失败。';
    }

    if (empty($errors)) {
        try {
            $pdo = db();
            $originalAnswer = (string) $problem['answer_code'];

            if ($isEdit) {
                $answerChanged = $postedAnswer !== $originalAnswer;
                $newManual = $answerChanged ? 1 : (int) $problem['is_answer_manual'];
                $stmt = $pdo->prepare(
                    'UPDATE problems SET pid = :pid, title = :title, chapter_id = :chapter_id,
                     time_limit = :time_limit, memory_limit = :memory_limit,
                     description = :description, input_desc = :input_desc, output_desc = :output_desc,
                     input_sample = :input_sample, output_sample = :output_sample,
                     source_url = :source_url, answer_code = :answer_code, is_answer_manual = :is_manual,
                     updated_at = NOW()
                     WHERE id = :id'
                );
                $stmt->execute([
                    'pid' => $problem['pid'],
                    'title' => $problem['title'],
                    'chapter_id' => $problem['chapter_id'],
                    'time_limit' => $problem['time_limit'],
                    'memory_limit' => $problem['memory_limit'],
                    'description' => $problem['description'],
                    'input_desc' => $problem['input_desc'],
                    'output_desc' => $problem['output_desc'],
                    'input_sample' => $problem['input_sample'],
                    'output_sample' => $problem['output_sample'],
                    'source_url' => $problem['source_url'],
                    'answer_code' => $postedAnswer,
                    'is_manual' => $newManual,
                    'id' => $id,
                ]);
                $problem['answer_code'] = $postedAnswer;
                $problem['is_answer_manual'] = $newManual;
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO problems (pid, title, chapter_id, time_limit, memory_limit,
                     description, input_desc, output_desc, input_sample, output_sample,
                     source_url, answer_code, is_answer_manual, created_at, updated_at)
                     VALUES (:pid, :title, :chapter_id, :time_limit, :memory_limit,
                     :description, :input_desc, :output_desc, :input_sample, :output_sample,
                     :source_url, :answer_code, 0, NOW(), NOW())'
                );
                $stmt->execute([
                    'pid' => $problem['pid'],
                    'title' => $problem['title'],
                    'chapter_id' => $problem['chapter_id'],
                    'time_limit' => $problem['time_limit'],
                    'memory_limit' => $problem['memory_limit'],
                    'description' => $problem['description'],
                    'input_desc' => $problem['input_desc'],
                    'output_desc' => $problem['output_desc'],
                    'input_sample' => $problem['input_sample'],
                    'output_sample' => $problem['output_sample'],
                    'source_url' => $problem['source_url'],
                    'answer_code' => $postedAnswer,
                ]);
                $id = (int) $pdo->lastInsertId();
                $problem['id'] = $id;
                $problem['answer_code'] = $postedAnswer;
                $isEdit = true;
            }

            TreeCache::clear();

            if ($generate) {
                $client = new AiClient();
                $result = $client->generateAnswer($problem);
                if ($result['ok']) {
                    $pdo->prepare('UPDATE problems SET answer_code = :code, is_answer_manual = 0, updated_at = NOW() WHERE id = :id')
                        ->execute(['code' => $result['code'], 'id' => $id]);
                    flash('success', '题目已保存，AI 答案已生成。');
                } else {
                    flash('warning', '题目已保存，但 AI 答案生成失败：' . $result['message']);
                }
            } else {
                flash('success', '题目已保存。');
            }
            redirect(url('admin/problem_edit.php?id=' . $id));
        } catch (Throwable $ex) {
            app_log('保存题目失败: ' . $ex->getMessage(), 'error');
            $errors[] = '保存失败，请查看日志。';
        }
    }
}

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
  <h1><?= $isEdit ? '编辑题目 ' . e((string) $problem['pid']) : '添加题目' ?></h1>
  <a class="btn" href="<?= e(url('admin/problems.php')) ?>">返回列表</a>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert--error" role="alert">
    <?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<form method="post" action="<?= e(url('admin/problem_edit.php' . ($isEdit ? '?id=' . $id : ''))) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">

  <div class="card">
    <h2 style="font-size: 16px;">基本信息</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0 16px;">
      <div class="form-group">
        <label class="form-label" for="pid">题号 <span style="color: var(--color-error);">*</span></label>
        <input class="form-input" type="text" id="pid" name="pid" value="<?= e((string) $problem['pid']) ?>" required pattern="\d{3,5}" placeholder="如 1000">
      </div>
      <div class="form-group">
        <label class="form-label" for="title">标题 <span style="color: var(--color-error);">*</span></label>
        <input class="form-input" type="text" id="title" name="title" value="<?= e((string) $problem['title']) ?>" required maxlength="200">
      </div>
      <div class="form-group">
        <label class="form-label" for="chapter_id">所属章节 <span style="color: var(--color-error);">*</span></label>
        <select class="form-select" id="chapter_id" name="chapter_id" required>
          <option value="">请选择</option>
          <?php foreach ($chapters as $chapter): ?>
            <option value="<?= (int) $chapter['id'] ?>" <?= (int) $problem['chapter_id'] === (int) $chapter['id'] ? 'selected' : '' ?>>
              <?= e($chapter['part_name'] . ' / ' . $chapter['subpart_name'] . ' / ' . $chapter['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="time_limit">时间限制</label>
        <input class="form-input" type="text" id="time_limit" name="time_limit" value="<?= e((string) $problem['time_limit']) ?>" placeholder="如 1000 ms">
      </div>
      <div class="form-group">
        <label class="form-label" for="memory_limit">内存限制</label>
        <input class="form-input" type="text" id="memory_limit" name="memory_limit" value="<?= e((string) $problem['memory_limit']) ?>" placeholder="如 32768 KB">
      </div>
      <div class="form-group">
        <label class="form-label" for="source_url">原网站链接</label>
        <input class="form-input" type="url" id="source_url" name="source_url" value="<?= e((string) $problem['source_url']) ?>" placeholder="https://ybt.ssoier.cn/problem_show.php?pid=1000">
      </div>
    </div>
  </div>

  <div class="card">
    <h2 style="font-size: 16px;">题目内容 <span class="form-label__hint">支持 HTML 与 $...$ 数学公式</span></h2>
    <div class="form-group">
      <label class="form-label" for="description">题目描述</label>
      <textarea class="form-textarea" id="description" name="description" rows="8"><?= e((string) $problem['description']) ?></textarea>
    </div>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px;">
      <div class="form-group">
        <label class="form-label" for="input_desc">输入说明</label>
        <textarea class="form-textarea" id="input_desc" name="input_desc" rows="5"><?= e((string) $problem['input_desc']) ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label" for="output_desc">输出说明</label>
        <textarea class="form-textarea" id="output_desc" name="output_desc" rows="5"><?= e((string) $problem['output_desc']) ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label" for="input_sample">输入样例</label>
        <textarea class="form-textarea" id="input_sample" name="input_sample" rows="4"><?= e((string) $problem['input_sample']) ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label" for="output_sample">输出样例</label>
        <textarea class="form-textarea" id="output_sample" name="output_sample" rows="4"><?= e((string) $problem['output_sample']) ?></textarea>
      </div>
    </div>
  </div>

  <div class="card">
    <h2 style="font-size: 16px;">参考答案</h2>
    <div class="form-group">
      <label class="form-label" for="answer_code">C++ 代码 <span class="form-label__hint">手动修改后将标记为「人工校对」</span></label>
      <textarea class="form-textarea" id="answer_code" name="answer_code" rows="14" spellcheck="false"><?= e((string) $problem['answer_code']) ?></textarea>
    </div>
    <label class="form-checkbox">
      <input type="checkbox" name="generate_answer" value="1">
      保存后调用 AI 自动生成答案（将覆盖上方代码框内容）
    </label>
  </div>

  <div class="flex gap-8" style="margin-top: 8px;">
    <button type="submit" class="btn btn--primary"><?= $isEdit ? '保存修改' : '添加题目' ?></button>
    <?php if ($isEdit): ?>
      <button type="button" class="btn" data-regenerate="<?= (int) $id ?>" data-manual="<?= (int) $problem['is_answer_manual'] ?>">重新生成 AI 答案</button>
    <?php endif; ?>
    <a class="btn btn--ghost" href="<?= e(url('admin/problems.php')) ?>">取消</a>
  </div>
</form>
<?php
$content = ob_get_clean();

render_admin_layout([
    'pageTitle' => $isEdit ? '编辑题目' : '添加题目',
    'content' => $content,
    'active' => 'problems',
]);
