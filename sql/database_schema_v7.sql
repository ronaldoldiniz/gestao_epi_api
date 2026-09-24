-- ==========================================================================
-- GESTÃO EPI - ESQUEMA DO BANCO DE DADOS COMPLETO (V7 - AIVEN CLOUD DB)
-- Gerado em: 2026-09-24 20:54:07
-- Inclui restrições UNIQUE para fun_esocial, fun_cpf, fun_qrcode e integridade LGPD.
-- ==========================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Estrutura da view `Assinatura_Eletronica`
-- --------------------------------------------------------
CREATE VIEW "Assinatura_Eletronica" AS select "assinatura_eletronica"."ass_id" AS "ass_id","assinatura_eletronica"."fun_id" AS "fun_id","assinatura_eletronica"."usu_id" AS "usu_id","assinatura_eletronica"."ass_senha_hash" AS "ass_senha_hash","assinatura_eletronica"."ass_salt" AS "ass_salt","assinatura_eletronica"."ass_status" AS "ass_status","assinatura_eletronica"."ass_data_cadastro" AS "ass_data_cadastro","assinatura_eletronica"."ass_ultimo_uso" AS "ass_ultimo_uso","assinatura_eletronica"."ass_tentativas_falha" AS "ass_tentativas_falha","assinatura_eletronica"."ass_data_bloqueio" AS "ass_data_bloqueio","assinatura_eletronica"."ass_motivo_bloqueio" AS "ass_motivo_bloqueio" from "assinatura_eletronica";

-- --------------------------------------------------------
-- Estrutura da view `EPIs`
-- --------------------------------------------------------
CREATE VIEW "EPIs" AS select "epis"."epi_id" AS "epi_id","epis"."epi_nome" AS "epi_nome","epis"."epi_tipo_item" AS "epi_tipo_item","epis"."epi_ca" AS "epi_ca","epis"."epi_vencimento_ca" AS "epi_vencimento_ca","epis"."epi_fabricante" AS "epi_fabricante","epis"."epi_validade_uso_dias" AS "epi_validade_uso_dias","epis"."epi_status" AS "epi_status","epis"."epi_valor" AS "epi_valor","epis"."epi_origem_preco" AS "epi_origem_preco","epis"."epi_localizacao" AS "epi_localizacao","epis"."epi_vida_util" AS "epi_vida_util","epis"."epi_vida_util_unidade" AS "epi_vida_util_unidade","epis"."epi_vida_util_tipo" AS "epi_vida_util_tipo","epis"."epi_vida_util_alerta" AS "epi_vida_util_alerta","epis"."epi_vida_util_obs" AS "epi_vida_util_obs","epis"."epi_numero_lote" AS "epi_numero_lote","epis"."epi_modelo" AS "epi_modelo","epis"."epi_identificacao" AS "epi_identificacao","epis"."epi_ref_fornecedor" AS "epi_ref_fornecedor","epis"."epi_exige_tamanho" AS "epi_exige_tamanho" from "epis";

-- --------------------------------------------------------
-- Estrutura da view `Entrega_EPIs`
-- --------------------------------------------------------
CREATE VIEW "Entrega_EPIs" AS select "entrega_epis"."entr_id" AS "entr_id","entrega_epis"."fun_id" AS "fun_id","entrega_epis"."usu_id" AS "usu_id","entrega_epis"."ass_id" AS "ass_id","entrega_epis"."entr_data_entrega" AS "entr_data_entrega","entrega_epis"."entr_hash_assinatura" AS "entr_hash_assinatura","entrega_epis"."entr_termo_ciencia" AS "entr_termo_ciencia","entrega_epis"."entr_status" AS "entr_status","entrega_epis"."entr_status_sinc" AS "entr_status_sinc","entrega_epis"."entr_validacao_senha" AS "entr_validacao_senha","entrega_epis"."entr_motivo" AS "entr_motivo","entrega_epis"."entr_client_operation_id" AS "entr_client_operation_id","entrega_epis"."termo_id" AS "termo_id","entrega_epis"."entr_termo_versao" AS "entr_termo_versao","entrega_epis"."entr_texto_termo_snapshot" AS "entr_texto_termo_snapshot","entrega_epis"."entr_data_hora_aceite" AS "entr_data_hora_aceite","entrega_epis"."entr_metodo_aceite" AS "entr_metodo_aceite","entrega_epis"."entr_hash_termo" AS "entr_hash_termo","entrega_epis"."entr_substituicao_vinculada" AS "entr_substituicao_vinculada" from "entrega_epis";

