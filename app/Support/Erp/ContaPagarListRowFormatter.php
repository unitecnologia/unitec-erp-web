<?php

namespace App\Support\Erp;

use App\Models\ContaPagar;
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
            'valor' => e($this->formatMoney($record->valor)),
            'desconto' => e($this->formatMoney($record->desconto)),
            'juros' => e($this->formatMoney($record->juros)),
            'valor_pago' => e($this->formatMoney($record->valor_pago)),
            'pago_em' => e($this->formatData($record->pago_em)),
            'saldo' => e($this->formatMoney($record->saldo)),
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

        if ($record->vencimento && $record->vencimento->isBefore(now()->startOfDay())) {
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

    private function formatMoney(mixed $state): string
    {
        return number_format((float) ($state ?? 0), 2, ',', '.');
    }
}
