# Changelog

Todas as alterações notáveis neste projeto serão documentadas neste arquivo.


## [2026-09-25] - Uniformização Global do Fuso Horário (America/Sao_Paulo UTC-3) e Otimização da Auditoria

### Adicionado / Modificado
- **Padronização Global do Fuso Horário (America/Sao_Paulo)**:
  - Definido o fuso horário oficial `date_default_timezone_set('America/Sao_Paulo')` na inicialização do PHP (`index.php` e `config/database.php`).
  - Adicionada a instrução `SET time_zone = '-03:00'` no comando de inicialização PDO e após a abertura da conexão em `config/database.php`.
  - Substituído o uso da função `NOW()` do MySQL em `core/Audit.php` e nos models (`EntregaEpi.php`, `ItemEntrega.php`, `AssinaturaEletronica.php`) pela data/hora formatada em PHP (`date('Y-m-d H:i:s')`), garantindo que todas as transações e logs sejam gravados com a hora exata de Brasília independentemente do fuso do servidor de hospedagem em nuvem (Render/Linux).
  - Atualizada a consulta de contadores do dia no `DashboardController.php` para considerar os limites diários (`00:00:00` às `23:59:59`) no fuso `America/Sao_Paulo`.
- **Saneamento e Normalização da Auditoria (`core/Audit.php`)**:
  - Otimizada a função `Audit::compareAndLog` para ignorar campos de metadados internos (`*_id`, `*_enc`, `*_iv`, `*_tag`, `*_lookup`, `*_salt`, `*_hash`, `*_snapshot`, `created_at`, `updated_at`, `client_operation_id`, `device_id`, `operation_origin`).
  - Tratamento robusto para evitar falsos positivos na comparação de tipos (datas `YYYY-MM-DD` vs `YYYY-MM-DD 00:00:00`, e booleanos).
  - Aprimorado o formatador `Audit::formatValue` para suprimir o horário `00:00` em atributos exclusivamente de data (ex: `Validade do C.A.`).
- **Banco de Dados (Saneamento Histórico Aiven Cloud DB)**:
  - Realizado saneamento retroativo na tabela `log_auditoria` para registros de log gravados anteriormente em fuso UTC (+3h), alinhando o histórico do banco ao fuso de Brasília.


## [2026-09-24] - Restrição UNIQUE para Matrículas e Validação na API

### Adicionado
- **Validação de Matrícula Única na API**:
  - Criado o método `findByEsocial()` no model `Models\Funcionario`.
  - Adicionada checagem prévia de duplicidade de Matrícula (`findByEsocial`) nos métodos `store()` e `update()` do `Controllers\FuncionariosController`, retornando código de resposta `HTTP 409` (*"Já existe um funcionário cadastrado com esta Matrícula (eSocial)."*).
  - Adicionado tratamento para exceção de chave duplicada `1062` referente à coluna `fun_esocial`.

### Alterado / Corrigido
- **Banco de Dados (MySQL / Aiven Cloud DB)**:
  - Aplicada a restrição de integridade `ALTER TABLE funcionarios ADD UNIQUE KEY fun_esocial (fun_esocial);` no banco de dados central.
  - Realizado o saneamento de registros de teste duplicados com a re-criptografia completa AES-256-GCM dos atributos `fun_esocial_enc`, `fun_esocial_iv` e `fun_esocial_tag`.

## [2026-09-22] - Correções de Sincronização e Banco de Dados

### Corrigido
- Ajuste no nome da coluna de relacionamento de usuário na tabela `operacoes_idempotentes`. A referência foi corrigida de `usu_id` para `usuario_id` nos controllers `EntregasController.php` e `DevolucoesController.php`, garantindo o sucesso no registro de idempotência das transações e resolvendo falhas nas entregas off-line.
- Ajuste no nome da coluna de vínculo de substituição de EPI. Corrigido de `entr_id_substituicao` para `item_devolucao_vinculo_entrega_id` no model `ItemEntrega.php` e no `EntregasController.php` para alinhar perfeitamente com o schema do banco de dados na nuvem (Aiven) e evitar falhas SQL nas transações de devolução com substituição vinculada.

- **Banco de Dados/API**: Renomeada a coluna `usuario_id` para `usu_id` na tabela `operacoes_idempotentes` para manter o padrão de nomenclatura.
- **Documentação**: Dicionário de Dados do projeto revisado e perfeitamente sincronizado com as tabelas de `funcionarios`, `usuarios`, `assinatura_eletronica`, `epis`, `termos_responsabilidade`, `entrega_epis`, `itens_entrega`, `historico_preco_epi`, `operacoes_idempotentes`, `log_auditoria` e `Views (LGPD)`.

## [2026-09-23] - Injeção Universal de Metadados em Logs de Auditoria

### Adicionado
- Implementada a "Injeção Cirúrgica Universal" no arquivo `core/Audit.php`.
- Agora, a função estática genérica `Audit::log` (usada em todos os endpoints da API para gravar histórico) intercepta de forma nativa o payload JSON (`log_detalhes`) enviado por qualquer Controller e injeta silenciosamente um bloco auxiliar de `contexto`.
- O bloco de contexto rastreia a origem da requisição, extraindo o header `User-Agent` personalizado que é enviado pelo App Android e formatando o ID do dispositivo utilizado na requisição.
- Inclusão do IP de origem e introdução do atributo interno `versao_log = 2` no JSON para suportar retrocompatibilidade perfeita com o Dashboard e o Frontend sem exigir atualizações manuais nos registros do Banco de Dados.
