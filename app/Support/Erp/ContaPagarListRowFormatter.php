<?php

namespace App\Support\Erp;

use App\Models\ContaPagar;
use App\Support\Erp\Financeiro\ContaPagarCompetencia;
use Illuminate\Support\Carbon;

final class ContaPagarListRowFormatter
{
    /**
     * @return array<string, mixed>
     */
    public function format(ContaPagar $record): array
    {
        return [
            'numero' => e($this->formatId($record->numero)),
            'num_compra' => e($this->formatId($record->compra?->numero)),
            'emissao' => e($this->formatData($record->emissao)),
            'documento' => filled($record->documento) ? e((string) $record->documento) : '—',
            'nota_fiscal' => filled($record->compra?->numero_nota) ? e((string) $record->compra->numero_nota) : '—',
            'fornecedor' => e($record->fornecedor?->nome_razao ?? '—'),
            'vencimento' => e($this->formatData($record->vencimento)),
            'competencia' => e($this->formatCompetenciaMesAno($record->competencia ?? null)),
            'valor' => $this->formatMoneyCell($record->valor),
            'desconto' => $this->formatMoneyCell($record->desconto),
            'juros' => $this->formatMoneyCell($record->juros),
            'valor_pago' => $this->formatMoneyCell($record->valor_pago),
            'pago_em' => e($this->formatData($record->pago_em)),
            'saldo' => $this->formatMoneyCell($record->saldo),
            'row_class' => $this->rowClass($record),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function rowClass(ContaPagar $record): array
    {
        if ((float) $record->saldo <= 0) {
            return ['erp-pagar-row--paga'];
        }

        if ($record->vencimento && $record->vencimento->toDateString() < ErpTimezone::today()) {
            return ['erp-pagar-row--vencida'];
        }

        return [];
    }

    private function formatId(mixed $state): string
    {
        $raw = trim((string) ($state ?? ''));
        if ($raw === '') {
            return '—';
        }

        $numero = ltrim($raw, '0');

        return $numero !== '' ? $numero : '0';
    }

    private function formatData(mixed $state): string
    {
        if ($state === null || $state === '') {
            return '—';
        }

        try {
            return Carbon::parse($state)->format('d/m/Y');
        } catch (\Throwable) {
            return '—';
        }
    }

    private function formatCompetenciaMesAno(mixed $state): string
    {
        $fmt = ContaPagarCompetencia::formatMesAno($state);

        return $fmt !== '' ? $fmt : '—';
    }

    private function formatMoney(mixed $state): string
    {
        return number_format((float) ($state ?? 0), 2, ',', '.');
    }

    private function formatMoneyCell(mixed $state): string
    {
        $valor = $this->formatMoney($state);

        return '<span class="erp-pagar-money">'
            .'<span class="erp-pagar-money__currency">R$</span>'
            .'<span class="erp-pagar-money__amount" title="R$ '.e($valor).'">'.e($valor).'</span>'
            .'</span>';
    }
}
