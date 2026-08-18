-- 1. Criar tabela de Termos de Responsabilidade
CREATE TABLE IF NOT EXISTS Termos_Responsabilidade (
    termo_id INT AUTO_INCREMENT PRIMARY KEY,
    termo_codigo VARCHAR(50) NOT NULL,
    termo_versao VARCHAR(20) NOT NULL,
    termo_titulo VARCHAR(100) NOT NULL,
    termo_texto_completo TEXT NOT NULL,
    termo_data_inicio_vigencia DATETIME NOT NULL,
    termo_data_fim_vigencia DATETIME NULL,
    termo_status VARCHAR(20) NOT NULL DEFAULT 'ATIVO',
    usu_cadastro_id INT NOT NULL,
    termo_data_hora_cadastro DATETIME NOT NULL,
    FOREIGN KEY (usu_cadastro_id) REFERENCES Usuarios(usu_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Inserir a versão 2.0 oficial do termo no banco de dados
INSERT INTO Termos_Responsabilidade (termo_codigo, termo_versao, termo_titulo, termo_texto_completo, termo_data_inicio_vigencia, termo_status, usu_cadastro_id, termo_data_hora_cadastro)
VALUES (
    'TERMO_EPI',
    '2.0',
    'Termo de Responsabilidade Eletrônico de EPI',
    'Declaro estar recebendo, gratuitamente e sem qualquer ônus, os Equipamentos de Proteção Individual — EPIs discriminados neste termo. Declaro estar ciente da obrigatoriedade de sua utilização durante a execução das atividades para as quais foram fornecidos, conforme as orientações da empresa e a legislação aplicável. Declaro que recebi informações e orientações básicas quanto à correta utilização, ajuste, limitações, inspeção, limpeza/higienização de rotina, guarda, conservação e solicitação de substituição dos equipamentos fornecidos. Comprometo-me a utilizar os equipamentos corretamente, zelar por sua guarda e conservação e comunicar imediatamente à empresa qualquer dano, desgaste, contaminação, perda de eficiência, extravio ou inadequação, solicitando sua substituição. A empresa permanecerá responsável pelos procedimentos de higienização especializada, manutenção periódica e substituição, quando aplicáveis, conforme as orientações do fabricante ou importador. Estou ciente de que o prazo de validade do produto, a vida útil estimada e a validade do Certificado de Aprovação — CA são informações distintas. Independentemente desses prazos, o equipamento deverá ser substituído quando não apresentar condições seguras de uso. Os itens de segurança que não possuam Certificado de Aprovação serão registrados separadamente por modelo, lote ou identificação equivalente, sem serem caracterizados como Equipamento de Proteção Individual nos termos da NR-6. O descumprimento injustificado das orientações de segurança poderá resultar na adoção das medidas administrativas e disciplinares cabíveis, observados a legislação vigente, os procedimentos internos da empresa, a apuração dos facts e o direito de manifestação do trabalhador. A validação por senha ou PIN confirma o recebimento dos itens discriminados, a ciência do conteúdo deste termo e a aceitação das responsabilidades nele descritas.',
    NOW(),
    'ATIVO',
    1,
    NOW()
);

-- 3. Adicionar campos de versionamento e snapshot do termo em Entrega_EPIs
ALTER TABLE Entrega_EPIs
    ADD COLUMN termo_id INT NULL,
    ADD COLUMN termo_versao VARCHAR(20) NULL,
    ADD COLUMN texto_termo_snapshot TEXT NULL,
    ADD COLUMN data_hora_aceite DATETIME NULL,
    ADD COLUMN metodo_aceite VARCHAR(30) NULL,
    ADD COLUMN hash_termo VARCHAR(255) NULL,
    ADD CONSTRAINT fk_entrega_termo FOREIGN KEY (termo_id) REFERENCES Termos_Responsabilidade(termo_id);

-- 4. Ampliar ENUM de tipo_item na tabela EPIs para conter Uniforme e Outro
ALTER TABLE EPIs
    MODIFY COLUMN epi_tipo_item ENUM('EPI_COM_CA', 'ITEM_SEGURANCA_SEM_CA', 'UNIFORME', 'OUTRO') NOT NULL DEFAULT 'EPI_COM_CA';
