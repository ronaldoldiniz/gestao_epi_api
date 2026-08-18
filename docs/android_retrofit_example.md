# Exemplo de Integração Android Studio (Retrofit em Java)

Esta documentação fornece o modelo técnico para consumir a API REST de Gestão EPI usando a biblioteca **Retrofit 2** em Java no Android Studio.

## 1. Configuração do Base URL

Ao testar o aplicativo Android:
- **No Emulador Oficial do Android**: utilize o endereço de loopback especial do roteador do emulador que aponta para o localhost da sua máquina:
  `http://10.0.2.2/gestao_epi_api/`
- **Em Dispositivo Físico (celular via Wi-Fi na rede local)**: utilize o IP de rede do servidor Apache/XAMPP:
  `http://IP_DO_SERVIDOR/gestao_epi_api/`

---

## 2. Dependências do Gradle (`app/build.gradle`)

Adicione as dependências do Retrofit e do conversor do Gson no seu projeto Android:

```groovy
dependencies {
    implementation 'com.squareup.retrofit2:retrofit:2.9.0'
    implementation 'com.squareup.retrofit2:converter-gson:2.9.0'
    implementation 'com.squareup.okhttp3:logging-interceptor:4.9.3'
}
```

---

## 3. Estrutura das Classes Java

### 3.1. Classe de Resposta Padrão (`ApiResponse.java`)

Todas as rotas da API retornam este padrão genérico:

```java
package br.com.gestao_epi.network;

public class ApiResponse<T> {
    private boolean success;
    private String message;
    private T data;

    public boolean isSuccess() { return success; }
    public String getMessage() { return message; }
    public T getData() { return data; }
}
```

### 3.2. Modelo de Login (`LoginRequest.java` e `LoginResponse.java`)

```java
package br.com.gestao_epi.network.models;

public class LoginRequest {
    private String usu_login;
    private String senha;

    public LoginRequest(String usu_login, String senha) {
        this.usu_login = usu_login;
        this.senha = senha;
    }
}
```

```java
package br.com.gestao_epi.network.models;

public class LoginResponse {
    private String token;
    private Usuario usuario;

    public String getToken() { return token; }
    public Usuario getUsuario() { return usuario; }

    public static class Usuario {
        private int usu_id;
        private String usu_login;
        private String usu_perfil;

        public int getUsuId() { return usu_id; }
        public String getUsuLogin() { return usu_login; }
        public String getUsuPerfil() { return usu_perfil; }
    }
}
```

### 3.3. Modelo de Entrega de EPI (`EntregaRequest.java`)

```java
package br.com.gestao_epi.network.models;

import java.util.List;

public class EntregaRequest {
    private int fun_id;
    private String entr_motivo;
    private String pin;
    private List<Item> itens;

    public EntregaRequest(int fun_id, String entr_motivo, String pin, List<Item> itens) {
        this.fun_id = fun_id;
        this.entr_motivo = entr_motivo;
        this.pin = pin;
        this.itens = itens;
    }

    public static class Item {
        private int epi_id;
        private int item_quantidade;

        public Item(int epi_id, int item_quantidade) {
            this.epi_id = epi_id;
            this.item_quantidade = item_quantidade;
        }
    }
}
```

---

## 4. Declaração do Serviço Retrofit (`ApiService.java`)

```java
package br.com.gestao_epi.network;

import br.com.gestao_epi.network.models.*;
import java.util.List;
import retrofit2.Call;
import retrofit2.http.*;

public interface ApiService {

    @GET("health")
    Call<ApiResponse<Void>> checkHealth();

    @POST("auth/login")
    Call<ApiResponse<LoginResponse>> login(@Body LoginRequest request);

    @POST("auth/logout")
    Call<ApiResponse<Void>> logout();

    @GET("funcionarios/qrcode/{codigo}")
    Call<ApiResponse<Funcionario>> getFuncionarioByQrCode(@Path("codigo") String codigo);

    @POST("entregas")
    Call<ApiResponse<EntregaResponse>> registrarEntrega(@Body EntregaRequest request);
}
```

---

## 5. Gerenciador do Cliente HTTP com Interceptor de Token (`ApiClient.java`)

Este gerenciador garante que, uma vez armazenado o Token de login (por exemplo, em `SharedPreferences`), ele será injetado automaticamente em todas as requisições subsequentes no header HTTP `Authorization: Bearer <TOKEN>`.

```java
package br.com.gestao_epi.network;

import android.content.Context;
import android.content.SharedPreferences;
import okhttp3.Interceptor;
import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.Response;
import okhttp3.logging.HttpLoggingInterceptor;
import retrofit2.Retrofit;
import retrofit2.converter.gson.GsonConverterFactory;
import java.io.IOException;

public class ApiClient {
    private static final String BASE_URL = "http://10.0.2.2/gestao_epi_api/"; // No emulador
    private static Retrofit retrofit = null;

    public static ApiService getService(final Context context) {
        if (retrofit === null) {
            // Interceptador para adicionar o Token Bearer
            Interceptor authInterceptor = new Interceptor() {
                @Override
                public Response intercept(Chain chain) throws IOException {
                    SharedPreferences prefs = context.getSharedPreferences("GestaoEpiPrefs", Context.MODE_PRIVATE);
                    String token = prefs.getString("auth_token", "");

                    Request.Builder builder = chain.request().newBuilder();
                    if (!token.isEmpty()) {
                        builder.addHeader("Authorization", "Bearer " + token);
                    }
                    return chain.proceed(builder.build());
                }
            };

            // Logger para depuração em desenvolvimento
            HttpLoggingInterceptor logging = new HttpLoggingInterceptor();
            logging.setLevel(HttpLoggingInterceptor.Level.BODY);

            OkHttpClient client = new OkHttpClient.Builder()
                    .addInterceptor(authInterceptor)
                    .addInterceptor(logging)
                    .build();

            retrofit = new Retrofit.Builder()
                    .baseUrl(BASE_URL)
                    .addConverterFactory(GsonConverterFactory.create())
                    .client(client)
                    .build();
        }
        return retrofit.create(ApiService.class);
    }
}
```

---

## 6. Exemplo Prático de Login

```java
ApiService apiService = ApiClient.getService(getApplicationContext());
LoginRequest request = new LoginRequest("admin", "123456");

apiService.login(request).enqueue(new Callback<ApiResponse<LoginResponse>>() {
    @Override
    public void onResponse(Call<ApiResponse<LoginResponse>> call, Response<ApiResponse<LoginResponse>> response) {
        if (response.isSuccessful() && response.body() !== null && response.body().isSuccess()) {
            LoginResponse loginData = response.body().getData();
            String token = loginData.getToken();

            // Salva token nas SharedPreferences
            SharedPreferences.Editor editor = getSharedPreferences("GestaoEpiPrefs", MODE_PRIVATE).edit();
            editor.putString("auth_token", token);
            editor.putString("user_profile", loginData.getUsuario().getUsuPerfil());
            editor.apply();

            Toast.makeText(LoginActivity.this, "Bem-vindo!", Toast.LENGTH_SHORT).show();
            // Abre a tela principal...
        } else {
            Toast.makeText(LoginActivity.this, "Usuário ou senha inválidos.", Toast.LENGTH_SHORT).show();
        }
    }

    @Override
    public void onFailure(Call<ApiResponse<LoginResponse>> call, Throwable t) {
        Toast.makeText(LoginActivity.this, "Erro de rede: " + t.getMessage(), Toast.LENGTH_SHORT).show();
    }
});
```
