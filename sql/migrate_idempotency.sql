-- Criação da tabela de controle de idempotência de operações
CREATE TABLE IF NOT EXISTS operacoes_idempotentes (
    ope_id INT AUTO_INCREMENT PRIMARY KEY,
    ope_client_operation_id VARCHAR(100) NOT NULL UNIQUE,
    ope_tipo_operacao VARCHAR(50) NOT NULL,
    usuario_id INT NOT NULL,
    fun_id INT NOT NULL,
    ope_status VARCHAR(30) NOT NULL,
    ope_request_hash VARCHAR(64) NULL,
    ope_entrega_id INT NULL,
    ope_devolucao_id INT NULL,
    ope_data_hora_inicio DATETIME NOT NULL,
    ope_data_hora_conclusao DATETIME NULL,
    ope_codigo_resultado VARCHAR(50) NULL,
    ope_resposta_json LONGTEXT NULL,
    ope_erro_referencia VARCHAR(100) NULL,
    FOREIGN KEY (usuario_id) REFERENCES Usuarios(usu_id),
    FOREIGN KEY (fun_id) REFERENCES Funcionarios(fun_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
