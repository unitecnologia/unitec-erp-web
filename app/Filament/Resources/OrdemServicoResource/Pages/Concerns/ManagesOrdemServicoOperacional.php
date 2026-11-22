<?php

namespace App\Filament\Resources\OrdemServicoResource\Pages\Concerns;

use App\Models\CaixaConta;
use App\Models\Person;
use App\Models\PriceTable;
use App\Models\Product;
use App\Models\Vendedor;
use App\Support\Erp\EmpresaParametros;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ProductEmpresaPrecoService;
use App\Support\Erp\ProductTabelaPrecoService;
use App\Support\Erp\Rh\OperadorDoUsuario;
use App\Support\Erp\VendedorEstoque;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Faixa operacional da OS: Técnico, Caixa/Estoque (leitura), Tabela de preços.
 * Caixa/Estoque apenas informativos — faturamento continua igual.
 */
trait ManagesOrdemServicoOperacional
{
    public ?int $tabelaPrecoId = null;

    public string $tabelaPrecoLabel = '';

    /** @var list<array{id: int, label: string}> */
    public array $tabelaPrecoOpcoes = [];

    public string $osCaixaLabel = '';

    public ?int $osEstoqueId = null;

    public string $osEstoqueLabel = '';

    protected function bootOsOperacional(): void
    {
        $this->carregarTabelaPrecoOpcoesOs();
        // Mesma ordem da Tela de Venda: padrão do técnico → caixa/estoque → tabela.
        $this->carregarTecnicoPadraoOs();
        $this->syncOsCaixaLabel();
        $this->carregarTabelaPrecoPadraoOs();
    }

    protected function syncOsCaixaEstoqueLabels(): void
    {
        $this->syncOsCaixaLabel();
        $this->syncOsEstoqueLabelDoAtendente();
    }

    protected function syncOsCaixaLabel(): void
    {
        // Caixa: destino atual do faturamento (somente leitura nesta etapa).
        $caixa = CaixaConta::query()
            ->whereRaw('UPPER(nome) = ?', [CaixaConta::NOME_CAIXA_GERAL])
            ->value('nome');

        $this->osCaixaLabel = $caixa
            ? mb_strtoupper((string) $caixa, 'UTF-8')
            : CaixaConta::NOME_CAIXA_GERAL;
    }

