<?php

declare(strict_types=1);

final class TreeCache
{
    private static function cacheFile(): string
    {
        $dir = defined('CACHE_PATH') ? CACHE_PATH : dirname(__DIR__) . '/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/sidebar_tree.php';
    }

    public static function getTree(): array
    {
        $file = self::cacheFile();
        if (is_file($file)) {
            $data = @include $file;
            if (is_array($data)) {
                return $data;
            }
        }
        $tree = self::buildTree();
        self::save($tree);
        return $tree;
    }

    public static function clear(): void
    {
        $file = self::cacheFile();
        if (is_file($file)) {
            @unlink($file);
        }
    }

    private static function save(array $tree): void
    {
        $content = "<?php\n// 侧边栏树缓存，由系统自动生成，请勿手动修改。\nreturn "
            . var_export($tree, true) . ";\n";
        @file_put_contents(self::cacheFile(), $content, LOCK_EX);
    }

    private static function buildTree(): array
    {
        $pdo = db();

        $parts = $pdo->query('SELECT id, name FROM parts ORDER BY sort_order, id')->fetchAll();
        $subparts = $pdo->query('SELECT id, part_id, name FROM subparts ORDER BY sort_order, id')->fetchAll();
        $chapters = $pdo->query('SELECT id, subpart_id, name FROM chapters ORDER BY sort_order, id')->fetchAll();
        $problems = $pdo->query(
            'SELECT id, chapter_id, pid, title FROM problems WHERE deleted_at IS NULL ORDER BY pid'
        )->fetchAll();

        $subsByPart = [];
        foreach ($subparts as $sub) {
            $subsByPart[(int) $sub['part_id']][] = $sub;
        }
        $chaptersBySub = [];
        foreach ($chapters as $chapter) {
            $chaptersBySub[(int) $chapter['subpart_id']][] = $chapter;
        }
        $problemsByChapter = [];
        foreach ($problems as $problem) {
            $problemsByChapter[(int) $problem['chapter_id']][] = $problem;
        }

        $tree = [];
        foreach ($parts as $part) {
            $partNode = [
                'id' => (int) $part['id'],
                'name' => (string) $part['name'],
                'subparts' => [],
            ];
            foreach ($subsByPart[(int) $part['id']] ?? [] as $sub) {
                $subNode = [
                    'id' => (int) $sub['id'],
                    'name' => (string) $sub['name'],
                    'chapters' => [],
                ];
                foreach ($chaptersBySub[(int) $sub['id']] ?? [] as $chapter) {
                    $chapterNode = [
                        'id' => (int) $chapter['id'],
                        'name' => (string) $chapter['name'],
                        'problems' => [],
                    ];
                    foreach ($problemsByChapter[(int) $chapter['id']] ?? [] as $problem) {
                        $chapterNode['problems'][] = [
                            'pid' => (string) $problem['pid'],
                            'title' => (string) $problem['title'],
                        ];
                    }
                    $subNode['chapters'][] = $chapterNode;
                }
                $partNode['subparts'][] = $subNode;
            }
            $tree[] = $partNode;
        }
        return $tree;
    }
}
