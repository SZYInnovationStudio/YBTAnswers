<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_log(string $message, string $level = 'info'): void
{
    $file = defined('LOG_FILE') ? LOG_FILE : dirname(__DIR__) . '/logs/app.log';
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $line = sprintf("[%s] [%s] %s%s", date('Y-m-d H:i:s'), strtoupper($level), $message, PHP_EOL);
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($token) && $token !== '' && hash_equals(csrf_token(), $token);
}

function require_csrf(): void
{
    if (!csrf_verify()) {
        http_response_code(419);
        exit('CSRF 校验失败，请刷新页面后重试。');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function str_input(?string $value): string
{
    $value = (string) $value;
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    return trim($value);
}

function int_input($value, int $default = 0): int
{
    return is_numeric($value) ? (int) $value : $default;
}

function highlight_keyword(string $text, string $keyword): string
{
    $escaped = e($text);
    $keyword = trim($keyword);
    if ($keyword === '') {
        return $escaped;
    }
    $pattern = '/' . preg_quote(e($keyword), '/') . '/iu';
    return (string) preg_replace($pattern, '<mark>$0</mark>', $escaped);
}

function format_limit(?string $timeLimit, ?string $memoryLimit): string
{
    $parts = [];
    if ($timeLimit !== null && $timeLimit !== '') {
        $parts[] = e($timeLimit);
    }
    if ($memoryLimit !== null && $memoryLimit !== '') {
        $parts[] = e($memoryLimit);
    }
    return implode(' · ', $parts);
}

function format_datetime(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);
    return $ts ? date('Y-m-d H:i', $ts) : $datetime;
}

function setting_get(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query('SELECT `key`, `value` FROM settings');
            foreach ($stmt as $row) {
                $cache[$row['key']] = (string) $row['value'];
            }
        } catch (Throwable $ex) {
            return $default;
        }
    }
    return $cache[$key] ?? $default;
}

function setting_set(string $key, string $value): void
{
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare(
        'INSERT INTO settings (`key`, `value`) VALUES (:key, :value)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
    );
    $stmt->execute(['key' => $key, 'value' => $value]);
}

function is_installed(): bool
{
    if (defined('APP_INSTALLED') && APP_INSTALLED) {
        return true;
    }
    return is_file(__DIR__ . '/config.local.php');
}

function guard_installed(): void
{
    if (!is_installed()) {
        redirect(url('install.php'));
    }
}

function base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
    $scriptFile = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $root = str_replace('\\', '/', ROOT_PATH);
    if ($scriptFile !== '' && str_starts_with($scriptFile, $root)) {
        $relative = '/' . ltrim(substr($scriptFile, strlen($root)), '/');
        if (str_ends_with($scriptName, $relative)) {
            $base = rtrim(substr($scriptName, 0, strlen($scriptName) - strlen($relative)), '/');
            return $base;
        }
    }
    $base = '';
    return $base;
}

function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

function current_path(): string
{
    return str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
}

function is_admin_area(): bool
{
    return str_contains(current_path(), '/admin/');
}

function sanitize_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    for ($i = 0; $i < 3; $i++) {
        $next = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($next === $html) {
            break;
        }
        $html = $next;
    }
    if (!preg_match('/<[a-z][\s\S]*>/i', $html)) {
        return nl2br(e($html));
    }
    if (!class_exists('DOMDocument')) {
        return nl2br(e(strip_tags($html)));
    }

    $allow = [
        'p', 'br', 'hr', 'b', 'strong', 'i', 'em', 'u', 's', 'sub', 'sup',
        'span', 'div', 'pre', 'code', 'blockquote', 'center',
        'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'img', 'font',
    ];
    $attrMap = [
        'a' => ['href', 'title'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'td' => ['colspan', 'rowspan', 'align', 'valign', 'width', 'height'],
        'th' => ['colspan', 'rowspan', 'align', 'valign', 'width', 'height'],
        'table' => ['border', 'width', 'align', 'cellpadding', 'cellspacing'],
        'font' => ['color', 'size'],
    ];

    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $body = $doc->getElementsByTagName('body')->item(0);
    if (!$body) {
        return nl2br(e($html));
    }

    $out = '';
    foreach ($body->childNodes as $child) {
        $out .= sanitize_node($child, $allow, $attrMap);
    }
    return $out;
}

