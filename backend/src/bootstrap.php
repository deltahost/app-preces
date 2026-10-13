<?php

declare(strict_types=1);

$configPath = dirname(__DIR__) . '/config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    exit('Configuração ausente. Copie config.example.php para config.php e informe o banco.');
}

$config = require $configPath;

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_name('preces_admin');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $isHttps,
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function config(string $section, ?string $key = null, mixed $default = null): mixed
{
    global $config;
    $value = $config[$section] ?? $default;
    return $key === null ? $value : ($value[$key] ?? $default);
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $db = config('database');
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], $db['port'], $db['name'], $db['charset']);
    $pdo = new PDO($dsn, $db['user'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Sessão expirada. Atualize a página e tente novamente.');
    }
}

function require_admin(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function slugify(string $value): string
{
    $value = trim(mb_strtolower($value, 'UTF-8'));
    $value = strtr($value, [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n',
    ]);
    $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $value = $converted !== false ? $converted : $value;
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-') ?: 'conteudo-' . time();
}

function ensure_media_schema(): void
{
    db()->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS media_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    description VARCHAR(500) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_media_categories_public (is_active, sort_order)
) ENGINE=InnoDB
SQL);

    db()->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS media_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NULL,
    type VARCHAR(16) NOT NULL,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    description TEXT NULL,
    source_url VARCHAR(2048) NOT NULL,
    thumbnail_url VARCHAR(2048) NULL,
    duration_label VARCHAR(30) NULL,
    is_premium TINYINT(1) NOT NULL DEFAULT 0,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    published_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_media_category FOREIGN KEY (category_id) REFERENCES media_categories(id) ON DELETE SET NULL,
    INDEX idx_media_public (type, is_published, published_at, sort_order),
    INDEX idx_media_category (category_id, is_published)
) ENGINE=InnoDB
SQL);
}

function ensure_prayer_audio_schema(): void
{
    $statement = db()->query("SHOW COLUMNS FROM prayers LIKE 'audio_url'");
    if (!$statement->fetch()) {
        db()->exec('ALTER TABLE prayers ADD COLUMN audio_url VARCHAR(2048) NULL AFTER image_url');
    }
}

function ensure_ai_schema(): void
{
    db()->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS api_credentials (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(24) NOT NULL,
    label VARCHAR(120) NOT NULL,
    encrypted_key MEDIUMTEXT NOT NULL,
    key_suffix VARCHAR(8) NOT NULL,
    priority INT NOT NULL DEFAULT 100,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    cooldown_until DATETIME NULL,
    failure_count INT NOT NULL DEFAULT 0,
    last_error VARCHAR(500) NULL,
    last_used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_api_credentials_rotation (provider, is_enabled, cooldown_until, priority)
) ENGINE=InnoDB
SQL);

    db()->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS bible_chat_usage (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    installation_hash CHAR(64) NOT NULL,
    usage_date DATE NOT NULL,
    request_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_bible_chat_daily (installation_hash, usage_date)
) ENGINE=InnoDB
SQL);
}

function credential_cipher_key(): string
{
    $secret = trim((string) config('app', 'secret_key', ''));
    if ($secret === '') {
        // Compatibilidade com instalações existentes. Recomenda-se definir app.secret_key no config.php.
        $secret = (string) config('app', 'setup_token', '') . '|' . (string) config('database', 'password', '');
    }
    if (strlen($secret) < 24) {
        throw new RuntimeException('Defina app.secret_key no config.php com pelo menos 24 caracteres aleatórios.');
    }
    return hash('sha256', $secret, true);
}

function encrypt_credential(string $plainText): string
{
    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('A extensão OpenSSL precisa estar ativa na hospedagem.');
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipherText = openssl_encrypt($plainText, 'aes-256-gcm', credential_cipher_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipherText === false) {
        throw new RuntimeException('Não foi possível proteger a chave.');
    }
    return base64_encode($iv . $tag . $cipherText);
}

function decrypt_credential(string $payload): string
{
    $decoded = base64_decode($payload, true);
    if ($decoded === false || strlen($decoded) < 29) {
        throw new RuntimeException('Chave armazenada inválida. Cadastre-a novamente.');
    }
    $iv = substr($decoded, 0, 12);
    $tag = substr($decoded, 12, 16);
    $plainText = openssl_decrypt(substr($decoded, 28), 'aes-256-gcm', credential_cipher_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($plainText === false) {
        throw new RuntimeException('Não foi possível abrir a chave. Verifique app.secret_key.');
    }
    return $plainText;
}

function store_audio_upload(string $field = 'audio_file'): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('O envio do áudio falhou. Verifique o tamanho permitido pela hospedagem.');
    }
    if ((int) $_FILES[$field]['size'] <= 0) {
        throw new RuntimeException('O arquivo de áudio está vazio. Converta novamente ou escolha outro arquivo.');
    }
    if ((int) $_FILES[$field]['size'] > 25 * 1024 * 1024) {
        throw new RuntimeException('O áudio deve ter no máximo 25 MB.');
    }
    $extension = strtolower(pathinfo((string) $_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['mp3', 'm4a', 'aac', 'wav', 'ogg'], true)) {
        throw new RuntimeException('Formato de áudio inválido. Use MP3, M4A, AAC, WAV ou OGG.');
    }
    $directory = dirname(__DIR__) . '/uploads/audio';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Não foi possível criar a pasta de áudios.');
    }
    $filename = date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
    $destination = $directory . '/' . $filename;
    if (!move_uploaded_file((string) $_FILES[$field]['tmp_name'], $destination)) {
        throw new RuntimeException('Não foi possível salvar o arquivo de áudio.');
    }
    if (!is_file($destination) || filesize($destination) <= 0) {
        @unlink($destination);
        throw new RuntimeException('O áudio chegou vazio ao servidor. Converta novamente e repita o envio.');
    }
    return rtrim((string) config('app', 'base_url', ''), '/') . '/uploads/audio/' . $filename;
}

function json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: public, max-age=60');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function private_json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Cache-Control: no-store, private');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
