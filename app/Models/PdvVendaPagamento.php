<?php

namespace App\Models;

use App\Support\Erp\Pdv\PdvFinalizarPagamentosHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdvVendaPagamento extends Model
{
    protected $table = 'pdv_venda_pagamentos';

    protected $fillable = [
        'pdv_venda_id',
        'forma',
        'valor',
        'cartao_nsu',
        'cartao_autorizacao',
        'cartao_maquininha',
        'cartao_bandeira',
        'cartao_parcela',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
        ];
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(PdvVenda::class, 'pdv_venda_id');
    }

    public function temCanhotoCartao(): bool
    {
        return filled($this->cartao_maquininha)
            || filled($this->cartao_bandeira)
            || filled($this->cartao_nsu)
            || filled($this->cartao_autorizacao)
            || filled($this->cartao_parcela);
    }

    /**
     * Forma + dados do canhoto para pedido/NFC-e.
     * CREDIÁRIO: não embute "10x" no nome (evita ler como 10 × o total); use linhaCupom().
     */
    public function descricaoComCanhoto(): string
    {
        $base = trim((string) $this->forma);
        $isCrediario = PdvFinalizarPagamentosHelper::isFormaCrediario((string) $this->forma);
        $parcelaLabel = filled($this->cartao_parcela)
            ? (string) $this->cartao_parcela
            : $this->parcelasLabelFromContasReceber();

        $extra = array_values(array_filter([
            filled($this->cartao_maquininha) ? (string) $this->cartao_maquininha : null,
            filled($this->cartao_bandeira) ? (string) $this->cartao_bandeira : null,
            // Cartão mantém Nx no canhoto; CREDIÁRIO fica só na linhaCupom com total + parcela.
            (! $isCrediario && filled($parcelaLabel)) ? $parcelaLabel : null,
            filled($this->cartao_nsu) ? 'NSU '.$this->cartao_nsu : null,
            filled($this->cartao_autorizacao) ? 'AUT '.$this->cartao_autorizacao : null,
        ]));

        if ($extra === []) {
            return $base !== '' ? $base : '—';
        }

        return trim($base.' ('.implode(' | ', $extra).')');
    }

    /**
     * Linha completa para cupom NFC-e (forma + valor sem ambiguidade de parcelas).
     * Ex.: CREDIARIO: R$ 490,00 em 10x de R$ 49,00
     */
    public function linhaCupom(): string
    {
        $valor = (float) $this->valor;
        $valorFmt = number_format($valor, 2, ',', '');
        $base = $this->descricaoComCanhoto();

        if (! PdvFinalizarPagamentosHelper::isFormaCrediario((string) $this->forma)) {
            return $base.': R$ '.$valorFmt;
        }

        $n = $this->quantidadeParcelasCrediario();

        if ($n <= 1) {
            return ($base !== '' ? $base : 'CREDIARIO').': R$ '.$valorFmt;
        }

        $parcela = round($valor / $n, 2);
        $parcelaFmt = number_format($parcela, 2, ',', '');

        return ($base !== '' ? $base : 'CREDIARIO').': R$ '.$valorFmt.' em '.$n.'x de R$ '.$parcelaFmt;
    }

    private function quantidadeParcelasCrediario(): int
    {
        $label = filled($this->cartao_parcela)
            ? (string) $this->cartao_parcela
            : ($this->parcelasLabelFromContasReceber() ?? '');

        if (preg_match('/(\d+)\s*x/i', $label, $m) === 1) {
            return max(1, (int) $m[1]);
        }

        return 1;
    }

    /**
     * Reimpressão de CREDIÁRIO antigo sem cartao_parcela: conta títulos PDV-NNNNNN.
     */
    private function parcelasLabelFromContasReceber(): ?string
    {
        if (! PdvFinalizarPagamentosHelper::isFormaCrediario((string) $this->forma)) {
            return null;
        }

        $this->loadMissing('venda');
        $venda = $this->venda;

        if ($venda === null || ! filled($venda->numero)) {
            return null;
        }

        $documento = 'PDV-'.str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT);

        $n = ContaReceber::query()
            ->where(function ($q) use ($documento): void {
                $q->where('documento', $documento)
                    ->orWhere('documento', 'like', $documento.'/%');
            })
            ->count();

        return $n > 1 ? $n.'x' : null;
    }

    /**
     * Código tBand da NFC-e (01–09 / 99).
     */
    public function tBandFiscal(): ?string
    {
        $bandeira = mb_strtoupper(trim((string) $this->cartao_bandeira), 'UTF-8');

        if ($bandeira === '') {
            return null;
        }

        return match (true) {
            str_contains($bandeira, 'VISA') => '01',
            str_contains($bandeira, 'MASTER') => '02',
            str_contains($bandeira, 'AMERICAN') || str_contains($bandeira, 'AMEX') => '03',
            str_contains($bandeira, 'SOROCRED') => '04',
            str_contains($bandeira, 'DINERS') => '05',
            str_contains($bandeira, 'ELO') => '06',
            str_contains($bandeira, 'HIPER') => '07',
            str_contains($bandeira, 'AURA') => '08',
            str_contains($bandeira, 'CABAL') => '09',
            default => '99',
        };
    }
}