-- --------------------------------------------------------
-- Estrutura da view `Funcionarios`
-- --------------------------------------------------------
CREATE VIEW "Funcionarios" AS select "funcionarios"."fun_id" AS "fun_id","funcionarios"."fun_nome" AS "fun_nome","funcionarios"."fun_cpf" AS "fun_cpf","funcionarios"."fun_cpf_enc" AS "fun_cpf_enc","funcionarios"."fun_cpf_iv" AS "fun_cpf_iv","funcionarios"."fun_cpf_tag" AS "fun_cpf_tag","funcionarios"."fun_cpf_lookup" AS "fun_cpf_lookup","funcionarios"."fun_esocial" AS "fun_esocial","funcionarios"."fun_esocial_enc" AS "fun_esocial_enc","funcionarios"."fun_esocial_iv" AS "fun_esocial_iv","funcionarios"."fun_esocial_tag" AS "fun_esocial_tag","funcionarios"."fun_departamento" AS "fun_departamento","funcionarios"."fun_cargo" AS "fun_cargo","funcionarios"."fun_dataadmissao" AS "fun_dataadmissao","funcionarios"."fun_situacao" AS "fun_situacao","funcionarios"."fun_qrcode" AS "fun_qrcode" from "funcionarios";

-- --------------------------------------------------------
-- Estrutura da view `Historico_Preco_EPI`
-- --------------------------------------------------------
CREATE VIEW "Historico_Preco_EPI" AS select "historico_preco_epi"."hist_id" AS "hist_id","historico_preco_epi"."epi_id" AS "epi_id","historico_preco_epi"."usu_id" AS "usu_id","historico_preco_epi"."hist_valor" AS "hist_valor","historico_preco_epi"."hist_data_vigencia" AS "hist_data_vigencia","historico_preco_epi"."hist_origem" AS "hist_origem","historico_preco_epi"."hist_nota_fiscal" AS "hist_nota_fiscal","historico_preco_epi"."hist_fornecedor" AS "hist_fornecedor" from "historico_preco_epi";

-- --------------------------------------------------------
-- Estrutura da view `Itens_Entrega`
-- --------------------------------------------------------
CREATE VIEW "Itens_Entrega" AS select "itens_entrega"."item_id" AS "item_id","itens_entrega"."entr_id" AS "entr_id","itens_entrega"."epi_id" AS "epi_id","itens_entrega"."item_quantidade" AS "item_quantidade","itens_entrega"."item_data_devolucao" AS "item_data_devolucao","itens_entrega"."item_status" AS "item_status","itens_entrega"."item_numero_lote" AS "item_numero_lote","itens_entrega"."item_tamanho" AS "item_tamanho","itens_entrega"."item_epi_nome_snapshot" AS "item_epi_nome_snapshot","itens_entrega"."item_epi_descricao_snapshot" AS "item_epi_descricao_snapshot","itens_entrega"."item_epi_fabricante_snapshot" AS "item_epi_fabricante_snapshot","itens_entrega"."item_epi_modelo_snapshot" AS "item_epi_modelo_snapshot","itens_entrega"."item_epi_ca_snapshot" AS "item_epi_ca_snapshot","itens_entrega"."item_epi_validade_ca_snapshot" AS "item_epi_validade_ca_snapshot","itens_entrega"."item_epi_vida_util_snapshot" AS "item_epi_vida_util_snapshot","itens_entrega"."item_epi_valor_snapshot" AS "item_epi_valor_snapshot","itens_entrega"."item_epi_origem_preco_snapshot" AS "item_epi_origem_preco_snapshot","itens_entrega"."item_epi_localizacao_snapshot" AS "item_epi_localizacao_snapshot","itens_entrega"."item_devolucao_motivo" AS "item_devolucao_motivo","itens_entrega"."item_devolucao_condicao" AS "item_devolucao_condicao","itens_entrega"."item_devolucao_destino" AS "item_devolucao_destino","itens_entrega"."item_devolucao_obs" AS "item_devolucao_obs","itens_entrega"."item_devolucao_vinculo_entrega_id" AS "item_devolucao_vinculo_entrega_id","itens_entrega"."item_devolucao_vinculo_item_id" AS "item_devolucao_vinculo_item_id","itens_entrega"."item_devolucao_tipo_operacao" AS "item_devolucao_tipo_operacao","itens_entrega"."item_motivo_entrega" AS "item_motivo_entrega" from "itens_entrega";

