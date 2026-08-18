<?php
declare(strict_types=1);

use Core\Router;

$router = new Router();

// ==========================================
// ROTA PÚBLICA / HEALTH CHECK
// ==========================================
$router->add('GET', '/health', 'AuthController@health');

// ==========================================
// ROTAS DE AUTENTICAÇÃO
// ==========================================
$router->add('POST', '/auth/login', 'AuthController@login');
$router->add('POST', '/auth/logout', 'AuthController@logout'); // Protegido (Auth será checado internamente)
$router->add('GET', '/auth/me', 'AuthController@me');         // Protegido (Auth será checado internamente)
$router->add('POST', '/auth/alterar-senha-primeiro-acesso', 'AuthController@alterarSenhaPrimeiroAcesso'); // Protegido
$router->add('POST', '/auth/recuperar-senha', 'AuthController@recuperarSenha'); // Público (fluxo de recuperação de senha)

// ==========================================
// ROTAS DE USUÁRIOS E AUDITORIA (Apenas ADMINISTRADOR)
// ==========================================
$router->add('GET', '/usuarios', 'UsuariosController@index', ['ADMINISTRADOR']);
$router->add('GET', '/usuarios/{id}', 'UsuariosController@show', ['ADMINISTRADOR']);
$router->add('POST', '/usuarios', 'UsuariosController@store', ['ADMINISTRADOR']);
$router->add('PUT', '/usuarios/{id}', 'UsuariosController@update', ['ADMINISTRADOR']);
$router->add('POST', '/usuarios/{id}/redefinir-senha', 'UsuariosController@redefinirSenha', ['ADMINISTRADOR']);
$router->add('DELETE', '/usuarios/{id}', 'UsuariosController@destroy', ['ADMINISTRADOR']);
$router->add('GET', '/logs', 'LogsController@index', ['ADMINISTRADOR']);
$router->add('GET', '/logs/{id}', 'LogsController@show', ['ADMINISTRADOR']);
$router->add('POST', '/logs/registrar-exportacao', 'LogsController@registrarExportacao', ['ADMINISTRADOR']);

