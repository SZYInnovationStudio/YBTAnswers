<?php

declare(strict_types=1);

final class Scraper
{
    private int $timeout = 30;

    public static function buildUrl(string $pid): string
    {
        return SOURCE_SITE . '/problem_show.php?pid=' . urlencode($pid);
    }

    public function fetchProblem(string $input): array
    {
        $pid = $this->extractPid($input);
        if ($pid === '') {
            return ['ok' => false, 'message' => '无法识别题号，请输入形如 https://ybt.ssoier.cn/problem_show.php?pid=1000 的链接或 4 位题号。'];
        }

        $url = self::buildUrl($pid);
        $html = $this->fetchPage($url);
        if ($html === null) {
            return ['ok' => false, 'message' => '抓取页面失败，请检查网络或稍后重试。'];
        }

        try {
            $data = $this->parsePage($html, $pid);
        } catch (Throwable $ex) {
            app_log('解析题目 ' . $pid . ' 失败: ' . $ex->getMessage(), 'error');
            return ['ok' => false, 'message' => '页面解析失败：' . $ex->getMessage()];
        }

        $data['source_url'] = $url;
        return ['ok' => true, 'data' => $data];
    }

    public function extractPid(string $input): string
    {
        $input = trim($input);
        if (preg_match('/[?&]pid=(\d{3,5})/', $input, $m)) {
            return $m[1];
        }
        if (preg_match('/^(\d{3,5})$/', $input, $m)) {
            return $m[1];
        }
        return '';
    }

    private function fetchPage(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
                . '(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: zh-CN,zh;q=0.9',
                'Referer: ' . SOURCE_SITE . '/',
            ],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $code >= 400) {
            return null;
        }
        $html = (string) $body;
        if (!preg_match('//u', $html)) {
            $converted = @iconv('GBK', 'UTF-8//IGNORE', $html);
            if ($converted !== false) {
                $html = $converted;
            }
        }
        return $html;
    }

    public function parsePage(string $html, string $fallbackPid): array
    {
        $title = '';
        if (preg_match('/<h3[^>]*>([\s\S]*?)<\/h3>/i', $html, $m)) {
            $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $pid = $fallbackPid;
        if ($title !== '' && preg_match('/^(\d{3,5})\s*[：:]\s*(.+)$/u', $title, $m)) {
            $pid = $m[1];
            $title = trim($m[2]);
        }
        if ($title === '') {
            throw new RuntimeException('未找到题目标题（h3），页面结构可能已变化。');
        }

        $timeLimit = '';
        $memoryLimit = '';
        if (preg_match('/时间限制\s*[：:]\s*([\d,]+\s*ms)/u', $html, $m)) {
            $timeLimit = trim($m[1]);
        }
        if (preg_match('/内存限制\s*[：:]\s*([\d,]+\s*KB)/iu', $html, $m)) {
            $memoryLimit = trim($m[1]);
        }

        $pshowContents = $this->extractPshow($html);
        $description = $pshowContents[0] ?? '';
        $inputDesc = $pshowContents[1] ?? '';
        $outputDesc = $pshowContents[2] ?? '';

        if ($description === '') {
            throw new RuntimeException('未能从页面提取题目描述（pshow），页面结构可能已变化。');
        }

        $pres = $this->extractPreBlocks($html);
        $inputSample = $pres[0] ?? '';
        $outputSample = $pres[1] ?? '';

        return [
            'pid' => $pid,
            'title' => $title,
            'time_limit' => $timeLimit,
            'memory_limit' => $memoryLimit,
            'description' => $this->sanitizeHtml($description),
            'input_desc' => $this->sanitizeHtml($inputDesc),
            'output_desc' => $this->sanitizeHtml($outputDesc),
            'input_sample' => $inputSample,
            'output_sample' => $outputSample,
        ];
    }

    private function extractPshow(string $html): array
    {
        $results = [];
        if (preg_match_all('/pshow\s*\(\s*((?:"(?:[^"\\\\]|\\\\.)*")|(?:\'(?:[^\'\\\\]|\\\\.)*\'))\s*\)/s', $html, $matches)) {
            foreach ($matches[1] as $raw) {
                $results[] = $this->decodeJsString($raw);
            }
            return $results;
        }
        if (preg_match_all('/pshow\s*\(\s*unescape\s*\(\s*"([^"]*)"\s*\)\s*\)/s', $html, $matches)) {
            foreach ($matches[1] as $raw) {
                $decoded = rawurldecode($raw);
                $results[] = $decoded;
            }
        }
        return $results;
    }

    private function decodeJsString(string $quoted): string
    {
        $body = substr($quoted, 1, -1);
        $body = preg_replace_callback(
            '/\\\\u([0-9a-fA-F]{4})/',
            static fn(array $m): string => mb_convert_encoding(pack('H*', $m[1]), 'UTF-8', 'UTF-16BE'),
            $body
        ) ?? $body;
        $body = preg_replace_callback(
            '/\\\\x([0-9a-fA-F]{2})/',
            static fn(array $m): string => chr((int) hexdec($m[1])),
            $body
        ) ?? $body;
        $map = [
            '\\n' => "\n",
            '\\r' => "\r",
            '\\t' => "\t",
            '\\"' => '"',
            "\\'" => "'",
            '\\\\' => '\\',
            '\\/' => '/',
        ];
        return strtr($body, $map);
    }

    private function extractPreBlocks(string $html): array
    {
        $results = [];
        if (preg_match_all('/<pre[^>]*>([\s\S]*?)<\/pre>/i', $html, $matches)) {
            foreach ($matches[1] as $block) {
                $text = preg_replace('/<br\s*\/?>/i', "\n", $block) ?? $block;
                $text = strip_tags($text);
                $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $results[] = trim($text);
            }
        }
        return $results;
    }

    private function sanitizeHtml(string $html): string
    {
        $html = preg_replace('/<script[\s\S]*?<\/script>/i', '', $html) ?? $html;
        $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/javascript\s*:/i', '', $html) ?? $html;
        $html = preg_replace('/(src|href)\s*=\s*(["\'])\/(?!\/)/i', '$1=$2' . SOURCE_SITE . '/', $html) ?? $html;
        return trim($html);
    }
}
