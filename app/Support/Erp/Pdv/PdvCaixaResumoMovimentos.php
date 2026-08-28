<?php

namespace App\Support\Erp\Pdv;

use App\Models\PdvCaixaMovimento;
use App\Models\PdvCaixaSessao;
use App\Models\PdvVenda;
use App\Models\PdvVendaPagamento;

/**
 * Resumo do fechamento: abertura, TODAS as formas das vendas da sessão
 * (mesmo PIX/cartão que não lançam no caixa físico), sangria e suprimento.
 */
final class PdvCaixaResumoMovimentos
{
    /**
     * Totais dos cards = soma das linhas do resumo por forma (mesma fonte da grade).
     *
     * @return array{entrada: float, saida: float, saldo: float}
     */
    public static function totaisFromSessao(PdvCaixaSessao $sessao): array
    {
        $entrada = 0.0;
        $saida = 0.0;

        foreach (self::fromSessao($sessao) as $linha) {
            $entrada = round($entrada + (float) ($linha['entrada'] ?? 0), 2);
            $saida = round($saida + (float) ($linha['saida'] ?? 0), 2);
        }

        return [
            'entrada' => $entrada,
            'saida' => $saida,
            'saldo' => round($entrada - $saida, 2),
        ];
    }

    /**
     * @return list<array{historico: string, entrada: float, saida: float}>
     */
    public static function fromSessao(PdvCaixaSessao $sessao): array
    {
        $movimentos = $sessao->relationLoaded('movimentos')
            ? $sessao->movimentos
            : $sessao->movimentos()->orderBy('id')->get();

        $aberturaEntrada = 0.0;
        $aberturaSaida = 0.0;
        $temAbertura = false;

        /** @var array<string, array{entrada: float, saida: float}> $porForma */
        $porForma = [];
        /** @var array<string, array{entrada: float, saida: float}> $sangrias */
        $sangrias = [];
        /** @var array<string, array{entrada: float, saida: float}> $suprimentos */
        $suprimentos = [];

        foreach ($movimentos as $movimento) {
            if (! $movimento instanceof PdvCaixaMovimento) {
                continue;
            }

            $entrada = round((float) $movimento->entrada, 2);
            $saida = round((float) $movimento->saida, 2);
            $tipo = mb_strtolower(trim((string) $movimento->tipo), 'UTF-8');

            if ($tipo === 'abertura') {
                $temAbertura = true;
                $aberturaEntrada = round($aberturaEntrada + $entrada, 2);
                $aberturaSaida = round($aberturaSaida + $saida, 2);

                continue;
            }

            if ($tipo === 'sangria') {
                $label = self::labelOperacao('SANGRIA', (string) $movimento->historico);
                self::somar($sangrias, $label, $entrada, $saida);

                continue;
            }

            if ($tipo === 'suprimento') {
                $label = self::labelOperacao('SUPRIMENTO', (string) $movimento->historico);
                self::somar($suprimentos, $label, $entrada, $saida);

                continue;
            }

            // venda/estorno no caixa: ignorados aqui — formas vêm de pdv_venda_pagamentos
            // para incluir PIX/cartão com destino depósito/CR.
        }

        self::agregarPagamentosDasVendas((int) $sessao->id, $porForma);

        $linhas = [];

        if ($temAbertura || $aberturaEntrada > 0 || $aberturaSaida > 0) {
            $linhas[] = [
                'historico' => 'ABERTURA DE CAIXA',
                'entrada' => $aberturaEntrada,
                'saida' => $aberturaSaida,
            ];
        }

        ksort($porForma, SORT_STRING);

        foreach ($porForma as $forma => $totais) {
            if ($totais['entrada'] <= 0 && $totais['saida'] <= 0) {
                continue;
            }

            $linhas[] = [
                'historico' => $forma,
                'entrada' => $totais['entrada'],
                'saida' => $totais['saida'],
            ];
        }

        ksort($sangrias, SORT_STRING);
        foreach ($sangrias as $label => $totais) {
            $linhas[] = [
                'historico' => $label,
                'entrada' => $totais['entrada'],
                'saida' => $totais['saida'],
            ];
        }

        ksort($suprimentos, SORT_STRING);
        foreach ($suprimentos as $label => $totais) {
            $linhas[] = [
                'historico' => $label,
                'entrada' => $totais['entrada'],
                'saida' => $totais['saida'],
            ];
        }

        return $linhas;
    }

    /**
     * Soma pagamentos de todas as vendas da sessão (finalizadas = entrada, canceladas = saída).
     *
     * @param  array<string, array{entrada: float, saida: float}>  $porForma
     */
    private static function agregarPagamentosDasVendas(int $sessaoId, array &$porForma): void
    {
        if ($sessaoId <= 0) {
            return;
        }

        $vendaIds = PdvVenda::query()
            ->where('pdv_caixa_sessao_id', $sessaoId)
            ->whereIn('situacao', ['F', 'C'])
            ->pluck('situacao', 'id');

        if ($vendaIds->isEmpty()) {
            return;
        }

        $pagamentos = PdvVendaPagamento::query()
            ->whereIn('pdv_venda_id', $vendaIds->keys()->all())
            ->get(['pdv_venda_id', 'forma', 'valor']);

        foreach ($pagamentos as $pagamento) {
            $forma = mb_strtoupper(trim((string) ($pagamento->forma ?: '')), 'UTF-8');
            $valor = round((float) $pagamento->valor, 2);

            if ($forma === '' || $valor <= 0) {
                continue;
            }

            $situacao = (string) ($vendaIds[$pagamento->pdv_venda_id] ?? '');

            if ($situacao === 'F') {
                self::somar($porForma, $forma, $valor, 0.0);
            } elseif ($situacao === 'C') {
                self::somar($porForma, $forma, 0.0, $valor);
            }
        }
    }

    /**
     * @param  array<string, array{entrada: float, saida: float}>  $bucket
     */
    private static function somar(array &$bucket, string $label, float $entrada, float $saida): void
    {
        if (! isset($bucket[$label])) {
            $bucket[$label] = ['entrada' => 0.0, 'saida' => 0.0];
        }

        $bucket[$label]['entrada'] = round($bucket[$label]['entrada'] + $entrada, 2);
        $bucket[$label]['saida'] = round($bucket[$label]['saida'] + $saida, 2);
    }

    private static function labelOperacao(string $tipo, string $historico): string
    {
        $historico = mb_strtoupper(trim($historico), 'UTF-8');

        if ($historico === '' || $historico === $tipo) {
            return $tipo;
        }

        return $tipo.' — '.$historico;
    }
}
