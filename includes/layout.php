<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/tree_cache.php';

function render_sidebar_tree(array $tree, ?int $activeChapterId, ?string $activePid): string
{
    $html = '<ul class="tree" role="tree" aria-label="题库目录树">';
    foreach ($tree as $part) {
        $partOpen = false;
        $subpartsHtml = '';
        foreach ($part['subparts'] as $sub) {
            $subOpen = false;
            $chaptersHtml = '';
            foreach ($sub['chapters'] as $chapter) {
                $isActiveChapter = $activeChapterId !== null && (int) $chapter['id'] === $activeChapterId;
                if ($isActiveChapter) {
                    $partOpen = true;
                    $subOpen = true;
                }
                $problemsHtml = '';
                $chapterHasActive = false;
                foreach ($chapter['problems'] as $problem) {
                    $isActiveProblem = $activePid !== null && (string) $problem['pid'] === $activePid;
                    if ($isActiveProblem) {
                        $partOpen = true;
                        $subOpen = true;
                        $chapterHasActive = true;
                    }
                    $problemsHtml .= '<li class="tree__leaf" role="treeitem">'
                        . '<a class="tree__problem' . ($isActiveProblem ? ' tree__problem--active' : '') . '" '
                        . 'href="' . e(url('problem.php?pid=' . urlencode((string) $problem['pid']))) . '">'
                        . '<span class="tree__problem-pid">' . e((string) $problem['pid']) . '</span> '
                        . '<span class="tree__problem-title">' . e((string) $problem['title']) . '</span>'
                        . '</a></li>';
                }
                $count = count($chapter['problems']);
                $chaptersHtml .= '<li class="tree__leaf' . ($chapterHasActive ? ' is-open' : '') . '" role="treeitem">'
                    . '<a class="tree__chapter' . ($isActiveChapter ? ' tree__chapter--active' : '') . '" '
                    . 'href="' . e(url('index.php?chapter=' . (int) $chapter['id'])) . '">'
                    . '<span class="tree__chapter-name">' . e((string) $chapter['name']) . '</span>'
                    . '<span class="tree__count">' . $count . '</span>'
                    . '</a>'
                    . ($problemsHtml !== '' ? '<ul class="tree__group tree__group--problems" role="group">' . $problemsHtml . '</ul>' : '')
                    . '</li>';
            }
            $subpartsHtml .= '<li class="tree__leaf' . ($subOpen ? ' is-open' : '') . '" role="treeitem" aria-expanded="' . ($subOpen ? 'true' : 'false') . '">'
                . '<button type="button" class="tree__subtoggle" aria-expanded="' . ($subOpen ? 'true' : 'false') . '">'
                . '<svg class="tree__chevron" width="12" height="12" viewBox="0 0 16 16" fill="none" aria-hidden="true">'
                . '<path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'
                . '<span class="tree__subpart-name">' . e((string) $sub['name']) . '</span>'
                . '</button>'
                . ($chaptersHtml !== '' ? '<ul class="tree__group" role="group">' . $chaptersHtml . '</ul>' : '')
                . '</li>';
        }
        $html .= '<li class="tree__part' . ($partOpen ? ' is-open' : '') . '" role="treeitem" aria-expanded="' . ($partOpen ? 'true' : 'false') . '">'
            . '<button type="button" class="tree__toggle" aria-expanded="' . ($partOpen ? 'true' : 'false') . '">'
            . '<svg class="tree__chevron" width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true">'
            . '<path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'
            . '<span class="tree__part-name">' . e((string) $part['name']) . '</span>'
            . '</button>'
            . ($subpartsHtml !== '' ? '<ul class="tree__group" role="group">' . $subpartsHtml . '</ul>' : '')
            . '</li>';
    }
    $html .= '</ul>';
    return $html;
}

