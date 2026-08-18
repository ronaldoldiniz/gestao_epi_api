# Documentação dos Endpoints da API Gestão EPI

Esta API expõe serviços em formato JSON para autenticação, controle de usuários, funcionários, gerenciamento de EPIs, controle de assinaturas eletrônicas, entregas, devoluções e relatórios.

## Resposta Padrão de Erro (4xx e 5xx)

```json
{
  "success": false,
  "message": "Mensagem descritiva do erro.",
  "data": null
}
```

---

## 1. Health Check

### GET `/health`
Retorna o estado operacional básico da API.
- **Autenticação**: Não necessária.
- **Resposta (200 OK)**:
```json
{
  "success": true,
  "message": "API Gestão EPI online.",
  "data": null
}
```

---

## 2. Autenticação

### POST `/auth/login`
Efetua a autenticação do usuário.
- **Autenticação**: Não necessária.
- **Corpo da Requisição**:
```json
{
  "usu_login": "admin",
  "senha": "123"
}
```
- **Resposta (200 OK)**:
```json
{
  "success": true,
  "message": "Login realizado com sucesso.",
  "data": {
    "token": "header.payload.signature",
    "usuario": {
      "usu_id": 1,
      "usu_login": "admin",
      "usu_perfil": "ADMINISTRADOR"
    }
  }
}
```

### POST `/auth/logout`
Invalida o token no cliente e registra auditoria.
- **Autenticação**: Obrigatória.
- **Headers**: `Authorization: Bearer <token>`
- **Resposta (200 OK)**:
```json
{
  "success": true,
  "message": "Logout realizado com sucesso.",
  "data": null
}
```

### GET `/auth/me`
Retorna dados do usuário atualmente logado com base no Token.
- **Autenticação**: Obrigatória.
- **Headers**: `Authorization: Bearer <token>`
- **Resposta (200 OK)**:
```json
{
  "success": true,
  "message": "Informações do usuário autenticado.",
  "data": {
    "usuario": {
      "usu_id": 1,
      "usu_login": "admin",
      "usu_perfil": "ADMINISTRADOR"
    }
  }
}
```

---

## 3. Usuários (Apenas ADMINISTRADOR)

### GET `/usuarios`
Lista todos os usuários.
- **Autenticação**: Obrigatória (`ADMINISTRADOR`).
- **Resposta (200 OK)**:
```json
{
  "success": true,
  "message": "Usuários listados com sucesso.",
  "data": [
    {
      "usu_id": 1,
      "usu_login": "admin",
      "usu_perfil": "ADMINISTRADOR",
      "usu_status": "ATIVO",
      "usu_data_cadastro": "2026-06-06 22:00:00",
      "usu_tentativas_falha": 0,
      "usu_data_bloqueio": null,
      "usu_motivo_bloqueio": null
    }
  ]
}
```

### POST `/usuarios`
Cadastra um novo usuário.
- **Autenticação**: Obrigatória (`ADMINISTRADOR`).
- **Corpo da Requisição**:
```json
{
  "usu_login": "almoxarife",
  "senha": "123",
  "usu_perfil": "ALMOXARIFE_OPERADOR",
  "usu_status": "ATIVO"
}
```
- **Resposta (201 Created)**:
```json
{
  "success": true,
  "message": "Usuário cadastrado com sucesso.",
  "data": {
    "usu_id": 2,
    "usu_login": "almoxarife",
    "usu_perfil": "ALMOXARIFE_OPERADOR"
  }
}
```

### PUT `/usuarios/{id}`
Atualiza dados do usuário.
- **Autenticação**: Obrigatória (`ADMINISTRADOR`).
- **Corpo da Requisição**:
```json
{
  "usu_perfil": "GESTOR"
}
```
- **Resposta (200 OK)**

### DELETE `/usuarios/{id}`
Exclusão lógica de usuário (inativação).
- **Autenticação**: Obrigatória (`ADMINISTRADOR`).
- **Resposta (200 OK)**

---

## 4. Funcionários

### GET `/funcionarios`
Lista funcionários. Perfis SST, Almoxarife e Gestor recebem dados de CPF e eSocial mascarados (`***.***.***-XX`).
- **Autenticação**: Obrigatória (Todos os perfis).
- **Resposta (200 OK)**

### GET `/funcionarios/{id}`
Mostra funcionário por ID.
- **Autenticação**: Obrigatória (Todos os perfis).

### GET `/funcionarios/qrcode/{codigo}`
Localiza funcionário pelo QR Code.
- **Autenticação**: Obrigatória (Todos os perfis).

