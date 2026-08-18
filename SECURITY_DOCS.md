# Documentação de Segurança da Informação — Gestão_EPI

Esta documentação descreve a arquitetura de segurança do sistema **Gestão_EPI**, detalhando as abordagens criptográficas adotadas, o controle de acesso, o fluxo de dados e os princípios de integridade recomendados para utilização acadêmica no Trabalho de Conclusão de Curso (TCC).

---

## 1. Visão Geral da Arquitetura

A segurança do sistema **Gestão_EPI** foi estruturada em múltiplas camadas independentes, seguindo o princípio da *Defesa em Profundidade*. A API PHP funciona como um controlador central de segurança: todas as validações, regras de negócio e manipulações criptográficas ocorrem exclusivamente nela.

O tráfego de rede entre clientes (Aplicativo Android / futuro Sistema Web) e a API é protegido por protocolos seguros de trânsito (**HTTPS**), impedindo ataques de interceptação (*Man-in-the-Middle*). Os dados confidenciais armazenados em banco de dados (**MySQL**) passam por proteção antes da escrita.

```text
                    USUÁRIO
                       │
                       ▼
                ANDROID / WEB
                       │
                       ▼
                     HTTPS
                       │
                       ▼
                 ┌───────────┐
                 │  API PHP  │
                 └─────┬─────┘
                       │
        ┌──────────────┼──────────────┐
        │              │              │
        ▼              ▼              ▼
 CONTROLE DE       CRIPTOGRAFIA       HASH
 ACESSO/RBAC       AES-256-GCM    SHA-256 + SALT
        │              │              │
        └──────────────┼──────────────┘
                       │
                       ▼
                  MYSQL DATABASE
                       │
              ┌────────┴────────┐
              │                 │
              ▼                 ▼
        DADOS PESSOAIS       SENHAS/PIN
        AES-256-GCM         SHA-256 + SALT
        REVERSÍVEIS         IRREVERSÍVEIS
```

---

## 2. Diferença Teórica entre Criptografia e Hash

Para blindar o banco de dados de vazamentos, a arquitetura divide a informação em dois grupos lógicos:

### 2.1 Criptografia Simétrica Reversível (AES-256-GCM)
Utilizada para dados pessoais que precisam ser consultados e exibidos na aplicação por operadores autorizados (ex: CPF e número eSocial do Funcionário). 
O **AES-256-GCM** garante:
* **Confidencialidade**: Apenas quem possui a chave secreta pode ler o dado original.
* **Integridade e Autenticidade (GCM)**: O modo *Galois/Counter Mode* gera uma *Authentication Tag* (tag de autenticação). Se o dado cifrado ou as configurações forem alterados no MySQL por um invasor, a API detecta a violação e recusa a descriptografia.

### 2.2 Hash Criptográfico Irreversível (SHA-256 + Salt)
Utilizado para credenciais que servem apenas para validação de acesso (ex: Senha de login do Usuário e PIN de assinatura eletrônica do Funcionário).
O hash é uma função matemática unidirecional. Não existe processo reverso para descobrir a senha original a partir do hash.
* **Salt Individual**: Para mitigar ataques de tabelas pré-computadas (*Rainbow Tables*), cada usuário e cada funcionário possuem um **Salt** exclusivo de 16 bytes gerado aleatoriamente. O hash gravado é o resultado de `SHA-256(Salt + Senha)`. Mesmo se dois usuários possuírem a mesma senha (ex: "123456"), seus hashes gravados no banco de dados serão totalmente diferentes.

---

## 3. Pesquisa Eficiente em Dados Criptografados (Blind Index)

Como a criptografia **AES-GCM** utiliza um vetor de inicialização (IV) dinâmico e aleatório para cada registro, o mesmo CPF criptografado duas vezes gerará textos cifrados diferentes. Isso impede a busca direta no banco de dados utilizando queries do tipo:
`SELECT * FROM funcionarios WHERE fun_cpf_enc = '...'`

Para contornar esse obstáculo com performance e segurança, foi implementado o conceito de **Blind Index**:
1. O CPF fornecido é normalizado (removendo pontos, traços e espaços).
2. É gerado um hash seguro **HMAC-SHA-256** do CPF normalizado utilizando uma chave secreta separada (`HMAC_KEY`).
3. Esse valor é gravado na coluna `fun_cpf_lookup`.
4. As buscas exatas são realizadas comparando a chave gerada com o valor de lookup indexado, preservando a confidencialidade do CPF original.

---

## 4. Controle de Acesso Baseado em Perfis (RBAC) e Mascaramento

O controle de acesso de privilégios é validado diretamente na API PHP de forma imperativa (no backend), não confiando apenas na ocultação de botões na interface do aplicativo.
Os perfis suportados são:
* `ADMINISTRADOR`
* `RH_ADMINISTRATIVO`
* `TECNICO_SST`
* `ALMOXARIFE_OPERADOR`
* `GESTOR`

### Mascaramento Automático LGPD
Com base nas diretrizes da LGPD (Lei Geral de Proteção de Dados), os dados descriptografados sofrem mascaramento dinâmico na API antes do envio aos clientes:
* Perfis administrativos (`ADMINISTRADOR`, `RH_ADMINISTRATIVO`) visualizam CPF e eSocial puros e completos.
* Perfis operacionais (`TECNICO_SST`, `ALMOXARIFE_OPERADOR`, `GESTOR`) visualizam os dados com máscara:
  - **CPF**: `***.***.***-01` (exibe apenas os últimos 2 dígitos).
  - **eSocial**: `***.***.5678` (exibe apenas os últimos 4 dígitos).

---

## 5. Gerenciamento de Chaves e Variáveis de Ambiente

As chaves criptográficas (`AES_KEY` e `HMAC_KEY`) **nunca** são persistidas no banco de dados, em arquivos públicos do servidor ou comitadas para repositórios Git/GitHub.
* **Armazenamento Seguro**: As chaves são mantidas exclusivamente em variáveis de ambiente (.env local ou variáveis de painel de controle do Render em produção).
* **Estrutura de Rotação**: A arquitetura prevê versionamento futuro da chave AES sem alteração da estrutura de banco.

---

## 6. Logs de Auditoria e Resiliência a Tentativas (Brute-Force)

### Bloqueio Temporário
Para evitar ataques de força bruta:
* **Usuários**: Bloqueio temporário de 15 minutos após 5 tentativas incorretas consecutivas.
* **PIN de Assinatura**: Bloqueio da assinatura do funcionário após 3 tentativas inválidas consecutivas.

### Auditoria de Segurança
Todos os eventos relevantes (logins, falhas de login, bloqueios, cadastros, alterações e validações de PIN) geram logs estruturados de auditoria contendo timestamp, usuário responsável e descrição legível do evento, **omitindo** qualquer informação sigilosa como senhas, PINs puros ou chaves secretas.
