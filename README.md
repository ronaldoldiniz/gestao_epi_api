# API REST Backend - Gestão EPI

Esta é a API REST backend em PHP puro e MySQL para o aplicativo Android **Gestão EPI**. Ela serve como intermediária obrigatória e segura para que o aplicativo móvel realize autenticação, operações de CRUD e assinaturas eletrônicas com PIN, em conformidade com as boas práticas de segurança, SST e a LGPD.

---

## 🚀 Requisitos do Servidor

- **PHP 8.0 ou superior**
- Extensão **PDO** ativa com o driver **pdo_mysql** habilitado.
- Servidor Web **Apache** (com módulo `mod_rewrite` ativo para suporte a rotas amigáveis via `.htaccess`).
- Banco de dados **MySQL 5.7** ou **MariaDB 10.3** (ou superiores).

---

## 🛠️ Instalação e Configuração

### Opção 1: Configuração no XAMPP (Local)

1. Mova ou clone a pasta `gestao_epi_api` dentro do diretório `htdocs` do seu XAMPP (geralmente `C:\xampp\htdocs\gestao_epi_api`).
2. Certifique-se de que o módulo **Apache** e **MySQL** estão ativos no Painel do XAMPP.
3. Crie o arquivo `config/config.php` a partir do `config.example.php` e ajuste os dados da conexão MySQL se necessário.

### Opção 2: Configuração no Servidor Apache (Linux)

1. Faça o deploy dos arquivos da pasta `gestao_epi_api` para o diretório `/var/www/html/gestao_epi_api/`.
2. Habilite o módulo de reescrita no Apache e reinicie o serviço:
   ```bash
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```
3. Defina as permissões corretas para a pasta da API (evite permissões permissivas como 777, utilize proprietários adequados como `www-data`):
   ```bash
   sudo chown -R www-data:www-data /var/www/html/gestao_epi_api
   ```

---

## 🗄️ Configuração do Banco de Dados

1. Acesse o seu gerenciador do MySQL (como phpMyAdmin) e crie um banco de dados com o nome: `db_gestao_epi`.
2. Importe a estrutura física de tabelas fornecida pelo projeto Android/DB.
3. Execute os scripts SQL da pasta `sql/` na seguinte ordem para configurar os acessos de segurança da API:
   - **`sql/create_api_user.sql`**: Cria o usuário do MySQL `user_api_epi` com privilégios limitados.
   - **`sql/seed_examples.sql`**: Cria a view `vw_funcionarios_mascarado` necessária para as rotas com privacidade (LGPD) e insere dados de testes locais.
   - **`sql/create_admin_example.sql`**: Insere o usuário administrador inicial (`admin` / senha: `123456`) com senha hash adequada.

---

## ⚙️ Arquivo de Configuração (`config/config.php`)

Copie o arquivo `config/config.example.php` para `config/config.php` e altere os dados:

```php
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'dbname' => 'db_gestao_epi',
        'username' => 'user_api_epi', // Usuário limitado criado no SQL
        'password' => 'sua_senha_segura',
        'charset' => 'utf8mb4'
    ],
    'app' => [
        'env' => 'production', // Mude para 'production' ao subir no servidor
        'debug' => false,       // Habilite debug (detalhamento de erros) apenas em local
        'secret_key' => 'chave_secreta_muito_longa_e_segura_aqui_para_os_tokens',
        'token_ttl' => 43200   // Token dura 12 horas
    ],
    // ...
];
```

---

## 🧪 Como Testar a API

### 1. Testando o Health Check
Abra o navegador ou use o terminal para acessar:
```bash
curl -X GET http://localhost/gestao_epi_api/health
```
**Resposta esperada (200 OK):**
```json
{
  "success": true,
  "message": "API Gestão EPI online.",
  "data": null
}
```

### 2. Testando o Login
```bash
curl -X POST http://localhost/gestao_epi_api/auth/login \
     -H "Content-Type: application/json" \
     -d '{"usu_login": "admin", "senha": "123456"}'
```
**Resposta esperada (200 OK):**
```json
{
  "success": true,
  "message": "Login realizado com sucesso.",
  "data": {
    "token": "seu_token_jwt_aqui...",
    "usuario": {
      "usu_id": 1,
      "usu_login": "admin",
      "usu_perfil": "ADMINISTRADOR"
    }
  }
}
```

### 3. Acessando uma rota protegida (como listar funcionários)
Utilize o token retornado no login dentro do Header `Authorization`:
```bash
curl -X GET http://localhost/gestao_epi_api/funcionarios \
     -H "Authorization: Bearer seu_token_jwt_aqui..."
```

---

## 🗺️ Mapa de Endpoints Mínimos

