<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

if (Auth::isLoggedIn()) {
    redirect(url('admin/index.php'));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $result = Auth::login(
        str_input((string) ($_POST['username'] ?? '')),
        (string) ($_POST['password'] ?? '')
    );
    if ($result['ok']) {
        redirect(url('admin/index.php'));
    }
    $error = $result['message'];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>登录 - <?= e(APP_NAME) ?> 后台</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/svg+xml" href="<?= e(url('assets/logo.svg')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(url('css/admin.css')) ?>">
<link rel="stylesheet" href="<?= e(url('css/responsive.css')) ?>">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-card__brand">
      <img src="<?= e(url('assets/logo.svg')) ?>" alt="<?= e(APP_NAME) ?> Logo" width="26" height="26">
      <?= e(APP_SHORT_NAME) ?>
    </div>
    <h1 class="auth-card__title">后台登录</h1>
    <p class="auth-card__desc">请使用管理员账号登录</p>

    <?php if ($error !== ''): ?>
      <div class="alert alert--error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('admin/login.php')) ?>" novalidate>
      <?= csrf_field() ?>
      <div class="form-group">
        <label class="form-label" for="username">账号</label>
        <input class="form-input" type="text" id="username" name="username" required
               autocomplete="username" value="<?= e((string) ($_POST['username'] ?? '')) ?>">
      </div>
      <div class="form-group">
        <label class="form-label" for="password">密码</label>
        <input class="form-input" type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn--primary">登 录</button>
    </form>

    <p class="text-muted" style="text-align: center; font-size: 12.5px; margin-top: 20px;">
      连续失败 5 次将锁定 15 分钟 · <a href="<?= e(url('index.php')) ?>">返回前台</a>
    </p>
  </div>
</div>
</body>
</html>
