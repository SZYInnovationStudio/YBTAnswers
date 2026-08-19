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
        if ($action === 'part_create') {
            $name = str_input((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                flash('error', '名称不能为空。');
            } else {
                $stmt = db()->prepare('INSERT INTO parts (name, sort_order) VALUES (:name, (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM (SELECT sort_order FROM parts) t))');
                $stmt->execute(['name' => $name]);
                TreeCache::clear();
                flash('success', '大部分「' . $name . '」已添加。');
            }
        } elseif ($action === 'subpart_create') {
            $name = str_input((string) ($_POST['name'] ?? ''));
            $partId = int_input($_POST['part_id'] ?? 0);
            if ($name === '' || $partId <= 0) {
                flash('error', '请填写完整信息。');
            } else {
                $stmt = db()->prepare('INSERT INTO subparts (part_id, name, sort_order) VALUES (:pid, :name, (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM (SELECT sort_order FROM subparts WHERE part_id = :pid2) t))');
                $stmt->execute(['pid' => $partId, 'name' => $name, 'pid2' => $partId]);
                TreeCache::clear();
                flash('success', '小部分「' . $name . '」已添加。');
            }
        } elseif ($action === 'chapter_delete') {
            $id = int_input($_POST['id'] ?? 0);
            $countStmt = db()->prepare('SELECT COUNT(*) FROM problems WHERE chapter_id = :id');
            $countStmt->execute(['id' => $id]);
            if ((int) $countStmt->fetchColumn() > 0) {
                flash('error', '该章节下仍有题目（含回收站），无法删除。请先移动或彻底删除题目。');
            } else {
                db()->prepare('DELETE FROM chapters WHERE id = :id')->execute(['id' => $id]);
                TreeCache::clear();
                flash('success', '章节已删除。');
            }
        } elseif ($action === 'subpart_delete') {
            $id = int_input($_POST['id'] ?? 0);
            $countStmt = db()->prepare('SELECT COUNT(*) FROM chapters WHERE subpart_id = :id');
            $countStmt->execute(['id' => $id]);
            if ((int) $countStmt->fetchColumn() > 0) {
                flash('error', '该小部分下仍有章节，无法删除。');
            } else {
                db()->prepare('DELETE FROM subparts WHERE id = :id')->execute(['id' => $id]);
                TreeCache::clear();
                flash('success', '小部分已删除。');
            }
        }
    } catch (Throwable $ex) {
        app_log('章节管理操作失败: ' . $ex->getMessage(), 'error');
        flash('error', '操作失败，请查看日志。');
    }
    redirect(url('admin/chapters.php'));
}

try {
    $parts = db()->query('SELECT * FROM parts ORDER BY sort_order, id')->fetchAll();
    $subparts = db()->query(
        'SELECT sp.*, (SELECT COUNT(*) FROM chapters c WHERE c.subpart_id = sp.id) AS chapter_count
         FROM subparts sp ORDER BY sp.sort_order, sp.id'
    )->fetchAll();
    $chapters = db()->query(
        'SELECT ch.*, (SELECT COUNT(*) FROM problems p WHERE p.chapter_id = ch.id AND p.deleted_at IS NULL) AS problem_count
         FROM chapters ch ORDER BY ch.sort_order, ch.id'
    )->fetchAll();
} catch (Throwable $ex) {
    app_log('章节列表查询失败: ' . $ex->getMessage(), 'error');
    $parts = $subparts = $chapters = [];
}

$subsByPart = [];
foreach ($subparts as $sub) {
    $subsByPart[(int) $sub['part_id']][] = $sub;
}
$chaptersBySub = [];
foreach ($chapters as $chapter) {
    $chaptersBySub[(int) $chapter['subpart_id']][] = $chapter;
}

ob_start();
?>
<div class="admin-page-header">
  <h1>章节管理</h1>
  <div class="flex gap-8">
    <button type="button" class="btn" data-modal-open="partModal">+ 大部分</button>
    <button type="button" class="btn" data-modal-open="subpartModal">+ 小部分</button>
    <button type="button" class="btn btn--primary" data-modal-open="chapterModal">+ 添加章节</button>
  </div>
</div>

<?php if (empty($parts)): ?>
  <div class="card">
    <div class="empty-state">
      <p class="empty-state__title">还没有任何分类</p>
      <p class="empty-state__desc">请先添加大部分、小部分，再添加章节。</p>
    </div>
  </div>
