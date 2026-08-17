<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/crypto.php';

if (is_installed()) {
    flash('warning', '系统已安装。如需重新安装，请先删除 includes/config.local.php。');
    redirect(url('index.php'));
}

$step = int_input($_POST['step'] ?? ($_GET['step'] ?? 1), 1);
$step = max(1, min(4, $step));
$error = '';
$csrf = csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'test_api') {
    $key = trim((string) ($_POST['api_key'] ?? ''));
    if ($key === '') {
        json_response(['ok' => false, 'message' => '请先输入 API Key。']);
    }
    $endpoint = rtrim(trim((string) ($_POST['endpoint'] ?: 'https://api.deepseek.com')), '/');
    $model = trim((string) ($_POST['model'] ?: 'deepseek-v4-flash'));

    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => 'You are a helpful assistant.'],
            ['role' => 'user', 'content' => 'Reply with the single word: pong'],
        ],
    ];
    $ch = curl_init($endpoint . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        json_response(['ok' => false, 'message' => '网络请求失败：' . $curlError]);
    }
    if ($httpCode !== 200) {
        $data = json_decode((string) $response, true);
        $msg = $data['error']['message'] ?? ('HTTP ' . $httpCode);
        json_response(['ok' => false, 'message' => '连接失败：' . $msg]);
    }
    json_response(['ok' => true, 'message' => '连接成功，API Key 有效。']);
}

function install_env_checks(): array
{
    $checks = [];
    $checks[] = [
        'label' => 'PHP 版本 >= 8.0（当前 ' . PHP_VERSION . '）',
        'ok' => version_compare(PHP_VERSION, '8.0.0', '>='),
    ];
    foreach (['pdo_mysql', 'mbstring', 'curl', 'json', 'openssl'] as $ext) {
        $checks[] = [
            'label' => '扩展 ' . $ext,
            'ok' => extension_loaded($ext),
        ];
    }
    foreach (['includes', 'cache', 'logs'] as $dir) {
        $path = ROOT_PATH . '/' . $dir;
        $writable = is_dir($path) ? is_writable($path) : is_writable(ROOT_PATH);
        $checks[] = ['label' => '目录 /' . $dir . ' 可写', 'ok' => (bool) $writable];
    }
    return $checks;
}

