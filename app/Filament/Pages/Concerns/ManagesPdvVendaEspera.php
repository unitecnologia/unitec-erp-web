<?php

namespace App\Filament\Pages\Concerns;

use App\Livewire\Erp\PdvHotPath;
use App\Models\PdvVendaEspera;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Pdv\PdvCaixaEsperaDescarteLog;
use App\Support\Erp\Pdv\PdvImportReserva;
use App\Support\Erp\Pdv\PdvVendaEsperaService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

trait ManagesPdvVendaEspera
{
    public string $vendaEsperaSearch = '';

    /** @var list<array<string, mixed>> */
    public array $vendaEsperaResults = [];

    public ?int $selectedVendaEsperaIndex = null;

    public string $vendaEsperaMotivoDescarte = '';

    public function suspenderVendaEmEspera(): void
    {
        if (! $this->caixaAberto || ! $this->caixaSessaoId) {
            $this->notifyPdvError('Caixa fechado.');

            return;
        }

        if ($this->mesaBloqueiaOperacao('Venda em espera')) {
            return;
        }

        // Hot path grava o cupom na sessão; carregar antes de salvar a espera.
        $this->loadCupomFromSession();

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
                'desconto_venda' => session('erp.pdv.import_desconto_venda'),
                'acrescimo_venda' => session('erp.pdv.import_acrescimo_venda'),
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
        $itens = array_values($this->cupomItens);

        $espera = PdvVendaEspera::query()->create([
            'pdv_caixa_sessao_id' => $this->caixaSessaoId,
            'user_id' => $user->id,
            'vendedor_id' => $this->vendedorId,
            'sequencia' => $service->nextSequencia($this->caixaSessaoId),
            'cliente_nome' => $clienteNome !== '' ? $clienteNome : null,
            'vendedor_nome' => $this->vendedor !== '' ? $this->vendedor : null,
            'qtd_itens' => count($itens),
            'total' => $total,
            'snapshot' => $service->encode($service->buildSnapshot($itens, $contexto)),
        ]);

        // Só limpa o cupom depois que a espera foi persistida. Documento importado segue reservado.
        $this->limparCupom(liberarImportacao: false);
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

        if ($this->mesaBloqueiaOperacao('Venda em espera')) {
            return;
        }

        $this->vendaEsperaSearch = '';
        $this->selectedVendaEsperaIndex = null;
        $this->vendaEsperaMotivoDescarte = '';
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

    public function updatedVendaEsperaMotivoDescarte(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');
        if ($this->vendaEsperaMotivoDescarte !== $upper) {
            $this->vendaEsperaMotivoDescarte = $upper;
        }
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
            $this->vendaEsperaMotivoDescarte = '';
            $this->dispatch('erp-pdv-focus-vendas-espera-motivo');
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

        if ($this->mesaBloqueiaOperacao('Venda em espera')) {
            return;
        }

        $this->loadCupomFromSession();

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

        $itens = array_values($snapshot['cupom_itens']);
        if ($itens === []) {
            $this->notifyPdvError('A espera não contém itens para recuperar.');

            return;
        }

        $this->cupomItens = $itens;
        $this->selectedCupomIndex = null;
        $this->pdvMostrarDetalheItem = false;
        $this->persistCupomToSession();

        $contexto = is_array($snapshot['contexto'] ?? null) ? $snapshot['contexto'] : [];
        $import = is_array($contexto['import'] ?? null) ? $contexto['import'] : [];
        $avisoReserva = $this->renovarReservaImportadaDaEspera($import);
        session([
            'erp.pdv.orcamento_id' => $import['orcamento_id'] ?? null,
            'erp.pdv.venda_id' => $import['venda_id'] ?? null,
            'erp.pdv.import_cliente_id' => $import['cliente_id'] ?? null,
            'erp.pdv.import_cliente_nome' => $import['cliente_nome'] ?? null,
            'erp.pdv.import_desconto_venda' => $import['desconto_venda'] ?? null,
            'erp.pdv.import_acrescimo_venda' => $import['acrescimo_venda'] ?? null,
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

        if ($this->pdvHotPathEnabled ?? false) {
            $this->dispatch('erp-pdv-hot-reload-cupom')->to(PdvHotPath::class);
        }

        $espera->delete();
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');

        Notification::make()
            ->title('Venda em espera recuperada.')
            ->body(sprintf('%d item(ns) — R$ %s', count($itens), ErpMoney::formatBr($this->cupomTotalValor())))
            ->success()
            ->send();

        if ($avisoReserva !== null) {
            Notification::make()
                ->title('Atenção: documento importado')
                ->body($avisoReserva.' A finalização será bloqueada se ele já tiver sido faturado.')
                ->warning()
                ->persistent()
                ->send();
        }
    }

    /**
     * @param  array<string, mixed>  $import
     */
    protected function renovarReservaImportadaDaEspera(array $import): ?string
    {
        $avisos = [];

        foreach ([
            PdvImportReserva::ORCAMENTO => (int) ($import['orcamento_id'] ?? 0),
            PdvImportReserva::PEDIDO => (int) ($import['venda_id'] ?? 0),
        ] as $tipo => $id) {
            if ($id > 0 && ($erro = $this->reservarDocumentoImportado($tipo, $id)) !== null) {
                $avisos[] = $erro;
            }
        }

        return $avisos === [] ? null : implode(' ', $avisos);
    }

    public function requestExcluirVendaEmEspera(): void
    {
        if ($this->selectedVendaEsperaIndex === null) {
            return;
        }

        $this->dispatch('erp-pdv-focus-vendas-espera-motivo');
    }

    public function confirmarExcluirVendaEmEspera(): void
    {
        $motivo = trim($this->vendaEsperaMotivoDescarte);

        if ($motivo === '' || mb_strlen($motivo, 'UTF-8') < 10) {
            Notification::make()
                ->title('Informe o motivo do descarte.')
                ->body('Mínimo de 10 caracteres.')
                ->warning()
                ->send();
            $this->dispatch('erp-pdv-focus-vendas-espera-motivo');

            return;
        }

        $user = Auth::user();
        $row = $this->vendaEsperaResults[$this->selectedVendaEsperaIndex ?? -1] ?? null;

        if (! $row || ! $user || ! $this->caixaSessaoId) {
            return;
        }

        $espera = PdvVendaEspera::query()
            ->whereKey((int) $row['id'])
            ->where('pdv_caixa_sessao_id', $this->caixaSessaoId)
            ->where('user_id', $user->id)
            ->first();

        if (! $espera) {
            $this->vendaEsperaMotivoDescarte = '';
            $this->refreshVendasEsperaResults();

            return;
        }

        $sessao = $this->caixaSessaoAtual();
        if (! $sessao || $sessao->fechado_em !== null) {
            Notification::make()
                ->title('Caixa fechado.')
                ->body('Não foi possível registrar o descarte.')
                ->warning()
                ->send();

            return;
        }

        $snapshot = app(PdvVendaEsperaService::class)->decode($espera);
        $contexto = is_array($snapshot['contexto'] ?? null) ? $snapshot['contexto'] : [];
        $import = is_array($contexto['import'] ?? null) ? $contexto['import'] : [];

        (new PdvCaixaEsperaDescarteLog())->registrar($sessao, $espera, $motivo);
        $espera->delete();

        $this->liberarDocumentosImportados(
            (int) ($import['orcamento_id'] ?? 0),
            (int) ($import['venda_id'] ?? 0),
        );

        $this->vendaEsperaMotivoDescarte = '';
        $this->selectedVendaEsperaIndex = null;
        $this->refreshVendasEsperaResults();

        Notification::make()
            ->title('Venda em espera descartada.')
            ->success()
            ->send();
    }

    public function cancelVendaEmEspera(): void
    {
        $this->vendaEsperaMotivoDescarte = '';
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
