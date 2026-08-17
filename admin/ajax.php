<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/scraper.php';
require_once dirname(__DIR__) . '/includes/api_client.php';
require_once dirname(__DIR__) . '/includes/tree_cache.php';

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => '仅支持 POST 请求。'], 405);
}

if (!csrf_verify()) {
    json_response(['ok' => false, 'message' => 'CSRF 校验失败，请刷新页面。'], 419);
}

$action = (string) ($_POST['action'] ?? '');

try {
    switch ($action) {
        case 'chapter_create':
        case 'chapter_update':
            handleChapterSave($action === 'chapter_update');
            break;
        case 'chapter_move':
            handleChapterMove();
            break;
        case 'problem_generate':
            handleProblemGenerate();
            break;
        case 'fetch_one':
            handleFetchOne();
            break;
        case 'test_api':
            handleTestApi();
            break;
        default:
            json_response(['ok' => false, 'message' => '未知操作。'], 400);
    }
} catch (Throwable $ex) {
    app_log('ajax 操作失败 [' . $action . ']: ' . $ex->getMessage(), 'error');
    json_response(['ok' => false, 'message' => '操作失败：' . $ex->getMessage()], 500);
}

function handleChapterSave(bool $isUpdate): void
{
    $name = str_input((string) ($_POST['name'] ?? ''));
    $subpartId = int_input($_POST['subpart_id'] ?? 0);

    if ($name === '' || $subpartId <= 0) {
        json_response(['ok' => false, 'message' => '请填写章节名称并选择所属小部分。']);
    }

    $pdo = db();
    if ($isUpdate) {
        $id = int_input($_POST['id'] ?? 0);
        if ($id <= 0) {
            json_response(['ok' => false, 'message' => '参数错误。']);
        }
        $pdo->prepare('UPDATE chapters SET name = :name, subpart_id = :subpart_id WHERE id = :id')
            ->execute(['name' => $name, 'subpart_id' => $subpartId, 'id' => $id]);
        TreeCache::clear();
        json_response(['ok' => true, 'message' => '章节已更新。']);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO chapters (subpart_id, name, sort_order)
         VALUES (:subpart_id, :name, (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM (SELECT sort_order FROM chapters WHERE subpart_id = :sid2) t))'
    );
    $stmt->execute(['subpart_id' => $subpartId, 'name' => $name, 'sid2' => $subpartId]);
    TreeCache::clear();
    json_response(['ok' => true, 'message' => '章节已添加。']);
}

function handleChapterMove(): void
{
    $id = int_input($_POST['id'] ?? 0);
    $dir = $_POST['dir'] === 'down' ? 'down' : 'up';

    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, subpart_id, sort_order FROM chapters WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $chapter = $stmt->fetch();
    if (!$chapter) {
        json_response(['ok' => false, 'message' => '章节不存在。']);
    }

    $op = $dir === 'up' ? '<' : '>';
    $order = $dir === 'up' ? 'DESC' : 'ASC';
    $neighborStmt = $pdo->prepare(
        "SELECT id, sort_order FROM chapters
         WHERE subpart_id = :sid AND sort_order $op :so
         ORDER BY sort_order $order, id $order LIMIT 1"
    );
    $neighborStmt->execute(['sid' => $chapter['subpart_id'], 'so' => $chapter['sort_order']]);
    $neighbor = $neighborStmt->fetch();

    if ($neighbor) {
        $pdo->prepare('UPDATE chapters SET sort_order = :so WHERE id = :id')
            ->execute(['so' => $neighbor['sort_order'], 'id' => $id]);
        $pdo->prepare('UPDATE chapters SET sort_order = :so WHERE id = :id')
            ->execute(['so' => $chapter['sort_order'], 'id' => $neighbor['id']]);
    }
    TreeCache::clear();
    json_response(['ok' => true, 'message' => '已移动。']);
}

function handleProblemGenerate(): void
{
    $id = int_input($_POST['id'] ?? 0);
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM problems WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $id]);
    $problem = $stmt->fetch();
    if (!$problem) {
        json_response(['ok' => false, 'message' => '题目不存在。']);
    }

    $client = new AiClient();
    $result = $client->generateAnswer($problem);
    if (!$result['ok']) {
        json_response(['ok' => false, 'message' => $result['message']]);
    }

    $pdo->prepare('UPDATE problems SET answer_code = :code, is_answer_manual = 0, updated_at = NOW() WHERE id = :id')
        ->execute(['code' => $result['code'], 'id' => $id]);
    json_response(['ok' => true, 'message' => '答案已重新生成。']);
}

