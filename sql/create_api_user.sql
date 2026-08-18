-- -----------------------------------------------------
-- Criação do usuário específico para a API Gestão EPI
-- -----------------------------------------------------

-- Cria o usuário dedicado (substitua a senha abaixo por uma forte em produção)
CREATE USER IF NOT EXISTS 'user_api_epi'@'localhost' IDENTIFIED BY 'senha_segura_api_123';

-- Concede privilégios limitados apenas no banco de dados do sistema
-- (A API realiza apenas exclusões lógicas, mas o privilégio de DELETE é concedido para manipulações seguras controladas se necessário)
GRANT SELECT, INSERT, UPDATE, DELETE ON db_gestao_epi.* TO 'user_api_epi'@'localhost';

-- Aplica os privilégios imediatamente
FLUSH PRIVILEGES;
