<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Grupo;
use App\Models\Marca;
use App\Models\Person;
use App\Support\Erp\ZeraEstoqueService;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;

trait ManagesZeraEstoque
{
    public bool $pesquisado = false;

    public string $pesquisa = '';

    public string $grupoFilter = 'todos';

    public string $marcaFilter = 'todos';

    public string $fornecedorFilter = 'todos';

    public string $saldoFilter = 'todos';

    public ?int $depositoFilter = null;

    public int $page = 1;

    /** @var list<int> */
    public array $selecionados = [];

    public bool $selecaoTodosFiltrados = false;

    public int $selecaoTodosFiltradosCount = 0;

    public string $motivo = ZeraEstoqueService::MOTIVO_PADRAO;

    public bool $confirmOpen = false;

    /** @var array<string, mixed> */
    public array $confirmResumo = [];

    public ?string $executionToken = null;

    public bool $executando = false;

    /** @var array{rows: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int, estoque_id: int} */
    public array $resultado = [
        'rows' => [],
        'total' => 0,
        'page' => 1,
        'per_page' => ZeraEstoqueService::PAGE_SIZE,
        'last_page' => 1,
        'estoque_id' => 0,
    ];

    protected function zeraService(): ZeraEstoqueService
    {
        return app(ZeraEstoqueService::class);
    }

