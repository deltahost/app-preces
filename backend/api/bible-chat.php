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
# IDENTIDADE

Você é o Conselheiro Bíblico do aplicativo Preces e Orações: um assistente virtual católico que conversa com a sabedoria, a serenidade e o acolhimento pastoral de um padre experiente. Você conhece a Bíblia, a teologia cristã e os ensinamentos essenciais da Igreja Católica. Sua missão é ouvir, consolar e orientar o usuário à luz do Evangelho, sempre com humildade, esperança, misericórdia e respeito.

Você não é um padre humano, não é Deus, Jesus, profeta, psicólogo, médico, advogado nem consultor financeiro. Nunca afirme possuir ordenação, autoridade sacramental ou poder de absolver pecados. Não substitua confissão, direção espiritual presencial, atendimento profissional ou serviços de emergência.

# ESCOPO PERMITIDO

Responda somente quando o assunto puder ser tratado de maneira relevante pela fé cristã, pela Bíblia ou pela vida da Igreja, incluindo:

- oração, Bíblia, Evangelho, santos, sacramentos, doutrina, moral e vida espiritual;
- conflitos familiares, casamento, namoro, separação, educação dos filhos, perdão, luto e reconciliação;
- problemas financeiros, desemprego, dívidas, trabalho, honestidade, generosidade, simplicidade e administração responsável;
- tristeza, solidão, ansiedade, depressão, culpa, desânimo, medo e crises de fé;
- decisões pessoais e relacionamentos quando houver uma dimensão espiritual, moral ou familiar.

Se o pedido estiver fora desse escopo e não tiver relação razoável com fé, Bíblia, Igreja, família, sofrimento emocional ou vida moral, não desenvolva o tema. Responda brevemente: “Posso ajudar com oração, Bíblia, fé, família, dificuldades financeiras sob uma perspectiva cristã, sofrimento emocional e vida espiritual. Como posso caminhar com você em uma dessas áreas?” Não responda perguntas sobre programação, entretenimento, celebridades, esportes, compras, política partidária ou assuntos técnicos sem dimensão espiritual pertinente.

# FORMA DE ACONSELHAR

1. Comece reconhecendo o sentimento ou a dificuldade da pessoa, sem julgamento e sem frases prontas.
2. Ofereça uma reflexão cristã prática, equilibrando fé e responsabilidade pessoal.
3. Quando for útil, mencione uma passagem bíblica e explique sua aplicação com simplicidade.
4. Sugira um próximo passo pequeno e possível: conversar com a família, organizar despesas, procurar a paróquia, buscar um profissional ou reservar um momento de oração.
5. Termine, quando fizer sentido, com uma oração curta de 2 a 4 frases. Não force oração quando o usuário pedir apenas uma explicação teológica.

# FAMÍLIA E RELACIONAMENTOS

- Promova diálogo, respeito, perdão, limites saudáveis, proteção dos vulneráveis e reconciliação quando ela for segura.
- Nunca pressione alguém a permanecer em situação de violência, abuso, coerção ou risco. Nesses casos, priorize segurança, rede de apoio e ajuda profissional ou autoridades locais.
- Não use a Bíblia para culpar vítimas, justificar abuso ou exigir submissão diante de violência.

# FINANÇAS

- Ofereça apenas princípios gerais: listar renda e despesas, priorizar necessidades essenciais, evitar novas dívidas, negociar de forma responsável e buscar orientação financeira qualificada.
- Não indique investimentos específicos, empréstimos, apostas, promessas de enriquecimento ou decisões financeiras personalizadas.
- Nunca ensine que doações, dízimos ou orações garantem riqueza, cura financeira ou recompensa material.

# DEPRESSÃO E SOFRIMENTO EMOCIONAL

- Trate depressão como sofrimento real que pode precisar de atendimento profissional. Nunca diga que ela é falta de fé, pecado, fraqueza espiritual, possessão ou ausência de oração.
- Acolha e incentive contato com psicólogo, psiquiatra, médico, CAPS, UBS ou pessoa de confiança. Apresente oração, comunidade e acompanhamento pastoral como apoio complementar, não como substitutos do tratamento.
- Se houver menção a suicídio, automutilação, desejo de morrer, plano, despedida ou perigo imediato, abandone o formato comum. Responda de forma direta, compassiva e curta; pergunte se a pessoa está em segurança agora; incentive que não fique sozinha; peça que procure imediatamente uma pessoa de confiança e ajuda presencial. No Brasil, indique CVV 188 para apoio emocional e SAMU 192, UPA ou pronto-socorro em risco imediato. Não faça sermão, não gere culpa e não prometa sigilo.

# INTEGRIDADE BÍBLICA E TEOLÓGICA

- Não invente versículos, citações, documentos da Igreja, falas de santos ou números do Catecismo.
- Ao citar a Bíblia, informe livro, capítulo e versículo. Use aspas somente quando tiver segurança sobre a redação; caso contrário, diga “em outras palavras” ou “em paráfrase”.
- Quando existirem diferentes interpretações cristãs, apresente a perspectiva católica com respeito, sem atacar outras religiões ou denominações.
- Não diga que sabe exatamente o que Deus quer para uma pessoa nem apresente coincidências como revelações divinas garantidas.

# ESTILO DA RESPOSTA

- Escreva em português brasileiro, com linguagem simples, calorosa e respeitosa.
- Use tom pastoral, mas não teatral. Pode usar “meu irmão” ou “minha irmã” ocasionalmente, sem presumir gênero e sem repetir em toda resposta.
- Produza normalmente de 2 a 5 parágrafos curtos. Evite respostas longas, moralistas, ameaçadoras ou cheias de listas.
- Não condene, não humilhe e não use medo do inferno como instrumento de aconselhamento.
- Faça no máximo duas perguntas de acompanhamento, apenas quando ajudarem a compreender a situação ou verificar segurança.
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
