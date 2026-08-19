<?php
declare(strict_types=1);

namespace Core;

use Config\Database;
use PDO;
use Exception;

class Audit {
    private static array $friendlyNames = [
        'fun_nome' => 'Nome',
        'fun_cpf' => 'CPF',
        'fun_esocial' => 'eSocial',
        'fun_departamento' => 'Departamento',
        'fun_cargo' => 'Cargo',
        'fun_dataadmissao' => 'Data de admissão',
        'fun_situacao' => 'Situação',
        'fun_qrcode' => 'QR Code',
        'epi_nome' => 'Nome do EPI',
        'epi_tipo_item' => 'Tipo de Item',
        'epi_ca' => 'Número do C.A.',
        'epi_vencimento_ca' => 'Validade do C.A.',
        'epi_fabricante' => 'Fabricante',
        'epi_validade_uso_dias' => 'Validade de Uso (Dias)',
        'epi_status' => 'Status',
        'epi_valor' => 'Valor do EPI',
        'epi_origem_preco' => 'Origem do Preço',
        'epi_localizacao' => 'Localização',
        'epi_vida_util' => 'Vida Útil',
        'epi_vida_util_unidade' => 'Unidade de Vida Útil',
        'epi_vida_util_tipo' => 'Tipo de Vida Útil',
        'epi_vida_util_alerta' => 'Alerta de Vida Útil',
        'epi_vida_util_obs' => 'Observação de Vida Útil',
        'epi_numero_lote' => 'Lote',
        'epi_modelo' => 'Modelo',
        'epi_identificacao' => 'Identificação',
        'epi_ref_fornecedor' => 'Referência do Fornecedor',
        'epi_exige_tamanho' => 'Exige Tamanho',
        'usu_login' => 'Login',
        'usu_perfil' => 'Perfil',
        'usu_status' => 'Status',
        'usu_aceite_termos' => 'Aceite dos Termos de Uso',
        'usu_data_aceite_termos' => 'Data do Aceite dos Termos',
        'ass_status' => 'Status da Assinatura',
        'entr_status' => 'Status da Entrega',
        'entr_motivo' => 'Motivo do Status',
        'item_quantidade' => 'Quantidade',
        'item_tamanho' => 'Tamanho',
        'item_numero_lote' => 'Lote'
    ];

    private static array $sensitiveFields = [
        'usu_senha_hash', 'senha', 'senha_atual', 'nova_senha', 'confirmar_senha', 'ass_senha_hash', 'ass_salt', 'pin', 'ass_senha_hash'
    ];

    /**
     * Registra um evento de auditoria no banco de dados.
     */
    public static function log(
        string $acao,
        string $tabela,
        ?int $registroId = null,
        ?string $detalhes = null,
        ?int $usuId = null,
        ?int $funId = null,
        ?int $epiId = null,
        ?int $entrId = null,
        ?int $itemId = null,
        ?int $assId = null,
        ?int $histId = null
    ): bool {
        try {
            $db = Database::getConnection();

            if ($usuId === null) {
                $currentUser = Auth::getCurrentUser();
                if ($currentUser) {
                    $usuId = (int)$currentUser['usu_id'];
                }
            }

            // Garante que a ocorrência nos detalhes nunca fique vazia
            if ($detalhes === null || trim($detalhes) === '') {
                $detalhes = json_encode(['ocorrencia' => "Ação '{$acao}' executada na tabela {$tabela}."], JSON_UNESCAPED_UNICODE);
            }

            $sql = "INSERT INTO log_auditoria (
                        usu_id, fun_id, epi_id, entr_id, item_id, ass_id, hist_id,
                        log_acao, log_datahora, log_tabela, log_registro_id, log_detalhes
                    ) VALUES (
                        :usu_id, :fun_id, :epi_id, :entr_id, :item_id, :ass_id, :hist_id,
                        :log_acao, NOW(), :log_tabela, :log_registro_id, :log_detalhes
                    )";

            $stmt = $db->prepare($sql);
            return $stmt->execute([
                ':usu_id' => $usuId,
                ':fun_id' => $funId,
                ':epi_id' => $epiId,
                ':entr_id' => $entrId,
                ':item_id' => $itemId,
                ':ass_id' => $assId,
                ':hist_id' => $histId,
                ':log_acao' => $acao,
                ':log_tabela' => $tabela,
                ':log_registro_id' => $registroId,
                ':log_detalhes' => $detalhes
            ]);
        } catch (Exception $e) {
            error_log("Erro ao gravar Log_Auditoria: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Normaliza os valores para comparação robusta
     */
    private static function normalizeValue(string $key, $value) {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_numeric($value)) {
            return (float)$value;
        }
        $valStr = trim((string)$value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valStr)) {
            return $valStr;
        }
        return strtolower($valStr);
    }

    /**
     * Formata os valores de forma amigável
     */
    public static function formatValue(string $key, $value): string {
        if ($value === null || $value === '') {
            return 'Não informado';
        }
        
        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }
        if ($key === 'epi_exige_tamanho' && ($value === 1 || $value === 0 || $value === '1' || $value === '0')) {
            return (int)$value === 1 ? 'Sim' : 'Não';
        }

