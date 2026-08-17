<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function render_admin_layout(array $vars): void
{
    $pageTitle = $vars['pageTitle'] ?? '后台管理';
    $contentHtml = $vars['content'] ?? '';
    $active = $vars['active'] ?? '';
    $extraHead = $vars['extraHead'] ?? '';
    $extraScripts = $vars['extraScripts'] ?? '';

    $flashesHtml = '';
    foreach (get_flashes() as $flash) {
        $type = in_array($flash['type'], ['success', 'error', 'warning'], true) ? $flash['type'] : 'info';
        $flashesHtml .= '<div class="alert alert--' . $type . '" role="alert">' . e($flash['message']) . '</div>';
    }

    $menu = [
        ['key' => 'dashboard', 'label' => '仪表盘', 'href' => url('admin/index.php'), 'icon' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="1.5" y="1.5" width="5.5" height="5.5" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="9" y="1.5" width="5.5" height="5.5" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="1.5" y="9" width="5.5" height="5.5" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="9" y="9" width="5.5" height="5.5" rx="1" stroke="currentColor" stroke-width="1.4"/></svg>'],
        ['key' => 'chapters', 'label' => '章节管理', 'href' => url('admin/chapters.php'), 'icon' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M2 3.5A1.5 1.5 0 013.5 2h3l1.5 2h4.5A1.5 1.5 0 0114 5.5v6A1.5 1.5 0 0112.5 13h-9A1.5 1.5 0 012 11.5v-8z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>'],
        ['key' => 'problems', 'label' => '题目管理', 'href' => url('admin/problems.php'), 'icon' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 2.5h10a1 1 0 011 1v9a1 1 0 01-1 1H3a1 1 0 01-1-1v-9a1 1 0 011-1z" stroke="currentColor" stroke-width="1.4"/><path d="M5 6l2 2-2 2M8.5 10H11" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>'],
        ['key' => 'fetch', 'label' => '一键抓取', 'href' => url('admin/fetch.php'), 'icon' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 1.5v9M8 10.5l-3-3M8 10.5l3-3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/><path d="M2.5 11v2a1 1 0 001 1h9a1 1 0 001-1v-2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>'],
        ['key' => 'settings', 'label' => '系统设置', 'href' => url('admin/settings.php'), 'icon' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="8" cy="8" r="2.2" stroke="currentColor" stroke-width="1.4"/><path d="M8 1.8v2M8 12.2v2M1.8 8h2M12.2 8h2M3.6 3.6l1.4 1.4M11 11l1.4 1.4M12.4 3.6L11 5M5 11l-1.4 1.4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>'],
    ];

    ?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> - <?= e(APP_NAME) ?> 后台</title>
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="admin-base" content="<?= e(url('admin')) ?>">
<link rel="icon" type="image/svg+xml" href="<?= e(url('assets/logo.svg')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('css/style.css')) ?>?v=<?= e(APP_VERSION) ?>">
<link rel="stylesheet" href="<?= e(url('css/admin.css')) ?>?v=<?= e(APP_VERSION) ?>">
<link rel="stylesheet" href="<?= e(url('css/responsive.css')) ?>?v=<?= e(APP_VERSION) ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.9.0/build/styles/atom-one-dark.min.css">
<script defer src="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.9.0/build/highlight.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.9.0/build/languages/cpp.min.js"></script>
<?= $extraHead ?>
</head>
<body class="no-sidebar">
<a class="skip-link" href="#adminMain">跳到主内容</a>

<header class="topnav">
  <div class="topnav__inner">
    <a class="topnav__brand" href="<?= e(url('admin/index.php')) ?>">
      <img src="<?= e(url('assets/logo.svg')) ?>" alt="<?= e(APP_NAME) ?> Logo" class="topnav__logo" width="28" height="28">
      <span class="topnav__name"><?= e(APP_SHORT_NAME) ?> · 后台</span>
    </a>
    <nav class="topnav__links" aria-label="导航" style="margin-left: auto;">
      <a class="topnav__link" href="<?= e(url('index.php')) ?>" target="_blank">查看前台</a>
      <a class="topnav__link" href="<?= e(url('admin/logout.php')) ?>">退出登录</a>
    </nav>
  </div>
</header>

<div class="admin-shell">
  <nav class="admin-side" aria-label="后台菜单">
    <div class="admin-side__title">管理菜单</div>
    <?php foreach ($menu as $item): ?>
    <a class="admin-side__link<?= $active === $item['key'] ? ' admin-side__link--active' : '' ?>" href="<?= e($item['href']) ?>">
      <?= $item['icon'] ?>
      <?= e($item['label']) ?>
    </a>
    <?php endforeach; ?>
  </nav>

  <main class="admin-main" id="adminMain">
    <div class="admin-main__inner">
      <?= $flashesHtml ?>
      <?= $contentHtml ?>
    </div>
  </main>
</div>

<div class="toast-container" id="toastContainer" aria-live="polite"></div>

<script src="<?= e(url('js/main.js')) ?>?v=<?= e(APP_VERSION) ?>" defer></script>
<script src="<?= e(url('js/admin.js')) ?>?v=<?= e(APP_VERSION) ?>" defer></script>
<?= $extraScripts ?>
</body>
</html><?php
}
