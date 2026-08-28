<?php

namespace App\Filament\Pages\Concerns;

use App\Models\PdvVendaEspera;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Pdv\PdvVendaEsperaService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

trait ManagesPdvVendaEspera
{
    public string $vendaEsperaSearch = '';

    /** @var list<array<string, mixed>> */
    public array $vendaEsperaResults = [];

    public ?int $selectedVendaEsperaIndex = null;

    public ?int $vendaEsperaExcluirId = null;

    public function suspenderVendaEmEspera(): void
    {
        if (! $this->caixaAberto || ! $this->caixaSessaoId) {
            $this->notifyPdvError('Caixa fechado.');

            return;
        }

        if (! $this->cupomTemItens()) {
            Notification::make()
                ->title('Não há itens para suspender.')
                ->warning()
                ->send();

            return;
        }

        $user = Auth::user();
        if (! $user) {
            $this->notifyPdvError('Usuário não identificado.');

            return;
        }

        $service = app(PdvVendaEsperaService::class);
        $contexto = [
            'import' => [
                'orcamento_id' => session('erp.pdv.orcamento_id'),
                'venda_id' => session('erp.pdv.venda_id'),
                'cliente_id' => session('erp.pdv.import_cliente_id'),
                'cliente_nome' => session('erp.pdv.import_cliente_nome'),
            ],
            'vendedor' => [
                'id' => $this->vendedorId,
                'nome' => $this->vendedor,
            ],
            'price_table_id' => session('erp.pdv.price_table_id'),
            'cupom_iniciado_em' => session('erp.pdv.cupom_iniciado_em'),
        ];

        $clienteNome = trim((string) ($contexto['import']['cliente_nome'] ?? ''));
        $total = (float) $this->cupomTotalValor();
        $espera = PdvVendaEspera::query()->create([
            'pdv_caixa_sessao_id' => $this->caixaSessaoId,
            'user_id' => $user->id,
            'vendedor_id' => $this->vendedorId,
            'sequencia' => $service->nextSequencia($this->caixaSessaoId),
            'cliente_nome' => $clienteNome !== '' ? $clienteNome : null,
            'vendedor_nome' => $this->vendedor !== '' ? $this->vendedor : null,
            'qtd_itens' => count($this->cupomItens),
            'total' => $total,
            'snapshot' => $service->encode($service->buildSnapshot($this->cupomItens, $contexto)),
        ]);

        $this->limparCupom();
        $this->dispatch('erp-pdv-focus-search');

        Notification::make()
            ->title('Venda em espera salva.')
            ->body(sprintf('Espera #%d — R$ %s', $espera->sequencia, ErpMoney::formatBr($total)))
            ->success()
            ->send();
    }

    public function openVendasEsperaModal(): void
    {
        if (! $this->caixaAberto || ! $this->caixaSessaoId) {
            $this->notifyPdvError('Caixa fechado.');

            return;
        }

        $this->vendaEsperaSearch = '';
        $this->selectedVendaEsperaIndex = null;
        $this->vendaEsperaExcluirId = null;
        $this->refreshVendasEsperaResults();
        $this->openPdvModal('vendas_espera');
        $this->dispatch('erp-pdv-focus-vendas-espera');
    }

    public function updatedVendaEsperaSearch(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');
        if ($this->vendaEsperaSearch !== $upper) {
            $this->vendaEsperaSearch = $upper;
        }

        $this->refreshVendasEsperaResults();
    }

    public function refreshVendasEsperaResults(): void
    {
        $user = Auth::user();
        if (! $this->caixaSessaoId || ! $user) {
            $this->vendaEsperaResults = [];

            return;
        }

        $term = trim($this->vendaEsperaSearch);
        $query = PdvVendaEspera::query()
            ->where('pdv_caixa_sessao_id', $this->caixaSessaoId)
            ->where('user_id', $user->id)
            ->latest('id');

        if ($term !== '') {
            $query->where(function ($builder) use ($term): void {
                $builder->where('sequencia', 'like', '%'.$term.'%')
                    ->orWhere('cliente_nome', 'like', '%'.$term.'%')
                    ->orWhere('vendedor_nome', 'like', '%'.$term.'%');
            });
        }

        $this->vendaEsperaResults = $query
            ->get()
            ->map(fn (PdvVendaEspera $espera): array => [
                'id' => $espera->id,
                'numero' => $espera->sequencia,
                'cliente' => $espera->cliente_nome ?: 'Consumidor final',
                'operador' => $espera->vendedor_nome ?: '—',
                'itens' => $espera->qtd_itens,
                'total' => ErpMoney::formatBr((float) $espera->total),
                'data' => $espera->created_at ? ErpTimezone::toLocal($espera->created_at)->format('d/m/Y') : '—',
                'hora' => $espera->created_at ? ErpTimezone::toLocal($espera->created_at)->format('H:i') : '—',
            ])
            ->values()
            ->all();

        $this->selectedVendaEsperaIndex = $this->vendaEsperaResults === [] ? null : 0;
    }

