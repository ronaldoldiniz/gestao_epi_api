ALTER TABLE Itens_Entrega
    ADD COLUMN item_epi_nome_snapshot VARCHAR(100) NULL,
    ADD COLUMN item_epi_descricao_snapshot TEXT NULL,
    ADD COLUMN item_epi_fabricante_snapshot VARCHAR(100) NULL,
    ADD COLUMN item_epi_modelo_snapshot VARCHAR(150) NULL,
    ADD COLUMN item_epi_ca_snapshot VARCHAR(30) NULL,
    ADD COLUMN item_epi_validade_ca_snapshot DATE NULL,
    ADD COLUMN item_epi_vida_util_snapshot INT NULL,
    ADD COLUMN item_epi_valor_snapshot DECIMAL(10,2) NULL,
    ADD COLUMN item_epi_origem_preco_snapshot VARCHAR(50) NULL,
    ADD COLUMN item_epi_localizacao_snapshot VARCHAR(100) NULL;
