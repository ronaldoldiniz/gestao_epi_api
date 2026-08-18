-- 1. Adicionar campos de devolução estendida e vínculo na tabela Itens_Entrega
ALTER TABLE Itens_Entrega
    ADD COLUMN item_devolucao_motivo VARCHAR(50) NULL,
    ADD COLUMN item_devolucao_condicao VARCHAR(30) NULL,
    ADD COLUMN item_devolucao_destino VARCHAR(50) NULL,
    ADD COLUMN item_devolucao_obs TEXT NULL,
    ADD COLUMN item_devolucao_vinculo_entrega_id INT NULL,
    ADD COLUMN item_devolucao_vinculo_item_id INT NULL,
    ADD COLUMN item_devolucao_tipo_operacao VARCHAR(50) NULL DEFAULT 'DEVOLUCAO_INDEPENDENTE',
    ADD CONSTRAINT fk_item_devolucao_vinculo_entrega FOREIGN KEY (item_devolucao_vinculo_entrega_id) REFERENCES Entrega_EPIs(entr_id),
    ADD CONSTRAINT fk_item_devolucao_vinculo_item FOREIGN KEY (item_devolucao_vinculo_item_id) REFERENCES Itens_Entrega(item_id);

-- 2. Adicionar campo que indica se a entrega gerou alguma devolução vinculada na tabela Entrega_EPIs
ALTER TABLE Entrega_EPIs
    ADD COLUMN entr_substituicao_vinculada TINYINT(1) NOT NULL DEFAULT 0;
