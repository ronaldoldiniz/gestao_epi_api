<?php
/**
 * Arquivo de configuração de exemplo da API Gestão EPI.
 * Renomeie ou copie este arquivo como `config.php` na mesma pasta e ajuste os valores.
 */

return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'dbname' => 'db_gestao_epi',
        'username' => 'user_api_epi',
        'password' => 'senha_segura_api_123',
        'charset' => 'utf8mb4'
    ],
    'app' => [
        'env' => 'local', // 'local' ou 'production'
        'debug' => true,   // Exibe erros mais detalhados apenas se 'local'
        'secret_key' => 'coloque_uma_chave_secreta_e_longa_aqui_para_os_tokens_da_api_1234567890',
        'token_ttl' => 43200 // Tempo de vida do token em segundos (43200 = 12 horas)
    ],
    'security' => [
        'max_login_attempts' => 5,       // Limite de tentativas consecutivas de login
        'lockout_time' => 900,           // Tempo de bloqueio temporário (900s = 15 minutos)
        'max_pin_attempts' => 3          // Limite de tentativas consecutivas do PIN de assinatura
    ]
];
