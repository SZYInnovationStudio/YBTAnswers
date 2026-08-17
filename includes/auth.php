<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

final class Auth
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_id']);
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            redirect(url('admin/login.php'));
        }
    }

    public static function login(string $username, string $password): array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return ['ok' => false, 'message' => '请输入账号和密码。'];
        }

        $lock = self::lockInfo($username);
        if ($lock['locked']) {
            return ['ok' => false, 'message' => sprintf('登录失败次数过多，账号已锁定，请 %d 分钟后再试。', $lock['minutes'])];
        }

        try {
            $stmt = db()->prepare('SELECT id, username, password_hash FROM admins WHERE username = :u LIMIT 1');
            $stmt->execute(['u' => $username]);
            $admin = $stmt->fetch();
        } catch (Throwable $ex) {
            app_log('登录查询失败: ' . $ex->getMessage(), 'error');
            return ['ok' => false, 'message' => '系统错误，请稍后再试。'];
        }

        if (!$admin || !password_verify($password, (string) $admin['password_hash'])) {
            self::recordFailure($username);
            $remain = self::MAX_ATTEMPTS - self::failureCount($username) - 1;
            $message = $remain > 0
                ? sprintf('账号或密码错误，剩余 %d 次尝试机会。', $remain)
                : '账号或密码错误，账号已锁定 ' . self::LOCK_MINUTES . ' 分钟。';
            return ['ok' => false, 'message' => $message];
        }

        self::clearFailures($username);
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_username'] = (string) $admin['username'];
        $_SESSION['admin_login_at'] = time();
        return ['ok' => true, 'message' => '登录成功'];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    private static function lockKey(string $username): string
    {
        return 'login_lock_' . md5(strtolower($username));
    }

    private static function lockInfo(string $username): array
    {
        $data = $_SESSION[self::lockKey($username)] ?? null;
        if (!is_array($data) || empty($data['until'])) {
            return ['locked' => false, 'minutes' => 0];
        }
        $remain = (int) $data['until'] - time();
        if ($remain <= 0) {
            unset($_SESSION[self::lockKey($username)]);
            return ['locked' => false, 'minutes' => 0];
        }
        return ['locked' => true, 'minutes' => (int) ceil($remain / 60)];
    }

    private static function failureCount(string $username): int
    {
        $data = $_SESSION[self::lockKey($username)] ?? null;
        return is_array($data) ? (int) ($data['count'] ?? 0) : 0;
    }

    private static function recordFailure(string $username): void
    {
        $key = self::lockKey($username);
        $data = $_SESSION[$key] ?? ['count' => 0, 'until' => 0];
        $data['count'] = (int) $data['count'] + 1;
        if ($data['count'] >= self::MAX_ATTEMPTS) {
            $data['until'] = time() + self::LOCK_MINUTES * 60;
            $data['count'] = 0;
        }
        $_SESSION[$key] = $data;
        app_log(sprintf('管理员登录失败: %s (IP: %s)', $username, $_SERVER['REMOTE_ADDR'] ?? '-'), 'warning');
    }

    private static function clearFailures(string $username): void
    {
        unset($_SESSION[self::lockKey($username)]);
    }
}
