<?php

namespace App\Support\Erp\Pdv;

use App\Models\PdvCaixaMovimento;
use App\Models\PdvVenda;
use App\Models\PdvVendaPagamento;
use App\Support\Erp\ErpMoney;
use Illuminate\Support\Collection;

final class PdvCaixaMovimentoService
{
    public function __construct(
        private ?PdvConfig $config = null,
    ) {}

    /**
     * @param  array<int, array{forma: string, valor: string|float|int}>  $pagamentos
     */
    public function registrarEntradasVenda(
        int $sessaoId,
        PdvVenda $venda,
        array $pagamentos,
        float $troco = 0,
    ): void {
        $this->registrarMovimentosVenda($sessaoId, $venda, $pagamentos, $troco, 'entrada');
    }

    /**
     * @param  array<int, array{forma: string, valor: string|float|int}>  $pagamentos
     */
    public function registrarSaidasEstorno(
        int $sessaoId,
        PdvVenda $venda,
        array $pagamentos,
        float $troco = 0,
    ): void {
        $this->registrarMovimentosVenda($sessaoId, $venda, $pagamentos, $troco, 'estorno');
    }

    /**
     * @param  Collection<int, PdvVendaPagamento>  $pagamentos
     */
    public function registrarSaidasEstornoFromModel(
        int $sessaoId,
        PdvVenda $venda,
        Collection $pagamentos,
    ): void {
        $array = $pagamentos
            ->map(fn (PdvVendaPagamento $pagamento): array => [
                'forma' => $pagamento->forma,
                'valor' => (float) $pagamento->valor,
            ])
            ->all();

        $this->registrarSaidasEstorno($sessaoId, $venda, $array, (float) $venda->troco);
    }

    /**
     * @param  array<int, array{forma: string, valor: string|float|int}>  $pagamentos
     */
    private function registrarMovimentosVenda(
        int $sessaoId,
        PdvVenda $venda,
        array $pagamentos,
        float $troco,
        string $direcao,
    ): void {
        $trocoRestante = round(max(0, $troco), 2);
        $numero = $venda->numero;
        $tipo = $direcao === 'estorno' ? 'estorno' : 'venda';
        $prefixo = $direcao === 'estorno' ? 'ESTORNO VENDA PDV #' : 'VENDA PDV #';

        foreach ($pagamentos as $pagamento) {
            $forma = mb_strtoupper(trim((string) ($pagamento['forma'] ?? '')), 'UTF-8');
            $valor = is_numeric($pagamento['valor'] ?? null)
                ? (float) $pagamento['valor']
                : ErpMoney::parseBr((string) ($pagamento['valor'] ?? '0'));

            if ($valor <= 0 || $forma === '') {
                continue;
            }

            $liquido = $valor;

            if ($forma === 'DINHEIRO' && $trocoRestante > 0) {
                $descontoTroco = min($liquido, $trocoRestante);
                $liquido = round($liquido - $descontoTroco, 2);
                $trocoRestante = round($trocoRestante - $descontoTroco, 2);
            }

            if ($liquido <= 0) {
                continue;
            }

            $historico = $prefixo . $numero;

            if ($forma !== 'DINHEIRO') {
                $historico .= ' - ' . $forma;
            }

            PdvCaixaMovimento::query()->create($this->movimentoPayload($tipo, [
                'pdv_caixa_sessao_id' => $sessaoId,
                'tipo' => $tipo,
                'historico' => $historico,
                'forma_pagamento' => $forma,
                'entrada' => $direcao === 'entrada' ? $liquido : 0,
                'saida' => $direcao === 'estorno' ? $liquido : 0,
                'pdv_venda_id' => $venda->id,
            ]));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function movimentoPayload(string $tipo, array $payload): array
    {
        if ($this->config !== null) {
            $plano = $this->config->planoContaCodigo($tipo);

            if ($plano) {
                $payload['plano_conta_codigo'] = $plano;
            }
        }

        return $payload;
    }
}