function install_render(int $step, string $error = '', array $extra = []): void
{
    $steps = ['环境检查', '数据库', '管理员与 API', '完成'];
    ?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>安装向导 - <?= e(APP_NAME) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/svg+xml" href="<?= e(url('assets/logo.svg')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(url('css/admin.css')) ?>">
<link rel="stylesheet" href="<?= e(url('css/responsive.css')) ?>">
</head>
<body>
<div class="install-page">
  <div class="install-wrap">
    <div class="install-header">
      <img src="<?= e(url('assets/logo.svg')) ?>" alt="<?= e(APP_NAME) ?> Logo" class="install-header__logo" width="52" height="52">
      <h1><?= e(APP_NAME) ?></h1>
      <p>安装向导 · v<?= e(APP_VERSION) ?></p>
    </div>

    <div class="install-steps" aria-label="安装步骤">
      <?php foreach ($steps as $i => $label): ?>
        <?php
        $num = $i + 1;
        $cls = '';
        if ($num < $step) {
            $cls = ' install-step--done';
        } elseif ($num === $step) {
            $cls = ' install-step--active';
        }
        ?>
        <div class="install-step<?= $cls ?>">
          <span class="install-step__dot"><?= $num < $step ? '✓' : $num ?></span>
          <span><?= e($label) ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($error !== ''): ?>
      <div class="alert alert--error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <?= $extra['body'] ?? '' ?>
  </div>
</div>
<div class="toast-container" id="toastContainer" aria-live="polite"></div>
<script src="<?= e(url('js/main.js')) ?>" defer></script>
<?= $extra['scripts'] ?? '' ?>
</body>
</html><?php
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step >= 2 && !csrf_verify()) {
    $error = 'CSRF 校验失败，请重试。';
    $step = max(1, $step - 1);
}

if ($step === 1) {
    $checks = install_env_checks();
    $allOk = true;
    foreach ($checks as $check) {
        if (!$check['ok']) {
            $allOk = false;
        }
    }
    $listHtml = '<ul class="check-list">';
    foreach ($checks as $check) {
        $listHtml .= '<li><span>' . e($check['label']) . '</span>'
            . ($check['ok'] ? '<span class="check-ok">✓ 通过</span>' : '<span class="check-fail">✗ 不满足</span>')
            . '</li>';
    }
    $listHtml .= '</ul>';

    $body = '<div class="install-card"><h2>环境检查</h2>' . $listHtml;
    if ($allOk) {
        $body .= '<form method="post"><input type="hidden" name="csrf_token" value="' . e($csrf) . '">'
            . '<input type="hidden" name="step" value="2">'
            . '<button type="submit" class="btn btn--accent" style="width: 100%;">环境满足要求，下一步</button></form>';
    } else {
        $body .= '<div class="alert alert--warning">请先解决上述问题，然后刷新本页面。</div>';
    }
    $body .= '</div>';
    install_render(1, $error, ['body' => $body]);
    exit;
}

if ($step === 2) {
    $dbHost = (string) ($_POST['db_host'] ?? 'localhost');
    $dbPort = (string) ($_POST['db_port'] ?? '3306');
    $dbUser = (string) ($_POST['db_user'] ?? 'root');
    $dbName = (string) ($_POST['db_name'] ?? 'ybt_answers');

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === '2') {
        $dbPass = (string) ($_POST['db_pass'] ?? '');
        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $dbHost, (int) $dbPort),
                $dbUser,
                $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $safeName = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);
            if ($safeName === '' || $safeName !== $dbName) {
                throw new RuntimeException('数据库名只能包含字母、数字和下划线。');
            }
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $safeName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (Throwable $ex) {
            $error = '数据库连接失败：' . $ex->getMessage();
        }

        if ($error === '') {
            $_SESSION['install_db'] = [
                'host' => $dbHost,
                'port' => (int) $dbPort,
                'user' => $dbUser,
                'pass' => $dbPass,
                'name' => $dbName,
            ];
            $step = 3;
        }
    }

    if ($step === 2) {
        $body = '<div class="install-card"><h2>数据库连接</h2>'
            . '<form method="post">'
            . '<input type="hidden" name="csrf_token" value="' . e($csrf) . '">'
            . '<input type="hidden" name="step" value="2">'
            . '<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 0 14px;">'
            . '<div class="form-group"><label class="form-label" for="db_host">主机</label>'
            . '<input class="form-input" type="text" id="db_host" name="db_host" value="' . e($dbHost) . '" required></div>'
            . '<div class="form-group"><label class="form-label" for="db_port">端口</label>'
            . '<input class="form-input" type="number" id="db_port" name="db_port" value="' . e($dbPort) . '" required></div>'
            . '</div>'
            . '<div class="form-group"><label class="form-label" for="db_user">用户名</label>'
            . '<input class="form-input" type="text" id="db_user" name="db_user" value="' . e($dbUser) . '" required autocomplete="username"></div>'
            . '<div class="form-group"><label class="form-label" for="db_pass">密码</label>'
            . '<input class="form-input" type="password" id="db_pass" name="db_pass" autocomplete="current-password"></div>'
            . '<div class="form-group"><label class="form-label" for="db_name">数据库名 <span class="form-label__hint">不存在时将自动创建</span></label>'
            . '<input class="form-input" type="text" id="db_name" name="db_name" value="' . e($dbName) . '" required></div>'
            . '<button type="submit" class="btn btn--accent" style="width: 100%;">连接并下一步</button>'
            . '</form></div>';
        install_render(2, $error, ['body' => $body]);
        exit;
    }
}