<?php else: ?>
  <?php foreach ($parts as $part): ?>
    <div class="card">
      <div class="flex-between" style="margin-bottom: 10px;">
        <h2 class="section-title mb-0"><?= e((string) $part['name']) ?></h2>
      </div>

      <?php if (empty($subsByPart[(int) $part['id']])): ?>
        <p class="text-muted mb-0" style="font-size: 13.5px;">暂无小部分</p>
      <?php endif; ?>

      <?php foreach ($subsByPart[(int) $part['id']] ?? [] as $sub): ?>
        <div style="margin-bottom: 14px;">
          <div class="flex-between" style="margin-bottom: 6px;">
            <h3 style="margin: 0; font-size: 15px; color: var(--color-text-secondary);"><?= e((string) $sub['name']) ?>
              <span class="text-muted" style="font-size: 12px; font-weight: 400;">（<?= (int) $sub['chapter_count'] ?> 章节）</span>
            </h3>
            <form method="post" data-confirm="确定删除小部分「<?= e((string) $sub['name']) ?>」吗？">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="subpart_delete">
              <input type="hidden" name="id" value="<?= (int) $sub['id'] ?>">
              <button type="submit" class="btn btn--sm btn--danger">删除小部分</button>
            </form>
          </div>

          <?php if (empty($chaptersBySub[(int) $sub['id']])): ?>
            <p class="text-muted" style="font-size: 13px; margin: 4px 0 0;">暂无章节</p>
          <?php else: ?>
            <div class="table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th style="width: 70px;">排序</th>
                    <th>章节名称</th>
                    <th style="width: 90px;">题目数</th>
                    <th style="width: 220px; text-align: right;">操作</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $subChapters = $chaptersBySub[(int) $sub['id']]; ?>
                  <?php foreach ($subChapters as $idx => $chapter): ?>
                  <tr>
                    <td>
                      <button type="button" class="row-move-btn" data-move-chapter="<?= (int) $chapter['id'] ?>" data-dir="up" aria-label="上移" <?= $idx === 0 ? 'disabled' : '' ?>>↑</button>
                      <button type="button" class="row-move-btn" data-move-chapter="<?= (int) $chapter['id'] ?>" data-dir="down" aria-label="下移" <?= $idx === count($subChapters) - 1 ? 'disabled' : '' ?>>↓</button>
                    </td>
                    <td><?= e((string) $chapter['name']) ?></td>
                    <td class="text-muted"><?= (int) $chapter['problem_count'] ?></td>
                    <td>
                      <div class="admin-table__actions">
                        <button type="button" class="btn btn--sm" data-edit-chapter='<?= e(json_encode([
                            'id' => (int) $chapter['id'],
                            'name' => (string) $chapter['name'],
                            'subpart_id' => (int) $chapter['subpart_id'],
                        ], JSON_UNESCAPED_UNICODE)) ?>'>编辑</button>
                        <a class="btn btn--sm btn--ghost" href="<?= e(url('index.php?chapter=' . (int) $chapter['id'])) ?>" target="_blank">前台预览</a>
                        <form method="post" data-confirm="确定删除章节「<?= e((string) $chapter['name']) ?>」吗？" style="display: inline;">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="chapter_delete">
                          <input type="hidden" name="id" value="<?= (int) $chapter['id'] ?>">
                          <button type="submit" class="btn btn--sm btn--danger">删除</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<div class="modal-backdrop" id="chapterModal" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="chapterModalTitle">
    <div class="modal__header">
      <h2 class="modal__title" id="chapterModalTitle">添加章节</h2>
      <button type="button" class="modal__close" data-modal-close aria-label="关闭">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
      </button>
    </div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="">
      <div class="modal__body">
        <div class="form-group">
          <label class="form-label" for="chapterName">章节名称</label>
          <input class="form-input" type="text" id="chapterName" name="name" required maxlength="100" placeholder="如：第一章 C++语言入门">
        </div>
        <div class="form-group">
          <label class="form-label" for="chapterSubpart">所属小部分</label>
          <select class="form-select" id="chapterSubpart" name="subpart_id" required>
            <option value="">请选择</option>
            <?php foreach ($parts as $part): ?>
              <?php foreach ($subsByPart[(int) $part['id']] ?? [] as $sub): ?>
                <option value="<?= (int) $sub['id'] ?>"><?= e($part['name'] . ' / ' . $sub['name']) ?></option>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal__footer">
        <button type="button" class="btn" data-modal-close>取消</button>
        <button type="submit" class="btn btn--primary">保存</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-backdrop" id="partModal" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="partModalTitle">
    <div class="modal__header">
      <h2 class="modal__title" id="partModalTitle">添加大部分</h2>
      <button type="button" class="modal__close" data-modal-close aria-label="关闭">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
      </button>
    </div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="part_create">
      <div class="modal__body">
        <div class="form-group">
          <label class="form-label" for="partName">名称</label>
          <input class="form-input" type="text" id="partName" name="name" required maxlength="100" placeholder="如：一、语言及算法基础篇">
        </div>
      </div>
      <div class="modal__footer">
        <button type="button" class="btn" data-modal-close>取消</button>
        <button type="submit" class="btn btn--primary">保存</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-backdrop" id="subpartModal" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="subpartModalTitle">
    <div class="modal__header">
      <h2 class="modal__title" id="subpartModalTitle">添加小部分</h2>
      <button type="button" class="modal__close" data-modal-close aria-label="关闭">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
      </button>
    </div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="subpart_create">
      <div class="modal__body">
        <div class="form-group">
          <label class="form-label" for="subpartName">名称</label>
          <input class="form-input" type="text" id="subpartName" name="name" required maxlength="100" placeholder="如：基础(一) C++语言基础">
        </div>
        <div class="form-group">
          <label class="form-label" for="subpartPart">所属大部分</label>
          <select class="form-select" id="subpartPart" name="part_id" required>
            <option value="">请选择</option>
            <?php foreach ($parts as $part): ?>
              <option value="<?= (int) $part['id'] ?>"><?= e((string) $part['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal__footer">
        <button type="button" class="btn" data-modal-close>取消</button>
        <button type="submit" class="btn btn--primary">保存</button>
      </div>
    </form>
  </div>
</div>
<?php
$content = ob_get_clean();

render_admin_layout([
    'pageTitle' => '章节管理',
    'content' => $content,
    'active' => 'chapters',
]);
