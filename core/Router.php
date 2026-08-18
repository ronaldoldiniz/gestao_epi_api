<?php
declare(strict_types=1);

namespace Core;

class Router {
    private array $routes = [];

    /**
     * Registra uma rota com método HTTP, caminho amigável, classe/método handler e perfis permitidos
     */
    public function add(string $method, string $path, string $handler, array $allowedRoles = []): void {
        // Converte a rota amigável como '/usuarios/{id}' em uma expressão regular '^/usuarios/([^/]+)$'
        $regexPath = preg_replace('/\{[a-zA-Z0-9_]+\}/', '([^/]+)', $path);
        $regexPath = '#^' . $regexPath . '$#';

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'regex' => $regexPath,
            'handler' => $handler,
            'allowedRoles' => $allowedRoles
        ];
    }

    /**
     * Tenta encontrar e disparar o controlador/método mapeado para a requisição atual
     */
    public function dispatch(string $requestedMethod, string $requestedUri): void {
        // Separa a URL para obter somente o caminho sem os parâmetros da Query String (GET)
        $requestedUri = parse_url($requestedUri, PHP_URL_PATH) ?? '/';

        // Remove barra no final da URI para padronização (a não ser que seja a raiz '/')
        if ($requestedUri !== '/' && str_ends_with($requestedUri, '/')) {
            $requestedUri = rtrim($requestedUri, '/');
        }

        // Trata o caso de a API estar rodando em um subdiretório (como no XAMPP c:/xampp/htdocs/gestao_epi_api)
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = dirname($scriptName);
        
        // Corrige possíveis barras do Windows no caminho
        $basePath = str_replace('\\', '/', $basePath);
        if ($basePath !== '/' && str_starts_with($requestedUri, $basePath)) {
            $requestedUri = substr($requestedUri, strlen($basePath));
        }

        // Se a URI ficar vazia após remover o prefixo, considera como raiz
        if ($requestedUri === '') {
            $requestedUri = '/';
        }

        $requestedMethod = strtoupper($requestedMethod);

        foreach ($this->routes as $route) {
            // Verifica se o método HTTP corresponde e a regex da rota bate com a URI solicitada
            if ($route['method'] === $requestedMethod && preg_match($route['regex'], $requestedUri, $matches)) {
                // Remove a primeira correspondência completa do array de matches (resta apenas os parâmetros dinâmicos)
                array_shift($matches);

                // Executa a autenticação e validação do perfil do usuário se a rota exigir perfis específicos
                if (!empty($route['allowedRoles'])) {
                    Auth::requireAuth($route['allowedRoles']);
                }

                // Espera o formato "ControllerName@methodName"
                $handlerParts = explode('@', $route['handler']);
                if (count($handlerParts) !== 2) {
                    Response::json(false, "Estrutura do handler da rota inválida.", null, 500);
                }

                [$controllerName, $methodName] = $handlerParts;
                $controllerClass = "Controllers\\" . $controllerName;

                if (!class_exists($controllerClass)) {
                    Response::json(false, "Controlador '{$controllerClass}' não encontrado.", null, 500);
                }

                $controllerInstance = new $controllerClass();
                if (!method_exists($controllerInstance, $methodName)) {
                    Response::json(false, "Método '{$methodName}' não encontrado no controlador '{$controllerName}'.", null, 500);
                }

                // Invoca o método no controlador passando os parâmetros capturados na rota
                try {
                    call_user_func_array([$controllerInstance, $methodName], $matches);
                } catch (\Throwable $th) {
                    $configFile = dirname(__DIR__) . '/config/config.php';
                    if (!file_exists($configFile)) {
                        $configFile = dirname(__DIR__) . '/config/config.example.php';
                    }
                    $config = require $configFile;
                    $isDebug = $config['app']['debug'] ?? false;

                    if ($isDebug) {
                        Response::json(false, "Erro interno: " . $th->getMessage() . " em " . $th->getFile() . ":" . $th->getLine(), null, 500);
                    } else {
                        Response::json(false, "Ocorreu um erro interno no servidor.", null, 500);
                    }
                }
                return;
            }
        }

        // Se nenhuma rota corresponder
        Response::json(false, "Endpoint não encontrado para {$requestedMethod} {$requestedUri}.", null, 404);
    }
}