function sanitize_node(DOMNode $node, array $allow, array $attrMap): string
{
    if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
        return e((string) $node->nodeValue);
    }
    if ($node->nodeType !== XML_ELEMENT_NODE) {
        return '';
    }

    $tag = strtolower((string) $node->nodeName);
    if (!in_array($tag, $allow, true)) {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= sanitize_node($child, $allow, $attrMap);
        }
        return $out;
    }

    $attrStr = '';
    $allowedAttrs = $attrMap[$tag] ?? [];
    if ($node->hasAttributes()) {
        foreach ($node->attributes as $attr) {
            $name = strtolower((string) $attr->nodeName);
            if (!in_array($name, $allowedAttrs, true)) {
                continue;
            }
            $value = trim((string) $attr->nodeValue);
            if ($value === '') {
                continue;
            }
            if ($name === 'href' || $name === 'src') {
                if (!is_safe_url($value)) {
                    continue;
                }
            } elseif (preg_match('/[\x00-\x1f\x7f]/', $value)) {
                continue;
            }
            $attrStr .= ' ' . $name . '="' . e($value) . '"';
        }
    }

    if (in_array($tag, ['br', 'hr', 'img'], true)) {
        return '<' . $tag . $attrStr . '>';
    }

    $inner = '';
    foreach ($node->childNodes as $child) {
        $inner .= sanitize_node($child, $allow, $attrMap);
    }
    return '<' . $tag . $attrStr . '>' . $inner . '</' . $tag . '>';
}

function is_safe_url(string $url): bool
{
    $url = trim($url);
    if ($url === '') {
        return false;
    }
    if (preg_match('#^https?://#i', $url)) {
        return true;
    }
    if (preg_match('#^//#', $url)) {
        return false;
    }
    if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*:#', $url)) {
        return false;
    }
    return true;
}

function render_code_block(string $code, string $language = 'cpp'): string
{
    return '<pre class="code-block"><code class="language-' . e($language) . '">'
        . e($code) . '</code></pre>';
}

function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $pages));
    return ['total' => $total, 'pages' => $pages, 'page' => $page, 'offset' => ($page - 1) * $perPage];
}

function pagination_links(int $pages, int $page, string $pattern): string
{
    if ($pages <= 1) {
        return '';
    }
    $make = static function (int $p) use ($pattern): string {
        return e(str_replace('{page}', (string) $p, $pattern));
    };
    $html = '<nav class="pagination" aria-label="分页">';
    if ($page > 1) {
        $html .= '<a class="pagination__link" href="' . $make($page - 1) . '" rel="prev">上一页</a>';
    }
    $window = [];
    $window[] = 1;
    for ($i = $page - 2; $i <= $page + 2; $i++) {
        if ($i >= 1 && $i <= $pages) {
            $window[] = $i;
        }
    }
    $window[] = $pages;
    $window = array_values(array_unique($window));
    sort($window);
    $prev = 0;
    foreach ($window as $p) {
        if ($p - $prev > 1) {
            $html .= '<span class="pagination__ellipsis">…</span>';
        }
        $active = $p === $page ? ' pagination__link--active' : '';
        $html .= '<a class="pagination__link' . $active . '" href="' . $make($p) . '">' . $p . '</a>';
        $prev = $p;
    }
    if ($page < $pages) {
        $html .= '<a class="pagination__link" href="' . $make($page + 1) . '" rel="next">下一页</a>';
    }
    $html .= '</nav>';
    return $html;
}

function password_strength_ok(string $password): bool
{
    if (strlen($password) < 8) {
        return false;
    }
    $score = 0;
    if (preg_match('/[a-z]/', $password)) {
        $score++;
    }
    if (preg_match('/[A-Z]/', $password)) {
        $score++;
    }
    if (preg_match('/\d/', $password)) {
        $score++;
    }
    if (preg_match('/[^a-zA-Z0-9]/', $password)) {
        $score++;
    }
    return $score >= 3;
}
