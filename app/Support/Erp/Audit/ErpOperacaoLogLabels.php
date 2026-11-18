<?php

namespace App\Support\Erp\Audit;

/**
 * Rótulos amigáveis do Log de Operações (somente apresentação).
 */
final class ErpOperacaoLogLabels
{
    /**
     * @return array<string, string> operacao => rótulo
     */
    public static function operacoes(): array
    {
        return [
            'ESTORNAR_VENDA' => 'Estorno de venda',
            'GERAR_COMPRA_XML' => 'Geração de compra (XML)',
            'FINALIZAR_LANCAMENTO_COMPRA' => 'Finalização de compra',
            'REABRIR_LANCAMENTO_COMPRA' => 'Reabertura de compra',
            'CANCELAR_COMPRA' => 'Cancelamento de compra',
            'FINALIZAR_DEVOLUCAO_COMPRA' => 'Finalização de devolução de compra',
            'REABRIR_DEVOLUCAO_COMPRA' => 'Reabertura de devolução de compra',
            'FINALIZAR_DEVOLUCAO_VENDA' => 'Finalização de devolução de venda',
            'ESTORNAR_CREDITO_DEVOLUCAO' => 'Estorno de crédito da devolução',
            'REABRIR_ORDEM_SERVICO' => 'Reabertura de ordem de serviço',
            'CANCELAR_ORDEM_SERVICO' => 'Cancelamento de ordem de serviço',
            'GERAR_CREDITO_CLIENTE' => 'Geração de crédito do cliente',
            'ESTORNAR_CREDITO_CLIENTE' => 'Estorno de crédito do cliente',
            'REGULARIZAR_NFCE' => 'Regularização de NFC-e',
            'NFCE_TENTATIVA_ARQUIVADA' => 'Arquivamento de tentativa NFC-e',
            'NFCE_INUTILIZACAO' => 'Inutilização de NFC-e',
            'NFCE_NUMERO_CONFLITO_OFFLINE' => 'Conflito de número NFC-e (offline)',
            'MIGRACAO_FIREBIRD' => 'Migração Firebird',
            'ALTERAR_PERMISSOES_USUARIO' => 'Alteração de permissões do usuário',
            'ALTERAR_PERMISSOES_PERFIL' => 'Alteração de permissões do perfil',
            'ESTORNAR_BAIXA_CONTAS_RECEBER' => 'Estorno de baixa a receber',
            'ESTORNAR_BAIXA_CONTAS_PAGAR' => 'Estorno de baixa a pagar',
            'EXCLUIR_CONTAS_RECEBER' => 'Exclusão de título a receber',
            'EXCLUIR_CONTAS_PAGAR' => 'Exclusão de título a pagar',
            'EXCLUIR_LANCAMENTO_CAIXA' => 'Exclusão de lançamento do caixa',
            'ALTERAR_EMPRESA_PARAMETROS' => 'Alteração de parâmetros da empresa',
            'ALTERAR_CONFIG_FISCAIS' => 'Alteração de configurações fiscais',
            'ALTERAR_TERMINAL' => 'Alteração de terminal',
            'ALTERAR_TERMINAL_TEF' => 'Alteração de TEF do terminal',
            'EXCLUIR_RH_DOCUMENTO' => 'Exclusão de documento RH',
            'EXCLUIR_RH_FUNCIONARIO' => 'Exclusão de funcionário RH',
        ];
    }

    public static function operacao(?string $operacao): string
    {
        $codigo = mb_strtoupper(trim((string) $operacao), 'UTF-8');

        if ($codigo === '') {
            return '—';
        }

        return self::operacoes()[$codigo] ?? self::humanizarCodigo($codigo);
    }

    /**
     * @return array<string, string> value => label (filtro da tela)
     */
    public static function opcoesFiltroOperacao(): array
    {
        $opcoes = ['todas' => 'Todas'];

        foreach (self::operacoes() as $codigo => $label) {
            $opcoes[$codigo] = $label;
        }

        return $opcoes;
    }

    public static function resultado(?string $resultado): string
    {
        return match (mb_strtolower(trim((string) $resultado), 'UTF-8')) {
            'ok', 'concluido', 'concluído' => 'Concluído',
            'erro', 'error', 'falha' => 'Erro',
            default => $resultado !== null && $resultado !== ''
                ? mb_strtoupper((string) $resultado, 'UTF-8')
                : '—',
        };
    }

    public static function documento(?string $tipo, ?string $numero, ?int $id = null): string
    {
        $tipoLabel = self::tipoDocumento($tipo);
        $numero = trim((string) ($numero ?? ''));

        if ($numero === '' && $id) {
            $numero = (string) $id;
        }

        if ($tipoLabel === '' && $numero === '') {
            return '—';
        }

        if ($tipoLabel === '') {
            return '#'.$numero;
        }

        if ($numero === '') {
            return $tipoLabel;
        }

        return $tipoLabel.' #'.$numero;
    }

    public static function tipoDocumento(?string $tipo): string
    {
        $codigo = mb_strtolower(trim((string) $tipo), 'UTF-8');

        return match ($codigo) {
            'venda' => 'Venda',
            'compra' => 'Compra',
            'devolucao_compra' => 'Devolução compra',
            'devolucao_venda' => 'Devolução venda',
            'ordem_servico' => 'OS',
            'cliente_credito' => 'Crédito',
            'pdv_venda_nfce' => 'NFC-e',
            'nfce_inutilizacao' => 'Inutilização NFC-e',
            'firebird' => 'Migração',
            'probe' => 'Diagnóstico',
            'usuario' => 'Usuário',
            'perfil' => 'Perfil',
            'conta_receber' => 'Conta a receber',
            'conta_pagar' => 'Conta a pagar',
            'caixa_lancamento' => 'Lançamento caixa',
            'empresa' => 'Empresa',
            'config_fiscais' => 'Config. fiscais',
            'terminal' => 'Terminal',
            'rh_documento' => 'Documento RH',
            'rh_funcionario' => 'Funcionário RH',
            '' => '',
            default => self::humanizarCodigo($codigo),
        };
    }

    private static function humanizarCodigo(string $codigo): string
    {
        $texto = str_replace(['_', '-'], ' ', $codigo);
        $texto = mb_strtolower($texto, 'UTF-8');

        return mb_convert_case($texto, MB_CASE_TITLE, 'UTF-8');
    }
}
