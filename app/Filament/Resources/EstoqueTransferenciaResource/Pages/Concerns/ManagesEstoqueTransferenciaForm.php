<?php

namespace App\Filament\Resources\EstoqueTransferenciaResource\Pages\Concerns;

use App\Models\Estoque;
use App\Models\EstoqueMovimentacao;
use App\Models\EstoqueTransferencia;
use App\Models\Product;
use App\Support\Erp\BrDecimal;
use App\Support\Erp\ErpContext;
use App\Support\Erp\EstoqueTransferenciaException;
use App\Support\Erp\EstoqueTransferenciaSaldoCompat;
use App\Support\Erp\EstoqueTransferenciaService;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

trait ManagesEstoqueTransferenciaForm
{
    public bool $showTransferForm = false;

    public bool $transferFormSomenteLeitura = false;

    public ?int $transferFormId = null;

    /** @var array<string, mixed> */
    public array $transferForm = [];

    /** @var list<array{product_id:int,codigo:string,descricao:string,disponivel:string,qtd:string}> */
    public array $transferItens = [];

    /** @var list<array{id:int,codigo:string,descricao:string,disponivel:string}> */
    public array $produtoSugestoes = [];

    public int $selectedProdutoSugestaoIndex = 0;

    /** @var list<array{data:string,tipo:string,qtd:string,deposito:string,saldo_antes:string,saldo_depois:string,obs:string}> */
    public array $transferMovimentacoes = [];

    public function createTransferencia(): void
    {
        if ($this->showTransferForm) {
            return;
        }

        if (! $this->erpCan('ajuste_estoque.access')
            && ! $this->erpCan('ajuste_estoque.create')
            && ! $this->erpCan('transferencia_estoque.access')
            && ! $this->erpCan('transferencia_estoque.create')) {
            Notification::make()->title('Sem permissão para transferir estoque.')->warning()->send();

            return;
        }

        $depositos = $this->depositosEmpresaAtiva();
        if (count($depositos) < 2) {
            Notification::make()
                ->title('Cadastre ao menos dois depósitos ativos nesta empresa.')
                ->body('A transferência interna move estoque entre depósitos do mesmo estabelecimento.')
                ->warning()
                ->send();

            return;
        }

        $this->resetTransferForm();
        $this->transferForm['estoque_origem_id'] = (string) $depositos[0]['id'];
        $this->transferForm['estoque_destino_id'] = (string) $depositos[1]['id'];
        $this->showTransferForm = true;
        $this->transferFormSomenteLeitura = false;
        $this->dispatch('erp-transf-focus-codigo');
    }

