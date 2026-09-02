<?php

namespace App\Support\Erp\Dashboard;

use App\Support\Erp\ErpSchema;
use App\Models\Person;
use App\Models\PdvVenda;
use App\Models\PdvVendaItem;
use App\Models\Product;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Models\Vendedor;
use App\Support\Erp\ErpEmpresaScopeFilter;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Financeiro\ErpFinanceiroMetricas;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ErpDashboardHighlights
{
    /** @var int|list<int>|null */
    private static int|array|null $empresaScope = null;

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return list<array{label: string, value: string, hint: string}>
     */
    public static function build(int|array|null $empresaScope = null): array
    {
        self::$empresaScope = $empresaScope;

        $inicio = ErpFinanceiroMetricas::hoje()->copy()->startOfMonth();
        $fim = ErpFinanceiroMetricas::hoje()->copy()->endOfMonth();

        try {
            return [
                static::safe('ticketMedio', $inicio, $fim, [
                    'label' => 'Ticket médio',
                    'value' => '—',
                    'hint' => 'Sem vendas no mês',
                ]),
                static::safe('produtoMaisVendido', $inicio, $fim, [
                    'label' => 'Produto mais vendido',
                    'value' => '—',
                    'hint' => 'Sem itens no mês',
                ]),
                static::safe('clienteMaisComprou', $inicio, $fim, [
                    'label' => 'Cliente que mais comprou',
                    'value' => '—',
                    'hint' => 'Sem clientes no mês',
                ]),
                static::safe('vendedorDestaque', $inicio, $fim, [
                    'label' => 'Vendedor destaque',
                    'value' => '—',
                    'hint' => 'Sem vendedores no mês',
                ]),
            ];
        } finally {
            self::$empresaScope = null;
        }
    }

    /**
     * @param  array{label: string, value: string, hint: string}  $fallback
     * @return array{label: string, value: string, hint: string}
     */
    private static function safe(string $method, Carbon $inicio, Carbon $fim, array $fallback): array
    {
        try {
            return static::$method($inicio, $fim);
        } catch (Throwable $e) {
            Log::warning('ErpDashboardHighlights::'.$method.' falhou', [
                'message' => $e->getMessage(),
            ]);

            return $fallback;
        }
    }

    /**
     * @return array{label: string, value: string, hint: string}
     */
    private static function ticketMedio(Carbon $inicio, Carbon $fim): array
    {
        $total = 0.0;
        $qtd = 0;

        if (ErpSchema::hasTable((new PdvVenda)->getTable())) {
            $q = PdvVenda::query()
                ->where('situacao', '!=', 'C')
                ->where(function ($query) use ($inicio, $fim): void {
                    static::scopePdvPeriodo($query, $inicio, $fim);
                });

            ErpEmpresaScopeFilter::applyPdvSessao($q, self::$empresaScope);

            $row = $q->selectRaw('COUNT(*) as qtd, COALESCE(SUM(total), 0) as total')->first();

            $total += (float) ($row->total ?? 0);
            $qtd += (int) ($row->qtd ?? 0);
        }

        if (ErpSchema::hasTable((new Venda)->getTable())) {
            $q = Venda::query()
                ->whereNotIn('status', [Venda::STATUS_CANCELADO])
                ->where(function ($query): void {
                    $query->whereNull('plataforma')
                        ->orWhere('plataforma', '!=', Venda::PLATAFORMA_PDV);
                })
                ->whereDate('data', '>=', $inicio->toDateString())
                ->whereDate('data', '<=', $fim->toDateString());

            ErpEmpresaScopeFilter::applyColumn($q, (new Venda)->getTable(), self::$empresaScope);

            $row = $q->selectRaw('COUNT(*) as qtd, COALESCE(SUM(total), 0) as total')->first();

            $total += (float) ($row->total ?? 0);
            $qtd += (int) ($row->qtd ?? 0);
        }

        if ($qtd <= 0) {
            return [
                'label' => 'Ticket médio',
                'value' => '—',
                'hint' => 'Sem vendas no mês',
            ];
        }

        return [
            'label' => 'Ticket médio',
            'value' => 'R$ '.ErpMoney::formatBr($total / $qtd),
            'hint' => number_format($qtd, 0, ',', '.').' vendas no mês',
        ];
    }

    /**
     * @return array{label: string, value: string, hint: string}
     */
    private static function produtoMaisVendido(Carbon $inicio, Carbon $fim): array
    {
        /** @var array<string, array{nome: string, qty: float}> $map */
        $map = [];

        if (ErpSchema::hasTable((new PdvVendaItem)->getTable()) && ErpSchema::hasTable((new PdvVenda)->getTable())) {
            $rows = PdvVendaItem::query()
                ->selectRaw('product_id, MAX(descricao) as descricao, SUM(quantidade) as qty')
                ->whereHas('venda', function (Builder $query) use ($inicio, $fim): void {
                    $query->where('situacao', '!=', 'C')
                        ->where(function ($periodo) use ($inicio, $fim): void {
                            static::scopePdvPeriodo($periodo, $inicio, $fim);
                        });
                    ErpEmpresaScopeFilter::applyPdvSessao($query, self::$empresaScope);
                })
                ->groupBy('product_id')
                ->get();

            foreach ($rows as $row) {
                $pid = (int) ($row->product_id ?? 0);
                $nome = trim((string) ($row->descricao ?: 'Produto'));
                $key = $pid > 0
                    ? 'p:'.$pid
                    : 'd:'.mb_strtoupper($nome !== '' ? $nome : 'Produto', 'UTF-8');
                $map[$key] = [
                    'nome' => $nome !== '' ? $nome : 'Produto',
                    'qty' => (float) ($map[$key]['qty'] ?? 0) + (float) $row->qty,
                ];
            }
        }

        if (ErpSchema::hasTable((new VendaItem)->getTable()) && ErpSchema::hasTable((new Venda)->getTable())) {
            $rows = VendaItem::query()
                ->selectRaw('product_id, SUM(quantidade) as qty')
                ->whereNotNull('product_id')
                ->where('product_id', '>', 0)
                ->whereHas('venda', function (Builder $query) use ($inicio, $fim): void {
                    $query->whereNotIn('status', [Venda::STATUS_CANCELADO])
                        ->where('tipo', '!=', Venda::TIPO_CUPOM)
                        ->where(function ($plataforma): void {
                            $plataforma->whereNull('plataforma')
                                ->orWhere('plataforma', '!=', Venda::PLATAFORMA_PDV);
                        })
                        ->whereDate('data', '>=', $inicio->toDateString())
                        ->whereDate('data', '<=', $fim->toDateString());
                    ErpEmpresaScopeFilter::applyColumn($query, (new Venda)->getTable(), self::$empresaScope);
                })
                ->groupBy('product_id')
                ->get();

            $nomes = Product::query()
                ->whereIn('id', $rows->pluck('product_id')->filter()->all())
                ->pluck('descricao', 'id');

            foreach ($rows as $row) {
                $pid = (int) ($row->product_id ?? 0);
                if ($pid <= 0) {
                    continue;
                }
                $key = 'p:'.$pid;
                $nome = trim((string) ($nomes[$pid] ?? 'Produto'));
                $map[$key] = [
                    'nome' => $nome !== '' ? $nome : ($map[$key]['nome'] ?? 'Produto'),
                    'qty' => (float) ($map[$key]['qty'] ?? 0) + (float) $row->qty,
                ];
            }
        }

        if ($map === []) {
            return [
                'label' => 'Produto mais vendido',
                'value' => '—',
                'hint' => 'Sem itens no mês',
            ];
        }

        uasort($map, fn (array $a, array $b): int => $b['qty'] <=> $a['qty']);
        $top = reset($map);

        return [
            'label' => 'Produto mais vendido',
            'value' => static::truncate((string) $top['nome'], 36),
            'hint' => number_format((float) $top['qty'], 0, ',', '.').' un. no mês',
        ];
    }

    /**
     * @return array{label: string, value: string, hint: string}
     */
    private static function clienteMaisComprou(Carbon $inicio, Carbon $fim): array
    {
        /** @var array<int, float> $totais */
        $totais = [];

        if (ErpSchema::hasTable((new PdvVenda)->getTable())) {
            $q = PdvVenda::query()
                ->selectRaw('person_id, SUM(total) as total')
                ->where('situacao', '!=', 'C')
                ->whereNotNull('person_id')
                ->where(function ($query) use ($inicio, $fim): void {
                    static::scopePdvPeriodo($query, $inicio, $fim);
                });

            ErpEmpresaScopeFilter::applyPdvSessao($q, self::$empresaScope);

            $rows = $q->groupBy('person_id')->pluck('total', 'person_id');

            foreach ($rows as $id => $total) {
                $totais[(int) $id] = round((float) ($totais[(int) $id] ?? 0) + (float) $total, 2);
            }
        }

        if (ErpSchema::hasTable((new Venda)->getTable())) {
            $q = Venda::query()
                ->selectRaw('cliente_id, SUM(total) as total')
                ->whereNotIn('status', [Venda::STATUS_CANCELADO])
                ->whereNotNull('cliente_id')
                ->where(function ($query): void {
                    $query->whereNull('plataforma')
                        ->orWhere('plataforma', '!=', Venda::PLATAFORMA_PDV);
                })
                ->whereDate('data', '>=', $inicio->toDateString())
                ->whereDate('data', '<=', $fim->toDateString());

            ErpEmpresaScopeFilter::applyColumn($q, (new Venda)->getTable(), self::$empresaScope);

            $rows = $q->groupBy('cliente_id')->pluck('total', 'cliente_id');

            foreach ($rows as $id => $total) {
                $totais[(int) $id] = round((float) ($totais[(int) $id] ?? 0) + (float) $total, 2);
            }
        }

        if ($totais === []) {
            return [
                'label' => 'Cliente que mais comprou',
                'value' => '—',
                'hint' => 'Sem clientes no mês',
            ];
        }

        arsort($totais);
        $clienteId = (int) array_key_first($totais);
        $valor = (float) $totais[$clienteId];
        $nome = Person::query()->whereKey($clienteId)->value('nome_razao') ?: 'Cliente';

        return [
            'label' => 'Cliente que mais comprou',
            'value' => static::truncate((string) $nome, 36),
            'hint' => 'R$ '.ErpMoney::formatBr($valor).' no mês',
        ];
    }

    /**
     * @return array{label: string, value: string, hint: string}
     */
    private static function vendedorDestaque(Carbon $inicio, Carbon $fim): array
    {
        /** @var array<int, float> $totais */
        $totais = [];

        if (ErpSchema::hasTable((new PdvVenda)->getTable())) {
            $q = PdvVenda::query()
                ->selectRaw('vendedor_id, SUM(total) as total')
                ->where('situacao', '!=', 'C')
                ->whereNotNull('vendedor_id')
                ->where(function ($query) use ($inicio, $fim): void {
                    static::scopePdvPeriodo($query, $inicio, $fim);
                });

            ErpEmpresaScopeFilter::applyPdvSessao($q, self::$empresaScope);

            $rows = $q->groupBy('vendedor_id')->pluck('total', 'vendedor_id');

            foreach ($rows as $id => $total) {
                $totais[(int) $id] = round((float) ($totais[(int) $id] ?? 0) + (float) $total, 2);
            }
        }

        if (ErpSchema::hasTable((new Venda)->getTable())) {
            $q = Venda::query()
                ->selectRaw('vendedor_id, SUM(total) as total')
                ->whereNotIn('status', [Venda::STATUS_CANCELADO])
                ->whereNotNull('vendedor_id')
                ->where(function ($query): void {
                    $query->whereNull('plataforma')
                        ->orWhere('plataforma', '!=', Venda::PLATAFORMA_PDV);
                })
                ->whereDate('data', '>=', $inicio->toDateString())
                ->whereDate('data', '<=', $fim->toDateString());

            ErpEmpresaScopeFilter::applyColumn($q, (new Venda)->getTable(), self::$empresaScope);

            $rows = $q->groupBy('vendedor_id')->pluck('total', 'vendedor_id');

            foreach ($rows as $id => $total) {
                $totais[(int) $id] = round((float) ($totais[(int) $id] ?? 0) + (float) $total, 2);
            }
        }

        if ($totais === []) {
            return [
                'label' => 'Vendedor destaque',
                'value' => '—',
                'hint' => 'Sem vendedores no mês',
            ];
        }

        arsort($totais);
        $vendedorId = (int) array_key_first($totais);
        $valor = (float) $totais[$vendedorId];
        $nome = Vendedor::query()->whereKey($vendedorId)->value('nome') ?: 'Vendedor';

        return [
            'label' => 'Vendedor destaque',
            'value' => static::truncate((string) $nome, 36),
            'hint' => 'R$ '.ErpMoney::formatBr($valor).' no mês',
        ];
    }

    private static function scopePdvPeriodo($query, Carbon $inicio, Carbon $fim, string $table = ''): void
    {
        $prefix = $table !== '' ? $table.'.' : '';

        $query->where(function ($periodo) use ($inicio, $fim, $prefix): void {
            $periodo->where(function ($fechamento) use ($inicio, $fim, $prefix): void {
                $fechamento->whereNotNull($prefix.'fechado_em')
                    ->whereDate($prefix.'fechado_em', '>=', $inicio->toDateString())
                    ->whereDate($prefix.'fechado_em', '<=', $fim->toDateString());
            })->orWhere(function ($fallback) use ($inicio, $fim, $prefix): void {
                $fallback->whereNull($prefix.'fechado_em')
                    ->whereDate($prefix.'created_at', '>=', $inicio->toDateString())
                    ->whereDate($prefix.'created_at', '<=', $fim->toDateString());
            });
        });
    }

    private static function truncate(string $text, int $max): string
    {
        $text = trim($text);
        if (mb_strlen($text, 'UTF-8') <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max - 1, 'UTF-8')).'…';
    }
}
