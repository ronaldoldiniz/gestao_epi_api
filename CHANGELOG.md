# Changelog

Todas as alterações notáveis neste projeto serão documentadas neste arquivo.

## [2026-09-22] - Correções de Sincronização e Banco de Dados

### Corrigido
- Ajuste no nome da coluna de relacionamento de usuário na tabela `operacoes_idempotentes`. A referência foi corrigida de `usu_id` para `usuario_id` nos controllers `EntregasController.php` e `DevolucoesController.php`, garantindo o sucesso no registro de idempotência das transações e resolvendo falhas nas entregas off-line.
- Ajuste no nome da coluna de vínculo de substituição de EPI. Corrigido de `entr_id_substituicao` para `item_devolucao_vinculo_entrega_id` no model `ItemEntrega.php` e no `EntregasController.php` para alinhar perfeitamente com o schema do banco de dados na nuvem (Aiven) e evitar falhas SQL nas transações de devolução com substituição vinculada.

- **Banco de Dados/API**: Renomeada a coluna `usuario_id` para `usu_id` na tabela `operacoes_idempotentes` para manter o padrão de nomenclatura.
- **Documentação**: Dicionário de Dados do projeto revisado e perfeitamente sincronizado com as tabelas de `funcionarios`, `usuarios`, `assinatura_eletronica`, `epis`, `termos_responsabilidade`, `entrega_epis`, `itens_entrega`, `historico_preco_epi`, `operacoes_idempotentes`, `log_auditoria` e `Views (LGPD)`.
