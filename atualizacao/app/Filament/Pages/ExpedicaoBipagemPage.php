<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ExpedicaoResource;
use App\Models\Entrega;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\Expedicao\ExpedicaoConfig;
use App\Support\Erp\ProductLocalizacao;
use App\Support\Logistica\ExpedicaoService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Js;
use Livewire\Attributes\Url;

class ExpedicaoBipagemPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?string $title = '';

    protected static ?string $slug = 'expedicao-bipagem';

    protected static bool $shouldRegisterNavigation = false;

    #[Url(as: 'ids')]
    public string $idsParam = '';

    public string $codigoBarras = '';

    public string $quantidade = '1,00';

    /** @var array<int, string> */
    public array $itensSelecionados = [];

    /** @var list<int> */
    public array $entregaIds = [];

    public ?int $ultimoItemBipadoId = null;

    public string $ordenacaoGrid = 'localizacao';

    public bool $confirmarModalAberto = false;

    /** @var list<int> */
    public array $confirmarFilaIds = [];

    public ?bool $confirmarVaiParaEntrega = null;

    public string $confirmarVolumes = '1';

    public int $confirmarTotalPedidos = 0;

    public int $confirmarPedidosExpedidos = 0;

    public function mount(): void
    {
        ErpScreen::set('Expedição — Bipagem');

        $this->entregaIds = $this->parseIds($this->idsParam);

        if ($this->entregaIds === []) {
            $this->redirect(ExpedicaoResource::getUrl('index'));

            return;
        }

        if (! ExpedicaoConfig::make()->pedirQuantidade()) {
            $this->quantidade = '1,00';
        }
    }

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('logistica.access');
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'erp-list-page', 'erp-expedicao-page', 'erp-expedicao-bipagem-page'];
    }

    /**
     * @return Collection<int, Entrega>
     */
    public function entregas(): Collection
    {
        return Entrega::query()
            ->with(['itens.product', 'venda', 'cliente'])
            ->whereIn('id', $this->entregaIds)
            ->orderBy('numero')
            ->get();
    }

    public function pedirQuantidade(): bool
    {
        return ExpedicaoConfig::make()->pedirQuantidade();
    }

    /**
     * @return list<array{tipo: string, entrega?: Entrega, item?: \App\Models\EntregaItem, label?: string}>
     */
    public function linhasGrid(): array
    {
        $linhas = [];

        foreach ($this->entregas() as $entrega) {
            foreach ($entrega->itens as $item) {
                $linhas[] = [
                    'tipo' => 'item',
                    'entrega' => $entrega,
                    'item' => $item,
                ];
            }
        }

        if ($this->ordenacaoGrid === 'pedido') {
            usort($linhas, function (array $a, array $b): int {
                return $this->compareLinhasPorPedido($a, $b);
            });

            return $this->injectPedidoSeparators($linhas);
        }

        usort($linhas, function (array $a, array $b): int {
            return $this->compareLinhasGrid($a['item'], $b['item']);
        });

        return $linhas;
    }

    /**
     * @param  list<array{tipo: string, entrega: Entrega, item: \App\Models\EntregaItem}>  $linhas
     * @return list<array{tipo: string, entrega?: Entrega, item?: \App\Models\EntregaItem, label?: string}>
     */
    private function injectPedidoSeparators(array $linhas): array
    {
        $result = [];
        $entregaAtualId = null;

        foreach ($linhas as $linha) {
            $entrega = $linha['entrega'];

            if ($entrega->id !== $entregaAtualId) {
                $result[] = [
                    'tipo' => 'pedido_sep',
                    'entrega' => $entrega,
                    'label' => $this->formatPedidoSepLabel($entrega),
                ];
                $entregaAtualId = $entrega->id;
            }

            $result[] = $linha;
        }

        return $result;
    }

    private function formatPedidoSepLabel(Entrega $entrega): string
    {
        $num = ltrim((string) ($entrega->venda?->numero ?? $entrega->numero), '0') ?: '0';
        $cliente = $entrega->cliente_nome ?? 'CONSUMIDOR';

        return "Pedido {$num} — {$cliente}";
    }

    /**
     * @param  array{entrega: Entrega, item: \App\Models\EntregaItem}  $a
     * @param  array{entrega: Entrega, item: \App\Models\EntregaItem}  $b
     */
    private function compareLinhasPorPedido(array $a, array $b): int
    {
        $entregaA = $a['entrega'];
        $entregaB = $b['entrega'];

        $numCmp = strcmp(
            (string) ($entregaA->venda?->numero ?? $entregaA->numero),
            (string) ($entregaB->venda?->numero ?? $entregaB->numero),
        );

        if ($numCmp !== 0) {
            return $numCmp;
        }

        $entregaCmp = $entregaA->id <=> $entregaB->id;

        if ($entregaCmp !== 0) {
            return $entregaCmp;
        }

        return $a['item']->id <=> $b['item']->id;
    }

    private function compareLinhasGrid(\App\Models\EntregaItem $itemA, \App\Models\EntregaItem $itemB): int
    {
        return match ($this->ordenacaoGrid) {
            'localizacao' => $this->compareLinhasPorLocalizacao($itemA, $itemB),
            'alfabetica' => $this->compareLinhasAlfabetica($itemA, $itemB),
            'codigo' => $this->compareLinhasCodigo($itemA, $itemB),
            'quantidade' => $this->compareLinhasQuantidade($itemA, $itemB),
            default => $this->compareLinhasPorLocalizacao($itemA, $itemB),
        };
    }

    private function compareLinhasAlfabetica(\App\Models\EntregaItem $itemA, \App\Models\EntregaItem $itemB): int
    {
        $descCmp = strcasecmp((string) $itemA->descricao, (string) $itemB->descricao);

        if ($descCmp !== 0) {
            return $descCmp;
        }

        $codCmp = strcmp((string) ($itemA->codigo ?? ''), (string) ($itemB->codigo ?? ''));

        if ($codCmp !== 0) {
            return $codCmp;
        }

        return (float) $itemA->quantidade_pedida <=> (float) $itemB->quantidade_pedida;
    }

    private function compareLinhasCodigo(\App\Models\EntregaItem $itemA, \App\Models\EntregaItem $itemB): int
    {
        $codCmp = strcmp((string) ($itemA->codigo ?? ''), (string) ($itemB->codigo ?? ''));

        if ($codCmp !== 0) {
            return $codCmp;
        }

        $descCmp = strcasecmp((string) $itemA->descricao, (string) $itemB->descricao);

        if ($descCmp !== 0) {
            return $descCmp;
        }

        return (float) $itemA->quantidade_pedida <=> (float) $itemB->quantidade_pedida;
    }

    private function compareLinhasQuantidade(\App\Models\EntregaItem $itemA, \App\Models\EntregaItem $itemB): int
    {
        $qtdCmp = (float) $itemA->quantidade_pedida <=> (float) $itemB->quantidade_pedida;

        if ($qtdCmp !== 0) {
            return $qtdCmp;
        }

        $descCmp = strcasecmp((string) $itemA->descricao, (string) $itemB->descricao);

        if ($descCmp !== 0) {
            return $descCmp;
        }

        return strcmp((string) ($itemA->codigo ?? ''), (string) ($itemB->codigo ?? ''));
    }

    private function compareLinhasPorLocalizacao(\App\Models\EntregaItem $itemA, \App\Models\EntregaItem $itemB): int
    {
        return ProductLocalizacao::compareForBipagemSort(
            ProductLocalizacao::resolveFromEntregaItem($itemA->localizacao, $itemA->product?->localizacao),
            ProductLocalizacao::resolveFromEntregaItem($itemB->localizacao, $itemB->product?->localizacao),
            (string) $itemA->descricao,
            (string) $itemB->descricao,
            $itemA->codigo,
            $itemB->codigo,
            (float) $itemA->quantidade_pedida,
            (float) $itemB->quantidade_pedida,
        );
    }

    public function bipar(): void
    {
        if (! ErpAccess::currentCan('logistica.update')) {
            Notification::make()->title('Sem permissão.')->danger()->send();

            return;
        }

        $codigo = trim($this->codigoBarras);

        if ($codigo === '') {
            return;
        }

        $qtd = $this->pedirQuantidade()
            ? $this->parseQuantidade($this->quantidade)
            : 1.0;

        if ($qtd <= 0) {
            Notification::make()->title('Quantidade inválida.')->warning()->send();

            return;
        }

        $item = (new ExpedicaoService())->biparPorCodigo($codigo, $qtd, $this->entregaIds);

        if ($item === null) {
            Notification::make()
                ->title('Produto não encontrado ou já expedido.')
                ->body('Código: ' . $codigo)
                ->warning()
                ->send();
        } else {
            $this->ultimoItemBipadoId = $item->id;

            Notification::make()
                ->title('Item registrado.')
                ->body($item->descricao)
                ->success()
                ->send();

            $this->dispatch('expedicao-bipagem-scroll-top');
        }

        $this->codigoBarras = '';

        if (! $this->pedirQuantidade()) {
            $this->quantidade = '1,00';
        }
    }

    public function estornarSelecionados(): void
    {
        if (! ErpAccess::currentCan('logistica.update')) {
            return;
        }

        $ids = array_values(array_filter(array_map('intval', $this->itensSelecionados)));

        if ($ids === []) {
            Notification::make()->title('Selecione itens para estornar.')->warning()->send();

            return;
        }

        (new ExpedicaoService())->estornarItens($ids);
        $this->itensSelecionados = [];

        Notification::make()->title('Itens estornados.')->success()->send();
    }

    public function marcarDesmarcarTodos(): void
    {
        $allIds = $this->entregas()
            ->flatMap(fn (Entrega $e) => $e->itens->pluck('id'))
            ->map(fn ($id): string => (string) $id)
            ->all();

        if (count($this->itensSelecionados) >= count($allIds)) {
            $this->itensSelecionados = [];
        } else {
            $this->itensSelecionados = $allIds;
        }
    }

    public function podeConfirmarExpedicao(): bool
    {
        if ($this->entregaIds === []) {
            return false;
        }

        $entregas = $this->entregas();

        if ($entregas->isEmpty()) {
            return false;
        }

        return $entregas->every(fn (Entrega $e): bool => $this->entregaPendenteConfirmacao($e) && $e->estaCompleta());
    }

    private function entregaPendenteConfirmacao(Entrega $entrega): bool
    {
        return in_array($entrega->status, [Entrega::STATUS_PENDENTE, Entrega::STATUS_EM_EXPEDICAO], true);
    }

    public function confirmarExpedicao(): void
    {
        if (! ErpAccess::currentCan('logistica.update')) {
            Notification::make()->title('Sem permissão.')->danger()->send();

            return;
        }

        if (! $this->podeConfirmarExpedicao()) {
            Notification::make()
                ->title('Expedição incompleta.')
                ->body('Bipe todos os produtos antes de confirmar.')
                ->warning()
                ->send();

            return;
        }

        $fila = $this->entregas()
            ->filter(fn (Entrega $e): bool => $this->entregaPendenteConfirmacao($e) && $e->estaCompleta())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($fila === []) {
            Notification::make()->title('Nenhum pedido completo para confirmar.')->warning()->send();

            return;
        }

        $this->confirmarFilaIds = $fila;
        $this->confirmarTotalPedidos = count($fila);
        $this->confirmarPedidosExpedidos = 0;
        $this->resetConfirmarPedidoForm();
        $this->confirmarModalAberto = true;
    }

    public function fecharConfirmarModal(): void
    {
        $this->confirmarModalAberto = false;
        $this->confirmarFilaIds = [];
        $this->confirmarTotalPedidos = 0;
        $this->confirmarPedidosExpedidos = 0;
        $this->resetConfirmarPedidoForm();
    }

    public function escolherConfirmarTipoSaida(bool $entrega): void
    {
        $this->confirmarVaiParaEntrega = $entrega;

        if ($entrega) {
            $this->confirmarVolumes = '1';
        }
    }

    public function modulePendingTransportadora(): void
    {
        Notification::make()
            ->title('Transportadora')
            ->body('Em implementação.')
            ->info()
            ->send();
    }

    public function imprimirRomaneioRetiradaAtual(): void
    {
        if (! ErpAccess::currentCan('logistica.print')) {
            Notification::make()->title('Sem permissão para imprimir.')->danger()->send();

            return;
        }

        $entrega = $this->entregaConfirmacaoAtual();

        if ($entrega === null) {
            return;
        }

        $url = route('erp.reports.expedicao-retirada', [
            'entrega' => $entrega->id,
            'auto' => 1,
        ]);

        (new ExpedicaoService())->registrarRomaneioRetiradaEmitido($entrega);

        $this->js('window.open(' . Js::from($url) . ", '_blank')");
    }

    public function confirmarPedidoAtual(): void
    {
        if (! ErpAccess::currentCan('logistica.update')) {
            Notification::make()->title('Sem permissão.')->danger()->send();

            return;
        }

        if (! $this->confirmarModalAberto || $this->confirmarFilaIds === []) {
            return;
        }

        $entrega = $this->entregaConfirmacaoAtual();

        if ($entrega === null) {
            $this->fecharConfirmarModal();

            return;
        }

        if ($this->confirmarVaiParaEntrega === null) {
            Notification::make()
                ->title('Informe o tipo de saída.')
                ->body('Selecione se o pedido vai para entrega ou se o cliente retirou.')
                ->warning()
                ->send();

            return;
        }

        $service = new ExpedicaoService();
        $dados = ['tipo_saida' => $this->confirmarVaiParaEntrega ? Entrega::TIPO_SAIDA_ENTREGA : Entrega::TIPO_SAIDA_RETIRADA];

        if ($this->confirmarVaiParaEntrega) {
            $volumes = (int) preg_replace('/\D/', '', $this->confirmarVolumes);

            if ($volumes < 1) {
                Notification::make()
                    ->title('Informe a quantidade de volumes.')
                    ->body('Para entrega, informe pelo menos 1 volume.')
                    ->warning()
                    ->send();

                return;
            }

            $pesoInfo = $service->calcularPesoExpedicao($entrega);

            $dados['qtd_volumes'] = $volumes;
            $dados['peso_calculado_kg'] = $pesoInfo['peso_kg'];
            $dados['transportadora_id'] = null;
        }

        if (! $service->confirmarExpedicaoPedido($entrega, $dados, Auth::user())) {
            Notification::make()
                ->title('Não foi possível confirmar o pedido.')
                ->body('Verifique se a bipagem está completa.')
                ->warning()
                ->send();

            return;
        }

        $this->confirmarPedidosExpedidos++;
        array_shift($this->confirmarFilaIds);

        if ($this->confirmarFilaIds === []) {
            $total = $this->confirmarPedidosExpedidos;
            $this->fecharConfirmarModal();

            Notification::make()
                ->title('Expedição confirmada.')
                ->body($total . ' pedido(s) expedido(s).')
                ->success()
                ->send();

            $this->redirect(ExpedicaoResource::getUrl('index'));

            return;
        }

        $this->resetConfirmarPedidoForm();

        Notification::make()
            ->title('Pedido expedido.')
            ->body('Confirme o próximo pedido.')
            ->success()
            ->send();
    }

    public function entregaConfirmacaoAtual(): ?Entrega
    {
        if ($this->confirmarFilaIds === []) {
            return null;
        }

        return Entrega::query()
            ->with(['itens.product', 'venda', 'cliente'])
            ->find($this->confirmarFilaIds[0]);
    }

    public function confirmacaoProgressoLabel(): string
    {
        if ($this->confirmarTotalPedidos === 0) {
            return '';
        }

        $atual = min($this->confirmarPedidosExpedidos + 1, $this->confirmarTotalPedidos);

        return "Pedido {$atual} de {$this->confirmarTotalPedidos}";
    }

    /**
     * @return array{peso_kg: float, peso_formatado: string, itens_sem_peso: int}
     */
    public function pesoConfirmacaoAtual(): array
    {
        $entrega = $this->entregaConfirmacaoAtual();

        if ($entrega === null) {
            return [
                'peso_kg' => 0.0,
                'peso_formatado' => '0,000',
                'itens_sem_peso' => 0,
            ];
        }

        $info = (new ExpedicaoService())->calcularPesoExpedicao($entrega);

        return [
            'peso_kg' => $info['peso_kg'],
            'peso_formatado' => number_format($info['peso_kg'], 3, ',', '.'),
            'itens_sem_peso' => $info['itens_sem_peso'],
        ];
    }

    public function labelEntregaConfirmacaoAtual(): string
    {
        $entrega = $this->entregaConfirmacaoAtual();

        if ($entrega === null) {
            return '';
        }

        return $this->formatPedidoSepLabel($entrega);
    }

    private function resetConfirmarPedidoForm(): void
    {
        $this->confirmarVaiParaEntrega = null;
        $this->confirmarVolumes = '1';
    }

    public function voltarControle(): void
    {
        $this->redirect(ExpedicaoResource::getUrl('index'));
    }

    public function imprimirSeparacao(): void
    {
        if (! ErpAccess::currentCan('logistica.print')) {
            Notification::make()->title('Sem permissão para imprimir.')->danger()->send();

            return;
        }

        if ($this->entregaIds === []) {
            Notification::make()->title('Nenhum pedido selecionado.')->warning()->send();

            return;
        }

        $url = route('erp.reports.expedicao-separacao', [
            'ids' => implode(',', $this->entregaIds),
            'ord' => $this->ordenacaoGrid,
        ]);

        $this->redirect($url, navigate: false);
    }

    public function closeScreen(): void
    {
        ErpScreen::set('Principal');
        $this->redirect(filament()->getUrl());
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.expedicao.bipagem-screen'),
                View::make('filament.components.erp.expedicao.bipagem-footer'),
                View::make('filament.components.erp.expedicao.confirmar-expedicao-modal'),
            ]);
    }

    /**
     * @return list<int>
     */
    private function parseIds(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('intval', explode(',', $raw))));
    }

    private function parseQuantidade(string $value): float
    {
        $normalized = str_replace(['.', ' '], '', trim($value));
        $normalized = str_replace(',', '.', $normalized);

        return (float) $normalized;
    }
}