if ($step === 3) {
    if (empty($_SESSION['install_db'])) {
        redirect(url('install.php?step=1'));
    }

    $username = (string) ($_POST['admin_user'] ?? 'admin');
    $apiKey = '';
    $model = 'deepseek-v4-flash';
    $endpoint = 'https://api.deepseek.com';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === '3') {
        $username = trim((string) ($_POST['admin_user'] ?? ''));
        $password = (string) ($_POST['admin_pass'] ?? '');
        $password2 = (string) ($_POST['admin_pass2'] ?? '');
        $apiKey = trim((string) ($_POST['api_key'] ?? ''));
        $model = trim((string) ($_POST['model'] ?: 'deepseek-v4-flash'));
        $endpoint = rtrim(trim((string) ($_POST['endpoint'] ?: 'https://api.deepseek.com')), '/');

        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            $error = '管理员账号须为 3-20 位字母、数字或下划线。';
        } elseif ($password === '' || $password !== $password2) {
            $error = '两次输入的密码不一致或为空。';
        } elseif (!password_strength_ok($password)) {
            $error = '密码强度不足：至少 8 位，且包含大写字母、小写字母、数字、特殊符号中的至少三种。';
        } elseif ($apiKey === '') {
            $error = 'AI API Key 为必填项。';
        }

        if ($error === '') {
            try {
                do_install($_SESSION['install_db'], $username, $password, $apiKey, $model, $endpoint);
                unset($_SESSION['install_db']);
                $step = 4;
            } catch (Throwable $ex) {
                app_log('安装失败: ' . $ex->getMessage(), 'error');
                $error = '安装失败：' . $ex->getMessage();
            }
        }
    }

    if ($step === 3) {
        $scripts = <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', function () {
  var btn = document.getElementById('installTestApi');
  if (!btn) return;
  btn.addEventListener('click', function () {
    var form = document.getElementById('step3Form');
    var fd = new FormData();
    fd.append('action', 'test_api');
    fd.append('api_key', form.querySelector('[name="api_key"]').value.trim());
    fd.append('model', form.querySelector('[name="model"]').value.trim());
    fd.append('endpoint', form.querySelector('[name="endpoint"]').value.trim());
    btn.disabled = true;
    btn.textContent = '测试中…';
    fetch('install.php', { method: 'POST', body: fd })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        YBT.toast(data.message || (data.ok ? '连接成功' : '连接失败'), data.ok ? 'success' : 'error');
      })
      .catch(function () { YBT.toast('网络错误', 'error'); })
      .finally(function () {
        btn.disabled = false;
        btn.textContent = '测试连接';
      });
  });
});
</script>
JS;
        $body = '<div class="install-card"><h2>管理员账号与 AI API</h2>'
            . '<form method="post" id="step3Form">'
            . '<input type="hidden" name="csrf_token" value="' . e($csrf) . '">'
            . '<input type="hidden" name="step" value="3">'
            . '<div class="form-group"><label class="form-label" for="admin_user">管理员账号</label>'
            . '<input class="form-input" type="text" id="admin_user" name="admin_user" value="' . e($username) . '" required autocomplete="username"></div>'
            . '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0 14px;">'
            . '<div class="form-group"><label class="form-label" for="admin_pass">密码</label>'
            . '<input class="form-input" type="password" id="admin_pass" name="admin_pass" required autocomplete="new-password"></div>'
            . '<div class="form-group"><label class="form-label" for="admin_pass2">确认密码</label>'
            . '<input class="form-input" type="password" id="admin_pass2" name="admin_pass2" required autocomplete="new-password"></div>'
            . '</div>'
            . '<p class="text-muted" style="font-size: 12.5px;">密码至少 8 位，需包含大写字母、小写字母、数字、特殊符号中的至少三种。</p>'
            . '<div class="form-group"><label class="form-label" for="api_key">AI API Key <span style="color: var(--color-error);">*</span> <span class="form-label__hint">将加密存储</span></label>'
            . '<input class="form-input" type="password" id="api_key" name="api_key" required placeholder="sk-…" autocomplete="new-password"></div>'
            . '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0 14px;">'
            . '<div class="form-group"><label class="form-label" for="model">模型名称</label>'
            . '<input class="form-input" type="text" id="model" name="model" value="' . e($model) . '"></div>'
            . '<div class="form-group"><label class="form-label" for="endpoint">API Endpoint</label>'
            . '<input class="form-input" type="url" id="endpoint" name="endpoint" value="' . e($endpoint) . '"></div>'
            . '</div>'
            . '<div class="flex gap-8" style="margin-bottom: 16px;">'
            . '<button type="button" class="btn" id="installTestApi">测试连接</button>'
            . '</div>'
            . '<button type="submit" class="btn btn--accent" style="width: 100%;">开始安装</button>'
            . '</form></div>';
        install_render(3, $error, ['body' => $body, 'scripts' => $scripts]);
        exit;
    }
}

if ($step === 4) {
    $selfDeleted = !is_file(__FILE__);
    $body = '<div class="install-card"><div class="install-success">'
        . '<div class="install-success__icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>'
        . '<h2 style="margin-bottom: 6px;">安装成功</h2>'
        . '<p class="text-muted" style="font-size: 14px;">数据库结构已创建，初始题库分类已导入。</p>';
    if (!$selfDeleted) {
        $body .= '<div class="alert alert--warning" style="text-align: left;">出于安全考虑，请手动删除根目录下的 <code>install.php</code> 文件。</div>';
    }
    $body .= '<div class="flex gap-8" style="justify-content: center; margin-top: 18px;">'
        . '<a class="btn btn--accent" href="' . e(url('index.php')) . '">访问前台</a>'
        . '<a class="btn" href="' . e(url('admin/login.php')) . '">登录后台</a>'
        . '</div></div></div>';
    install_render(4, '', ['body' => $body]);
    exit;
}

