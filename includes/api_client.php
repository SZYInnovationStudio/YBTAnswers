<?php

declare(strict_types=1);

require_once __DIR__ . '/crypto.php';

final class AiClient
{
    private string $apiKey;
    private string $model;
    private string $endpoint;
    private int $timeout = 60;

    public function __construct()
    {
        $this->apiKey = Crypto::decrypt(setting_get('deepseek_api_key'));
        $this->model = setting_get('deepseek_model', 'deepseek-v4-flash');
        $this->endpoint = rtrim(setting_get('deepseek_endpoint', 'https://api.deepseek.com'), '/');
    }

    public function hasKey(): bool
    {
        return $this->apiKey !== '';
    }

    public function maskedKey(): string
    {
        return Crypto::mask($this->apiKey);
    }

    public function generateAnswer(array $problem): array
    {
        $systemPrompt = '你是一个专业的 C++ 算法竞赛教练，请根据用户提供的题目信息，'
            . '编写能够通过该题目的完整 C++ 代码。代码应包含必要的头文件、使用标准命名空间、'
            . '读取输入并正确输出结果。只输出代码，不要额外解释。';

        $userPrompt = sprintf(
            "题目编号：%s\n题目标题：%s\n时间限制：%s\n内存限制：%s\n\n【题目描述】\n%s\n\n【输入说明】\n%s\n\n【输出说明】\n%s\n\n【输入样例】\n%s\n\n【输出样例】\n%s\n\n请编写能够通过该题目的完整 C++ 代码，只输出代码。",
            $problem['pid'] ?? '',
            $problem['title'] ?? '',
            $problem['time_limit'] ?? '',
            $problem['memory_limit'] ?? '',
            self::stripHtml($problem['description'] ?? ''),
            self::stripHtml($problem['input_desc'] ?? ''),
            self::stripHtml($problem['output_desc'] ?? ''),
            $problem['input_sample'] ?? '',
            $problem['output_sample'] ?? ''
        );

        $result = $this->chat($systemPrompt, $userPrompt);
        if (!$result['ok']) {
            return $result;
        }
        return ['ok' => true, 'code' => self::cleanCode($result['content'])];
    }

    public function testConnection(): array
    {
        if (!$this->hasKey()) {
            return ['ok' => false, 'message' => '尚未配置 API Key。'];
        }
        $result = $this->chat('You are a helpful assistant.', 'Reply with the single word: pong', 15);
        if (!$result['ok']) {
            return $result;
        }
        return ['ok' => true, 'message' => '连接成功，模型响应正常。'];
    }

    private function chat(string $system, string $user, ?int $timeout = null): array
    {
        if (!$this->hasKey()) {
            return ['ok' => false, 'message' => '尚未配置 AI API Key，请先在系统设置中填写。'];
        }

        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'temperature' => 0.2,
        ];

        $ch = curl_init($this->endpoint . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => $timeout ?? $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            app_log('AI API 网络错误: ' . $curlError, 'error');
            return ['ok' => false, 'message' => '网络请求失败：' . $curlError];
        }

        $data = json_decode((string) $response, true);

        if ($httpCode !== 200) {
            $apiMessage = $data['error']['message'] ?? ('HTTP ' . $httpCode);
            app_log('AI API 返回错误 HTTP ' . $httpCode . ': ' . $apiMessage, 'error');
            return ['ok' => false, 'message' => 'API 调用失败：' . $apiMessage];
        }

        $content = $data['choices'][0]['message']['content'] ?? '';
        if (!is_string($content) || trim($content) === '') {
            return ['ok' => false, 'message' => 'API 返回内容为空。'];
        }
        return ['ok' => true, 'content' => $content];
    }

    public static function cleanCode(string $content): string
    {
        $code = trim($content);
        if (preg_match('/```(?:cpp|c\+\+|c|cc)?\s*\n([\s\S]*?)```/i', $code, $m)) {
            $code = $m[1];
        } else {
            $code = preg_replace('/^```[a-z+\-]*\s*/i', '', $code) ?? $code;
            $code = preg_replace('/```\s*$/', '', $code) ?? $code;
        }
        return trim($code);
    }

    private static function stripHtml(string $html): string
    {
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim($text);
    }
}
