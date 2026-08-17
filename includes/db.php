<?php

declare(strict_types=1);

final class Database
{
    private static ?Database $instance = null;
    private ?PDO $connection = null;

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            if (!defined('DB_HOST')) {
                throw new RuntimeException('数据库尚未配置，请先运行 install.php 完成安装。');
            }
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                DB_HOST,
                defined('DB_PORT') ? DB_PORT : 3306,
                DB_NAME
            );
            try {
                $this->connection = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ]);
            } catch (PDOException $ex) {
                app_log('数据库连接失败: ' . $ex->getMessage(), 'error');
                throw new RuntimeException('数据库连接失败，请检查配置。');
            }
        }
        return $this->connection;
    }
}

function db(): PDO
{
    return Database::getInstance()->getConnection();
}