function do_install(array $dbConfig, string $username, string $password, string $apiKey, string $model, string $endpoint): void
{
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbConfig['host'], $dbConfig['port'], $dbConfig['name']);
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS parts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS subparts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            part_id INT UNSIGNED NOT NULL,
            name VARCHAR(100) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_part (part_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS chapters (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            subpart_id INT UNSIGNED NOT NULL,
            name VARCHAR(100) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_subpart (subpart_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS problems (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            chapter_id INT UNSIGNED NOT NULL,
            pid VARCHAR(10) NOT NULL,
            title VARCHAR(200) NOT NULL,
            time_limit VARCHAR(50) NOT NULL DEFAULT "",
            memory_limit VARCHAR(50) NOT NULL DEFAULT "",
            description MEDIUMTEXT,
            input_desc TEXT,
            output_desc TEXT,
            input_sample TEXT,
            output_sample TEXT,
            source_url VARCHAR(255) NOT NULL DEFAULT "",
            answer_code MEDIUMTEXT,
            is_answer_manual TINYINT(1) NOT NULL DEFAULT 0,
            deleted_at DATETIME NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_pid (pid),
            KEY idx_chapter (chapter_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS settings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `key` VARCHAR(64) NOT NULL,
            `value` TEXT,
            PRIMARY KEY (id),
            UNIQUE KEY uk_key (`key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS admins (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            username VARCHAR(50) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $count = (int) $pdo->query('SELECT COUNT(*) FROM parts')->fetchColumn();
    if ($count === 0) {
        $structure = [
            '一、语言及算法基础篇' => ['基础(一) C++语言基础', '基础(二) 基础算法基础', '基础(三) 数据结构'],
            '二、算法提高篇' => ['提高(一) 基础算法', '提高(二) 字符串算法', '提高(三) 图论', '提高(四) 数据结构', '提高(五) 动态规划', '提高(六) 数学基础'],
            '三、高手训练' => ['高手(一) 基础算法', '高手(二) 字符串算法', '高手(三) 图论', '高手(四) 数据结构', '高手(五) 动态规划', '高手(六) 数学基础'],
            '四、官方真题' => ['1. NOIP普及组', '2. NOIP提高组', '3. GESP组考级(上)', '4. GESP组考级(下)'],
        ];
        $partStmt = $pdo->prepare('INSERT INTO parts (name, sort_order) VALUES (:name, :so)');
        $subStmt = $pdo->prepare('INSERT INTO subparts (part_id, name, sort_order) VALUES (:pid, :name, :so)');
        $partOrder = 0;
        foreach ($structure as $partName => $subs) {
            $partOrder++;
            $partStmt->execute(['name' => $partName, 'so' => $partOrder]);
            $partId = (int) $pdo->lastInsertId();
            $subOrder = 0;
            foreach ($subs as $subName) {
                $subOrder++;
                $subStmt->execute(['pid' => $partId, 'name' => $subName, 'so' => $subOrder]);
            }
        }
    }

    $adminCount = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    if ($adminCount === 0) {
        $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (:u, :h)')
            ->execute(['u' => $username, 'h' => password_hash($password, PASSWORD_DEFAULT)]);
    }

    $settingStmt = $pdo->prepare(
        'INSERT INTO settings (`key`, `value`) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
    );

    $secretKeyFile = INCLUDES_PATH . '/secret.key';
    if (!is_file($secretKeyFile)) {
        file_put_contents($secretKeyFile, Crypto::generateKey(), LOCK_EX);
        @chmod($secretKeyFile, 0600);
    }

    $settingStmt->execute(['k' => 'deepseek_api_key', 'v' => Crypto::encrypt($apiKey)]);
    $settingStmt->execute(['k' => 'deepseek_model', 'v' => $model]);
    $settingStmt->execute(['k' => 'deepseek_endpoint', 'v' => $endpoint]);

    $configContent = "<?php\n\n"
        . "define('APP_INSTALLED', true);\n"
        . "define('DB_HOST', " . var_export($dbConfig['host'], true) . ");\n"
        . "define('DB_PORT', " . (int) $dbConfig['port'] . ");\n"
        . "define('DB_NAME', " . var_export($dbConfig['name'], true) . ");\n"
        . "define('DB_USER', " . var_export($dbConfig['user'], true) . ");\n"
        . "define('DB_PASS', " . var_export($dbConfig['pass'], true) . ");\n";
    if (file_put_contents(INCLUDES_PATH . '/config.local.php', $configContent, LOCK_EX) === false) {
        throw new RuntimeException('无法写入 includes/config.local.php，请检查目录权限。');
    }

    foreach (['cache', 'logs'] as $dir) {
        $path = ROOT_PATH . '/' . $dir;
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
        $deny = $path . '/.htaccess';
        if (!is_file($deny)) {
            @file_put_contents($deny, "Require all denied\n");
        }
    }

    @unlink(__FILE__);
}
