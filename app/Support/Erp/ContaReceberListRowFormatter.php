<?php

namespace App\Support\Erp;

use App\Models\ContaReceber;
use Illuminate\Support\Carbon;

final class ContaReceberListRowFormatter
{
    /**
     * id do pedido no Monitor (forca_vendas_orders) => Nº Pedido exibido.
     *
     * @var array<int, string>
     */
    public array $pedidosMonitor = [];

    /**
     * @param  array<int, int|string>  $selecionadosParaBaixa
     * @return array<string, string>
     */
    public function format(ContaReceber $record, string $clienteFilter, array $selecionadosParaBaixa): array
    {
        $podeMarcar = $clienteFilter !== 'todos'
            && is_numeric($clienteFilter)
            && (float) $record->saldo > 0;

        $checked = in_array((int) $record->getKey(), array_map('intval', $selecionadosParaBaixa), true);

        return [
            'baixa' => $this->formatBaixaCheckbox((int) $record->getKey(), $podeMarcar, $checked),
            'numero' => e($this->formatNumero($record->numero)),
            'emissao' => e($this->formatData($record->emissao)),
            'historico' => e((string) ($record->historico ?? '—')),
            'documento' => e(ContaReceberPedidoExibicao::texto($record->documento, $this->pedidosMonitor)),
            'cartao_maquininha' => filled($record->cartao_maquininha) ? e((string) $record->cartao_maquininha) : '—',
            'cartao_bandeira' => filled($record->cartao_bandeira) ? e((string) $record->cartao_bandeira) : '—',
            'cliente' => e($record->cliente?->nome_razao ?? '—'),
            'vencimento' => e($this->formatData($record->vencimento)),
            'valor' => $this->formatMoneyCell($record->valor),
            'numero_cheque' => filled($record->numero_cheque) ? e((string) $record->numero_cheque) : '—',
            'desconto' => $this->formatMoneyCell($record->desconto),
            'juros' => $this->formatMoneyCell($record->juros),
            'multa' => $this->formatMoneyCell($record->multa),
            'valor_recebido' => $this->formatMoneyCell($record->valor_recebido),
            'recebido_em' => e($this->formatData($record->recebido_em)),
            'saldo' => $this->formatMoneyCell($record->saldo),
            'visualizar' => $this->formatViewButton((int) $record->getKey()),
            'row_class' => $this->rowClass($record, $checked),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function rowClass(ContaReceber $record, bool $selected = false): array
    {
        $classes = [];

        if ($selected) {
            $classes[] = 'erp-row-selected';
        }

        if ((float) $record->saldo <= 0) {
            $classes[] = 'erp-receber-row--recebida';

            return $classes;
        }

        if ($record->vencimento && $record->vencimento->isBefore(now()->startOfDay())) {
            $classes[] = 'erp-receber-row--vencida';
        }

        return $classes;
    }

    private function formatBaixaCheckbox(int $contaId, bool $podeMarcar, bool $checked): string
    {
        $title = $podeMarcar ? 'Marcar para baixa' : 'Selecione um cliente para marcar contas';
        $disabled = $podeMarcar ? '' : ' disabled';
        $checkedAttr = $checked ? ' checked' : '';

        return '<input type="checkbox" class="erp-receber__check" value="'.$contaId.'"'
            . ' onclick="const row=this.closest(\'tr\'); if(row){ row.classList.toggle(\'erp-row-selected\', this.checked); }"'
            . ' wire:click.stop="$dispatch(\'erp-receber-toggle-baixa\', { contaId: '.$contaId.', selected: $event.target.checked })"'
            . ' wire:key="receber-baixa-'.$contaId.'"'
            . $checkedAttr
            . $disabled
            . ' title="'.e($title).'"'
            . ' @click.stop />';
    }

    private function formatViewButton(int $contaId): string
    {
        return '<span role="button" tabindex="0"'
            . ' wire:click.stop="$dispatch(\'erp-receber-open-view\', { contaId: '.$contaId.' })"'
            . ' wire:keydown.enter.stop="$dispatch(\'erp-receber-open-view\', { contaId: '.$contaId.' })"'
            . ' class="erp-receber__eye-btn" title="Visualizar conta e venda" aria-label="Visualizar conta e venda">'
            . '<svg class="erp-receber__eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"'
            . ' stroke-width="1.75" stroke="currentColor" aria-hidden="true">'
            . '<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>'
            . '<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>'
            . '</svg></span>';
    }

    private function formatNumero(mixed $numero): string
    {
        $texto = trim((string) ($numero ?? ''));

        if ($texto === '') {
            return '—';
        }

        $semZeros = ltrim($texto, '0');

        return $semZeros !== '' ? $semZeros : '0';
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

    private function formatMoneyCell(mixed $state): string
    {
        $valor = $this->formatMoney($state);

        return '<span class="erp-receber-money">'
            .'<span class="erp-receber-money__currency">R$</span>'
            .'<span class="erp-receber-money__amount" title="R$ '.e($valor).'">'.e($valor).'</span>'
            .'</span>';
    }
}
