<?php

namespace App\Support\Erp;

use App\Models\Product;
use App\Models\ProductLote;
use App\Models\ProductLoteMovimento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Controle de lote/validade (opt-in via products.controla_lote_validade).
 * Entrada na compra; saída FEFO nas vendas com rastro em product_lote_movimentos;
 * estorno devolve ao lote original quando há rastro.
 */
final class ProductLoteService
{
    public function tabelaExiste(): bool
    {
        return Schema::hasTable('product_lotes');
    }

    public function tabelaMovimentosExiste(): bool
    {
        return Schema::hasTable('product_lote_movimentos');
    }

    public function controla(Product|int|null $product): bool
    {
        if (! $this->tabelaExiste()) {
            return false;
        }

        if ($product instanceof Product) {
            return (bool) ($product->controla_lote_validade ?? false);
        }

        if (! $product) {
            return false;
        }

        return (bool) Product::query()->whereKey($product)->value('controla_lote_validade');
    }

    /**
     * Soma das quantidades disponíveis nos lotes (apenas qty > 0).
     */
    public function saldoDisponivel(Product|int $product): float
    {
        if (! $this->controla($product)) {
            return 0.0;
        }

        $productId = $product instanceof Product ? (int) $product->id : (int) $product;

        return round((float) ProductLote::query()
            ->where('product_id', $productId)
            ->where('quantidade_atual', '>', 0)
            ->sum('quantidade_atual'), 3);
    }

    public function possuiLotes(Product|int $product): bool
    {
        if (! $this->tabelaExiste()) {
            return false;
        }

        $productId = $product instanceof Product ? (int) $product->id : (int) $product;

        return ProductLote::query()->where('product_id', $productId)->exists();
    }

    /**
     * @param  list<array{lote?: string, data_validade?: string, quantidade?: float|string}>  $linhas
     */
    public function validarLinhasEntrada(float $quantidadeEsperada, array $linhas, bool $exigeValidade = true): void
    {
        if ($linhas === []) {
            throw new RuntimeException('Informe ao menos um lote para este produto.');
        }

        $soma = 0.0;
        foreach ($linhas as $i => $linha) {
            $lote = trim((string) ($linha['lote'] ?? ''));
            $validade = trim((string) ($linha['data_validade'] ?? ''));
            $qtd = $this->parseQtd($linha['quantidade'] ?? 0);

            if ($lote === '') {
                throw new RuntimeException('Lote obrigatório na linha '.($i + 1).'.');
            }
            if ($exigeValidade && $validade === '') {
                throw new RuntimeException('Validade obrigatória na linha '.($i + 1).'.');
            }
            if ($validade !== '' && ! $this->parseDate($validade)) {
                throw new RuntimeException('Validade inválida na linha '.($i + 1).'. Use dd/mm/aaaa ou aaaa-mm-dd.');
            }
            if ($qtd <= 0) {
                throw new RuntimeException('Quantidade do lote deve ser maior que zero (linha '.($i + 1).').');
            }

            $soma += $qtd;
        }

        if (abs($soma - $quantidadeEsperada) > 0.0005) {
            throw new RuntimeException(
                'A soma dos lotes ('.number_format($soma, 3, ',', '.').') deve ser igual à quantidade da entrada ('
                .number_format($quantidadeEsperada, 3, ',', '.').').'
            );
        }
    }

    /**
     * @param  list<array{lote?: string, data_validade?: string, quantidade?: float|string}>  $linhas
     */
    public function entrar(Product $product, array $linhas): void
    {
        if (! $this->controla($product)) {
            return;
        }

        DB::transaction(function () use ($product, $linhas): void {
            foreach ($linhas as $i => $linha) {
                $lote = mb_substr(trim((string) ($linha['lote'] ?? '')), 0, 60);
                $validade = $this->parseDate((string) ($linha['data_validade'] ?? ''));
                $qtd = $this->parseQtd($linha['quantidade'] ?? 0);

                if ($lote === '' || ! $validade || $qtd <= 0) {
                    throw new RuntimeException(
                        'Lote inválido na linha '.($i + 1).' ao registrar entrada por lote/validade.'
                    );
                }

                $row = ProductLote::query()
                    ->where('product_id', $product->id)
                    ->where('lote', $lote)
                    ->whereDate('data_validade', $validade->toDateString())
                    ->lockForUpdate()
                    ->first();

                if ($row) {
                    $row->quantidade_atual = round((float) $row->quantidade_atual + $qtd, 3);
                    $row->save();
                } else {
                    ProductLote::query()->create([
                        'product_id' => $product->id,
                        'lote' => $lote,
                        'data_validade' => $validade->toDateString(),
                        'quantidade_atual' => round($qtd, 3),
                    ]);
                }
            }

            $this->sincronizarEspelhoProduto($product);
        });
    }

