<?php
/**
 * Arquivo de configuração ativo da API Gestão EPI.
 * Altere as credenciais abaixo para corresponder ao seu ambiente local.
 */

return [
    'db' => [
        'host' => 'db-gestao-epi-gestaoepi.a.aivencloud.com',
        'port' => '10903',
        'dbname' => 'defaultdb',
        'username' => 'avnadmin',
        'password' => 'AVNS_T2WnhU7_Df8MJ2CvuW0',
        'charset' => 'utf8mb4'
    ],
    'app' => [
        'env' => 'production',
        'debug' => true,
        'secret_key' => 'gestao_epi_api_chavesecretadoservidor_987654321', // Substitua em produção
        'token_ttl' => 43200
    ],
    'security' => [
        'max_login_attempts' => 5,
        'lockout_time' => 900,
        'max_pin_attempts' => 3,
        'aes_key' => getenv('AES_KEY') ?: null,
        'hmac_key' => getenv('HMAC_KEY') ?: null
    ]
];
