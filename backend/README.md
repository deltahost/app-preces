# Painel de conteúdo

Backend PHP 8 + MySQL para administrar categorias e orações do aplicativo.

## Instalação

1. Crie um banco MySQL e importe `database/schema.sql` pelo phpMyAdmin.
2. Copie `config.example.php` para `config.php`.
3. Preencha as credenciais do banco, a URL do domínio e um `setup_token` longo.
4. Configure o domínio ou subdomínio para servir a pasta `backend`.
5. Acesse `/admin/setup.php`, informe o código de instalação e crie o primeiro administrador.
6. Entre em `/admin/login.php`.

O arquivo `config.php` é ignorado pelo Git e não deve ser enviado ao GitHub.

## Endpoints públicos

- `GET /api/health.php`
- `GET /api/categories.php`
- `GET /api/prayers.php`
- `GET /api/prayers.php?category=protecao`
- `GET /api/prayers.php?slug=oracao-da-manha`
- `GET /api/prayers.php?featured=1&limit=10&page=1`
- `GET /api/media.php`
- `GET /api/media-categories.php`
- `GET /api/media.php?type=video`
- `GET /api/media.php?type=audio&category=meditacao`

Conteúdo Premium ainda é identificado pelo campo `is_premium`. A validação de assinatura será adicionada quando o sistema de compras for integrado.