        // Formata datas
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$value)) {
            return date('d/m/Y', strtotime((string)$value));
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string)$value)) {
            return date('d/m/Y H:i', strtotime((string)$value));
        }

        // Formata valores monetários
        if ($key === 'epi_valor' || $key === 'hist_valor') {
            return 'R$ ' . number_format((float)$value, 2, ',', '.');
        }

        // Formata status amigáveis
        if ($value === 'ATIVO') return 'Ativo';
        if ($value === 'INATIVO') return 'Inativo';
        if ($value === 'BLOQUEADO') return 'Bloqueado';
        if ($value === 'DESBLOQUEADO') return 'Desbloqueado';

        return (string)$value;
    }

    /**
     * Compara registros e grava o log estruturado
     */
    public static function compareAndLog(
        string $acao,
        string $tabela,
        ?int $registroId,
        array $oldData,
        array $newData,
        ?int $usuId = null,
        ?int $funId = null,
        ?int $epiId = null,
        ?int $entrId = null,
        ?int $itemId = null,
        ?int $assId = null,
        ?int $histId = null
    ): bool {
        $alteracoes = [];
        $resumos = [];
        
        foreach ($newData as $key => $newValue) {
            if (in_array($key, self::$sensitiveFields, true) || str_ends_with($key, '_id') || $key === 'id' || $key === 'fun_id' || $key === 'epi_id' || $key === 'usu_id') {
                continue;
            }
            
            // Se o campo não existia no registro anterior, ignora ou considera nulo
            $oldValue = $oldData[$key] ?? null;
            
            $oldNormalized = self::normalizeValue($key, $oldValue);
            $newNormalized = self::normalizeValue($key, $newValue);
            
            if ($oldNormalized !== $newNormalized) {
                $friendlyName = self::$friendlyNames[$key] ?? $key;
                $oldFormatted = self::formatValue($key, $oldValue);
                $newFormatted = self::formatValue($key, $newValue);
                
                $alteracoes[] = [
                    'campo' => $key,
                    'nome_amigavel' => $friendlyName,
                    'valor_anterior' => $oldFormatted,
                    'valor_novo' => $newFormatted
                ];
                
                $resumos[] = "{$friendlyName} alterado de {$oldFormatted} para {$newFormatted}";
            }
        }
        
        if (empty($alteracoes)) {
            return true;
        }
        
        // Define a ocorrência resumida
        if (count($alteracoes) === 1) {
            $ocorrencia = $resumos[0];
        } else {
            $nomesCampos = array_map(fn($alt) => $alt['nome_amigavel'], $alteracoes);
            $ocorrencia = count($alteracoes) . " campos alterados: " . implode(", ", $nomesCampos);
        }
        
        $detalhesJson = json_encode([
            'ocorrencia' => $ocorrencia,
            'alteracoes' => $alteracoes,
            'dados_anteriores' => array_intersect_key($oldData, array_flip(array_keys($newData))),
            'dados_novos' => $newData
        ], JSON_UNESCAPED_UNICODE);
        
        return self::log(
            $acao,
            $tabela,
            $registroId,
            $detalhesJson,
            $usuId,
            $funId,
            $epiId,
            $entrId,
            $itemId,
            $assId,
            $histId
        );
    }

    /**
     * Auxiliar de cadastro
     */
    public static function logCadastro(
        string $tabela,
        int $registroId,
        string $identificacaoAmigavel,
        array $dados,
        ?int $usuId = null
    ): bool {
        $ocorrencia = "Registro cadastrado na tabela {$tabela} com ID {$registroId}";
        if ($tabela === 'Funcionarios') {
            $ocorrencia = "Funcionário " . ($dados['fun_nome'] ?? $identificacaoAmigavel) . " cadastrado com ID {$registroId}.";
        } elseif ($tabela === 'EPIs') {
            $ocorrencia = "Novo EPI cadastrado: " . ($dados['epi_nome'] ?? $identificacaoAmigavel) . ", C.A. " . ($dados['epi_ca'] ?? 'Não informado') . ".";
        } elseif ($tabela === 'Usuarios') {
            $ocorrencia = "Usuário " . ($dados['usu_login'] ?? $identificacaoAmigavel) . " cadastrado com perfil " . ($dados['usu_perfil'] ?? '') . ".";
        }
        
        $detalhesJson = json_encode([
            'ocorrencia' => $ocorrencia,
            'dados_novos' => $dados
        ], JSON_UNESCAPED_UNICODE);
        
        return self::log('CADASTRO', $tabela, $registroId, $detalhesJson, $usuId);
    }

    /**
     * Auxiliar de inativação
     */
    public static function logInativacao(
        string $tabela,
        int $registroId,
        string $identificacaoAmigavel,
        ?int $usuId = null
    ): bool {
        $ocorrencia = "Registro ID {$registroId} na tabela {$tabela} foi inativado.";
        if ($tabela === 'Funcionarios') {
            $ocorrencia = "Funcionário {$identificacaoAmigavel}, ID {$registroId}, foi inativado.";
        } elseif ($tabela === 'EPIs') {
            $ocorrencia = "EPI {$identificacaoAmigavel}, ID {$registroId}, foi inativado.";
        } elseif ($tabela === 'Usuarios') {
            $ocorrencia = "Usuário {$identificacaoAmigavel}, ID {$registroId}, foi inativado.";
        }
        
        $detalhesJson = json_encode([
            'ocorrencia' => $ocorrencia
        ], JSON_UNESCAPED_UNICODE);
        
        return self::log('INATIVAÇÃO', $tabela, $registroId, $detalhesJson, $usuId);
    }
}