function render_layout(array $vars): void
{
    $pageTitle = $vars['pageTitle'] ?? APP_NAME;
    $description = $vars['description'] ?? '信息学奥赛一本通（ybt.ssoier.cn）题库答案网，收录全部题目与参考解答，支持搜索与公式渲染。';
    $contentHtml = $vars['content'] ?? '';
    $activeNav = $vars['activeNav'] ?? 'home';
    $activeChapterId = $vars['activeChapterId'] ?? null;
    $activePid = $vars['activePid'] ?? null;
    $extraHead = $vars['extraHead'] ?? '';
    $extraScripts = $vars['extraScripts'] ?? '';
    $sidebarVisible = $vars['sidebar'] ?? true;
    $isAdmin = is_admin_area();

    $tree = [];
    $sidebarHtml = '';
    if ($sidebarVisible && !$isAdmin) {
        try {
            $tree = TreeCache::getTree();
        } catch (Throwable $ex) {
            $tree = [];
        }
        $sidebarHtml = render_sidebar_tree($tree, $activeChapterId, $activePid);
    }

    $flashesHtml = '';
    foreach (get_flashes() as $flash) {
        $type = in_array($flash['type'], ['success', 'error', 'warning'], true) ? $flash['type'] : 'info';
        $flashesHtml .= '<div class="alert alert--' . $type . '" role="alert">' . e($flash['message']) . '</div>';
    }

    $navLinks = [
        ['key' => 'home', 'label' => '首页', 'href' => url('index.php')],
        ['key' => 'problems', 'label' => '题目分类', 'href' => url('index.php#catalog')],
        ['key' => 'search', 'label' => '搜索', 'href' => url('search.php')],
    ];

    ?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> - <?= e(APP_NAME) ?></title>
<meta name="description" content="<?= e($description) ?>">
<meta name="app-base" content="<?= e(base_url()) ?>">
<link rel="icon" type="image/svg+xml" href="<?= e(url('assets/logo.svg')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('css/style.css')) ?>?v=<?= e(APP_VERSION) ?>">
<link rel="stylesheet" href="<?= e(url('css/responsive.css')) ?>?v=<?= e(APP_VERSION) ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.9.0/build/styles/atom-one-dark.min.css">
<script>
window.MathJax = {
  tex: {
    inlineMath: [['$', '$'], ['\\(', '\\)']],
    displayMath: [['$$', '$$'], ['\\[', '\\]']]
  },
  options: {
    skipHtmlTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code']
  }
};
</script>
<script defer src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
<script defer src="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.9.0/build/highlight.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.9.0/build/languages/cpp.min.js"></script>
<?= $extraHead ?>
</head>
<body class="<?= $sidebarVisible && !$isAdmin ? 'has-sidebar' : 'no-sidebar' ?>">
<a class="skip-link" href="#main">跳到主内容</a>

<header class="topnav" id="topnav">
  <div class="topnav__inner">
    <?php if ($sidebarVisible && !$isAdmin): ?>
    <button type="button" class="topnav__menu-btn" id="sidebarToggle" aria-label="切换侧边栏" aria-controls="sidebar" aria-expanded="true">
      <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
        <path d="M2 4.5h14M2 9h14M2 13.5h14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
      </svg>
    </button>
    <?php endif; ?>
    <a class="topnav__brand" href="<?= e(url('index.php')) ?>">
      <img src="<?= e(url('assets/logo.svg')) ?>" alt="<?= e(APP_NAME) ?> Logo" class="topnav__logo" width="28" height="28">
      <span class="topnav__name"><?= e(APP_SHORT_NAME) ?></span>
    </a>
    <nav class="topnav__links" aria-label="主导航">
      <?php foreach ($navLinks as $link): ?>
      <a class="topnav__link<?= $activeNav === $link['key'] ? ' topnav__link--active' : '' ?>" href="<?= e($link['href']) ?>"><?= e($link['label']) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="topnav__search" id="navSearch">
      <form action="<?= e(url('search.php')) ?>" method="get" role="search" id="navSearchForm">
        <svg class="topnav__search-icon" width="15" height="15" viewBox="0 0 16 16" fill="none" aria-hidden="true">
          <circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.5"/>
          <path d="M11 11l3.5 3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        <input type="text" name="q" id="navSearchInput" class="topnav__search-input"
               placeholder="搜索题号或标题…" autocomplete="off" aria-label="搜索题目" maxlength="60">
        <button type="button" class="topnav__search-cancel" id="navSearchCancel" aria-label="取消搜索">取消</button>
      </form>
      <div class="search-suggest" id="searchSuggest" hidden role="listbox" aria-label="搜索建议"></div>
    </div>
    <button type="button" class="topnav__search-trigger" id="navSearchTrigger" aria-label="打开搜索">
      <svg width="18" height="18" viewBox="0 0 16 16" fill="none" aria-hidden="true">
        <circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.5"/>
        <path d="M11 11l3.5 3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
      </svg>
    </button>
  </div>
</header>

<div class="layout">
  <?php if ($sidebarVisible && !$isAdmin): ?>
  <aside class="sidebar" id="sidebar" aria-label="题目目录">
    <div class="sidebar__header">
      <span class="sidebar__title">题库目录</span>
      <button type="button" class="sidebar__close" id="sidebarClose" aria-label="关闭侧边栏">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
          <path d="M4 4l8 8M12 4l-8 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
        </svg>
      </button>
    </div>
    <div class="sidebar__body" id="sidebarBody">
      <?= $sidebarHtml !== '' ? $sidebarHtml : '<p class="sidebar__empty">暂无内容，请先在后台添加章节与题目。</p>' ?>
    </div>
  </aside>
  <div class="sidebar-backdrop" id="sidebarBackdrop" hidden></div>
  <?php endif; ?>

  <main class="main" id="main">
    <div class="main__inner">
      <?= $flashesHtml ?>
      <?= $contentHtml ?>
    </div>
  </main>
</div>

<footer class="footer">
  <div class="footer__inner">
    <div class="footer__row">
      <span>© <?= date('Y') ?> <a href="https://www.szystudio.cn/" target="_blank" rel="noopener">SZY创新工作室</a> · 公益非盈利项目</span>
      <span class="footer__sep">|</span>
      <a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener">京ICP备2024098823号-4</a>
      <span class="footer__sep">|</span>
      <a href="<?= e(url('terms.php')) ?>">使用协议</a>
    </div>
    <div class="footer__row footer__row--muted">
      题目内容版权归 <a href="<?= e(SOURCE_SITE) ?>" target="_blank" rel="noopener">ybt.ssoier.cn</a> 所有 · 答案由 AI 自动生成，仅供参考
    </div>
  </div>
</footer>

<div class="toast-container" id="toastContainer" aria-live="polite"></div>

<script src="<?= e(url('js/main.js')) ?>?v=<?= e(APP_VERSION) ?>" defer></script>
<?php if ($sidebarVisible && !$isAdmin): ?>
<script src="<?= e(url('js/sidebar.js')) ?>?v=<?= e(APP_VERSION) ?>" defer></script>
<script src="<?= e(url('js/search.js')) ?>?v=<?= e(APP_VERSION) ?>" defer></script>
<?php endif; ?>
<?= $extraScripts ?>
</body>
</html><?php
}
