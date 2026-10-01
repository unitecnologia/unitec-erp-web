<?php

namespace App\Filament\Pages;

use App\Models\Carga;
use App\Models\CargaEntrega;
use App\Models\CargaPedido;
use App\Models\User;
use App\Models\Veiculo;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpTimezone;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class GestaoEntregasPage extends Page
{
    protected static ?string $slug = 'gestao-entregas';

    protected static ?string $routePath = 'gestao-entregas';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.gestao-entregas';

    public string $periodoDe = '';

    public string $periodoAte = '';

    public string $periodoDeApplied = '';

    public string $periodoAteApplied = '';

    public string $numeroCarga = '';

    public string $numeroCargaApplied = '';

    public string $statusFilter = 'todos';

    public string $statusFilterApplied = 'todos';

    public string $entregadorUserId = '';

    public string $entregadorUserIdApplied = '';

    public string $veiculoId = '';

    public string $veiculoIdApplied = '';

    /** @var list<array<string, mixed>> */
    public array $rows = [];

    public bool $detalheOpen = false;

    /** @var array<string, mixed>|null */
    public ?array $detalhe = null;

    public bool $cargaLookupOpen = false;

    /** @var list<array<string, mixed>> */
    public array $cargaLookupRows = [];

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        ErpScreen::set('Entregas');

        $hoje = ErpTimezone::toLocal();
        $this->periodoDe = $hoje->copy()->startOfMonth()->format('Y-m-d');
        $this->periodoAte = $hoje->format('Y-m-d');
        $this->periodoDeApplied = $this->periodoDe;
        $this->periodoAteApplied = $this->periodoAte;
        $this->statusFilterApplied = 'todos';
        $this->entregadorUserIdApplied = '';
        $this->veiculoIdApplied = '';

        $this->consultar();
    }

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('cargas.access');
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Entregas';
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'erp-list-page',
            'erp-gestao-entregas-page',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function refreshTable(): void
    {
        $this->consultar();
    }

    public function closeScreen(): void
    {
        ErpScreen::set('Principal');

        $this->redirect(filament()->getUrl());
    }

    public function updatedNumeroCarga(): void
    {
        $this->consultar();
    }

    public function updatedStatusFilter(): void
    {
        $this->consultar();
    }

    public function updatedEntregadorUserId(): void
    {
        $this->consultar();
    }

    public function updatedVeiculoId(): void
    {
        $this->consultar();
    }

    public function consultar(): void
    {
        $this->periodoDeApplied = $this->periodoDe;
        $this->periodoAteApplied = $this->periodoAte;
        $this->numeroCargaApplied = trim($this->numeroCarga);
        $this->statusFilterApplied = $this->normalizeStatusFilter($this->statusFilter);
        $this->entregadorUserIdApplied = filled($this->entregadorUserId) ? (string) ((int) $this->entregadorUserId) : '';
        $this->veiculoIdApplied = filled($this->veiculoId) ? (string) ((int) $this->veiculoId) : '';
        $this->detalheOpen = false;
        $this->detalhe = null;
        $this->rows = $this->buildRows();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function entregadorOptions(): array
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        return User::query()
            ->where('ativo', true)
            ->whereHas('vendedor', function ($q): void {
                $q->where('ativo', true)->where('entregador', true);
            })
            ->when($empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u): array => [
                'id' => (int) $u->id,
                'label' => (string) $u->name,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function veiculoOptions(): array
    {
        return Veiculo::query()
            ->where('ativo', true)
            ->orderBy('placa')
            ->get(['id', 'placa', 'descricao'])
            ->map(function (Veiculo $item): array {
                $label = (string) $item->placa;
                $descricao = trim((string) ($item->descricao ?? ''));

                if ($descricao !== '') {
                    $label .= ' — '.$descricao;
                }

                return [
                    'id' => (int) $item->id,
                    'label' => $label,
                ];
            })
            ->all();
    }

    public function abrirDetalhe(int $cargaId, int $pedidoId): void
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        $cp = CargaPedido::query()
            ->with([
                'carga:id,numero,empresa_id,entregador_user_id',
                'carga.entregador:id,name',
                'pedido:id,numero,cliente_id',
                'pedido.cliente:id,nome_razao',
            ])
            ->where('carga_id', $cargaId)
            ->where('pedido_id', $pedidoId)
            ->when($empresaId > 0, function (Builder $q) use ($empresaId): void {
                $q->whereHas('carga', fn (Builder $c) => $c->where('empresa_id', $empresaId));
            })
            ->first();

        if ($cp === null || $cp->carga === null || $cp->pedido === null) {
            $this->detalheOpen = false;
            $this->detalhe = null;

            return;
        }

        $entrega = CargaEntrega::query()
            ->with(['entregador:id,name', 'itens'])
            ->where('carga_id', $cargaId)
            ->where('pedido_id', $pedidoId)
            ->first();

        $status = $entrega?->status ? (string) $entrega->status : 'pendente';
        $pedidoNumero = ltrim((string) $cp->pedido->numero, '0');
        $cargaNumero = ltrim((string) $cp->carga->numero, '0');
        $fotoPath = filled($entrega?->foto_path) ? (string) $entrega->foto_path : null;
        $assinaturaPath = filled($entrega?->assinatura_path) ? (string) $entrega->assinatura_path : null;
        $entregador = $entrega?->entregador?->name ?: $cp->carga->entregador?->name;

        $this->detalhe = [
            'carga_id' => (int) $cp->carga_id,
            'carga_numero' => $cargaNumero !== '' ? $cargaNumero : '0',
            'pedido_id' => (int) $cp->pedido_id,
            'pedido_numero' => $pedidoNumero !== '' ? $pedidoNumero : '0',
            'cliente' => (string) ($cp->pedido->cliente?->nome_razao ?: 'CONSUMIDOR'),
            'entregador' => filled($entregador) ? (string) $entregador : '—',
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'motivo' => $status === CargaEntrega::STATUS_NAO_ENTREGUE
                ? (CargaEntrega::motivosNaoEntrega()[(string) ($entrega?->motivo_nao_entrega ?? '')] ?? (string) ($entrega?->motivo_nao_entrega ?: '—'))
                : null,
            'observacao' => filled($entrega?->observacao) ? (string) $entrega->observacao : null,
            'foto_url' => $fotoPath !== null ? route('erp.storage.file', ['path' => $fotoPath]) : null,
            'assinatura_url' => $assinaturaPath !== null ? route('erp.storage.file', ['path' => $assinaturaPath]) : null,
            'concluida_em' => $entrega?->concluida_em
                ? ErpTimezone::toLocal($entrega->concluida_em)->format('d/m/Y H:i')
                : null,
            'itens' => $entrega?->itens->map(fn ($it): array => [
                'codigo' => (string) ($it->codigo ?: '—'),
                'descricao' => (string) $it->descricao,
                'quantidade_original' => (float) $it->quantidade_original,
                'quantidade' => (float) $it->quantidade,
                'unidade' => (string) ($it->unidade ?: 'UN'),
            ])->values()->all() ?? [],
        ];
        $this->detalheOpen = true;
    }

    public function fecharDetalhe(): void
    {
        $this->detalheOpen = false;
        $this->detalhe = null;
    }

    public function abrirCargaLookup(): void
    {
        $this->cargaLookupRows = $this->buildCargasNaRua();
        $this->cargaLookupOpen = true;
        $this->detalheOpen = false;
        $this->detalhe = null;
    }

    public function fecharCargaLookup(): void
    {
        $this->cargaLookupOpen = false;
        $this->cargaLookupRows = [];
    }

    public function selecionarCargaLookup(string $numero): void
    {
        $this->numeroCarga = ltrim(trim($numero), '0') ?: '0';
        $this->fecharCargaLookup();
        $this->consultar();
    }

    /**
     * Cargas abertas/fechadas (em andamento / na rua) para filtrar a consulta.
     *
     * @return list<array<string, mixed>>
     */
    protected function buildCargasNaRua(): array
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        $cargas = Carga::query()
            ->with(['entregador:id,name'])
            ->withCount('pedidos')
            ->when($empresaId > 0, fn (Builder $q) => $q->where('empresa_id', $empresaId))
            ->whereIn('status', [Carga::STATUS_ABERTA, Carga::STATUS_FECHADA])
            ->orderByRaw("CASE WHEN status = ? THEN 0 ELSE 1 END", [Carga::STATUS_FECHADA])
            ->orderByDesc('data')
            ->orderByDesc('numero')
            ->limit(200)
            ->get(['id', 'numero', 'data', 'entregador_user_id', 'status']);

        if ($cargas->isEmpty()) {
            return [];
        }

        $cargaIds = $cargas->pluck('id')->all();
        $ocorrencias = CargaEntrega::query()
            ->whereIn('carga_id', $cargaIds)
            ->selectRaw('carga_id, status, COUNT(*) as total')
            ->groupBy('carga_id', 'status')
            ->get()
            ->groupBy('carga_id');

        return $cargas->map(function (Carga $carga) use ($ocorrencias): array {
            $porStatus = $ocorrencias->get($carga->id, collect());
            $entregues = (int) ($porStatus->firstWhere('status', CargaEntrega::STATUS_ENTREGUE)?->total ?? 0);
            $parciais = (int) ($porStatus->firstWhere('status', CargaEntrega::STATUS_PARCIAL)?->total ?? 0);
            $naoEntregues = (int) ($porStatus->firstWhere('status', CargaEntrega::STATUS_NAO_ENTREGUE)?->total ?? 0);
            $pedidos = (int) ($carga->pedidos_count ?? 0);
            $pendentes = max(0, $pedidos - $entregues - $parciais - $naoEntregues);
            $numero = ltrim((string) $carga->numero, '0');

            return [
                'id' => (int) $carga->id,
                'numero' => $numero !== '' ? $numero : '0',
                'data' => optional($carga->data)->format('d/m/Y') ?: '—',
                'entregador' => (string) ($carga->entregador?->name ?: '—'),
                'pedidos' => $pedidos,
                'entregues' => $entregues,
                'nao_entregues' => $naoEntregues,
                'pendentes' => $pendentes,
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildRows(): array
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        $query = CargaPedido::query()
            ->with([
                'carga:id,numero,data,status,empresa_id,entregador_user_id,veiculo_id',
                'carga.entregador:id,name',
                'pedido:id,numero,cliente_id',
                'pedido.cliente:id,nome_razao',
            ])
            ->whereHas('carga', function (Builder $q) use ($empresaId): void {
                $q->where('status', '!=', Carga::STATUS_CANCELADA);

                if ($empresaId > 0) {
                    $q->where('empresa_id', $empresaId);
                }

                if (filled($this->periodoDeApplied)) {
                    $q->whereDate('data', '>=', $this->periodoDeApplied);
                }

                if (filled($this->periodoAteApplied)) {
                    $q->whereDate('data', '<=', $this->periodoAteApplied);
                }

                if (filled($this->numeroCargaApplied)) {
                    $term = ltrim(trim($this->numeroCargaApplied), '0') ?: '0';
                    $q->where('numero', 'like', '%'.$term.'%');
                }

                if (filled($this->entregadorUserIdApplied)) {
                    $q->where('entregador_user_id', (int) $this->entregadorUserIdApplied);
                }

                if (filled($this->veiculoIdApplied)) {
                    $q->where('veiculo_id', (int) $this->veiculoIdApplied);
                }
            });

        if ($this->statusFilterApplied === 'pendente') {
            $query->whereNotExists(function ($q): void {
                $q->selectRaw('1')
                    ->from('carga_entregas')
                    ->whereColumn('carga_entregas.carga_id', 'carga_pedidos.carga_id')
                    ->whereColumn('carga_entregas.pedido_id', 'carga_pedidos.pedido_id');
            });
        } elseif ($this->statusFilterApplied === CargaEntrega::STATUS_ENTREGUE
            || $this->statusFilterApplied === CargaEntrega::STATUS_PARCIAL
            || $this->statusFilterApplied === CargaEntrega::STATUS_NAO_ENTREGUE) {
            $status = $this->statusFilterApplied;
            $query->whereExists(function ($q) use ($status): void {
                $q->selectRaw('1')
                    ->from('carga_entregas')
                    ->whereColumn('carga_entregas.carga_id', 'carga_pedidos.carga_id')
                    ->whereColumn('carga_entregas.pedido_id', 'carga_pedidos.pedido_id')
                    ->where('carga_entregas.status', $status);
            });
        }

        $items = $query
            ->orderByDesc('carga_id')
            ->orderBy('pedido_id')
            ->limit(500)
            ->get();

        $entregaMap = CargaEntrega::query()
            ->with('entregador:id,name')
            ->whereIn('carga_id', $items->pluck('carga_id')->unique()->filter()->all())
            ->whereIn('pedido_id', $items->pluck('pedido_id')->unique()->filter()->all())
            ->get()
            ->keyBy(fn (CargaEntrega $e): string => $e->carga_id.'-'.$e->pedido_id);

        return $items
            ->sortBy([
                fn (CargaPedido $a) => -1 * (int) ($a->carga?->numero ?? 0),
                fn (CargaPedido $a) => (int) ($a->pedido?->numero ?? 0),
            ])
            ->values()
            ->map(function (CargaPedido $cp) use ($entregaMap): array {
                $entrega = $entregaMap->get($cp->carga_id.'-'.$cp->pedido_id);
                $status = $entrega?->status ? (string) $entrega->status : 'pendente';
                $pedidoNumero = ltrim((string) ($cp->pedido?->numero ?? ''), '0');
                $cargaNumero = ltrim((string) ($cp->carga?->numero ?? ''), '0');
                $entregador = $entrega?->entregador?->name ?: $cp->carga?->entregador?->name;

                return [
                    'carga_id' => (int) $cp->carga_id,
                    'carga_numero' => $cargaNumero !== '' ? $cargaNumero : '0',
                    'pedido_id' => (int) $cp->pedido_id,
                    'pedido_numero' => $pedidoNumero !== '' ? $pedidoNumero : '0',
                    'cliente' => (string) ($cp->pedido?->cliente?->nome_razao ?: 'CONSUMIDOR'),
                    'entregador' => filled($entregador) ? (string) $entregador : '—',
                    'status' => $status,
                    'status_label' => $this->statusLabel($status),
                    'concluida_em' => $entrega?->concluida_em
                        ? ErpTimezone::toLocal($entrega->concluida_em)->format('d/m H:i')
                        : '—',
                ];
            })
            ->all();
    }

    protected function normalizeStatusFilter(mixed $value): string
    {
        $allowed = ['todos', 'pendente', CargaEntrega::STATUS_ENTREGUE, CargaEntrega::STATUS_PARCIAL, CargaEntrega::STATUS_NAO_ENTREGUE];

        return in_array($value, $allowed, true) ? (string) $value : 'todos';
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            CargaEntrega::STATUS_ENTREGUE => 'Entregue',
            CargaEntrega::STATUS_PARCIAL => 'Parcial',
            CargaEntrega::STATUS_NAO_ENTREGUE => 'Não entregue',
            default => 'Pendente',
        };
    }
}
