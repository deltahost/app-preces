<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
ensure_ai_schema();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    private_json_response(['ok' => true]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    private_json_response(['error' => 'Método não permitido.'], 405);
}

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) {
    private_json_response(['error' => 'Envie uma solicitação JSON válida.'], 400);
}
$message = trim((string) ($payload['message'] ?? ''));
$installationId = trim((string) ($payload['installationId'] ?? ''));
if (mb_strlen($message) < 3 || mb_strlen($message) > 800) {
    private_json_response(['error' => 'A pergunta deve ter entre 3 e 800 caracteres.'], 422);
}
if (!preg_match('/^[a-zA-Z0-9._:-]{12,160}$/', $installationId)) {
    private_json_response(['error' => 'Identificação do aplicativo inválida.'], 422);
}

$clientIp = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown');
$installationHash = hash('sha256', $installationId . '|' . $clientIp);
$ipHash = hash('sha256', 'ip|' . $clientIp);
$usage = db()->prepare('SELECT request_count FROM bible_chat_usage WHERE installation_hash=? AND usage_date=CURDATE()');
$usage->execute([$installationHash]);
if ((int) ($usage->fetchColumn() ?: 0) >= 1) {
    private_json_response([
        'error' => 'Você já usou sua conversa gratuita de hoje. O acesso é renovado amanhã.',
        'code' => 'FREE_DAILY_LIMIT',
    ], 429);
}
$usage->execute([$ipHash]);
if ((int) ($usage->fetchColumn() ?: 0) >= 20) {
    private_json_response(['error' => 'Muitas conversas foram solicitadas por esta rede hoje. Tente novamente amanhã.', 'code' => 'NETWORK_DAILY_LIMIT'], 429);
}

$systemPrompt = <<<'PROMPT'
Você é um assistente de reflexão cristã em português brasileiro. Responda com acolhimento, clareza e respeito, usando a Bíblia como referência. Não diga que é Deus, Jesus, profeta ou autoridade religiosa e não invente versículos. Quando citar a Bíblia, informe livro, capítulo e versículo; se não tiver certeza da redação literal, faça uma paráfrase e identifique-a como tal. Não julgue nem condene o usuário. Respostas devem ter de 2 a 5 parágrafos curtos e podem terminar com uma breve oração ou ação prática. Para risco imediato, violência, automutilação ou emergência médica, recomende ajuda presencial e serviços de emergência; não substitua orientação médica, psicológica, jurídica ou pastoral profissional.
PROMPT;

$credentials = db()->query("SELECT * FROM api_credentials WHERE provider IN ('openai','gemini') AND is_enabled=1 AND (cooldown_until IS NULL OR cooldown_until <= NOW()) ORDER BY priority, id LIMIT 12")->fetchAll();
if (!$credentials) {
    private_json_response(['error' => 'O serviço de conversa ainda não possui uma chave disponível. Tente novamente mais tarde.', 'code' => 'NO_PROVIDER'], 503);
}

function ai_http_post(string $url, array $headers, array $payload): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('A extensão cURL precisa estar ativa na hospedagem.');
    }
    $responseHeaders = [];
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 35,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_HEADERFUNCTION => static function ($curlHandle, string $line) use (&$responseHeaders): int {
            $length = strlen($line);
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return $length;
        },
    ]);
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);
    if ($body === false) {
        throw new RuntimeException('Falha de conexão com o provedor: ' . $curlError);
    }
    return ['status' => $status, 'headers' => $responseHeaders, 'json' => json_decode($body, true), 'raw' => $body];
}

function openai_answer(string $apiKey, string $systemPrompt, string $message): array
{
    $model = (string) config('app', 'openai_model', 'gpt-4o-mini');
    $response = ai_http_post('https://api.openai.com/v1/responses', [
        'Authorization: Bearer ' . $apiKey,
    ], [
        'model' => $model,
        'instructions' => $systemPrompt,
        'input' => $message,
        'max_output_tokens' => 700,
    ]);
    $answer = '';
    if (is_array($response['json']['output'] ?? null)) {
        foreach ($response['json']['output'] as $output) {
            foreach (($output['content'] ?? []) as $content) {
                if (($content['type'] ?? '') === 'output_text') {
                    $answer .= (string) ($content['text'] ?? '');
                }
            }
        }
    }
    $response['answer'] = trim($answer);
    return $response;
}