    public function viewTransferencia(): void
    {
        if ($this->showTransferForm) {
            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('edit');
        if (! $recordId) {
            return;
        }

        $this->abrirTransferencia((int) $recordId, somenteLeitura: true);
    }

    public function closeTransferForm(): void
    {
        $this->showTransferForm = false;
        $this->transferFormId = null;
        $this->transferFormSomenteLeitura = false;
        $this->fecharSugestoesProduto();
        $this->resetTransferForm();
        $this->transferMovimentacoes = [];
    }

    public function handleTransferEscape(): void
    {
        if ($this->produtoSugestoes !== []) {
            $this->fecharSugestoesProduto();

            return;
        }

        $this->closeTransferForm();
    }

    public function updatedTransferFormEstoqueOrigemId(): void
    {
        if ($this->transferFormSomenteLeitura) {
            return;
        }

        // Trocar origem invalida disponíveis — limpa itens e re-renderiza o formulário.
        $this->transferItens = [];
        $this->limparLinhaInclusao();
    }

    public function updatedTransferFormDescricaoBusca(): void
    {
        if ($this->transferFormSomenteLeitura) {
            return;
        }

        $raw = (string) ($this->transferForm['descricao_busca'] ?? '');
        $term = mb_strtoupper(trim($raw), 'UTF-8');
        if ($term !== $raw) {
            $this->transferForm['descricao_busca'] = $term;
        }

        $this->carregarSugestoesProduto($term);
    }

    public function resolveProdutoCodigoInterno(): void
    {
        if ($this->transferFormSomenteLeitura) {
            return;
        }

        $codigo = mb_strtoupper(trim((string) ($this->transferForm['codigo_interno'] ?? '')), 'UTF-8');
        if ($codigo === '') {
            return;
        }

        $product = Product::query()
            ->where('ativo', true)
            ->where(function ($q) use ($codigo): void {
                $q->where('codigo', $codigo)->orWhereRaw('CAST(codigo AS CHAR) = ?', [$codigo]);
            })
            ->first();

        $this->aplicarProdutoNaInclusao($product, 'Código interno não encontrado.');
    }

    public function confirmarProdutoSugestao(): void
    {
        if ($this->transferFormSomenteLeitura || $this->produtoSugestoes === []) {
            return;
        }

        $index = $this->selectedProdutoSugestaoIndex;
        if (! isset($this->produtoSugestoes[$index])) {
            $index = 0;
        }

        $this->selecionarProdutoSugestao((int) $this->produtoSugestoes[$index]['id']);
    }

    public function selecionarProdutoSugestao(int $productId): void
    {
        $product = Product::query()->whereKey($productId)->where('ativo', true)->first();
        $this->aplicarProdutoNaInclusao($product, 'Produto não encontrado.');
        $this->fecharSugestoesProduto();
    }

    public function fecharSugestoesProduto(): void
    {
        $this->produtoSugestoes = [];
        $this->selectedProdutoSugestaoIndex = 0;
    }

    public function adicionarItemTransferencia(): void
    {
        if ($this->transferFormSomenteLeitura) {
            return;
        }

        $productId = (int) ($this->transferForm['product_id'] ?? 0);
        $qtd = BrDecimal::parse($this->transferForm['qtd'] ?? 0, 3);
        $origemId = (int) ($this->transferForm['estoque_origem_id'] ?? 0);

        if ($productId <= 0) {
            Notification::make()->title('Selecione um produto.')->warning()->send();

            return;
        }

        if ($qtd <= 0) {
            Notification::make()->title('Informe a quantidade.')->warning()->send();

            return;
        }

        try {
            $compat = new EstoqueTransferenciaSaldoCompat();
            $disponivel = $compat->assertProdutoTransferivel($productId, $origemId, $qtd);
            $product = Product::query()->whereKey($productId)->firstOrFail(['id', 'codigo', 'descricao']);

            foreach ($this->transferItens as $idx => $item) {
                if ((int) $item['product_id'] === $productId) {
                    $novaQtd = round(BrDecimal::parse($item['qtd'], 3) + $qtd, 3);
                    $compat->assertProdutoTransferivel($productId, $origemId, $novaQtd);
                    $this->transferItens[$idx]['qtd'] = number_format($novaQtd, 3, ',', '.');
                    $this->transferItens[$idx]['disponivel'] = number_format($disponivel, 3, ',', '.');
                    $this->limparLinhaInclusao();
                    $this->dispatch('erp-transf-focus-codigo');
                    $this->skipRender();

                    return;
                }
            }

            $this->transferItens[] = [
                'product_id' => $productId,
                'codigo' => (string) $product->codigo,
                'descricao' => (string) $product->descricao,
                'disponivel' => number_format($disponivel, 3, ',', '.'),
                'qtd' => number_format($qtd, 3, ',', '.'),
            ];
            $this->limparLinhaInclusao();
            $this->dispatch('erp-transf-focus-codigo');
            $this->skipRender();
        } catch (EstoqueTransferenciaException $e) {
            Notification::make()->title('Item bloqueado.')->body($e->getMessage())->warning()->send();
        }
    }

    public function removerItemTransferencia(int $index): void
    {
        if ($this->transferFormSomenteLeitura) {
            return;
        }

        if (! isset($this->transferItens[$index])) {
            return;
        }

        unset($this->transferItens[$index]);
        $this->transferItens = array_values($this->transferItens);
        $this->skipRender();
    }

    public function confirmarTransferencia(): void
    {
        if ($this->transferFormSomenteLeitura) {
            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $origemId = (int) ($this->transferForm['estoque_origem_id'] ?? 0);
        $destinoId = (int) ($this->transferForm['estoque_destino_id'] ?? 0);

        $itens = [];
        foreach ($this->transferItens as $item) {
            $itens[] = [
                'product_id' => (int) $item['product_id'],
                'qtd' => BrDecimal::parse($item['qtd'] ?? 0, 3),
            ];
        }

        try {
            $service = app(EstoqueTransferenciaService::class);
            $transf = $service->confirmar([
                'empresa_id' => $empresaId,
                'estoque_origem_id' => $origemId,
                'estoque_destino_id' => $destinoId,
                'data' => (string) ($this->transferForm['data'] ?? now('America/Sao_Paulo')->toDateString()),
                'observacoes' => (string) ($this->transferForm['observacoes'] ?? ''),
                'idempotencia_token' => (string) ($this->transferForm['idempotencia_token'] ?? Str::uuid()),
                'transferencia_id' => $this->transferFormId,
                'itens' => $itens,
            ]);

            $this->closeTransferForm();
            $this->clearListSelection();
            $this->resetTable();

            Notification::make()
                ->title('Transferência confirmada.')
                ->body('Nº '.$transf->numero.' — estoque movido entre depósitos.')
                ->success()
                ->send();
        } catch (EstoqueTransferenciaException $e) {
            Notification::make()->title('Não foi possível confirmar.')->body($e->getMessage())->warning()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Erro ao confirmar.')->body($e->getMessage())->danger()->send();
        }
    }

    /**
     * @return list<array{id:int,label:string}>
     */
    public function depositosEmpresaAtiva(): array
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($empresaId <= 0) {
            return [];
        }

        return Estoque::query()
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->orderByRaw('CAST(codigo AS UNSIGNED)')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nome'])
            ->map(fn (Estoque $e): array => [
                'id' => (int) $e->id,
                'label' => trim($e->codigo.' — '.$e->nome),
            ])
            ->all();
    }

    private function abrirTransferencia(int $id, bool $somenteLeitura): void
    {
        $transf = EstoqueTransferencia::query()
            ->with(['itens', 'estoqueOrigem:id,codigo,nome', 'estoqueDestino:id,codigo,nome'])
            ->whereKey($id)
            ->first();

        if ($transf === null) {
            Notification::make()->title('Transferência não encontrada.')->warning()->send();

            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        if ((int) $transf->empresa_origem_id !== $empresaId) {
            Notification::make()->title('Transferência de outra empresa.')->warning()->send();

            return;
        }

        $this->resetTransferForm();
        $this->transferFormId = (int) $transf->id;
        $this->transferFormSomenteLeitura = $somenteLeitura || $transf->isConfirmada();
        $this->transferForm = [
            'numero' => (string) $transf->numero,
            'data' => $transf->data?->format('Y-m-d') ?? '',
            'estoque_origem_id' => (string) $transf->estoque_origem_id,
            'estoque_destino_id' => (string) $transf->estoque_destino_id,
            'observacoes' => (string) ($transf->observacoes ?? ''),
            'idempotencia_token' => (string) ($transf->idempotencia_token ?? ''),
            'situacao' => $transf->situacaoLabel(),
            'product_id' => 0,
            'codigo_interno' => '',
            'descricao_busca' => '',
            'disponivel' => '',
            'qtd' => '',
        ];

        $this->transferItens = $transf->itens->map(fn ($item): array => [
            'product_id' => (int) $item->product_id,
            'codigo' => (string) ($item->produto_codigo ?? ''),
            'descricao' => (string) ($item->produto_descricao ?? ''),
            'disponivel' => number_format((float) ($item->qtd_disponivel_origem ?? 0), 3, ',', '.'),
            'qtd' => number_format((float) $item->qtd, 3, ',', '.'),
        ])->all();

        $this->carregarMovimentacoes((int) $transf->id);
        $this->showTransferForm = true;
    }

    private function carregarMovimentacoes(int $transferenciaId): void
    {
        $this->transferMovimentacoes = EstoqueMovimentacao::query()
            ->with('estoque:id,codigo,nome')
            ->where('origem_tipo', 'estoque_transferencia')
            ->where('origem_id', $transferenciaId)
            ->orderBy('id')
            ->limit(200)
            ->get(['id', 'data_movimentacao', 'tipo', 'quantidade', 'saldo_anterior', 'saldo_atual', 'estoque_id', 'observacao'])
            ->map(function (EstoqueMovimentacao $mov): array {
                $dep = $mov->estoque;
                $depLabel = $dep
                    ? trim((string) $dep->codigo.' — '.(string) $dep->nome)
                    : '—';

                return [
                    'data' => $mov->data_movimentacao?->format('d/m/Y H:i') ?? '—',
                    'tipo' => EstoqueMovimentacao::tipoLabel((string) $mov->tipo),
                    'qtd' => number_format((float) $mov->quantidade, 3, ',', '.'),
                    'deposito' => $depLabel,
                    'saldo_antes' => number_format((float) $mov->saldo_anterior, 3, ',', '.'),
                    'saldo_depois' => number_format((float) $mov->saldo_atual, 3, ',', '.'),
                    'obs' => (string) ($mov->observacao ?? ''),
                ];
            })
            ->all();
    }

    private function carregarSugestoesProduto(string $term): void
    {
        if (mb_strlen($term) < 2) {
            $this->fecharSugestoesProduto();

            return;
        }

        $origemId = (int) ($this->transferForm['estoque_origem_id'] ?? 0);
        $compat = new EstoqueTransferenciaSaldoCompat();
        $like = '%'.$term.'%';
        $prefix = $term.'%';

        $this->produtoSugestoes = Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($term, $like): void {
                $query->where('descricao', 'like', $like)
                    ->orWhere('codigo', 'like', $like)
                    ->orWhere('referencia', 'like', $like);
                if (ctype_digit($term)) {
                    $query->orWhere('codigo', $term)
                        ->orWhere('codigo_barras', $term);
                }
            })
            ->orderByRaw(
                'CASE WHEN codigo = ? THEN 0 WHEN descricao LIKE ? THEN 1 ELSE 2 END',
                [$term, $prefix]
            )
            ->orderBy('descricao')
            ->limit(12)
            ->get(['id', 'codigo', 'descricao'])
            ->map(function (Product $product) use ($compat, $origemId): array {
                $disp = $origemId > 0
                    ? $compat->disponivelParaTransferir((int) $product->id, $origemId)
                    : 0.0;

                return [
                    'id' => (int) $product->id,
                    'codigo' => (string) $product->codigo,
                    'descricao' => (string) $product->descricao,
                    'disponivel' => number_format($disp, 3, ',', '.'),
                ];
            })
            ->all();

        $this->selectedProdutoSugestaoIndex = 0;
    }

    private function aplicarProdutoNaInclusao(?Product $product, string $msg): void
    {
        if ($product === null) {
            Notification::make()->title($msg)->warning()->send();

            return;
        }

        $origemId = (int) ($this->transferForm['estoque_origem_id'] ?? 0);
        $compat = new EstoqueTransferenciaSaldoCompat();
        $disponivel = $origemId > 0
            ? $compat->disponivelParaTransferir((int) $product->id, $origemId)
            : 0.0;

        if ($compat->depositosSuperestimamGlobal((int) $product->id)) {
            Notification::make()
                ->title('Produto com saldo inconsistente.')
                ->body('Depósitos superestimam o estoque global. Transferência deste item está bloqueada.')
                ->warning()
                ->send();

            return;
        }

        $this->transferForm['product_id'] = (int) $product->id;
        $this->transferForm['codigo_interno'] = (string) $product->codigo;
        $this->transferForm['descricao_busca'] = (string) $product->descricao;
        $this->transferForm['disponivel'] = number_format($disponivel, 3, ',', '.');
        $this->dispatch('erp-transf-focus-qtd');
    }

    private function limparLinhaInclusao(): void
    {
        $this->transferForm['product_id'] = 0;
        $this->transferForm['codigo_interno'] = '';
        $this->transferForm['descricao_busca'] = '';
        $this->transferForm['disponivel'] = '';
        $this->transferForm['qtd'] = '';
        $this->fecharSugestoesProduto();
    }

    private function resetTransferForm(): void
    {
        $this->transferFormId = null;
        $this->transferItens = [];
        $this->transferMovimentacoes = [];
        $this->transferForm = [
            'numero' => '(novo)',
            'data' => now('America/Sao_Paulo')->format('Y-m-d'),
            'estoque_origem_id' => '',
            'estoque_destino_id' => '',
            'observacoes' => '',
            'idempotencia_token' => (string) Str::uuid(),
            'situacao' => 'Nova',
            'product_id' => 0,
            'codigo_interno' => '',
            'descricao_busca' => '',
            'disponivel' => '',
            'qtd' => '',
        ];
        $this->fecharSugestoesProduto();
    }
}
