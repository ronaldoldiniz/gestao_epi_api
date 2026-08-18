-- Criação da tabela de controle de idempotência de operações
CREATE TABLE IF NOT EXISTS operacoes_idempotentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_operation_id VARCHAR(100) NOT NULL UNIQUE,
    tipo_operacao VARCHAR(50) NOT NULL,
    usuario_id INT NOT NULL,
    funcionario_id INT NOT NULL,
    status VARCHAR(30) NOT NULL,
    request_hash VARCHAR(64) NULL,
    entrega_id INT NULL,
    devolucao_id INT NULL,
    data_hora_inicio DATETIME NOT NULL,
    data_hora_conclusao DATETIME NULL,
    codigo_resultado VARCHAR(50) NULL,
    resposta_json LONGTEXT NULL,
    erro_referencia VARCHAR(100) NULL,
    FOREIGN KEY (usuario_id) REFERENCES Usuarios(usu_id),
    FOREIGN KEY (funcionario_id) REFERENCES Funcionarios(fun_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