    /**
     * @param  list<array{lote?: string, data_validade?: string, quantidade?: float|string}>  $linhas
     */
    public function estornarEntrada(Product $product, array $linhas): void
    {
        if (! $this->tabelaExiste() || $linhas === []) {
            return;
        }

        DB::transaction(function () use ($product, $linhas): void {
            foreach ($linhas as $linha) {
                $lote = mb_substr(trim((string) ($linha['lote'] ?? '')), 0, 60);
                $validade = $this->parseDate((string) ($linha['data_validade'] ?? ''));
                $qtd = $this->parseQtd($linha['quantidade'] ?? 0);
                $nome = trim((string) ($product->descricao ?? 'produto'));

                if ($lote === '' || ! $validade || $qtd <= 0) {
                    throw new RuntimeException('Não foi possível reabrir: um lote lançado nesta compra está incompleto.');
                }

                $validadeBr = $validade->format('d/m/Y');
                $row = ProductLote::query()
                    ->where('product_id', $product->id)
                    ->where('lote', $lote)
                    ->whereDate('data_validade', $validade->toDateString())
                    ->lockForUpdate()
                    ->first();

                if (! $row) {
                    throw new RuntimeException(
                        'Não foi possível reabrir: o lote '.$lote.' ('.$validadeBr.') de '.$nome.' não existe mais.'
                    );
                }

                $disponivel = round((float) $row->quantidade_atual, 3);

                if ($disponivel + 0.0005 < $qtd) {
                    throw new RuntimeException(
                        'Não foi possível reabrir: o lote '.$lote.' ('.$validadeBr.') de '.$nome
                        .' não tem quantidade suficiente para estornar '.number_format($qtd, 3, ',', '.')
                        .'. Saldo do lote: '.number_format($disponivel, 3, ',', '.').'.'
                    );
                }

                $row->quantidade_atual = round($disponivel - $qtd, 3);
                $row->save();
            }

            $this->sincronizarEspelhoProduto($product);
        });
    }

    /**
     * Baixa FEFO. Retorna fatias realmente consumidas (para rastro).
     * Com $permitirNegativo, o déficit vai para um lote real existente (nunca cria lote fictício).
     *
     * @return list<array{product_lote_id: int, lote: string, data_validade: string, quantidade: float}>
     */
    public function consumirFefo(Product|int $product, float $quantidade, bool $permitirNegativo = false): array
    {
        if ($quantidade <= 0) {
            return [];
        }

        $product = $product instanceof Product ? $product : Product::query()->find($product);
        if (! $product || ! $this->controla($product)) {
            return [];
        }

        return DB::transaction(function () use ($product, $quantidade, $permitirNegativo): array {
            $restante = round($quantidade, 3);
            $fatias = [];

            $lotes = ProductLote::query()
                ->where('product_id', $product->id)
                ->where('quantidade_atual', '>', 0)
                ->orderBy('data_validade')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($lotes as $lote) {
                if ($restante <= 0) {
                    break;
                }

                $disp = round((float) $lote->quantidade_atual, 3);
                $baixa = min($disp, $restante);
                $lote->quantidade_atual = round($disp - $baixa, 3);
                $lote->save();
                $restante = round($restante - $baixa, 3);

                $fatias[] = [
                    'product_lote_id' => (int) $lote->id,
                    'lote' => (string) $lote->lote,
                    'data_validade' => $lote->data_validade?->format('Y-m-d') ?? '',
                    'quantidade' => $baixa,
                ];
            }

            if ($restante > 0.0005) {
                if (! $permitirNegativo) {
                    throw new RuntimeException(
                        'Estoque por lote insuficiente para "'.$product->descricao.'". Faltam '
                        .number_format($restante, 3, ',', '.').' (FEFO).'
                    );
                }

                $alvo = $this->resolverLoteParaDeficit($product, $fatias);
                if (! $alvo) {
                    throw new RuntimeException(
                        'Produto "'.$product->descricao.'" controla lote/validade, mas não há lotes cadastrados.'
                        .' Cadastre lotes na compra ou na aba Lote/Validade. Não é permitido criar lote fictício.'
                    );
                }

                $alvo->quantidade_atual = round((float) $alvo->quantidade_atual - $restante, 3);
                $alvo->save();

                $fatias[] = [
                    'product_lote_id' => (int) $alvo->id,
                    'lote' => (string) $alvo->lote,
                    'data_validade' => $alvo->data_validade?->format('Y-m-d') ?? '',
                    'quantidade' => $restante,
                ];
            }

            $this->sincronizarEspelhoProduto($product);

            return $this->consolidarFatias($fatias);
        });
    }