-- --------------------------------------------------------
-- Estrutura da view `Log_Auditoria`
-- --------------------------------------------------------
CREATE VIEW "Log_Auditoria" AS select "log_auditoria"."log_id" AS "log_id","log_auditoria"."usu_id" AS "usu_id","log_auditoria"."fun_id" AS "fun_id","log_auditoria"."epi_id" AS "epi_id","log_auditoria"."entr_id" AS "entr_id","log_auditoria"."item_id" AS "item_id","log_auditoria"."ass_id" AS "ass_id","log_auditoria"."hist_id" AS "hist_id","log_auditoria"."log_acao" AS "log_acao","log_auditoria"."log_datahora" AS "log_datahora","log_auditoria"."log_tabela" AS "log_tabela","log_auditoria"."log_registro_id" AS "log_registro_id","log_auditoria"."log_detalhes" AS "log_detalhes" from "log_auditoria";

-- --------------------------------------------------------
-- Estrutura da tabela `Termos_Responsabilidade`
-- --------------------------------------------------------
CREATE TABLE "Termos_Responsabilidade" (
  "termo_id" int NOT NULL AUTO_INCREMENT,
  "termo_codigo" varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  "termo_versao" varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  "termo_titulo" varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  "termo_texto_completo" text COLLATE utf8mb4_unicode_ci NOT NULL,
  "termo_data_inicio_vigencia" datetime NOT NULL,
  "termo_data_fim_vigencia" datetime DEFAULT NULL,
  "termo_status" varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ATIVO',
  "usu_cadastro_id" int NOT NULL,
  "termo_data_hora_cadastro" datetime NOT NULL,
  PRIMARY KEY ("termo_id"),
  KEY "usu_cadastro_id" ("usu_cadastro_id"),
  CONSTRAINT "Termos_Responsabilidade_ibfk_1" FOREIGN KEY ("usu_cadastro_id") REFERENCES "Usuarios" ("usu_id")
);

-- --------------------------------------------------------
-- Estrutura da view `Usuarios`
-- --------------------------------------------------------
CREATE VIEW "Usuarios" AS select "usuarios"."usu_id" AS "usu_id","usuarios"."usu_login" AS "usu_login","usuarios"."usu_senha_hash" AS "usu_senha_hash","usuarios"."usu_senha_salt" AS "usu_senha_salt","usuarios"."usu_perfil" AS "usu_perfil","usuarios"."usu_status" AS "usu_status","usuarios"."usu_data_cadastro" AS "usu_data_cadastro","usuarios"."usu_tentativas_falha" AS "usu_tentativas_falha","usuarios"."usu_data_bloqueio" AS "usu_data_bloqueio","usuarios"."usu_motivo_bloqueio" AS "usu_motivo_bloqueio","usuarios"."usu_exige_troca_senha" AS "usu_exige_troca_senha","usuarios"."usu_aceite_termos" AS "usu_aceite_termos","usuarios"."usu_data_aceite_termos" AS "usu_data_aceite_termos" from "usuarios";

