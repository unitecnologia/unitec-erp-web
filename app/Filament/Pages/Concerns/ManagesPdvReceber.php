<?php

namespace App\Filament\Pages\Concerns;

use App\Models\ContaReceber;
use App\Models\PdvCaixaMovimento;
use App\Support\Erp\ErpMoney;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

trait ManagesPdvReceber
{
    public string $receberSearch = '';

    /** @var array<int, array<string, mixed>> */
    public array $receberResults = [];

    public ?int $selectedReceberIndex = null;

    public string $receberValor = '0,00';

    public function openReceberModal(): void
    {
        if (! $this->caixaAberto) {
            $this->notifyPdvError('Caixa fechado.');

            return;
        }

        $this->receberSearch = '';
        $this->receberValor = '0,00';
        $this->refreshReceberResults();
        $this->openPdvModal('receber');
        $this->dispatch('erp-pdv-focus-receber');
    }

    public function updatedReceberSearch(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->receberSearch !== $upper) {
            $this->receberSearch = $upper;
        }

        $this->refreshReceberResults();
    }

    public function refreshReceberResults(): void
    {
        $term = trim($this->receberSearch);
        $like = $term !== '' ? '%' . $term . '%' : null;

        $query = ContaReceber::query()
            ->with('cliente:id,nome_razao,codigo')
            ->where('saldo', '>', 0)
            ->orderBy('vencimento');

        if ($like) {
            $query->where(function ($q) use ($like): void {
                $q->where('numero', 'like', $like)
                    ->orWhere('historico', 'like', $like)
                    ->orWhere('documento', 'like', $like)
                    ->orWhereHas('cliente', fn ($sub) => $sub->where('nome_razao', 'like', $like));
            });
        }

        $this->receberResults = $query
            ->limit(50)
            ->get()
            ->map(fn (ContaReceber $conta): array => [
                'conta_id' => $conta->id,
                'numero' => $conta->numero,
                'cliente' => mb_strtoupper($conta->cliente?->nome_razao ?? '—', 'UTF-8'),
                'vencimento' => $conta->vencimento?->format('d/m/Y') ?? '',
                'saldo' => (float) $conta->saldo,
                'saldo_fmt' => ErpMoney::formatBr($conta->saldo),
            ])
            ->values()
            ->all();

        $this->selectedReceberIndex = $this->receberResults === [] ? null : 0;
        $this->syncReceberValorFromSelection();
    }

    public function selectReceberRow(int $index): void
    {
        if (isset($this->receberResults[$index])) {
            $this->selectedReceberIndex = $index;
            $this->syncReceberValorFromSelection();
        }
    }

    public function moveReceberSelection(int $delta): void
    {
        if ($this->receberResults === []) {
            return;
        }

        $count = count($this->receberResults);
        $index = ($this->selectedReceberIndex ?? 0) + $delta;
        $this->selectedReceberIndex = max(0, min($count - 1, $index));
        $this->syncReceberValorFromSelection();
    }

    protected function syncReceberValorFromSelection(): void
    {
        $index = $this->selectedReceberIndex;

        if ($index === null || ! isset($this->receberResults[$index])) {
            $this->receberValor = '0,00';

            return;
        }

        $this->receberValor = ErpMoney::formatBr($this->receberResults[$index]['saldo'] ?? 0);
    }

    public function confirmReceberConta(): void
    {
        if (! $this->caixaAberto || ! $this->caixaSessaoId) {
            $this->notifyPdvError('Caixa fechado.');

            return;
        }

        $index = $this->selectedReceberIndex;

        if ($index === null || ! isset($this->receberResults[$index])) {
            $this->notifyPdvError('Selecione uma conta.');

            return;
        }

        $contaId = (int) ($this->receberResults[$index]['conta_id'] ?? 0);
        $conta = ContaReceber::query()->find($contaId);

        if (! $conta || (float) $conta->saldo <= 0) {
            $this->notifyPdvError('Conta indisponível.');

            return;
        }

        $valor = ErpMoney::parseBr($this->receberValor, 2);
        $saldo = (float) $conta->saldo;

        if ($valor <= 0) {
            $this->notifyPdvError('Informe um valor válido.');

            return;
        }

        if ($valor > $saldo) {
            $this->notifyPdvError('Valor maior que o saldo da conta.');

            return;
        }

        DB::transaction(function () use ($conta, $valor): void {
            $conta->valor_recebido = round((float) $conta->valor_recebido + $valor, 2);
            $conta->recebido_em = now()->toDateString();
            $conta->save();

            PdvCaixaMovimento::query()->create(
                $this->pdvMovimentoPayload('recebimento', [
                    'pdv_caixa_sessao_id' => $this->caixaSessaoId,
                    'tipo' => 'recebimento',
                    'historico' => 'RECEB. CR ' . $conta->numero,
                    'forma_pagamento' => 'DINHEIRO',
                    'entrada' => $valor,
                    'saida' => 0,
                ]),
            );
        });

        $this->closePdvModal();

        Notification::make()
            ->title('Recebimento registrado.')
            ->body('Conta ' . $conta->numero . ' — R$ ' . ErpMoney::formatBr($valor))
            ->success()
            ->send();

        $this->dispatch('erp-pdv-focus-search');
    }

    public function cancelReceber(): void
    {
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');
    }
}
