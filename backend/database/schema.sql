-- Selecione o banco criado pela hospedagem antes de importar este arquivo.
-- Todas as tabelas usam utf8mb4 para preservar acentos e símbolos.

CREATE TABLE IF NOT EXISTS admin_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    description VARCHAR(500) NULL,
    icon VARCHAR(80) NOT NULL DEFAULT 'book-outline',
    color CHAR(7) NOT NULL DEFAULT '#7B5C45',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_categories_public (is_active, sort_order)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS prayers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    excerpt VARCHAR(500) NULL,
    body MEDIUMTEXT NOT NULL,
    image_url VARCHAR(2048) NULL,
    audio_url VARCHAR(2048) NULL,
    is_premium TINYINT(1) NOT NULL DEFAULT 0,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    published_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_prayers_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_prayers_public (is_published, published_at, sort_order),
    INDEX idx_prayers_category (category_id, is_published)
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

INSERT INTO categories (name, slug, icon, color, sort_order) VALUES
('Pessoal', 'pessoal', 'person-outline', '#7B5C45', 10),
('Família', 'familia', 'people-outline', '#9B6C72', 20),
('Trabalho', 'trabalho', 'briefcase-outline', '#55737B', 30),
('Relacionamento', 'relacionamento', 'heart-outline', '#A15D5D', 40),
('Saúde', 'saude', 'medkit-outline', '#5C8872', 50),
('Estudos', 'estudos', 'book-outline', '#6C6B93', 60),
('Finanças', 'financas', 'wallet-outline', '#98743F', 70),
('Conquistas', 'conquistas', 'trophy-outline', '#A87B36', 80),
('Proteção', 'protecao', 'shield-checkmark-outline', '#536E8A', 90)
ON DUPLICATE KEY UPDATE name = VALUES(name), icon = VALUES(icon), color = VALUES(color);