-- --------------------------------------------------------
-- Estrutura da tabela `assinatura_eletronica`
-- --------------------------------------------------------
CREATE TABLE "assinatura_eletronica" (
  "ass_id" int NOT NULL AUTO_INCREMENT,
  "fun_id" int NOT NULL,
  "usu_id" int NOT NULL,
  "ass_senha_hash" varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  "ass_salt" varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "ass_status" varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ATIVO',
  "ass_data_cadastro" datetime NOT NULL,
  "ass_ultimo_uso" datetime DEFAULT NULL,
  "ass_tentativas_falha" int NOT NULL DEFAULT '0',
  "ass_data_bloqueio" datetime DEFAULT NULL,
  "ass_motivo_bloqueio" varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY ("ass_id"),
  KEY "fun_id" ("fun_id"),
  KEY "usu_id" ("usu_id"),
  CONSTRAINT "assinatura_eletronica_ibfk_1" FOREIGN KEY ("fun_id") REFERENCES "funcionarios" ("fun_id") ON DELETE CASCADE,
  CONSTRAINT "assinatura_eletronica_ibfk_2" FOREIGN KEY ("usu_id") REFERENCES "usuarios" ("usu_id")
);

-- --------------------------------------------------------
-- Estrutura da tabela `entrega_epis`
-- --------------------------------------------------------
CREATE TABLE "entrega_epis" (
  "entr_id" int NOT NULL AUTO_INCREMENT,
  "fun_id" int NOT NULL,
  "usu_id" int NOT NULL,
  "ass_id" int NOT NULL,
  "entr_data_entrega" datetime NOT NULL,
  "entr_hash_assinatura" varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  "entr_termo_ciencia" varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SIM',
  "entr_status" varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'FINALIZADA',
  "entr_status_sinc" varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SINCRONIZADO',
  "entr_validacao_senha" varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VALIDADA',
  "entr_motivo" varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "entr_client_operation_id" varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "termo_id" int DEFAULT NULL,
  "entr_termo_versao" varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "entr_texto_termo_snapshot" text COLLATE utf8mb4_unicode_ci,
  "entr_data_hora_aceite" datetime DEFAULT NULL,
  "entr_metodo_aceite" varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "entr_hash_termo" varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "entr_substituicao_vinculada" tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY ("entr_id"),
  UNIQUE KEY "idx_entr_client_operation_id" ("entr_client_operation_id"),
  KEY "fun_id" ("fun_id"),
  KEY "usu_id" ("usu_id"),
  KEY "ass_id" ("ass_id"),
  KEY "fk_entrega_termo" ("termo_id"),
  CONSTRAINT "entrega_epis_ibfk_1" FOREIGN KEY ("fun_id") REFERENCES "funcionarios" ("fun_id"),
  CONSTRAINT "entrega_epis_ibfk_2" FOREIGN KEY ("usu_id") REFERENCES "usuarios" ("usu_id"),
  CONSTRAINT "entrega_epis_ibfk_3" FOREIGN KEY ("ass_id") REFERENCES "assinatura_eletronica" ("ass_id"),
  CONSTRAINT "fk_entrega_termo" FOREIGN KEY ("termo_id") REFERENCES "termos_responsabilidade" ("termo_id")
);

