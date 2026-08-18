# Rotas da API de Gestão de EPI - Usuários e Autenticação

Este documento descreve as rotas e o fluxo de autenticação com foco na exigência de troca de senha no primeiro acesso de novos usuários.

---

## 1. Autenticação

### POST /auth/login
Efetua o login de um usuário e retorna o token de acesso (JWT).

- **Corpo da Requisição (JSON):**
  ```json
  {
    "usu_login": "almox_teste",
    "senha": "senha_temporaria_ou_real"
  }
  ```

- **Resposta (Primeiro Acesso - Troca de Senha Obrigatória):**
  Status: `200 OK`
  ```json
  {
    "success": true,
    "message": "Login realizado com sucesso. Troca de senha obrigatória.",
    "data": {
      "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
      "exige_troca_senha": true,
      "usuario": {
        "usu_id": 6,
        "usu_login": "almox_teste",
        "usu_perfil": "ALMOXARIFE_OPERADOR",
        "usu_status": "ATIVO"
      }
    }
  }
  ```

- **Resposta (Acesso Normal - Senha Já Alterada):**
  Status: `200 OK`
  ```json
  {
    "success": true,
    "message": "Login realizado com sucesso.",
    "data": {
      "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
      "exige_troca_senha": false,
      "usuario": {
        "usu_id": 6,
        "usu_login": "almox_teste",
        "usu_perfil": "ALMOXARIFE_OPERADOR",
        "usu_status": "ATIVO"
      }
    }
  }
  ```

---

### POST /auth/alterar-senha-primeiro-acesso
Permite que o usuário altere sua senha temporária no primeiro acesso.

- **Requer Autenticação:** `Bearer <token>`
- **Corpo da Requisição (JSON):**
  ```json
  {
    "senha_atual": "senha_temporaria",
    "nova_senha": "nova_senha_segura1",
    "confirmar_senha": "nova_senha_segura1"
  }
  ```
- **Políticas de Senha:**
  - Mínimo de 6 caracteres.
  - Conter pelo menos uma letra e um número.

- **Resposta de Sucesso:**
  Status: `200 OK`
  ```json
  {
    "success": true,
    "message": "Senha alterada com sucesso.",
    "data": null
  }
  ```

---

### POST /auth/recuperar-senha
Permite que o usuário redefina sua senha no fluxo de recuperação sem necessidade de estar autenticado.

- **Corpo da Requisição (JSON):**
  ```json
  {
    "usu_login": "sst_user",
    "nova_senha": "nova_senha_segura1",
    "confirmar_senha": "nova_senha_segura1"
  }
  ```
- **Políticas de Senha:**
  - Mínimo de 6 caracteres.
  - Conter pelo menos uma letra e um número.

- **Resposta de Sucesso:**
  Status: `200 OK`
  ```json
  {
    "success": true,
    "message": "Senha redefinida com sucesso. Utilize a nova senha para acessar o sistema.",
    "data": null
  }
  ```

---

## 2. Gerenciamento de Usuários (Apenas ADMINISTRADOR)

### GET /usuarios
Lista todos os usuários do sistema. Senhas e hashes nunca são retornados.

- **Requer Autenticação:** `Bearer <token>` (Apenas `ADMINISTRADOR`)
- **Resposta:**
  Status: `200 OK`
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
        "usu_data_cadastro": "2026-06-07 10:00:00",
        "usu_exige_troca_senha": false
      }
    ]
  }
  ```

---

### POST /usuarios
Cria um novo usuário no sistema.

- **Requer Autenticação:** `Bearer <token>` (Apenas `ADMINISTRADOR`)
- **Corpo da Requisição (JSON):**
  ```json
  {
    "usu_login": "novo_usuario",
    "senha_temporaria": "123456",
    "usu_perfil": "ALMOXARIFE_OPERADOR",
    "usu_status": "ATIVO",
    "exigir_troca_senha": true
  }
  ```
  *(Também aceita "senha" como fallback para retrocompatibilidade).*

- **Resposta:**
  Status: `201 Created`
  ```json
  {
    "success": true,
    "message": "Usuário cadastrado com sucesso.",
    "data": {
      "usu_id": 7,
      "usu_login": "novo_usuario",
      "usu_perfil": "ALMOXARIFE_OPERADOR",
      "usu_status": "ATIVO",
      "usu_data_cadastro": "2026-06-07 10:55:00",
      "usu_exige_troca_senha": true
    }
  }
  ```

---

### PUT /usuarios/{id}
Atualiza as configurações de perfil, status e exigência de troca de senha de um usuário.

- **Requer Autenticação:** `Bearer <token>` (Apenas `ADMINISTRADOR`)
- **Corpo da Requisição (JSON):**
  ```json
  {
    "usu_perfil": "GESTORES",
    "usu_status": "ATIVO",
    "usu_exige_troca_senha": true
  }
  ```

- **Resposta:**
  Status: `200 OK`
  ```json
  {
    "success": true,
    "message": "Usuário atualizado com sucesso.",
    "data": {
      "usu_id": 7,
      "usu_login": "novo_usuario",
      "usu_perfil": "GESTORES",
      "usu_status": "ATIVO",
      "usu_data_cadastro": "2026-06-07 10:55:00",
      "usu_exige_troca_senha": true
    }
  }
  ```

---

### POST /usuarios/{id}/redefinir-senha
Redefine a senha de um usuário existente para uma senha temporária, exigindo a troca no próximo acesso.

- **Requer Autenticação:** `Bearer <token>` (Apenas `ADMINISTRADOR`)
- **Corpo da Requisição (JSON):**
  ```json
  {
    "senha_temporaria": "redefinida123",
    "exigir_troca_senha": true
  }
  ```

- **Resposta:**
  Status: `200 OK`
  ```json
  {
    "success": true,
    "message": "Senha redefinida com sucesso.",
    "data": {
      "usu_id": 7,
      "usu_login": "novo_usuario",
      "usu_perfil": "GESTORES",
      "usu_status": "ATIVO",
      "usu_data_cadastro": "2026-06-07 10:55:00",
      "usu_exige_troca_senha": true
    }
  }
  ```
