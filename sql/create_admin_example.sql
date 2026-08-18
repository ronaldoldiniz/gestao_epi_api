-- -----------------------------------------------------
-- Criação de um Administrador de Exemplo
-- -----------------------------------------------------

-- Insere o usuário administrador inicial
-- Login: admin
-- Senha de teste: 123456 (Representada pelo hash abaixo, gerada por password_hash no PHP)
INSERT INTO Usuarios (
    usu_login, 
    usu_senha_hash, 
    usu_perfil, 
    usu_status, 
    usu_data_cadastro, 
    usu_tentativas_falha
) VALUES (
    'admin', 
    '$2y$10$u3524.3Z4hZl7bOpxmF8J.VjK6yMeeO8Q12Y1Q4P24G59.7C9nBfe', -- Hash para '123456'
    'ADMINISTRADOR', 
    'ATIVO', 
    NOW(), 
    0
) ON DUPLICATE KEY UPDATE usu_status = 'ATIVO';
