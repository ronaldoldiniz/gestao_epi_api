<?php
/**
 * Arquivo de configuração ativo da API Gestão EPI.
 * Contém credenciais de banco e chaves de segurança fixadas.
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
        'secret_key' => 'gestao_epi_api_chavesecretadoservidor_987654321',
        'token_ttl' => 43200
    ],
    'security' => [
        'max_login_attempts' => 5,
        'lockout_time' => 900,
        'max_pin_attempts' => 3,
        'aes_key' => '89b856ebf864d56bf299c51355bac0b3f9028f00c50b7ddebe6cbe4936cfb85f',
        'hmac_key' => 'ff949f4043290a78fb87feb34410a57fc6e6c0db8df39513edaf635920487c7c'
    ]
];
