-- Migration: Aceite de Termos de Uso no perfil do usuario
-- Adiciona campos de aceite de termos na tabela Usuarios

ALTER TABLE usuarios
    ADD COLUMN usu_aceite_termos TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN usu_data_aceite_termos BIGINT(20) NULL DEFAULT NULL;
