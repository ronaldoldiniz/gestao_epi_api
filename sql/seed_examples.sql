-- -----------------------------------------------------
-- Criação da View vw_funcionarios_mascarado (LGPD)
-- -----------------------------------------------------

CREATE OR REPLACE VIEW vw_funcionarios_mascarado AS
SELECT 
    fun_id,
    fun_nome,
    CONCAT('***.***.***-', RIGHT(fun_cpf, 2)) AS fun_cpf,
    CONCAT('***.***.', RIGHT(fun_esocial, 4)) AS fun_esocial,
    fun_departamento,
    fun_cargo,
    fun_dataadmissao,
    fun_situacao,
    fun_qrcode
FROM Funcionarios;

-- -----------------------------------------------------
-- Sementes de Teste (Dados Fictícios)
-- -----------------------------------------------------

-- 1. Inserção de Usuários de Diferentes Perfis
-- Senha de teste padrão para todos: 123456
INSERT INTO Usuarios (usu_login, usu_senha_hash, usu_perfil, usu_status, usu_data_cadastro, usu_tentativas_falha) VALUES
('rh_user', '$2y$10$u3524.3Z4hZl7bOpxmF8J.VjK6yMeeO8Q12Y1Q4P24G59.7C9nBfe', 'RH_ADMINISTRATIVO', 'ATIVO', NOW(), 0),
('sst_user', '$2y$10$u3524.3Z4hZl7bOpxmF8J.VjK6yMeeO8Q12Y1Q4P24G59.7C9nBfe', 'TECNICO_SST', 'ATIVO', NOW(), 0),
('almoxarife', '$2y$10$u3524.3Z4hZl7bOpxmF8J.VjK6yMeeO8Q12Y1Q4P24G59.7C9nBfe', 'ALMOXARIFE_OPERADOR', 'ATIVO', NOW(), 0),
('gestor_user', '$2y$10$u3524.3Z4hZl7bOpxmF8J.VjK6yMeeO8Q12Y1Q4P24G59.7C9nBfe', 'GESTOR', 'ATIVO', NOW(), 0)
ON DUPLICATE KEY UPDATE usu_status = 'ATIVO';

-- 2. Inserção de Funcionários
INSERT INTO Funcionarios (fun_nome, fun_cpf, fun_esocial, fun_departamento, fun_cargo, fun_dataadmissao, fun_situacao, fun_qrcode) VALUES
('João da Silva', '12345678901', 'ESO12345678', 'Produção', 'Operador de Máquinas', '2023-01-15', 'ATIVO', 'QR-JOAO-123'),
('Maria de Souza', '98765432100', 'ESO98765432', 'Logística', 'Auxiliar de Almoxarifado', '2024-03-10', 'ATIVO', 'QR-MARIA-456'),
('Carlos Pereira', '55544433322', 'ESO55544433', 'Manutenção', 'Mecânico Industrial', '2022-05-20', 'ATIVO', 'QR-CARLOS-789')
ON DUPLICATE KEY UPDATE fun_situacao = 'ATIVO';

-- 3. Inserção de Assinaturas Eletrônicas para os Funcionários
-- PIN de teste padrão: 1234 (Representado pelo hash abaixo, gerado por password_hash no PHP)
INSERT INTO Assinatura_Eletronica (fun_id, usu_id, ass_senha_hash, ass_salt, ass_status, ass_data_cadastro, ass_tentativas_falha) VALUES
(1, 1, '$2y$10$rY39j2rZ92.1X4mH.Lz/I.62Wn.DqYV6b3S16Xw7hV3s1X5Y3x1w.', 'salt_joao_123', 'ATIVO', NOW(), 0),
(2, 1, '$2y$10$rY39j2rZ92.1X4mH.Lz/I.62Wn.DqYV6b3S16Xw7hV3s1X5Y3x1w.', 'salt_maria_456', 'ATIVO', NOW(), 0)
ON DUPLICATE KEY UPDATE ass_status = 'ATIVO';

-- 4. Inserção de EPIs
INSERT INTO EPIs (epi_nome, epi_ca, epi_vencimento_ca, epi_fabricante, epi_validade_uso_dias, epi_status, epi_valor, epi_origem_preco, epi_localizacao) VALUES
('Capacete com Carneira', '43210', '2028-12-31', '3M do Brasil', 365, 'ATIVO', 45.90, 'COMPRA_DIRETA', 'PRATELEIRA-A1'),
('Óculos de Proteção Contra Impacto', '12345', '2027-08-15', 'Danny', 180, 'ATIVO', 15.50, 'COMPRA_DIRETA', 'PRATELEIRA-A2'),
('Respirador Semi-facial PFF2', '98765', '2024-01-01', 'Air Safety', 30, 'VENCIDO', 89.90, 'LICITACAO', 'GAVETA-B3'),
('Luva Nitrílica Látex', '56789', '2026-06-25', 'Volk', 15, 'ATIVO', 8.50, 'LICITACAO', 'PRATELEIRA-C1')
ON DUPLICATE KEY UPDATE epi_status = 'ATIVO';