function handleFetchOne(): void
{
    $target = str_input((string) ($_POST['target'] ?? ''));
    $chapterId = int_input($_POST['chapter_id'] ?? 0);
    $generate = !empty($_POST['generate']) && $_POST['generate'] !== '0';

    if ($target === '' || $chapterId <= 0) {
        json_response(['ok' => false, 'message' => '请输入题目链接/题号并选择章节。']);
    }

    $checkStmt = db()->prepare('SELECT id FROM chapters WHERE id = :id LIMIT 1');
    $checkStmt->execute(['id' => $chapterId]);
    if (!$checkStmt->fetch()) {
        json_response(['ok' => false, 'message' => '所选章节不存在。']);
    }

    $scraper = new Scraper();
    $fetchResult = $scraper->fetchProblem($target);
    if (!$fetchResult['ok']) {
        json_response(['ok' => false, 'message' => $fetchResult['message']]);
    }

    $data = $fetchResult['data'];
    $data['chapter_id'] = $chapterId;

    try {
        $problemId = upsertProblem($data);
    } catch (Throwable $ex) {
        app_log('抓取入库失败: ' . $ex->getMessage(), 'error');
        json_response(['ok' => false, 'message' => '数据保存失败：' . $ex->getMessage()]);
    }

    $generated = false;
    if ($generate) {
        $client = new AiClient();
        $stmt = db()->prepare('SELECT * FROM problems WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $problemId]);
        $problem = $stmt->fetch();
        if ($problem) {
            $genResult = $client->generateAnswer($problem);
            if ($genResult['ok']) {
                db()->prepare('UPDATE problems SET answer_code = :code, is_answer_manual = 0, updated_at = NOW() WHERE id = :id')
                    ->execute(['code' => $genResult['code'], 'id' => $problemId]);
                $generated = true;
            } else {
                TreeCache::clear();
                json_response([
                    'ok' => true,
                    'pid' => $data['pid'],
                    'title' => $data['title'],
                    'generated' => false,
                    'message' => '抓取成功，但答案生成失败：' . $genResult['message'],
                ]);
            }
        }
    }

    TreeCache::clear();
    json_response([
        'ok' => true,
        'pid' => $data['pid'],
        'title' => $data['title'],
        'generated' => $generated,
        'message' => '抓取成功。',
    ]);
}

function upsertProblem(array $data): int
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM problems WHERE pid = :pid LIMIT 1');
    $stmt->execute(['pid' => $data['pid']]);
    $existing = $stmt->fetch();

    if ($existing) {
        $pdo->prepare(
            'UPDATE problems SET title = :title, chapter_id = :chapter_id,
             time_limit = :time_limit, memory_limit = :memory_limit,
             description = :description, input_desc = :input_desc, output_desc = :output_desc,
             input_sample = :input_sample, output_sample = :output_sample,
             source_url = :source_url, deleted_at = NULL, updated_at = NOW()
             WHERE id = :id'
        )->execute([
            'title' => $data['title'],
            'chapter_id' => $data['chapter_id'],
            'time_limit' => $data['time_limit'],
            'memory_limit' => $data['memory_limit'],
            'description' => $data['description'],
            'input_desc' => $data['input_desc'],
            'output_desc' => $data['output_desc'],
            'input_sample' => $data['input_sample'],
            'output_sample' => $data['output_sample'],
            'source_url' => $data['source_url'],
            'id' => $existing['id'],
        ]);
        return (int) $existing['id'];
    }

    $pdo->prepare(
        'INSERT INTO problems (pid, title, chapter_id, time_limit, memory_limit,
         description, input_desc, output_desc, input_sample, output_sample,
         source_url, answer_code, is_answer_manual, created_at, updated_at)
         VALUES (:pid, :title, :chapter_id, :time_limit, :memory_limit,
         :description, :input_desc, :output_desc, :input_sample, :output_sample,
         :source_url, "", 0, NOW(), NOW())'
    )->execute([
        'pid' => $data['pid'],
        'title' => $data['title'],
        'chapter_id' => $data['chapter_id'],
        'time_limit' => $data['time_limit'],
        'memory_limit' => $data['memory_limit'],
        'description' => $data['description'],
        'input_desc' => $data['input_desc'],
        'output_desc' => $data['output_desc'],
        'input_sample' => $data['input_sample'],
        'output_sample' => $data['output_sample'],
        'source_url' => $data['source_url'],
    ]);
    return (int) $pdo->lastInsertId();
}

function handleTestApi(): void
{
    $client = new AiClient();
    $result = $client->testConnection();
    json_response($result);
}