function gemini_answer(string $apiKey, string $systemPrompt, string $message): array
{
    $model = rawurlencode((string) config('app', 'gemini_model', 'gemini-3.8-flash'));
    $response = ai_http_post('https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent', [
        'x-goog-api-key: ' . $apiKey,
    ], [
        'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
        'contents' => [['role' => 'user', 'parts' => [['text' => $message]]]],
        'generationConfig' => ['maxOutputTokens' => 700, 'temperature' => 0.7],
    ]);
    $answer = '';
    foreach (($response['json']['candidates'][0]['content']['parts'] ?? []) as $part) {
        $answer .= (string) ($part['text'] ?? '');
    }
    $response['answer'] = trim($answer);
    return $response;
}

function provider_error_message(array $response): string
{
    $message = $response['json']['error']['message'] ?? $response['json']['message'] ?? '';
    return mb_strimwidth('HTTP ' . $response['status'] . ($message !== '' ? ' · ' . $message : ''), 0, 490, '…');
}

function credential_cooldown(array $response): int
{
    $status = (int) $response['status'];
    $retryAfter = (int) ($response['headers']['retry-after'] ?? 0);
    if ($retryAfter > 0) {
        return min(86400, max(60, $retryAfter));
    }
    if (in_array($status, [401, 402, 403], true)) return 86400;
    if ($status === 429) return 900;
    if ($status >= 500) return 180;
    return 3600;
}

$attempted = 0;
foreach ($credentials as $credential) {
    $attempted++;
    try {
        $apiKey = decrypt_credential((string) $credential['encrypted_key']);
        $response = $credential['provider'] === 'openai'
            ? openai_answer($apiKey, $systemPrompt, $message)
            : gemini_answer($apiKey, $systemPrompt, $message);
        if ((int) $response['status'] >= 200 && (int) $response['status'] < 300 && $response['answer'] !== '') {
            db()->prepare('UPDATE api_credentials SET cooldown_until=NULL,failure_count=0,last_error=NULL,last_used_at=NOW() WHERE id=?')->execute([$credential['id']]);
            db()->prepare('INSERT INTO bible_chat_usage (installation_hash,usage_date,request_count) VALUES (?,CURDATE(),1) ON DUPLICATE KEY UPDATE request_count=request_count+1')->execute([$installationHash]);
            db()->prepare('INSERT INTO bible_chat_usage (installation_hash,usage_date,request_count) VALUES (?,CURDATE(),1) ON DUPLICATE KEY UPDATE request_count=request_count+1')->execute([$ipHash]);
            private_json_response(['ok' => true, 'answer' => $response['answer'], 'provider' => $credential['provider']]);
        }
        $error = provider_error_message($response);
        $seconds = credential_cooldown($response);
        $cooldownUntil = date('Y-m-d H:i:s', time() + $seconds);
        db()->prepare('UPDATE api_credentials SET cooldown_until=?,failure_count=failure_count+1,last_error=?,last_used_at=NOW() WHERE id=?')->execute([$cooldownUntil, $error, $credential['id']]);
    } catch (Throwable $error) {
        db()->prepare('UPDATE api_credentials SET cooldown_until=DATE_ADD(NOW(), INTERVAL 1 HOUR),failure_count=failure_count+1,last_error=?,last_used_at=NOW() WHERE id=?')->execute([mb_strimwidth($error->getMessage(), 0, 490, '…'), $credential['id']]);
    }
}

private_json_response([
    'error' => 'Os provedores de conversa estão temporariamente indisponíveis. Tente novamente em alguns minutos.',
    'code' => 'PROVIDERS_UNAVAILABLE',
    'attempted' => $attempted,
], 503);