// ==========================================
// ROTAS DE FUNCIONÁRIOS
// ==========================================
$router->add('GET', '/funcionarios', 'FuncionariosController@index', ['ADMINISTRADOR', 'RH_ADMINISTRATIVO', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);
$router->add('GET', '/funcionarios/{id}', 'FuncionariosController@show', ['ADMINISTRADOR', 'RH_ADMINISTRATIVO', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);
$router->add('GET', '/funcionarios/qrcode/{codigo}', 'FuncionariosController@showByQrCode', ['ADMINISTRADOR', 'RH_ADMINISTRATIVO', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);
$router->add('POST', '/funcionarios', 'FuncionariosController@store', ['ADMINISTRADOR', 'RH_ADMINISTRATIVO']);
$router->add('PUT', '/funcionarios/{id}', 'FuncionariosController@update', ['ADMINISTRADOR', 'RH_ADMINISTRATIVO']);
$router->add('DELETE', '/funcionarios/{id}', 'FuncionariosController@destroy', ['ADMINISTRADOR']);

// ==========================================
// ROTAS DE EPIS
// ==========================================
$router->add('GET', '/epis', 'EpisController@index', ['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);
$router->add('GET', '/epis/{id}', 'EpisController@show', ['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);
$router->add('GET', '/epis/vencidos', 'EpisController@showExpired', ['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);
$router->add('GET', '/epis/proximos-vencimento', 'EpisController@showNextExpiration', ['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);
$router->add('POST', '/epis', 'EpisController@store', ['ADMINISTRADOR', 'TECNICO_SST']);
$router->add('PUT', '/epis/{id}', 'EpisController@update', ['ADMINISTRADOR', 'TECNICO_SST']);
$router->add('DELETE', '/epis/{id}', 'EpisController@destroy', ['ADMINISTRADOR', 'TECNICO_SST']);

// ==========================================
// ROTAS DE ASSINATURA ELETRÔNICA
// ==========================================
$router->add('POST', '/assinaturas', 'AssinaturasController@store', ['ADMINISTRADOR', 'RH_ADMINISTRATIVO', 'ALMOXARIFE_OPERADOR']);
$router->add('GET', '/assinaturas/funcionario/{fun_id}', 'AssinaturasController@showByFuncionario', ['ADMINISTRADOR', 'RH_ADMINISTRATIVO', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);
$router->add('PUT', '/assinaturas/{id}', 'AssinaturasController@update', ['ADMINISTRADOR', 'RH_ADMINISTRATIVO', 'ALMOXARIFE_OPERADOR']);
$router->add('POST', '/assinaturas/validar', 'AssinaturasController@validar', ['ADMINISTRADOR', 'RH_ADMINISTRATIVO', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR']);
$router->add('POST', '/assinaturas/redefinir', 'AssinaturasController@redefinir', ['ADMINISTRADOR', 'RH_ADMINISTRATIVO', 'ALMOXARIFE_OPERADOR']);
$router->add('POST', '/assinaturas/bloquear/{id}', 'AssinaturasController@bloquear', ['ADMINISTRADOR', 'RH_ADMINISTRATIVO', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR']);
$router->add('POST', '/assinaturas/desbloquear/{id}', 'AssinaturasController@desbloquear', ['ADMINISTRADOR', 'RH_ADMINISTRATIVO', 'ALMOXARIFE_OPERADOR']);

// ==========================================
// ROTAS DE ENTREGAS
// ==========================================
$router->add('GET', '/entregas', 'EntregasController@index', ['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);
$router->add('GET', '/entregas/{id}', 'EntregasController@show', ['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);
$router->add('GET', '/entregas/funcionario/{fun_id}', 'EntregasController@showByFuncionario', ['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);
$router->add('POST', '/entregas', 'EntregasController@store', ['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR']);
$router->add('POST', '/entregas/{id}/cancelar', 'EntregasController@cancelar', ['ADMINISTRADOR', 'TECNICO_SST']);
$router->add('POST', '/entregas/item/{id}/corrigir', 'EntregasController@corrigirItem', ['ADMINISTRADOR']);
$router->add('GET', '/operacoes/{client_operation_id}/status', 'EntregasController@checkStatus', ['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR']);
$router->add('GET', '/sync/status/{client_operation_id}', 'EntregasController@checkStatus', ['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR']);

// ==========================================
// ROTAS DE DEVOLUÇÕES
// ==========================================
$router->add('POST', '/devolucoes', 'DevolucoesController@store', ['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR']);
$router->add('GET', '/devolucoes/funcionario/{fun_id}', 'DevolucoesController@showByFuncionario', ['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);

// ==========================================
// ROTAS DE RELATÓRIOS
// ==========================================
$router->add('GET', '/relatorios/entregas', 'RelatoriosController@entregasGerais', ['ADMINISTRADOR', 'TECNICO_SST', 'GESTOR']);
$router->add('GET', '/relatorios/entregas/funcionario/{fun_id}', 'RelatoriosController@entregasPorFuncionario', ['ADMINISTRADOR', 'TECNICO_SST', 'GESTOR']);
$router->add('GET', '/relatorios/epis-vencidos', 'RelatoriosController@episVencidos', ['ADMINISTRADOR', 'TECNICO_SST', 'GESTOR']);
$router->add('GET', '/relatorios/ca-vencidos', 'RelatoriosController@caVencidos', ['ADMINISTRADOR', 'TECNICO_SST', 'GESTOR']);
$router->add('GET', '/relatorios/custo-mensal', 'RelatoriosController@custoMensal', ['ADMINISTRADOR', 'GESTOR']);
$router->add('GET', '/relatorios/epis/consumo', 'RelatoriosController@relatorioConsumoEpis', ['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);

// ==========================================
// ROTAS DE DASHBOARD
// ==========================================
$router->add('GET', '/dashboard/resumo', 'DashboardController@resumo', ['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);
$router->add('GET', '/dashboard/custos', 'DashboardController@custos', ['ADMINISTRADOR', 'GESTOR']);
$router->add('GET', '/dashboard/top-epis', 'DashboardController@topEpis', ['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);
$router->add('GET', '/dashboard/pendencias', 'DashboardController@pendencias', ['ADMINISTRADOR', 'TECNICO_SST', 'ALMOXARIFE_OPERADOR', 'GESTOR']);

return $router;