-- --------------------------------------------------------
-- Estrutura da tabela `epis`
-- --------------------------------------------------------
CREATE TABLE "epis" (
  "epi_id" int NOT NULL AUTO_INCREMENT,
  "epi_nome" varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  "epi_tipo_item" enum('EPI_COM_CA','ITEM_SEGURANCA_SEM_CA','UNIFORME','OUTRO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EPI_COM_CA',
  "epi_ca" varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "epi_vencimento_ca" date DEFAULT NULL,
  "epi_fabricante" varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  "epi_validade_uso_dias" int NOT NULL,
  "epi_status" varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ATIVO',
  "epi_valor" decimal(10,2) NOT NULL DEFAULT '0.00',
  "epi_origem_preco" varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  "epi_localizacao" varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "epi_vida_util" int DEFAULT NULL,
  "epi_vida_util_unidade" varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "epi_vida_util_tipo" varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'CONTROLADO',
  "epi_vida_util_alerta" int DEFAULT NULL,
  "epi_vida_util_obs" text COLLATE utf8mb4_unicode_ci,
  "epi_numero_lote" varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "epi_modelo" varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "epi_identificacao" varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "epi_ref_fornecedor" varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "epi_exige_tamanho" tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY ("epi_id")
);

-- --------------------------------------------------------
-- Estrutura da tabela `funcionarios`
-- --------------------------------------------------------
CREATE TABLE "funcionarios" (
  "fun_id" int NOT NULL AUTO_INCREMENT,
  "fun_nome" varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  "fun_cpf" varchar(11) COLLATE utf8mb4_unicode_ci NOT NULL,
  "fun_cpf_enc" varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "fun_cpf_iv" varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "fun_cpf_tag" varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "fun_cpf_lookup" varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "fun_esocial" varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  "fun_esocial_enc" varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "fun_esocial_iv" varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "fun_esocial_tag" varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "fun_departamento" varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  "fun_cargo" varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  "fun_dataadmissao" date NOT NULL,
  "fun_situacao" varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ATIVO',
  "fun_qrcode" varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY ("fun_id"),
  UNIQUE KEY "fun_cpf" ("fun_cpf"),
  UNIQUE KEY "fun_qrcode" ("fun_qrcode"),
  UNIQUE KEY "fun_esocial" ("fun_esocial")
);

-- --------------------------------------------------------
-- Estrutura da tabela `historico_preco_epi`
-- --------------------------------------------------------
CREATE TABLE "historico_preco_epi" (
  "hist_id" int NOT NULL AUTO_INCREMENT,
  "epi_id" int NOT NULL,
  "usu_id" int NOT NULL,
  "hist_valor" decimal(10,2) NOT NULL,
  "hist_data_vigencia" datetime NOT NULL,
  "hist_origem" varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  "hist_nota_fiscal" varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "hist_fornecedor" varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY ("hist_id"),
  KEY "epi_id" ("epi_id"),
  KEY "usu_id" ("usu_id"),
  CONSTRAINT "historico_preco_epi_ibfk_1" FOREIGN KEY ("epi_id") REFERENCES "epis" ("epi_id") ON DELETE CASCADE,
  CONSTRAINT "historico_preco_epi_ibfk_2" FOREIGN KEY ("usu_id") REFERENCES "usuarios" ("usu_id")
);

-- --------------------------------------------------------
-- Estrutura da tabela `itens_entrega`
-- --------------------------------------------------------
CREATE TABLE "itens_entrega" (
  "item_id" int NOT NULL AUTO_INCREMENT,
  "entr_id" int NOT NULL,
  "epi_id" int NOT NULL,
  "item_quantidade" int NOT NULL DEFAULT '1',
  "item_data_devolucao" datetime DEFAULT NULL,
  "item_status" varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ENTREGUE',
  "item_numero_lote" varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "item_tamanho" varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "item_epi_nome_snapshot" varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "item_epi_descricao_snapshot" text COLLATE utf8mb4_unicode_ci,
  "item_epi_fabricante_snapshot" varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "item_epi_modelo_snapshot" varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "item_epi_ca_snapshot" varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "item_epi_validade_ca_snapshot" date DEFAULT NULL,
  "item_epi_vida_util_snapshot" int DEFAULT NULL,
  "item_epi_valor_snapshot" decimal(10,2) DEFAULT NULL,
  "item_epi_origem_preco_snapshot" varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "item_epi_localizacao_snapshot" varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "item_devolucao_motivo" varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "item_devolucao_condicao" varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "item_devolucao_destino" varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "item_devolucao_obs" text COLLATE utf8mb4_unicode_ci,
  "item_devolucao_vinculo_entrega_id" int DEFAULT NULL,
  "item_devolucao_vinculo_item_id" int DEFAULT NULL,
  "item_devolucao_tipo_operacao" varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'DEVOLUCAO_INDEPENDENTE',
  "item_motivo_entrega" varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY ("item_id"),
  KEY "entr_id" ("entr_id"),
  KEY "epi_id" ("epi_id"),
  KEY "fk_item_devolucao_vinculo_entrega" ("item_devolucao_vinculo_entrega_id"),
  KEY "fk_item_devolucao_vinculo_item" ("item_devolucao_vinculo_item_id"),
  CONSTRAINT "fk_item_devolucao_vinculo_entrega" FOREIGN KEY ("item_devolucao_vinculo_entrega_id") REFERENCES "entrega_epis" ("entr_id"),
  CONSTRAINT "fk_item_devolucao_vinculo_item" FOREIGN KEY ("item_devolucao_vinculo_item_id") REFERENCES "itens_entrega" ("item_id"),
  CONSTRAINT "itens_entrega_ibfk_1" FOREIGN KEY ("entr_id") REFERENCES "entrega_epis" ("entr_id") ON DELETE CASCADE,
  CONSTRAINT "itens_entrega_ibfk_2" FOREIGN KEY ("epi_id") REFERENCES "epis" ("epi_id")
);

-- --------------------------------------------------------
-- Estrutura da tabela `log_auditoria`
-- --------------------------------------------------------
CREATE TABLE "log_auditoria" (
  "log_id" int NOT NULL AUTO_INCREMENT,
  "usu_id" int DEFAULT NULL,
  "fun_id" int DEFAULT NULL,
  "epi_id" int DEFAULT NULL,
  "entr_id" int DEFAULT NULL,
  "item_id" int DEFAULT NULL,
  "ass_id" int DEFAULT NULL,
  "hist_id" int DEFAULT NULL,
  "log_acao" varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  "log_datahora" datetime NOT NULL,
  "log_tabela" varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  "log_registro_id" int DEFAULT NULL,
  "log_detalhes" text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY ("log_id"),
  KEY "usu_id" ("usu_id"),
  KEY "fun_id" ("fun_id"),
  KEY "epi_id" ("epi_id"),
  KEY "entr_id" ("entr_id"),
  KEY "item_id" ("item_id"),
  KEY "ass_id" ("ass_id"),
  KEY "hist_id" ("hist_id"),
  CONSTRAINT "log_auditoria_ibfk_1" FOREIGN KEY ("usu_id") REFERENCES "usuarios" ("usu_id") ON DELETE SET NULL,
  CONSTRAINT "log_auditoria_ibfk_2" FOREIGN KEY ("fun_id") REFERENCES "funcionarios" ("fun_id") ON DELETE SET NULL,
  CONSTRAINT "log_auditoria_ibfk_3" FOREIGN KEY ("epi_id") REFERENCES "epis" ("epi_id") ON DELETE SET NULL,
  CONSTRAINT "log_auditoria_ibfk_4" FOREIGN KEY ("entr_id") REFERENCES "entrega_epis" ("entr_id") ON DELETE SET NULL,
  CONSTRAINT "log_auditoria_ibfk_5" FOREIGN KEY ("item_id") REFERENCES "itens_entrega" ("item_id") ON DELETE SET NULL,
  CONSTRAINT "log_auditoria_ibfk_6" FOREIGN KEY ("ass_id") REFERENCES "assinatura_eletronica" ("ass_id") ON DELETE SET NULL,
  CONSTRAINT "log_auditoria_ibfk_7" FOREIGN KEY ("hist_id") REFERENCES "historico_preco_epi" ("hist_id") ON DELETE SET NULL
);

-- --------------------------------------------------------
-- Estrutura da tabela `operacoes_idempotentes`
-- --------------------------------------------------------
CREATE TABLE "operacoes_idempotentes" (
  "ope_id" int NOT NULL AUTO_INCREMENT,
  "ope_client_operation_id" varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  "ope_tipo_operacao" varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  "usu_id" int NOT NULL,
  "fun_id" int DEFAULT NULL,
  "ope_status" varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  "ope_request_hash" varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "ope_entrega_id" int DEFAULT NULL,
  "ope_devolucao_id" int DEFAULT NULL,
  "ope_data_hora_inicio" datetime NOT NULL,
  "ope_data_hora_conclusao" datetime DEFAULT NULL,
  "ope_codigo_resultado" varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "ope_resposta_json" longtext COLLATE utf8mb4_unicode_ci,
  "ope_erro_referencia" varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY ("ope_id"),
  UNIQUE KEY "client_operation_id" ("ope_client_operation_id"),
  KEY "usuario_id" ("usu_id"),
  KEY "funcionario_id" ("fun_id"),
  CONSTRAINT "operacoes_idempotentes_ibfk_1" FOREIGN KEY ("usu_id") REFERENCES "usuarios" ("usu_id"),
  CONSTRAINT "operacoes_idempotentes_ibfk_2" FOREIGN KEY ("fun_id") REFERENCES "funcionarios" ("fun_id")
);

-- --------------------------------------------------------
-- Estrutura da tabela `termos_responsabilidade`
-- --------------------------------------------------------
CREATE TABLE "termos_responsabilidade" (
  "termo_id" int NOT NULL AUTO_INCREMENT,
  "termo_codigo" varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  "termo_versao" varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  "termo_titulo" varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  "termo_texto_completo" text COLLATE utf8mb4_unicode_ci NOT NULL,
  "termo_data_inicio_vigencia" datetime NOT NULL,
  "termo_data_fim_vigencia" datetime DEFAULT NULL,
  "termo_status" varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ATIVO',
  "usu_cadastro_id" int NOT NULL,
  "termo_data_hora_cadastro" datetime NOT NULL,
  "termo_usu_id" int DEFAULT NULL,
  "termo_texto_snapshot" text COLLATE utf8mb4_unicode_ci,
  "termo_data_hora_aceite" datetime DEFAULT NULL,
  "termo_metodo_aceite" varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "termo_hash_termo" varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY ("termo_id"),
  UNIQUE KEY "uk_termos_aceite_usuario_versao" ("termo_codigo","termo_versao","termo_usu_id"),
  KEY "usu_cadastro_id" ("usu_cadastro_id"),
  KEY "fk_termo_usuario" ("termo_usu_id"),
  KEY "idx_termos_codigo_versao_usuario" ("termo_codigo","termo_versao","termo_usu_id"),
  KEY "idx_termos_status_codigo" ("termo_status","termo_codigo"),
  CONSTRAINT "fk_termo_usuario" FOREIGN KEY ("termo_usu_id") REFERENCES "usuarios" ("usu_id"),
  CONSTRAINT "fk_termos_usu" FOREIGN KEY ("usu_cadastro_id") REFERENCES "usuarios" ("usu_id")
);

-- --------------------------------------------------------
-- Estrutura da tabela `usuarios`
-- --------------------------------------------------------
CREATE TABLE "usuarios" (
  "usu_id" int NOT NULL AUTO_INCREMENT,
  "usu_login" varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  "usu_senha_hash" varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  "usu_senha_salt" varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "usu_perfil" varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  "usu_status" varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ATIVO',
  "usu_data_cadastro" datetime NOT NULL,
  "usu_tentativas_falha" int NOT NULL DEFAULT '0',
  "usu_data_bloqueio" datetime DEFAULT NULL,
  "usu_motivo_bloqueio" varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  "usu_exige_troca_senha" tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY ("usu_id"),
  UNIQUE KEY "usu_login" ("usu_login")
);

-- --------------------------------------------------------
-- Estrutura da view `vw_funcionarios_mascarado`
-- --------------------------------------------------------
CREATE VIEW "vw_funcionarios_mascarado" AS select "funcionarios"."fun_id" AS "fun_id","funcionarios"."fun_nome" AS "fun_nome",concat('***.***.***-',substr("funcionarios"."fun_cpf",10)) AS "fun_cpf",concat('***.***.***-',substr("funcionarios"."fun_esocial",10)) AS "fun_esocial","funcionarios"."fun_departamento" AS "fun_departamento","funcionarios"."fun_cargo" AS "fun_cargo","funcionarios"."fun_dataadmissao" AS "fun_dataadmissao","funcionarios"."fun_situacao" AS "fun_situacao","funcionarios"."fun_qrcode" AS "fun_qrcode" from "funcionarios";

SET FOREIGN_KEY_CHECKS = 1;
