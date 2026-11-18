<?php

namespace App\Support\Erp\Dre;

use App\Support\Erp\Tef\TefMoney;

/**
 * D2.5–D2.8 — Monta o demonstrativo DRE a partir das etapas anteriores.
 *
 * Tributos: ocultos por padrão. Estimativa Simples (CRT 1) é gerencial,
 * exibida quando habilitada, e NÃO entra nos subtotais (evita duplicar DAS nas despesas).
 */
final class DreDemonstrativoGerencialService
{
    /**
     * @param  array<string, mixed>  $receitas  saída DreReceitasGerenciaisService
     * @param  array<string, mixed>  $despesas  saída DreDespesasGerenciaisService
     * @return array<string, mixed>
     */
    public function montar(array $receitas, array $despesas): array
    {
        $empresaId = (int) ($receitas['empresa_id'] ?? 0);
        $periodoDe = (string) ($receitas['periodo_de'] ?? '');
        $periodoAte = (string) ($receitas['periodo_ate'] ?? '');

        $bruta = TefMoney::normalize($receitas['receita_bruta_operacional'] ?? 0);
        $deducoes = TefMoney::normalize($receitas['devolucoes'] ?? 0);
        $liquida = TefMoney::normalize($receitas['receita_liquida_operacional'] ?? 0);
        $descontosInfo = TefMoney::normalize($receitas['descontos_embutidos_informativo'] ?? 0);

        $custosCp = $despesas['custos_competencia'] ?? null;
        $custosCpNorm = $custosCp === null ? null : TefMoney::normalize($custosCp);

        $despOp = TefMoney::normalize($despesas['despesas_operacionais'] ?? 0);
        $despAdm = TefMoney::normalize($despesas['despesas_administrativas'] ?? 0);
        $despCom = TefMoney::normalize($despesas['despesas_comerciais'] ?? 0);
        $despFin = TefMoney::normalize($despesas['despesas_financeiras_competencia'] ?? 0);

        /** @var array<string, mixed> $params */
        $params = is_array($despesas['dre_parametros'] ?? null)
            ? $despesas['dre_parametros']
            : app(DreEmpresaParametrosService::class)->obter($empresaId);

        $exibirTributos = (bool) ($params['exibir_tributos'] ?? false);
        $estimativaOn = (bool) ($params['calcular_estimativa_simples'] ?? false);
        $permiteEstimativa = (bool) ($params['permite_estimativa_simples'] ?? false);
        $aliqRaw = $params['aliquota_simples_pct'] ?? null;
        $aliqInformada = $aliqRaw !== null && $aliqRaw !== '';

        /** @var array<string, mixed> $cmvInfo */
        $cmvInfo = is_array($despesas['cmv_historico'] ?? null)
            ? $despesas['cmv_historico']
            : app(DreCmvHistoricoService::class)->calcular($empresaId, $periodoDe, $periodoAte);

        $cmvDisponivel = (bool) ($cmvInfo['cmv_disponivel'] ?? false);
        $cmv = $cmvDisponivel ? TefMoney::normalize($cmvInfo['cmv_liquido'] ?? $cmvInfo['cmv'] ?? 0) : null;
        $cmvPendencia = (string) ($cmvInfo['pendencia'] ?? 'CMV histórico indisponível.');
        if ($cmvDisponivel && (float) ($cmvInfo['cobertura_pct'] ?? 0) < 100) {
            $cmvPendencia = 'Cobertura parcial — CMV bloqueado.';
            $cmvDisponivel = false;
            $cmv = null;
        }

        $tributos = [
            'exibir' => false,
            'disponivel' => false,
            'modo' => 'oculto',
            'crt' => (int) ($params['crt'] ?? 0),
            'regime' => (string) ($params['regime'] ?? ''),
        ];

        if ($exibirTributos) {
            if (is_array($despesas['tributos_informativos'] ?? null)) {
                $tributos = $despesas['tributos_informativos'];
            } else {
                $tributos = app(DreTributosInformativosService::class)->calcular(
                    $empresaId,
                    $periodoDe,
                    $periodoAte,
                    $bruta,
                );
            }
            $tributos['exibir'] = true;
            $tributos['modo'] = ((int) ($tributos['crt'] ?? 0) === 3) ? 'nfe_crt3' : 'regime_sem_nfe';
        }

        // Estimativa Simples (CRT 1): só exibição gerencial — NÃO entra em subtotais
        // (pagamentos DAS nas despesas não são re-deduzidos).
        $estimativa = [
            'ativa' => false,
            'valor' => null,
            'aliquota_pct' => null,
            'base' => $liquida,
            'base_label' => 'Receita comercial após devoluções',
            'deduzida_nos_resultados' => false,
            'aviso' => '',
        ];

        if ($exibirTributos && $estimativaOn && $permiteEstimativa) {
            if (! $aliqInformada) {
                $estimativa['aviso'] = 'Estimativa Simples habilitada — informe a alíquota (%).';
            } else {
                $aliq = (float) $aliqRaw;
                if ($aliq < 0) {
                    $aliq = 0;
                }
                if ($aliq > 100) {
                    $aliq = 100;
                }
                $fator = bcdiv(number_format($aliq, 4, '.', ''), '100', 8);
                // Arredonda half-up (bcmul com scale 2 trunca — ex.: 14306,99×10% = 1.430,70).
                $produto = bcmul(TefMoney::normalize($liquida), $fator, 4);
                $valor = bccomp($produto, '0', 4) >= 0
                    ? bcadd($produto, '0.005', 2)
                    : bcsub($produto, '0.005', 2);
                $estimativa = [
                    'ativa' => true,
                    'valor' => $valor,
                    'aliquota_pct' => number_format($aliq, 4, '.', ''),
                    'base' => $liquida,
                    'base_label' => 'Receita comercial após devoluções',
                    'deduzida_nos_resultados' => false,
                    'aviso' => 'Estimativa gerencial (não é o DAS oficial). Base comercial — sem segregações/ST. Não deduzida dos resultados (evita duplicar DAS nas despesas).',
                ];
            }
        }

        // Impostos NÃO deduzem receita / resultados nesta V1.
        $impostosDisponivel = false;
        $impostos = null;

        $resultadoBruto = null;
        $resultadoBrutoDisponivel = false;
        if ($cmvDisponivel && $cmv !== null) {
            $resultadoBruto = TefMoney::sub($liquida, $cmv);
            $resultadoBrutoDisponivel = true;
        }

        $resultadoBrutoPendencia = $resultadoBrutoDisponivel
            ? 'Parcial: receita após devoluções − CMV. Tributos não deduzidos nos subtotais.'
            : 'Aguardando CMV com cobertura completa.';

        $resultadoOperacional = null;
        $resultadoOperacionalDisponivel = false;
        $resultadoOperacionalPendencia = 'Aguardando resultado bruto comprovável.';
        if ($resultadoBrutoDisponivel && $resultadoBruto !== null) {
            $tmp = TefMoney::sub($resultadoBruto, $despOp);
            $tmp = TefMoney::sub($tmp, $despAdm);
            $tmp = TefMoney::sub($tmp, $despCom);
            $resultadoOperacional = TefMoney::sub($tmp, $despFin);
            // Estimativa Simples NÃO é subtraída aqui (anti-duplicidade com DAS em despesas).
            $resultadoOperacionalDisponivel = true;
            $resultadoOperacionalPendencia = 'Gerencial (tributos não deduzidos nos subtotais). Não é lucro definitivo.';
        }

        $pendencias = [];
        if (! $cmvDisponivel) {
            $pendencias[] = $cmvPendencia !== '' ? $cmvPendencia : 'CMV histórico incompleto.';
        }
        if ($exibirTributos) {
            $crt = (int) ($tributos['crt'] ?? $params['crt'] ?? 0);
            if ($estimativa['ativa']) {
                $pendencias[] = 'Estimativa Simples exibida — não deduzida dos resultados (não é DAS oficial).';
            } elseif ($estimativaOn && $permiteEstimativa && ! $aliqInformada) {
                $pendencias[] = (string) $estimativa['aviso'];
            } elseif ($crt === 3 && ! (bool) ($tributos['disponivel'] ?? false)) {
                $pendencias[] = 'Sem NF-e de saída autorizada no período para tributos informativos.';
            } elseif ($crt === 3 && (bool) ($tributos['disponivel'] ?? false)) {
                $pendencias[] = 'Tributos NF-e informativos — não deduzidos da receita.';
            } elseif ($crt === 1 && ! $estimativaOn) {
                $pendencias[] = 'Simples Nacional: habilite a estimativa e informe a alíquota, se desejar.';
            } elseif ($crt === 4) {
                $pendencias[] = 'MEI: estimativa percentual do Simples não se aplica.';
            } elseif ($crt === 2) {
                $pendencias[] = 'CRT 2: tributos informativos limitados nesta versão.';
            }
        }
        if ((int) ($despesas['contagens']['pendencias_sem_competencia'] ?? 0) > 0) {
            $pendencias[] = 'Há títulos CP sem data de competência.';
        }
        if (! $resultadoOperacionalDisponivel) {
            $pendencias[] = 'Resultado operacional aguarda CMV com cobertura completa.';
        } elseif ($resultadoBrutoDisponivel) {
            $pendencias[] = 'Resultado bruto/operacional gerencial — não é lucro definitivo.';
        }
        $pendencias = array_values(array_unique(array_filter(
            array_map(static fn ($p): string => trim((string) $p), $pendencias),
            static fn (string $p): bool => $p !== '',
        )));

        return [
            'receita_bruta' => $bruta,
            'receita_mercadorias' => TefMoney::normalize($receitas['receita_mercadorias'] ?? 0),
            'receita_servicos' => TefMoney::normalize($receitas['receita_servicos'] ?? 0),
            'receita_pecas_os' => TefMoney::normalize($receitas['receita_pecas_os'] ?? 0),
            'deducoes_receita' => $deducoes,
            'deducoes_composicao' => [
                'devolucoes_finalizadas' => $deducoes,
                'descontos' => 'Já embutidos no total das fontes — não re-subtraídos',
                'impostos_sobre_vendas' => $exibirTributos
                    ? 'Informativos/estimativa — não deduzidos nos subtotais'
                    : 'Seção de tributos desativada nos parâmetros',
                'cancelamentos' => 'Já excluídos na receita (status≠fechado / PDV≠F)',
            ],
            'descontos_embutidos_informativo' => $descontosInfo,
            'receita_liquida' => $liquida,
            'cmv' => $cmv,
            'cmv_disponivel' => $cmvDisponivel,
            'cmv_pendencia' => $cmvPendencia !== '' ? $cmvPendencia : (
                $cmvDisponivel
                    ? 'CMV histórico completo no período.'
                    : 'CMV histórico incompleto.'
            ),
            'cmv_cobertura_pct' => (float) ($cmvInfo['cobertura_pct'] ?? 0),
            'cmv_itens_ok' => (int) ($cmvInfo['itens_ok'] ?? 0),
            'cmv_itens_sem_captura' => (int) ($cmvInfo['itens_sem_captura'] ?? 0),
            'cmv_itens_indisponivel' => (int) ($cmvInfo['itens_indisponivel'] ?? 0),
            'custos_cp_competencia' => $custosCpNorm,
            'custos_cp_observacao' => 'Custos do plano (CP) — não é CMV; informativo, fora do resultado operacional',
            'impostos' => $impostos,
            'impostos_disponivel' => $impostosDisponivel,
            'dre_parametros' => $params,
            'tributos_exibir' => $exibirTributos,
            'tributos_informativos' => $tributos,
            'tributos_estimativa' => $estimativa,
            'resultado_bruto' => $resultadoBruto,
            'resultado_bruto_disponivel' => $resultadoBrutoDisponivel,
            'resultado_bruto_pendencia' => $resultadoBrutoPendencia,
            'despesas_operacionais' => $despOp,
            'despesas_administrativas' => $despAdm,
            'despesas_comerciais' => $despCom,
            'despesas_financeiras' => $despFin,
            'resultado_operacional' => $resultadoOperacional,
            'resultado_operacional_disponivel' => $resultadoOperacionalDisponivel,
            'resultado_operacional_pendencia' => $resultadoOperacionalPendencia,
            'pendencias' => $pendencias,
            'limitacoes' => [
                'cmv' => $cmvDisponivel
                    ? 'CMV histórico liberado apenas com cobertura completa.'
                    : $cmvPendencia,
                'impostos' => $exibirTributos
                    ? 'Tributos/estimativa não deduzem subtotais (anti-duplicidade com DAS em despesas).'
                    : 'Seção de tributos desativada.',
                'compras' => 'Compras de estoque não entram como CMV.',
                'cadastro_produto' => 'products.preco_custo / e_medio não são usados para vendas passadas (só snapshot no fechamento).',
                'lucro_definitivo' => 'Resultado operacional gerencial não é lucro definitivo.',
            ],
        ];
    }
}
