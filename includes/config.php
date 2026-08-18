<?php

declare(strict_types=1);

define('APP_NAME', '信息学奥赛一本通答案网');
define('APP_SHORT_NAME', 'YBT Answers');
define('APP_VERSION', '1.2.3');
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDES_PATH', __DIR__);
define('CACHE_PATH', ROOT_PATH . '/cache');
define('LOG_FILE', ROOT_PATH . '/logs/app.log');
define('SOURCE_SITE', 'https://ybt.ssoier.cn');

define('APP_DEBUG', false);

date_default_timezone_set('Asia/Shanghai');

require_once __DIR__ . '/functions.php';

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED);
}

set_exception_handler(static function (Throwable $e): void {
    app_log(sprintf(
        "Uncaught %s: %s in %s:%d\n%s",
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ), 'error');

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    if (APP_DEBUG) {
        echo '<pre style="padding:16px;background:#fef2f2;color:#991b1b;">'
            . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</pre>';
        return;
    }
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>500</title>'
        . '<style>body{font-family:Inter,system-ui,sans-serif;background:#f8fafc;color:#0f172a;'
        . 'display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}'
        . '.box{text-align:center}.box h1{font-size:48px;margin:0 0 8px}.box p{color:#64748b}'
        . '.box a{color:#059669}</style></head><body><div class="box"><h1>500</h1>'
        . '<p>服务器开小差了，请稍后再试。</p><p><a href="/">返回首页</a></p></div></body></html>';
});

set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
    if (!(error_reporting() & $no)) {
        return false;
    }
    $fatal = in_array($no, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true);
    if ($fatal) {
        throw new ErrorException($str, 0, $no, $file, $line);
    }
    app_log(sprintf('PHP warning [%d]: %s in %s:%d', $no, $str, $file, $line), 'warning');
    return true;
});

$localConfig = __DIR__ . '/config.local.php';
if (is_file($localConfig)) {
    require $localConfig;
}

if (!defined('APP_INSTALLED')) {
    define('APP_INSTALLED', false);
}

if (session_status() === PHP_SESSION_NONE) {
    session_name('YBTSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