    public function selectVendaEsperaRow(int $index): void
    {
        if (isset($this->vendaEsperaResults[$index])) {
            $this->selectedVendaEsperaIndex = $index;
        }
    }

    public function moveVendaEsperaSelection(int $direction): void
    {
        $count = count($this->vendaEsperaResults);
        if ($count === 0) {
            return;
        }

        $current = $this->selectedVendaEsperaIndex ?? 0;
        $this->selectedVendaEsperaIndex = max(0, min($count - 1, $current + $direction));
    }

    public function recuperarVendaEmEspera(): void
    {
        if (! $this->caixaAberto) {
            $this->notifyPdvError('Caixa fechado.');

            return;
        }

        if ($this->cupomTemItens()) {
            $this->notifyPdvError('Há uma venda em andamento. Suspenda ou cancele antes de recuperar outra.');

            return;
        }

        $row = $this->vendaEsperaResults[$this->selectedVendaEsperaIndex ?? -1] ?? null;
        $user = Auth::user();
        if (! $row || ! $user || ! $this->caixaSessaoId) {
            return;
        }

        $espera = PdvVendaEspera::query()
            ->whereKey((int) $row['id'])
            ->where('pdv_caixa_sessao_id', $this->caixaSessaoId)
            ->where('user_id', $user->id)
            ->first();

        if (! $espera) {
            $this->refreshVendasEsperaResults();

            return;
        }

        $snapshot = app(PdvVendaEsperaService::class)->decode($espera);
        if ($snapshot === null) {
            $this->notifyPdvError('Não foi possível recuperar esta venda em espera.');

            return;
        }

        $this->cupomItens = array_values($snapshot['cupom_itens']);
        $this->selectedCupomIndex = null;
        $this->pdvMostrarDetalheItem = false;
        $this->persistCupomToSession();

        $contexto = is_array($snapshot['contexto'] ?? null) ? $snapshot['contexto'] : [];
        $import = is_array($contexto['import'] ?? null) ? $contexto['import'] : [];
        session([
            'erp.pdv.orcamento_id' => $import['orcamento_id'] ?? null,
            'erp.pdv.venda_id' => $import['venda_id'] ?? null,
            'erp.pdv.import_cliente_id' => $import['cliente_id'] ?? null,
            'erp.pdv.import_cliente_nome' => $import['cliente_nome'] ?? null,
            'erp.pdv.price_table_id' => $contexto['price_table_id'] ?? null,
            'erp.pdv.cupom_iniciado_em' => $contexto['cupom_iniciado_em']
                ?? ($espera->created_at?->toIso8601String()),
        ]);
        $this->loadPdvPriceTableFromSession();

        $vendedor = is_array($contexto['vendedor'] ?? null) ? $contexto['vendedor'] : [];
        if (isset($vendedor['id']) && (int) $vendedor['id'] > 0) {
            $this->vendedorId = (int) $vendedor['id'];
            $this->vendedor = (string) ($vendedor['nome'] ?? $this->vendedor);
            $this->persistVendedorToSession();
        }

        $espera->delete();
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');

        Notification::make()
            ->title('Venda em espera recuperada.')
            ->success()
            ->send();
    }

    public function requestExcluirVendaEmEspera(): void
    {
        $row = $this->vendaEsperaResults[$this->selectedVendaEsperaIndex ?? -1] ?? null;
        $this->vendaEsperaExcluirId = $row ? (int) $row['id'] : null;
    }

    public function confirmarExcluirVendaEmEspera(): void
    {
        $user = Auth::user();
        if ($this->vendaEsperaExcluirId && $user && $this->caixaSessaoId) {
            PdvVendaEspera::query()
                ->whereKey($this->vendaEsperaExcluirId)
                ->where('pdv_caixa_sessao_id', $this->caixaSessaoId)
                ->where('user_id', $user->id)
                ->delete();
        }

        $this->vendaEsperaExcluirId = null;
        $this->refreshVendasEsperaResults();
    }

    public function cancelVendaEmEspera(): void
    {
        $this->vendaEsperaExcluirId = null;
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');
    }

    protected function vendasEmEsperaPendentesCount(): int
    {
        return $this->caixaSessaoId
            ? (int) PdvVendaEspera::query()
                ->where('pdv_caixa_sessao_id', $this->caixaSessaoId)
                ->count()
            : 0;
    }
}
