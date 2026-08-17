<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin_layout.php';
require_once dirname(__DIR__) . '/includes/crypto.php';

Auth::requireLogin();

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        $apiKey = str_input((string) ($_POST['api_key'] ?? ''));
        $model = str_input((string) ($_POST['model'] ?? ''));
        $endpoint = str_input((string) ($_POST['endpoint'] ?? ''));
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($apiKey !== '') {
            setting_set('deepseek_api_key', Crypto::encrypt($apiKey));
        }
        if ($model !== '') {
            setting_set('deepseek_model', $model);
        }
        if ($endpoint !== '') {
            setting_set('deepseek_endpoint', rtrim($endpoint, '/'));
        }

        if ($newPassword !== '') {
            if ($newPassword !== $confirmPassword) {
                $message = '两次输入的新密码不一致。';
                $messageType = 'error';
            } elseif (!password_strength_ok($newPassword)) {
                $message = '新密码强度不足：至少 8 位，且包含大写、小写、数字、符号中的至少三种。';
                $messageType = 'error';
            } else {
                $adminId = (int) ($_SESSION['admin_id'] ?? 0);
                db()->prepare('UPDATE admins SET password_hash = :hash WHERE id = :id')
                    ->execute(['hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $adminId]);
                $message = '设置已保存，管理员密码已更新。';
            }
        } else {
            if ($message === '') {
                $message = '设置已保存。';
            }
        }
    } catch (Throwable $ex) {
        app_log('保存设置失败: ' . $ex->getMessage(), 'error');
        $message = '保存失败，请查看日志。';
        $messageType = 'error';
    }
}

$maskedKey = '';
$model = 'deepseek-v4-flash';
$endpoint = 'https://api.deepseek.com';
try {
    $rawKey = setting_get('deepseek_api_key');
    if ($rawKey !== '') {
        $maskedKey = Crypto::mask(Crypto::decrypt($rawKey));
    }
    $model = setting_get('deepseek_model', 'deepseek-v4-flash');
    $endpoint = setting_get('deepseek_endpoint', 'https://api.deepseek.com');
} catch (Throwable $ex) {
    // 忽略读取失败
}

ob_start();
?>
<div class="admin-page-header">
  <h1>系统设置</h1>
</div>

<?php if ($message !== ''): ?>
  <div class="alert alert--<?= $messageType === 'error' ? 'error' : 'success' ?>" role="alert"><?= e($message) ?></div>
<?php endif; ?>

<form method="post">
  <?= csrf_field() ?>

  <div class="card">
    <h2 style="font-size: 16px;">AI API 配置</h2>
    <p class="text-muted" style="font-size: 13.5px;">API Key 使用 AES-256-CBC 加密存储，保存后将不会在页面上明文显示。</p>

    <div class="form-group">
      <label class="form-label" for="apiKey">API Key <?= $maskedKey !== '' ? '<span class="form-label__hint">当前：' . e($maskedKey) . '（留空则保持不变）</span>' : '<span class="form-label__hint">尚未配置</span>' ?></label>
      <input class="form-input" type="password" id="apiKey" name="api_key" placeholder="sk-…" autocomplete="new-password">
    </div>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px;">
      <div class="form-group">
        <label class="form-label" for="model">模型名称</label>
        <input class="form-input" type="text" id="model" name="model" value="<?= e($model) ?>" placeholder="deepseek-v4-flash">
      </div>
      <div class="form-group">
        <label class="form-label" for="endpoint">API Endpoint</label>
        <input class="form-input" type="url" id="endpoint" name="endpoint" value="<?= e($endpoint) ?>" placeholder="https://api.deepseek.com">
      </div>
    </div>
    <button type="button" class="btn" id="testApiBtn">测试 API 连接</button>
  </div>

  <div class="card">
    <h2 style="font-size: 16px;">修改管理员密码</h2>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px;">
      <div class="form-group">
        <label class="form-label" for="newPassword">新密码</label>
        <input class="form-input" type="password" id="newPassword" name="new_password" autocomplete="new-password" placeholder="留空则不修改">
      </div>
      <div class="form-group">
        <label class="form-label" for="confirmPassword">确认新密码</label>
        <input class="form-input" type="password" id="confirmPassword" name="confirm_password" autocomplete="new-password">
      </div>
    </div>
    <p class="text-muted mb-0" style="font-size: 12.5px;">密码至少 8 位，需包含大写字母、小写字母、数字、特殊符号中的至少三种。</p>
  </div>

  <button type="submit" class="btn btn--primary">保存设置</button>
</form>
<?php
$content = ob_get_clean();

render_admin_layout([
    'pageTitle' => '系统设置',
    'content' => $content,
    'active' => 'settings',
]);