Para uma lista detalhada contendo exemplos de requisições e respostas JSON de todas as rotas de Usuários, Funcionários, EPIs, Assinaturas Eletrônicas, Entregas e Relatórios, consulte os arquivos:
👉 [docs/endpoints.md](file:///c:/xampp/htdocs/gestao_epi_api/docs/endpoints.md)
👉 [API_ROUTES.md](file:///c:/xampp/htdocs/gestao_epi_api/API_ROUTES.md) *(Detalha as rotas de autenticação, troca de senha no primeiro acesso e gerenciamento de usuários).*

---

## 🔐 Observações de Segurança e LGPD

1. **Acesso ao Banco**: O aplicativo Android **nunca** deve fazer conexão direta com o MySQL do servidor da empresa. O acesso se dá exclusivamente por HTTPS através da API.
2. **Minimização de Dados (LGPD)**: Quando usuários com perfil de menor privilégio (`TECNICO_SST`, `ALMOXARIFE_OPERADOR`, `GESTOR`) buscarem dados de funcionários, a API consultará a view `vw_funcionarios_mascarado`, ocultando CPFs e dados do eSocial.
3. **Troca de Senha Obrigatória no Primeiro Acesso**: Ao criar ou redefinir a senha de um usuário, o administrador pode fornecer uma senha temporária e definir que a troca de senha é obrigatória. Nesses casos, o usuário fica restrito e não pode acessar nenhum endpoint do sistema exceto os de redefinição de senha (`POST /auth/alterar-senha-primeiro-acesso`), informações do perfil (`GET /auth/me`) ou logout (`POST /auth/logout`).
4. **Segurança de Senhas e PIN (Hash Irreversível):** As senhas de acesso dos operadores e os PINs de assinatura eletrônica dos funcionários são processados e armazenados utilizando a função de dispersão criptográfica **SHA-256 combinada com Salt exclusivo**, garantindo a irreversibilidade matemática absoluta de senhas e PINs.
5. **Criptografia de Dados Pessoais (AES-256-GCM Reversível):** Em atendimento à LGPD, informações pessoais sensíveis e de identificação de colaboradores (como CPF e número do eSocial) são gravadas e mantidas sob criptografia simétrica forte **AES-256-GCM**, permitindo a descriptografia controlada em tempo real em canais de auditoria seguros (HTTPS).
6. **Log de Auditoria**: Todas as inserções, atualizações, login, logouts, validações de PIN, cancelamento e devoluções são registradas na tabela `log_auditoria` indicando quem efetuou a operação.
7. **HTTPS Obrigatório em Produção**: Configure um certificado SSL no seu servidor Apache em produção para blindar os dados em trânsito e proteger os tokens contra interceptações (ataques Man-in-the-Middle).

---

## 🚀 Histórico de Atualizações do Backend (25/08/2026)

*   **Enriquecimento Dinâmico de Logs Legados (Reconstrução em Runtime):**
    *   Atualizado o endpoint de exibição individual `GET /logs/{id}` no [`LogsController.php`](file:///c:/xampp/htdocs/gestao_epi_api_5/controllers/LogsController.php) para reconstruir dinamicamente dados do EPI (lote, CA, fabricante) e do colaborador (cargo, matrícula) para logs históricos antigos que não possuíam o JSON rico estruturado v2.
*   **Filtro por Funcionário na API de Logs:**
    *   Integrado o parâmetro `funcionario` na filtragem do endpoint `GET /logs` no model [`LogAuditoria.php`](file:///c:/xampp/htdocs/gestao_epi_api_5/models/LogAuditoria.php) permitindo cruzamentos avançados de logs vinculados a funcionários específicos por nome ou ID.
*   **Resolução de Conflito de Parâmetros no PDO (Bug HY093):**
    *   Ajustada a query de busca textual no model [`LogAuditoria.php`](file:///c:/xampp/htdocs/gestao_epi_api_5/models/LogAuditoria.php) para desmembrar a tag duplicada `:palavra_chave` em tags exclusivas (`:palavra_chave_detalhes`, `:palavra_chave_acao`, etc.), corrigindo o erro `SQLSTATE[HY093]` em servidores de produção com emulação de prepared statements desativada.

---

## 🚀 Histórico de Atualizações do Backend (24/08/2026)

*   **Sincronização Online do Histórico de Preços de EPIs:**
    *   Criada a rota `GET /epis/{id}/historico-precos` no arquivo [`api.php`](file:///c:/xampp/htdocs/gestao_epi_api_5/routes/api.php) protegida por autenticação JWT (acessada pelos perfis `ADMINISTRADOR`, `TECNICO_SST`, `ALMOXARIFE_OPERADOR`, `GESTOR`, `RH_ADMINISTRATIVO`).
    *   Implementado o método de busca ordenada `obterHistoricoPrecos(int $epiId)` no modelo [`Epi.php`](file:///c:/xampp/htdocs/gestao_epi_api_5/models/Epi.php) para retornar todo o histórico de alterações diretamente do banco centralizado na nuvem (Aiven).
    *   Adicionada a respectiva action `historicoPrecos` no controlador [`EpisController.php`](file:///c:/xampp/htdocs/gestao_epi_api_5/controllers/EpisController.php) para responder no formato padrão de JSON da API.

---

## 🚀 Histórico de Atualizações do Backend (13/08/2026)

*   **Rastreabilidade do Último Login Real de Operadores:**
    *   Como a tabela `usuarios` não possui coluna física para o último login (evitando alterações de esquemas desnecessárias), criei uma subquery dinâmica na model [Usuario.php](file:///C:/xampp/htdocs/gestao_epi_api_5/models/Usuario.php#L32) para calcular a data e hora do último login de sucesso registrado na tabela de auditoria:
        `SELECT ..., (SELECT MAX(log_datahora) FROM log_auditoria WHERE usu_id = u.usu_id AND log_acao = 'LOGIN') as usu_ultimo_login FROM usuarios u`.
    *   Mapeado o campo `usu_ultimo_login` na resposta do JSON das rotas `index` (listagem geral) e `show` (busca individual) de operadores no [UsuariosController.php](file:///C:/xampp/htdocs/gestao_epi_api_5/controllers/UsuariosController.php#L34).