### POST `/funcionarios`
Cadastra funcionário.
- **Autenticação**: Obrigatória (`ADMINISTRADOR`, `RH_ADMINISTRATIVO`).
- **Corpo da Requisição**:
```json
{
  "fun_nome": "José da Silva",
  "fun_cpf": "12345678901",
  "fun_esocial": "ESO12345",
  "fun_departamento": "Manutenção",
  "fun_cargo": "Mecânico",
  "fun_dataadmissao": "2025-01-10",
  "fun_situacao": "ATIVO"
}
```

---

## 5. EPIs

### GET `/epis`
Lista todos os EPIs.
- **Autenticação**: Obrigatória (Todos os perfis).

### GET `/epis/vencidos`
EPIs com C.A. vencidos.
- **Autenticação**: Obrigatória (Todos os perfis).

### GET `/epis/proximos-vencimento?dias=30`
EPIs com C.A. próximo do vencimento dentro do intervalo de dias.
- **Autenticação**: Obrigatória (Todos os perfis).

### POST `/epis`
Cadastra EPI e cria primeiro histórico de preço.
- **Autenticação**: Obrigatória (`ADMINISTRADOR`, `TECNICO_SST`).
- **Corpo da Requisição**:
```json
{
  "epi_nome": "Luva Nitrílica",
  "epi_ca": "12345",
  "epi_vencimento_ca": "2027-12-31",
  "epi_fabricante": "Volk",
  "epi_validade_uso_dias": 15,
  "epi_valor": 8.50,
  "epi_origem_preco": "COMPRA_DIRETA",
  "epi_localizacao": "PRATELEIRA-C1",
  "hist_nota_fiscal": "NF-9988",
  "hist_fornecedor": "Fornecedor EPIs Ltda"
}
```

---

## 6. Assinaturas Eletrônicas (PIN)

### POST `/assinaturas`
Cadastra o PIN de assinatura do funcionário.
- **Autenticação**: Obrigatória (`ADMINISTRADOR`, `RH_ADMINISTRATIVO`).
- **Corpo da Requisição**:
```json
{
  "fun_id": 1,
  "pin": "1234"
}
```

### POST `/assinaturas/validar`
Valida o PIN do funcionário. Incrementa tentativas falhas e bloqueia caso erre 3 vezes.
- **Autenticação**: Obrigatória (`ADMINISTRADOR`, `RH_ADMINISTRATIVO`, `TECNICO_SST`, `ALMOXARIFE_OPERADOR`).
- **Corpo da Requisição**:
```json
{
  "fun_id": 1,
  "pin": "1234"
}
```

### POST `/assinaturas/redefinir`
Redefine/desbloqueia o PIN do funcionário.
- **Autenticação**: Obrigatória (`ADMINISTRADOR`, `RH_ADMINISTRATIVO`).

---

## 7. Entregas de EPIs

### POST `/entregas`
Cria termo de entrega de EPI assinado via transação MySQL.
- **Autenticação**: Obrigatória (`ADMINISTRADOR`, `TECNICO_SST`, `ALMOXARIFE_OPERADOR`).
- **Corpo da Requisição**:
```json
{
  "fun_id": 1,
  "entr_motivo": "SUBSTITUICAO",
  "pin": "1234",
  "itens": [
    {
      "epi_id": 1,
      "item_quantidade": 1
    },
    {
      "epi_id": 2,
      "item_quantidade": 2
    }
  ]
}
```
- **Resposta (200 OK)**:
```json
{
  "success": true,
  "message": "Entrega registrada com sucesso.",
  "data": {
    "entr_id": 15,
    "entr_hash_assinatura": "b2685718dfd...hash...f18d7162"
  }
}
```

### POST `/entregas/{id}/cancelar`
Cancela a entrega e todos os itens atrelados.
- **Autenticação**: Obrigatória (`ADMINISTRADOR`, `TECNICO_SST`).
- **Corpo da Requisição**:
```json
{
  "motivo": "Funcionário recusou pois o tamanho ficou inadequado."
}
```

---

## 8. Devoluções de EPIs

### POST `/devolucoes`
Registra devolução de um item de entrega específico.
- **Autenticação**: Obrigatória (`ADMINISTRADOR`, `TECNICO_SST`, `ALMOXARIFE_OPERADOR`).
- **Corpo da Requisição**:
```json
{
  "item_id": 15,
  "item_status": "DEVOLVIDO"
}
```

### GET `/devolucoes/funcionario/{fun_id}`
Histórico de EPIs devolvidos pelo funcionário.
- **Autenticação**: Obrigatória (Todos os perfis).