    /**
     * Espelho de {@see \App\Filament\Pages\ForcaVendasTelaVendaPage::carregarVendedorPadrao}:
     * Pedir Vendedor ligado → exige escolha; desligado → OperadorDoUsuario → sessão PDV.
     * Validade operacional da OS: ativo + setor_servicos + RH + empresa (combo Técnico).
     */
    protected function carregarTecnicoPadraoOs(): void
    {
        $user = Auth::user();

        if (! $user) {
            $this->limparTecnicoOperacionalOs();

            return;
        }

        if ($this->deveExigirTecnicoAntesDoItemOs()) {
            $this->limparTecnicoOperacionalOs();

            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $vendedor = OperadorDoUsuario::vendedorAtivo($user, $empresaId > 0 ? $empresaId : null);

        if ($vendedor) {
            $vendedor->loadMissing(['rhFuncionario', 'estoqueCadastro', 'empresas']);
        }

        if ($vendedor && $this->tecnicoOperacionalValidoOs($vendedor, $empresaId > 0 ? $empresaId : null)) {
            $this->aplicarTecnicoOs($vendedor);

            return;
        }

        $sessionVendedorId = (int) (session('erp.pdv.vendedor_id') ?? 0);

        if ($sessionVendedorId > 0) {
            $vendedor = Vendedor::query()
                ->with(['rhFuncionario', 'estoqueCadastro', 'empresas'])
                ->where('ativo', true)
                ->find($sessionVendedorId);

            if ($vendedor && $this->tecnicoOperacionalValidoOs($vendedor, $empresaId > 0 ? $empresaId : null)) {
                $this->aplicarTecnicoOs($vendedor);

                return;
            }
        }

        $this->limparTecnicoOperacionalOs();
    }

    protected function deveExigirTecnicoAntesDoItemOs(): bool
    {
        return EmpresaParametros::solicitarVendedorCadaVenda(ErpContext::currentEmpresaId());
    }

    protected function limparTecnicoOperacionalOs(): void
    {
        $this->atendenteId = null;
        $this->osEstoqueId = null;
        $this->osEstoqueLabel = '';
    }

    /**
     * Paralelo de vendedorOperacionalValido da TV, com setor_servicos (combo Técnico da OS).
     */
    protected function tecnicoOperacionalValidoOs(Vendedor $vendedor, ?int $empresaId = null): bool
    {
        if (! $vendedor->ativo || ! $vendedor->setor_servicos) {
            return false;
        }

        $vendedor->loadMissing('rhFuncionario');

        if (! $vendedor->rhFuncionario) {
            return false;
        }

        $empresaId ??= ErpContext::currentEmpresaId();

        if (! $empresaId) {
            return false;
        }

        if ((int) ($vendedor->empresa_id ?? 0) === (int) $empresaId) {
            return true;
        }

        if ($vendedor->relationLoaded('empresas')) {
            return $vendedor->empresas->contains(
                fn ($empresa): bool => (int) $empresa->id === (int) $empresaId
            );
        }

        return $vendedor->empresas()->where('empresas.id', (int) $empresaId)->exists();
    }

    protected function aplicarTecnicoOs(Vendedor $vendedor): void
    {
        $vendedor->loadMissing(['rhFuncionario', 'estoqueCadastro']);
        $this->atendenteId = (int) $vendedor->id;
        unset($this->atendenteOptions);
        $this->syncOsEstoqueLabelDoAtendente($vendedor);
    }

    /**
     * Mesma fonte/regra da Tela de Venda ({@see VendedorEstoque}).
     * Somente leitura na faixa — não altera faturamento/baixa.
     * Prefere dados já carregados em atendenteOptions (sem query extra).
     */
    protected function syncOsEstoqueLabelDoAtendente(?Vendedor $vendedor = null): void
    {
        if ($vendedor !== null) {
            $resolvido = VendedorEstoque::fromVendedor($vendedor);
            $this->osEstoqueId = $resolvido['id'];
            $this->osEstoqueLabel = $resolvido['label'];

            return;
        }

        $atendenteId = (int) ($this->atendenteId ?? 0);
        if ($atendenteId <= 0) {
            $this->osEstoqueId = null;
            $this->osEstoqueLabel = '';

            return;
        }

        foreach ($this->atendenteOptions as $op) {
            if ((int) ($op['id'] ?? 0) !== $atendenteId) {
                continue;
            }

            $eid = isset($op['estoque_id']) ? (int) $op['estoque_id'] : 0;
            $this->osEstoqueId = $eid > 0 ? $eid : null;
            $this->osEstoqueLabel = (string) ($op['estoque_label'] ?? '');

            return;
        }

        $resolvido = VendedorEstoque::fromVendedorId($atendenteId);
        $this->osEstoqueId = $resolvido['id'];
        $this->osEstoqueLabel = $resolvido['label'];
    }

    /**
     * Chamado pelo updatedAtendenteId da form page (evita conflito de trait).
     */
    protected function onOsAtendenteIdUpdated(): void
    {
        $this->syncOsEstoqueLabelDoAtendente();

        if ($this->itens === [] && $this->tabelaPrecoId === null) {
            $this->carregarTabelaPrecoPadraoOs();
        }
    }

    protected function carregarTabelaPrecoOpcoesOs(): void
    {
        $tabSvc = app(ProductTabelaPrecoService::class);
        $ids = array_values($tabSvc->tabelasPadraoIds());

        $this->tabelaPrecoOpcoes = PriceTable::query()
            ->whereIn('id', $ids)
            ->where('ativo', true)
            ->orderByRaw('CAST(codigo AS UNSIGNED)')
            ->orderBy('descricao')
            ->get(['id', 'codigo', 'descricao'])
            ->map(fn (PriceTable $t): array => [
                'id' => (int) $t->id,
                'label' => trim(($t->codigo ? $t->codigo.' - ' : '').($t->descricao ?? '')),
            ])
            ->all();
    }

    /**
     * Prioridade igual à Tela de Venda: cliente → técnico/vendedor → varejo.
     */
    protected function carregarTabelaPrecoPadraoOs(): void
    {
        if ($this->clienteId) {
            $tabelaCliente = (int) (Person::query()->whereKey((int) $this->clienteId)->value('price_table_id') ?? 0);

            if ($tabelaCliente > 0 && $this->aplicarTabelaPrecoOs($tabelaCliente, recalcGrade: false)) {
                return;
            }
        }

        if ($this->atendenteId) {
            $tabelaVend = (int) (Vendedor::query()->whereKey((int) $this->atendenteId)->value('tabela_venda_id') ?? 0);

            if ($tabelaVend > 0 && $this->aplicarTabelaPrecoOs($tabelaVend, recalcGrade: false)) {
                return;
            }
        }

        $varejoId = app(ProductTabelaPrecoService::class)->tabelaIdDoNivel(ProductTabelaPrecoService::NIVEL_VAREJO);

        if ($varejoId && $this->aplicarTabelaPrecoOs((int) $varejoId, recalcGrade: false)) {
            return;
        }

        $this->tabelaPrecoId = null;
        $this->tabelaPrecoLabel = '';
    }

    protected function aplicarTabelaPrecoOs(int $id, bool $recalcGrade = true): bool
    {
        if ($id <= 0) {
            $this->tabelaPrecoId = null;
            $this->tabelaPrecoLabel = '';

            return false;
        }

        $labelOpcao = null;
        foreach ($this->tabelaPrecoOpcoes as $op) {
            if ((int) ($op['id'] ?? 0) === $id) {
                $labelOpcao = (string) ($op['label'] ?? '');
                break;
            }
        }

        if ($labelOpcao !== null) {
            $this->tabelaPrecoId = $id;
            $this->tabelaPrecoLabel = $labelOpcao;
        } else {
            $tabela = PriceTable::query()->find($id, ['id', 'codigo', 'descricao', 'ativo']);

            if (! $tabela || ! $tabela->ativo) {
                return false;
            }

            $this->tabelaPrecoId = (int) $tabela->id;
            $this->tabelaPrecoLabel = trim(($tabela->codigo ? $tabela->codigo.' - ' : '').($tabela->descricao ?? ''));
            array_unshift($this->tabelaPrecoOpcoes, [
                'id' => $this->tabelaPrecoId,
                'label' => $this->tabelaPrecoLabel,
            ]);
        }

        if ($recalcGrade) {
            $this->aplicarTabelaPrecoNaGradeOs();
        }

        return true;
    }

    public function aplicarTabelaPrecoNaGradeOs(int $id = 0): void
    {
        if ($this->osReadOnly()) {
            return;
        }

        if ($id > 0) {
            if (! $this->aplicarTabelaPrecoOs($id, recalcGrade: false)) {
                Notification::make()->title('Tabela de preço inválida.')->warning()->send();

                return;
            }
        }

        if ($this->itens === []) {
            return;
        }

        $productIds = [];
        foreach ($this->itens as $item) {
            $pid = (int) ($item['product_id'] ?? 0);
            if ($pid > 0) {
                $productIds[$pid] = $pid;
            }
        }

        if ($productIds === []) {
            return;
        }

        $products = Product::query()
            ->whereIn('id', array_values($productIds))
            ->get([
                'id', 'preco_venda', 'preco_atacado', 'preco_especial',
                'qtd_atacado', 'usa_tab_preco', 'promo_preco_venda',
                'promo_data_inicio', 'promo_data_fim',
            ])
            ->keyBy('id');

        $comTab = $products->filter(static fn (Product $p): bool => (bool) ($p->usa_tab_preco ?? false));
        if ($comTab->isNotEmpty()) {
            app(ProductTabelaPrecoService::class)->carregarItens($comTab->values());
        }

        $nivel = $this->nivelTabelaPrecoSelecionadaOs();
        $itens = $this->itens;
        $alterados = 0;

        foreach ($itens as $index => $item) {
            if (! empty($item['preco_manual'])) {
                continue;
            }

            $productId = (int) ($item['product_id'] ?? 0);
            $product = $productId > 0 ? $products->get($productId) : null;

            if (! $product) {
                continue;
            }

            $qtd = max(0.001, ErpMoney::parseBr($item['qtd'] ?? 1, 3));
            $definido = $this->precoCadastradoNoNivelOs($product, $nivel, $qtd);

            if ($definido === null) {
                continue;
            }

            $novoPreco = $this->precoProdutoNaTabelaOs($product, $qtd);
            $precoAtual = round(ErpMoney::parseBr($item['preco'] ?? 0), 2);

            if (abs($precoAtual - $novoPreco) < 0.005) {
                continue;
            }

            $itens[$index]['preco'] = ErpMoney::formatBr($novoPreco);
            $itens[$index] = $this->recalcItemRowData($itens[$index]);
            $alterados++;
        }

        $this->itens = array_values($itens);

        if ($alterados > 0) {
            $this->recalcTotais();
        }
    }

    /**
     * @return 'varejo'|'atacado'|'especial'
     */
    protected function nivelTabelaPrecoSelecionadaOs(): string
    {
        return app(ProductTabelaPrecoService::class)->nivelDaTabelaId($this->tabelaPrecoId)
            ?? ProductTabelaPrecoService::NIVEL_VAREJO;
    }

    protected function precoProdutoNaTabelaOs(Product $product, float $quantidade = 1.0): float
    {
        $tabSvc = app(ProductTabelaPrecoService::class);
        $nivel = $this->nivelTabelaPrecoSelecionadaOs();
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $empresaId = $empresaId > 0 ? $empresaId : null;

        if ($product->usa_tab_preco ?? false) {
            $tabSvc->carregarItens($product);

            if ($tabSvc->habilitaNivel($product, $nivel)) {
                return $tabSvc->resolvePrecoNivel($product, $nivel, $quantidade, $empresaId);
            }

            $habilitados = $tabSvc->niveisHabilitados($product);

            if ($habilitados !== []) {
                return $tabSvc->resolvePrecoNivel($product, $habilitados[0], $quantidade, $empresaId);
            }
        }

        return $tabSvc->resolvePrecoNivel($product, $nivel, $quantidade, $empresaId);
    }

    protected function precoCadastradoNoNivelOs(Product $product, string $nivel, float $quantidade = 1.0): ?float
    {
        $tabSvc = app(ProductTabelaPrecoService::class);
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        if ($product->usa_tab_preco ?? false) {
            $tabSvc->carregarItens($product);

            if (! $tabSvc->habilitaNivel($product, $nivel)) {
                return null;
            }

            $precos = app(ProductEmpresaPrecoService::class)->resolve(
                $product,
                $empresaId > 0 ? $empresaId : null,
            );

            $bruto = match ($nivel) {
                ProductTabelaPrecoService::NIVEL_ATACADO => (float) ($precos['preco_atacado'] ?? 0),
                ProductTabelaPrecoService::NIVEL_ESPECIAL => (float) ($precos['preco_especial'] ?? $product->preco_especial ?? 0),
                default => $tabSvc->resolvePrecoNivel(
                    $product,
                    ProductTabelaPrecoService::NIVEL_VAREJO,
                    $quantidade,
                    $empresaId > 0 ? $empresaId : null,
                ),
            };

            return $bruto > 0 ? round($bruto, 2) : null;
        }

        $bruto = match ($nivel) {
            ProductTabelaPrecoService::NIVEL_ATACADO => (float) ($product->preco_atacado ?? 0),
            ProductTabelaPrecoService::NIVEL_ESPECIAL => (float) ($product->preco_especial ?? 0),
            default => $tabSvc->resolvePrecoNivel(
                $product,
                ProductTabelaPrecoService::NIVEL_VAREJO,
                $quantidade,
                $empresaId > 0 ? $empresaId : null,
            ),
        };

        return $bruto > 0 ? round($bruto, 2) : null;
    }

}