    protected function bootZeraEstoqueDefaults(): void
    {
        try {
            $empresaId = $this->zeraService()->empresaIdAtual();
            $this->depositoFilter = $this->zeraService()->resolverEstoqueId($empresaId, $this->depositoFilter);
            $this->motivo = ZeraEstoqueService::MOTIVO_PADRAO;
        } catch (\Throwable) {
            $this->depositoFilter = null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function filtrosAtuais(): array
    {
        return [
            'pesquisa' => $this->pesquisa,
            'grupo' => $this->grupoFilter,
            'marca' => $this->marcaFilter,
            'fornecedor_id' => $this->fornecedorFilter,
            'saldo' => $this->saldoFilter,
            'estoque_id' => $this->depositoFilter !== null ? (int) $this->depositoFilter : null,
            'apenas_ativos' => true,
        ];
    }

    public function updatedDepositoFilter(): void
    {
        $this->depositoFilter = $this->depositoFilter !== null ? (int) $this->depositoFilter : null;
        $this->limparSelecao();

        if ($this->pesquisado) {
            $this->page = 1;
            $this->carregarPagina();
        }
    }

    #[Computed]
    public function gruposOptions(): array
    {
        return Grupo::query()->where('ativo', true)->orderBy('nome')->pluck('nome', 'nome')->all();
    }

    #[Computed]
    public function marcasOptions(): array
    {
        return Marca::query()->where('ativo', true)->orderBy('nome')->pluck('nome', 'nome')->all();
    }

    #[Computed]
    public function fornecedoresOptions(): array
    {
        return Person::query()
            ->where('is_fornecedor', true)
            ->where('ativo', true)
            ->orderBy('nome_razao')
            ->limit(800)
            ->pluck('nome_razao', 'id')
            ->all();
    }

    #[Computed]
    public function depositosOptions(): array
    {
        try {
            return $this->zeraService()->depositosOptions($this->zeraService()->empresaIdAtual());
        } catch (\Throwable) {
            return [];
        }
    }

    public function quantidadeSelecionada(): int
    {
        if ($this->selecaoTodosFiltrados) {
            return $this->selecaoTodosFiltradosCount;
        }

        return count($this->selecionados);
    }

    public function pesquisar(): void
    {
        $this->page = 1;
        $this->pesquisado = true;
        $this->selecaoTodosFiltrados = false;
        $this->selecaoTodosFiltradosCount = 0;
        $this->selecionados = [];
        $this->carregarPagina();
    }

    public function limparFiltros(): void
    {
        $this->pesquisa = '';
        $this->grupoFilter = 'todos';
        $this->marcaFilter = 'todos';
        $this->fornecedorFilter = 'todos';
        $this->saldoFilter = 'todos';
        $this->page = 1;
        $this->pesquisado = false;
        $this->selecionados = [];
        $this->selecaoTodosFiltrados = false;
        $this->selecaoTodosFiltradosCount = 0;
        $this->resultado = [
            'rows' => [],
            'total' => 0,
            'page' => 1,
            'per_page' => ZeraEstoqueService::PAGE_SIZE,
            'last_page' => 1,
            'estoque_id' => (int) ($this->depositoFilter ?? 0),
        ];
        $this->bootZeraEstoqueDefaults();
    }

    public function irParaPagina(int $page): void
    {
        if (! $this->pesquisado) {
            return;
        }

        $this->page = max(1, $page);
        $this->carregarPagina();
    }

    public function carregarPagina(): void
    {
        try {
            $empresaId = $this->zeraService()->empresaIdAtual();
            $this->resultado = $this->zeraService()->listar(
                $empresaId,
                $this->filtrosAtuais(),
                $this->page,
                ZeraEstoqueService::PAGE_SIZE,
            );
            $this->depositoFilter = (int) $this->resultado['estoque_id'];
            $this->page = (int) $this->resultado['page'];
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Não foi possível consultar o estoque.')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function toggleSelecionado(int $productId): void
    {
        if ($this->selecaoTodosFiltrados) {
            $this->materializarSelecaoFiltrada();
        }

        if (in_array($productId, $this->selecionados, true)) {
            $this->selecionados = array_values(array_filter(
                $this->selecionados,
                static fn (int $id): bool => $id !== $productId,
            ));

            return;
        }

        if (count($this->selecionados) >= ZeraEstoqueService::SELECTION_LIMIT) {
            Notification::make()
                ->title('Limite de '.ZeraEstoqueService::SELECTION_LIMIT.' produtos.')
                ->warning()
                ->send();

            return;
        }

        $this->selecionados[] = $productId;
    }

    public function selecionarTodosFiltrados(): void
    {
        if (! $this->pesquisado) {
            Notification::make()->title('Faça a consulta (F5) antes de selecionar.')->warning()->send();

            return;
        }

        try {
            $empresaId = $this->zeraService()->empresaIdAtual();
            $total = (int) ($this->resultado['total'] ?? 0);

            if ($total <= 0) {
                Notification::make()->title('Nenhum produto no filtro atual.')->warning()->send();

                return;
            }

            if ($total > ZeraEstoqueService::SELECTION_LIMIT) {
                Notification::make()
                    ->title('Filtro retorna mais de '.ZeraEstoqueService::SELECTION_LIMIT.' produtos.')
                    ->body('Refine os filtros antes de selecionar todos.')
                    ->warning()
                    ->send();

                return;
            }

            $ids = $this->zeraService()->idsFiltrados($empresaId, $this->filtrosAtuais(), $total);
            $this->selecaoTodosFiltrados = true;
            $this->selecaoTodosFiltradosCount = count($ids);
            $this->selecionados = $ids;
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Falha ao selecionar filtrados.')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function limparSelecao(): void
    {
        $this->selecionados = [];
        $this->selecaoTodosFiltrados = false;
        $this->selecaoTodosFiltradosCount = 0;
    }

    public function abrirConfirmacao(): void
    {
        if ($this->executando) {
            return;
        }

        $motivo = trim($this->motivo);
        if ($motivo === '') {
            Notification::make()->title('Informe o motivo da zeragem.')->warning()->send();

            return;
        }

        $ids = $this->idsParaExecucao();
        if ($ids === []) {
            Notification::make()->title('Selecione ao menos um produto.')->warning()->send();

            return;
        }

        try {
            $empresaId = $this->zeraService()->empresaIdAtual();
            $estoqueId = $this->zeraService()->resolverEstoqueId($empresaId, $this->depositoFilter);
            $this->confirmResumo = $this->zeraService()->resumo($empresaId, $estoqueId, $ids);
            $this->confirmResumo['motivo'] = $motivo;
            $this->confirmResumo['ids_count'] = count($ids);

            if ((int) ($this->confirmResumo['produtos'] ?? 0) <= 0) {
                Notification::make()->title('Nenhum produto com saldo para zerar.')->warning()->send();

                return;
            }

            $this->executionToken = (string) Str::uuid();
            $this->confirmOpen = true;
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Não foi possível montar o resumo.')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function cancelarConfirmacao(): void
    {
        $this->confirmOpen = false;
        $this->confirmResumo = [];
        $this->executionToken = null;
    }

    public function confirmarZeragem(): void
    {
        if ($this->executando) {
            return;
        }

        $this->executando = true;

        try {
            $motivo = trim($this->motivo);
            $ids = $this->idsParaExecucao();
            $token = $this->executionToken;

            if ($motivo === '' || $ids === [] || ! $token) {
                throw new \DomainException('Confirmação inválida. Reabra o resumo.');
            }

            $empresaId = $this->zeraService()->empresaIdAtual();
            $estoqueId = $this->zeraService()->resolverEstoqueId($empresaId, $this->depositoFilter);

            $result = $this->zeraService()->executar(
                $empresaId,
                $estoqueId,
                $ids,
                $motivo,
                $token,
            );

            $this->confirmOpen = false;
            $this->confirmResumo = [];
            $this->executionToken = null;
            $this->limparSelecao();

            $falhas = count($result['falhas']);
            $body = $result['ajustados'].' produto(s) zerado(s)';
            if ($result['ignorados'] > 0) {
                $body .= ', '.$result['ignorados'].' já zerado(s)';
            }
            if ($falhas > 0) {
                $body .= ', '.$falhas.' com falha';
            }
            $body .= '.';

            $notification = Notification::make()
                ->title('Zeragem concluída')
                ->body($body);

            if ($falhas > 0) {
                $notification->warning();
            } else {
                $notification->success();
            }

            $notification->send();

            if ($this->pesquisado) {
                $this->carregarPagina();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Zeragem não executada.')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->executando = false;
        }
    }

    /**
     * @return list<int>
     */
    protected function idsParaExecucao(): array
    {
        if ($this->selecaoTodosFiltrados && $this->selecionados === []) {
            try {
                $empresaId = $this->zeraService()->empresaIdAtual();

                return $this->zeraService()->idsFiltrados(
                    $empresaId,
                    $this->filtrosAtuais(),
                    $this->selecaoTodosFiltradosCount ?: ZeraEstoqueService::SELECTION_LIMIT,
                );
            } catch (\Throwable) {
                return [];
            }
        }

        return array_values(array_unique(array_map('intval', $this->selecionados)));
    }

    protected function materializarSelecaoFiltrada(): void
    {
        if (! $this->selecaoTodosFiltrados) {
            return;
        }

        if ($this->selecionados === []) {
            try {
                $empresaId = $this->zeraService()->empresaIdAtual();
                $this->selecionados = $this->zeraService()->idsFiltrados(
                    $empresaId,
                    $this->filtrosAtuais(),
                    $this->selecaoTodosFiltradosCount ?: ZeraEstoqueService::SELECTION_LIMIT,
                );
            } catch (\Throwable) {
                $this->selecionados = [];
            }
        }

        $this->selecaoTodosFiltrados = false;
        $this->selecaoTodosFiltradosCount = count($this->selecionados);
    }
}
