<?php

namespace App\Filament\Resources\ContaReceberResource\Pages\Concerns;

use App\Models\ContaReceber;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Financeiro\ContaReceberBaixaService;
use Filament\Notifications\Notification;
use InvalidArgumentException;

trait ManagesContaReceberBaixaModal
{
    public bool $baixaModalOpen = false;

    /** @var list<int> */
    public array $baixaContaIds = [];

    public ?int $baixaFormaPagamentoId = null;

    public string $baixaResumoQtd = '0';

    public string $baixaResumoTotal = '0,00';

    /** @var list<array{id: int, label: string, tipo: string|null}> */
    public array $baixaFormasOptions = [];

    public function baixarConta(): void
    {
        $ids = $this->resolverIdsParaBaixa();

        if ($ids === []) {
            return;
        }

        $contas = ContaReceber::query()
            ->whereIn('id', $ids)
            ->get(['id', 'saldo']);

        $pendentes = $contas->filter(fn (ContaReceber $c): bool => (float) $c->saldo > 0);

        if ($pendentes->isEmpty()) {
            Notification::make()
                ->title('Nenhuma conta com saldo para baixar.')
                ->warning()
                ->send();

            return;
        }

        $service = app(ContaReceberBaixaService::class);
        $this->baixaFormasOptions = $service->formasDisponiveis();

        if ($this->baixaFormasOptions === []) {
            Notification::make()
                ->title('Cadastre um meio de pagamento')
                ->body('Nenhuma forma de pagamento disponível para Contas a Receber.')
                ->warning()
                ->send();

            return;
        }

        $this->baixaContaIds = $pendentes->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
        $this->baixaResumoQtd = (string) count($this->baixaContaIds);
        $this->baixaResumoTotal = ErpMoney::formatBr((float) $pendentes->sum(fn (ContaReceber $c): float => (float) $c->saldo));
        $this->baixaFormaPagamentoId = (int) ($this->baixaFormasOptions[0]['id'] ?? 0);
        $this->baixaModalOpen = true;
    }

    public function closeBaixaModal(): void
    {
        $this->baixaModalOpen = false;
        $this->baixaContaIds = [];
        $this->baixaFormaPagamentoId = null;
        $this->baixaResumoQtd = '0';
        $this->baixaResumoTotal = '0,00';
        $this->baixaFormasOptions = [];
    }

    public function confirmarBaixaConta(): void
    {
        if (! $this->baixaModalOpen || $this->baixaContaIds === []) {
            return;
        }

        if (! $this->baixaFormaPagamentoId) {
            Notification::make()
                ->title('Selecione o meio de pagamento.')
                ->warning()
                ->send();

            return;
        }

        try {
            $resultado = app(ContaReceberBaixaService::class)
                ->baixarMuitas($this->baixaContaIds, (int) $this->baixaFormaPagamentoId);
        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title($e->getMessage())
                ->danger()
                ->send();

            return;
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Não foi possível baixar a conta.')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->closeBaixaModal();
        $this->selecionadosParaBaixa = [];
        $this->clearListSelection();
        $this->situacaoFilter = 'recebidas';
        $this->resetTable();

        if ($resultado['ok'] < 1) {
            Notification::make()
                ->title('Nenhuma conta foi baixada.')
                ->warning()
                ->send();

            return;
        }

        $qtd = $resultado['ok'];
        Notification::make()
            ->title($qtd === 1 ? 'Conta baixada.' : "{$qtd} contas baixadas.")
            ->body('Total recebido: R$ '.ErpMoney::formatBr((float) $resultado['total']))
            ->success()
            ->send();
    }

    /**
     * @return list<int>
     */
    protected function resolverIdsParaBaixa(): array
    {
        $ids = collect($this->selecionadosParaBaixa)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->values()
            ->all();

        if ($ids !== []) {
            if ($this->clienteFilter === 'todos' || ! is_numeric($this->clienteFilter)) {
                Notification::make()
                    ->title('Selecione um cliente antes de marcar contas para baixa.')
                    ->warning()
                    ->send();

                return [];
            }

            return $ids;
        }

        if (! $this->highlightedRecordIdOrNotify('baixar')) {
            return [];
        }

        return [(int) $this->highlightedRecordId];
    }
}
