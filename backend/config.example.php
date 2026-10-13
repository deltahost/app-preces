<?php

return [
    'app' => [
        'name' => 'Preces e Orações',
        'base_url' => 'https://seu-dominio.com',
        'setup_token' => 'troque-por-um-codigo-longo-e-aleatorio',
        // Gere uma chave aleatória diferente do setup_token e nunca a exponha no aplicativo.
        'secret_key' => 'troque-por-uma-chave-secreta-com-32-ou-mais-caracteres',
        'openai_model' => 'gpt-4o-mini',
        'gemini_model' => 'gemini-3.8-flash',
    ],
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'preces_app',
        'user' => 'preces_user',
        'password' => 'troque-esta-senha',
        'charset' => 'utf8mb4',
    ],
];
