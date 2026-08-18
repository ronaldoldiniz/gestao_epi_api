-- -----------------------------------------------------
-- Criação das Tabelas Físicas - db_gestao_epi
-- -----------------------------------------------------

CREATE TABLE IF NOT EXISTS Usuarios (
    usu_id INT AUTO_INCREMENT PRIMARY KEY,
    usu_login VARCHAR(50) NOT NULL UNIQUE,
    usu_senha_hash VARCHAR(255) NOT NULL,
    usu_perfil VARCHAR(30) NOT NULL,
    usu_status VARCHAR(20) NOT NULL DEFAULT 'ATIVO',
    usu_data_cadastro DATETIME NOT NULL,
    usu_tentativas_falha INT NOT NULL DEFAULT 0,
    usu_data_bloqueio DATETIME NULL,
    usu_motivo_bloqueio VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Funcionarios (
    fun_id INT AUTO_INCREMENT PRIMARY KEY,
    fun_nome VARCHAR(100) NOT NULL,
    fun_cpf VARCHAR(11) NOT NULL UNIQUE,
    fun_esocial VARCHAR(50) NOT NULL,
    fun_departamento VARCHAR(100) NOT NULL,
    fun_cargo VARCHAR(100) NOT NULL,
    fun_dataadmissao DATE NOT NULL,
    fun_situacao VARCHAR(30) NOT NULL DEFAULT 'ATIVO',
    fun_qrcode VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS EPIs (
    epi_id INT AUTO_INCREMENT PRIMARY KEY,
    epi_nome VARCHAR(100) NOT NULL,
    epi_ca VARCHAR(30) NOT NULL,
    epi_vencimento_ca DATE NOT NULL,
    epi_fabricante VARCHAR(100) NOT NULL,
    epi_validade_uso_dias INT NOT NULL,
    epi_status VARCHAR(30) NOT NULL DEFAULT 'ATIVO',
    epi_valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    epi_origem_preco VARCHAR(50) NOT NULL,
    epi_localizacao VARCHAR(100) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Assinatura_Eletronica (
    ass_id INT AUTO_INCREMENT PRIMARY KEY,
    fun_id INT NOT NULL,
    usu_id INT NOT NULL,
    ass_senha_hash VARCHAR(255) NOT NULL,
    ass_salt VARCHAR(255) NULL,
    ass_status VARCHAR(30) NOT NULL DEFAULT 'ATIVO',
    ass_data_cadastro DATETIME NOT NULL,
    ass_ultimo_uso DATETIME NULL,
    ass_tentativas_falha INT NOT NULL DEFAULT 0,
    ass_data_bloqueio DATETIME NULL,
    ass_motivo_bloqueio VARCHAR(255) NULL,
    FOREIGN KEY (fun_id) REFERENCES Funcionarios(fun_id) ON DELETE CASCADE,
    FOREIGN KEY (usu_id) REFERENCES Usuarios(usu_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Entrega_EPIs (
    entr_id INT AUTO_INCREMENT PRIMARY KEY,
    fun_id INT NOT NULL,
    usu_id INT NOT NULL,
    ass_id INT NOT NULL,
    entr_data_entrega DATETIME NOT NULL,
    entr_hash_assinatura VARCHAR(255) NOT NULL,
    entr_termo_ciencia VARCHAR(10) NOT NULL DEFAULT 'SIM',
    entr_status VARCHAR(30) NOT NULL DEFAULT 'FINALIZADA',
    entr_status_sinc VARCHAR(30) NOT NULL DEFAULT 'SINCRONIZADO',
    entr_validacao_senha VARCHAR(30) NOT NULL DEFAULT 'VALIDADA',
    entr_motivo VARCHAR(255) NULL,
    FOREIGN KEY (fun_id) REFERENCES Funcionarios(fun_id),
    FOREIGN KEY (usu_id) REFERENCES Usuarios(usu_id),
    FOREIGN KEY (ass_id) REFERENCES Assinatura_Eletronica(ass_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Itens_Entrega (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    entr_id INT NOT NULL,
    epi_id INT NOT NULL,
    item_quantidade INT NOT NULL DEFAULT 1,
    item_data_devolucao DATETIME NULL,
    item_status VARCHAR(30) NOT NULL DEFAULT 'ENTREGUE',
    FOREIGN KEY (entr_id) REFERENCES Entrega_EPIs(entr_id) ON DELETE CASCADE,
    FOREIGN KEY (epi_id) REFERENCES EPIs(epi_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Historico_Preco_EPI (
    hist_id INT AUTO_INCREMENT PRIMARY KEY,
    epi_id INT NOT NULL,
    usu_id INT NOT NULL,
    hist_valor DECIMAL(10,2) NOT NULL,
    hist_data_vigencia DATETIME NOT NULL,
    hist_origem VARCHAR(50) NOT NULL,
    hist_nota_fiscal VARCHAR(50) NULL,
    hist_fornecedor VARCHAR(100) NULL,
    FOREIGN KEY (epi_id) REFERENCES EPIs(epi_id) ON DELETE CASCADE,
    FOREIGN KEY (usu_id) REFERENCES Usuarios(usu_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Log_Auditoria (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    usu_id INT NULL,
    fun_id INT NULL,
    epi_id INT NULL,
    entr_id INT NULL,
    item_id INT NULL,
    ass_id INT NULL,
    hist_id INT NULL,
    log_acao VARCHAR(100) NOT NULL,
    log_datahora DATETIME NOT NULL,
    log_tabela VARCHAR(50) NOT NULL,
    log_registro_id INT NULL,
    log_detalhes TEXT NULL,
    FOREIGN KEY (usu_id) REFERENCES Usuarios(usu_id) ON DELETE SET NULL,
    FOREIGN KEY (fun_id) REFERENCES Funcionarios(fun_id) ON DELETE SET NULL,
    FOREIGN KEY (epi_id) REFERENCES EPIs(epi_id) ON DELETE SET NULL,
    FOREIGN KEY (entr_id) REFERENCES Entrega_EPIs(entr_id) ON DELETE SET NULL,
    FOREIGN KEY (item_id) REFERENCES Itens_Entrega(item_id) ON DELETE SET NULL,
    FOREIGN KEY (ass_id) REFERENCES Assinatura_Eletronica(ass_id) ON DELETE SET NULL,
    FOREIGN KEY (hist_id) REFERENCES Historico_Preco_EPI(hist_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
