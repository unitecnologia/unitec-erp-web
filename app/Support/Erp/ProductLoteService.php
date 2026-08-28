<?php

namespace App\Support\Erp;

use App\Models\Product;
use App\Models\ProductLote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Controle simples de lote/validade (opt-in via products.controla_lote_validade).
 * Entrada na compra; saída FEFO no PDV; estorno devolve no lote de validade mais próxima.
 */
final class ProductLoteService
{
    public function tabelaExiste(): bool
    {
        return Schema::hasTable('product_lotes');
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

        foreach ($linhas as $linha) {
            $lote = mb_substr(trim((string) ($linha['lote'] ?? '')), 0, 60);
            $validade = $this->parseDate((string) ($linha['data_validade'] ?? ''));
            $qtd = $this->parseQtd($linha['quantidade'] ?? 0);

            if ($lote === '' || ! $validade || $qtd <= 0) {
                continue;
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
    }

    public function consumirFefo(Product|int $product, float $quantidade): void
    {
        if ($quantidade <= 0) {
            return;
        }

        $product = $product instanceof Product ? $product : Product::query()->find($product);
        if (! $product || ! $this->controla($product)) {
            return;
        }

        DB::transaction(function () use ($product, $quantidade): void {
            $restante = round($quantidade, 3);
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
            }

            if ($restante > 0.0005) {
                throw new RuntimeException(
                    'Estoque por lote insuficiente para "'.$product->descricao.'". Faltam '
                    .number_format($restante, 3, ',', '.').' (FEFO).'
                );
            }

            $this->sincronizarEspelhoProduto($product);
        });
    }

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

            if ($alvo) {
                $alvo->quantidade_atual = round((float) $alvo->quantidade_atual + $quantidade, 3);
                $alvo->save();
            } else {
                $validade = $product->validade
                    ? Carbon::parse($product->validade)->toDateString()
                    : now()->addDays(30)->toDateString();

                ProductLote::query()->create([
                    'product_id' => $product->id,
                    'lote' => 'DEVOLUCAO',
                    'data_validade' => $validade,
                    'quantidade_atual' => round($quantidade, 3),
                ]);
            }

            $this->sincronizarEspelhoProduto($product);
        });
    }

    /**
     * Ao ligar o controle com estoque > 0 e sem lotes, cria um lote inicial.
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