    /**
     * Devolve quantidade a lotes reais existentes (ajuste sem rastro).
     * Nunca cria lote fictício (DEVOLUCAO etc.).
     */
    public function devolver(Product|int $product, float $quantidade): void
    {
        if ($quantidade <= 0) {
            return;
        }

        $product = $product instanceof Product ? $product : Product::query()->find($product);
        if (! $product || ! $this->controla($product)) {
            return;
        }

        DB::transaction(function () use ($product, $quantidade): void {
            $alvo = ProductLote::query()
                ->where('product_id', $product->id)
                ->orderBy('data_validade')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $alvo) {
                throw new RuntimeException(
                    'Produto "'.$product->descricao.'" controla lote/validade e não possui lotes cadastrados.'
                    .' Não é permitido criar lote fictício no estorno/ajuste.'
                );
            }

            $alvo->quantidade_atual = round((float) $alvo->quantidade_atual + $quantidade, 3);
            $alvo->save();

            $this->sincronizarEspelhoProduto($product);
        });
    }

    /**
     * Restitui quantidades aos lotes originais (por id ou lote+validade).
     *
     * @param  list<array{product_lote_id?: int, lote?: string, data_validade?: string, quantidade: float}>  $linhas
     */
    public function devolverLinhas(Product $product, array $linhas): void
    {
        if (! $this->controla($product) || $linhas === []) {
            return;
        }

        DB::transaction(function () use ($product, $linhas): void {
            foreach ($linhas as $linha) {
                $qtd = round((float) ($linha['quantidade'] ?? 0), 3);
                if ($qtd <= 0) {
                    continue;
                }

                $row = $this->localizarLoteParaDevolucao($product, $linha);
                if (! $row) {
                    $lote = trim((string) ($linha['lote'] ?? ''));
                    $validade = (string) ($linha['data_validade'] ?? '');

                    throw new RuntimeException(
                        'Não foi possível restituir o lote '.$lote.' ('.$validade.') de "'.$product->descricao.'": lote não encontrado.'
                    );
                }

                $row->quantidade_atual = round((float) $row->quantidade_atual + $qtd, 3);
                $row->save();
            }

            $this->sincronizarEspelhoProduto($product);
        });
    }

    /**
     * @param  list<array{product_lote_id: int, lote: string, data_validade: string, quantidade: float}>  $fatias
     */
    public function registrarSaidas(
        Product $product,
        array $fatias,
        string $origemTipo,
        int $origemId,
    ): void {
        if (! $this->tabelaMovimentosExiste() || $fatias === [] || $origemTipo === '' || $origemId <= 0) {
            return;
        }

        foreach ($fatias as $fatia) {
            $qtd = round((float) ($fatia['quantidade'] ?? 0), 3);
            if ($qtd <= 0) {
                continue;
            }

            ProductLoteMovimento::query()->create([
                'product_id' => (int) $product->id,
                'product_lote_id' => (int) ($fatia['product_lote_id'] ?? 0) ?: null,
                'lote' => mb_substr(trim((string) ($fatia['lote'] ?? '')), 0, 60),
                'data_validade' => (string) ($fatia['data_validade'] ?? ''),
                'quantidade' => $qtd,
                'sentido' => ProductLoteMovimento::SENTIDO_SAIDA,
                'origem_tipo' => $origemTipo,
                'origem_id' => $origemId,
                'referencia_movimento_id' => null,
            ]);
        }
    }

    /**
     * Estorna saídas rastreadas da origem, restituindo aos lotes originais.
     * Sem rastro: não inventa lote (retorna 0).
     *
     * @return float quantidade efetivamente restituída aos lotes
     */
    public function estornarPorOrigem(
        Product $product,
        string $origemTipo,
        int $origemId,
        ?float $quantidadeMax = null,
    ): float {
        if (! $this->controla($product)
            || ! $this->tabelaMovimentosExiste()
            || $origemTipo === ''
            || $origemId <= 0
        ) {
            return 0.0;
        }

        return (float) DB::transaction(function () use ($product, $origemTipo, $origemId, $quantidadeMax): float {
            $saidas = ProductLoteMovimento::query()
                ->where('product_id', $product->id)
                ->where('origem_tipo', $origemTipo)
                ->where('origem_id', $origemId)
                ->where('sentido', ProductLoteMovimento::SENTIDO_SAIDA)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            if ($saidas->isEmpty()) {
                return 0.0;
            }

            $restante = $quantidadeMax !== null ? round($quantidadeMax, 3) : null;
            $restituido = 0.0;
            $linhas = [];

            foreach ($saidas as $saida) {
                if ($restante !== null && $restante <= 0.0005) {
                    break;
                }

                $ja = round((float) ProductLoteMovimento::query()
                    ->where('referencia_movimento_id', $saida->id)
                    ->where('sentido', ProductLoteMovimento::SENTIDO_ENTRADA)
                    ->sum('quantidade'), 3);

                $disponivel = round((float) $saida->quantidade - $ja, 3);
                if ($disponivel <= 0.0005) {
                    continue;
                }

                $qtd = $restante !== null ? min($disponivel, $restante) : $disponivel;
                $qtd = round($qtd, 3);
                if ($qtd <= 0) {
                    continue;
                }

                ProductLoteMovimento::query()->create([
                    'product_id' => (int) $product->id,
                    'product_lote_id' => $saida->product_lote_id,
                    'lote' => (string) $saida->lote,
                    'data_validade' => $saida->data_validade?->format('Y-m-d') ?? '',
                    'quantidade' => $qtd,
                    'sentido' => ProductLoteMovimento::SENTIDO_ENTRADA,
                    'origem_tipo' => $origemTipo,
                    'origem_id' => $origemId,
                    'referencia_movimento_id' => (int) $saida->id,
                ]);

                $linhas[] = [
                    'product_lote_id' => (int) ($saida->product_lote_id ?? 0),
                    'lote' => (string) $saida->lote,
                    'data_validade' => $saida->data_validade?->format('Y-m-d') ?? '',
                    'quantidade' => $qtd,
                ];

                $restituido = round($restituido + $qtd, 3);
                if ($restante !== null) {
                    $restante = round($restante - $qtd, 3);
                }
            }

            if ($linhas !== []) {
                $this->devolverLinhas($product, $linhas);
            }

            return $restituido;
        });
    }

    public function origemTemRastro(string $origemTipo, int $origemId, ?int $productId = null): bool
    {
        if (! $this->tabelaMovimentosExiste() || $origemTipo === '' || $origemId <= 0) {
            return false;
        }

        $q = ProductLoteMovimento::query()
            ->where('origem_tipo', $origemTipo)
            ->where('origem_id', $origemId)
            ->where('sentido', ProductLoteMovimento::SENTIDO_SAIDA);

        if ($productId) {
            $q->where('product_id', $productId);
        }

        return $q->exists();
    }

    /**
     * Ao ligar o controle com estoque > 0 e sem lotes, cria um lote inicial a partir do espelho do produto.
     */
    public function garantirLoteInicial(Product $product): void
    {
        if (! $this->controla($product) || ! $this->tabelaExiste()) {
            return;
        }

        $existe = ProductLote::query()->where('product_id', $product->id)->exists();
        if ($existe) {
            return;
        }

        $qtd = round((float) ($product->estoque ?? 0), 3);
        if ($qtd <= 0) {
            return;
        }

        $lote = trim((string) ($product->lote ?? ''));
        if ($lote === '') {
            $lote = 'INICIAL';
        }

        $validade = $product->validade
            ? Carbon::parse($product->validade)->toDateString()
            : now()->addYear()->toDateString();

        ProductLote::query()->create([
            'product_id' => $product->id,
            'lote' => mb_substr($lote, 0, 60),
            'data_validade' => $validade,
            'quantidade_atual' => $qtd,
        ]);

        $this->sincronizarEspelhoProduto($product);
    }

    public function sincronizarEspelhoProduto(Product $product): void
    {
        if (! $this->tabelaExiste()) {
            return;
        }

        $proximo = ProductLote::query()
            ->where('product_id', $product->id)
            ->where('quantidade_atual', '>', 0)
            ->orderBy('data_validade')
            ->orderBy('id')
            ->first();

        $product->forceFill([
            'lote' => $proximo?->lote,
            'validade' => $proximo?->data_validade?->format('Y-m-d'),
        ])->saveQuietly();
    }

    /**
     * @param  list<array{product_lote_id: int, lote: string, data_validade: string, quantidade: float}>  $fatias
     */
    private function resolverLoteParaDeficit(Product $product, array $fatias): ?ProductLote
    {
        if ($fatias !== []) {
            $ultimoId = (int) ($fatias[array_key_last($fatias)]['product_lote_id'] ?? 0);
            if ($ultimoId > 0) {
                $row = ProductLote::query()
                    ->whereKey($ultimoId)
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();
                if ($row) {
                    return $row;
                }
            }
        }

        return ProductLote::query()
            ->where('product_id', $product->id)
            ->orderBy('data_validade')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param  array{product_lote_id?: int, lote?: string, data_validade?: string}  $linha
     */
    private function localizarLoteParaDevolucao(Product $product, array $linha): ?ProductLote
    {
        $loteId = (int) ($linha['product_lote_id'] ?? 0);
        if ($loteId > 0) {
            $row = ProductLote::query()
                ->whereKey($loteId)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();
            if ($row) {
                return $row;
            }
        }

        $lote = mb_substr(trim((string) ($linha['lote'] ?? '')), 0, 60);
        $validade = $this->parseDate((string) ($linha['data_validade'] ?? ''));
        if ($lote === '' || ! $validade) {
            return null;
        }

        return ProductLote::query()
            ->where('product_id', $product->id)
            ->where('lote', $lote)
            ->whereDate('data_validade', $validade->toDateString())
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param  list<array{product_lote_id: int, lote: string, data_validade: string, quantidade: float}>  $fatias
     * @return list<array{product_lote_id: int, lote: string, data_validade: string, quantidade: float}>
     */
    private function consolidarFatias(array $fatias): array
    {
        $map = [];
        foreach ($fatias as $fatia) {
            $id = (int) ($fatia['product_lote_id'] ?? 0);
            $key = $id > 0 ? 'id:'.$id : 'lv:'.$fatia['lote'].'|'.$fatia['data_validade'];
            if (! isset($map[$key])) {
                $map[$key] = $fatia;
                continue;
            }
            $map[$key]['quantidade'] = round((float) $map[$key]['quantidade'] + (float) $fatia['quantidade'], 3);
        }

        return array_values($map);
    }

    private function parseQtd(mixed $value): float
    {
        if (is_numeric($value)) {
            return round((float) $value, 3);
        }

        return BrDecimal::parse((string) $value, 3);
    }

    private function parseDate(string $raw): ?Carbon
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        try {
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $raw)) {
                return Carbon::createFromFormat('d/m/Y', $raw)->startOfDay();
            }

            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
