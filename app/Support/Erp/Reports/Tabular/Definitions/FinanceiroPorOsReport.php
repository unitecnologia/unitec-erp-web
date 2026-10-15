<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\CaixaLancamento;
use App\Models\ContaReceber;
use App\Models\OrdemServico;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Reports\ReportEmpresaScope;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Financeiro por OS — valores efetivos de Contas a Receber e Livro Caixa (à vista).
 *
 * Vínculo real: documento OS-{n} / OS-{n}-parcela (sem FK). Não usa venda espelho.
 * Caixa de baixa de CR (historico "Recebimento conta a receber…") não entra em "recebido"
 * para não duplicar o que já está em contas_receber.valor_recebido.
 */
class FinanceiroPorOsReport extends AbstractTabularReport
{
    private const EPS = 0.009;

    public function slug(): string
    {
        return 'financeiro-por-os';
    }

    public function title(): string
    {
        return 'FINANCEIRO POR OS';
    }

    public function permission(): string
    {
        return 'ordens_servico.print';
    }

    public function columns(): array
    {
        return [
            'numero' => 'OS',
            'cliente' => 'CLIENTE',
            'empresa' => 'EMPRESA',
            'faturamento' => 'FATURAMENTO',
            'faturado' => 'FATURADO',
            'recebido' => 'RECEBIDO',
            'pendente' => 'PENDENTE',
            'vencido' => 'VENCIDO',
            'situacao_fin' => 'SIT. FIN.',
        ];
    }

    public function columnWidthWeights(): array
    {
        return [
            'numero' => 4,
            'cliente' => 28,
            'empresa' => 10,
            'faturamento' => 9,
            'faturado' => 8,
            'recebido' => 8,
            'pendente' => 8,
            'vencido' => 8,
            'situacao_fin' => 9,
        ];
    }

    public function nowrapColumns(): array
    {
        return ['numero', 'faturamento', 'faturado', 'recebido', 'pendente', 'vencido', 'situacao_fin'];
    }

    public function defaultColumns(): array
    {
        return [
            'numero',
            'cliente',
            'faturamento',
            'faturado',
            'recebido',
            'pendente',
            'vencido',
            'situacao_fin',
        ];
    }

    public function numericColumns(): array
    {
        return ['faturado', 'recebido', 'pendente', 'vencido'];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField($this->withEmpresaFilter([
            ...$this->periodFilterFields(),
            [
                'key' => 'cliente',
                'label' => 'Cliente',
                'type' => 'text',
            ],
            [
                'key' => 'situacao_fin',
                'label' => 'Situação financeira',
                'type' => 'select',
                'options' => [
                    'todos' => 'Todas',
                    'quitada' => 'Quitadas',
                    'parcial' => 'Parciais',
                    'em_aberto' => 'Em aberto',
                    'vencido' => 'Vencidas',
                    'sem_vinculo' => 'Sem vínculo financeiro',
                ],
            ],
            [
                'key' => 'forma',
                'label' => 'Forma de pagamento',
                'type' => 'select',
                'options' => $this->formaOptions(),
            ],
        ]));
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->columnsForEmpresaScope(
            $this->resolveColumns($request->query('cols')),
            $request,
            after: 'cliente',
        );
        $clienteQ = trim((string) $request->query('cliente', ''));
        $situacaoFin = (string) $request->query('situacao_fin', 'todos');
        $forma = (string) $request->query('forma', 'todos');
        $multi = $this->isMultiEmpresaScope($request);
        $hoje = ErpTimezone::toLocal()->toDateString();

        $base = $this->filteredOsQuery(
            $request,
            $de->toDateString(),
            $ate->toDateString(),
            $clienteQ,
        );

        /** @var Collection<int, OrdemServico> $ordens */
        $ordens = (clone $base)
            ->with(['cliente:id,nome_razao'])
            ->select([
                'ordens_servico.id',
                'ordens_servico.empresa_id',
                'ordens_servico.numero',
                'ordens_servico.situacao',
                'ordens_servico.data_termino',
                'ordens_servico.cliente_id',
                'ordens_servico.nome',
                'ordens_servico.total_geral',
                'ordens_servico.faturamento_pagamentos',
            ])
            ->orderBy('ordens_servico.data_termino')
            ->orderBy('ordens_servico.numero')
            ->orderBy('ordens_servico.id')
            ->get();

        $financeByOs = $this->aggregateFinance($ordens, $hoje);

        $labels = $multi ? ReportEmpresaScope::labelsById() : [];
        $rowsBuilt = [];
        $sumFaturado = 0.0;
        $sumRecebido = 0.0;
        $sumPendente = 0.0;
        $sumVencido = 0.0;
        $qtdSemVinculo = 0;

        foreach ($ordens as $ordem) {
            $fin = $financeByOs[(int) $ordem->id] ?? $this->emptyFinance();
            $faturado = round((float) ($ordem->total_geral ?? 0), 2);
            $recebido = $fin['recebido'];
            $pendente = $fin['pendente'];
            $vencido = $fin['vencido'];
            $sit = $this->situacaoFinanceira($faturado, $recebido, $pendente, $vencido, $fin['tem_vinculo']);

            if ($situacaoFin !== 'todos' && $sit !== $situacaoFin) {
                continue;
            }

            if ($forma !== 'todos' && $forma !== '' && ! $this->osTemForma($ordem, $fin, $forma)) {
                continue;
            }

            if (! $fin['tem_vinculo']) {
                $qtdSemVinculo++;
            }

            $sumFaturado += $faturado;
            $sumRecebido += $recebido;
            $sumPendente += $pendente;
            $sumVencido += $vencido;

            $row = [
                'numero' => (string) ($ordem->numero ?? ''),
                'cliente' => $ordem->clienteNome(),
                'faturamento' => static::formatDate($ordem->data_termino),
                'faturado' => static::formatMoney($faturado),
                'recebido' => static::formatMoney($recebido),
                'pendente' => static::formatMoney($pendente),
                'vencido' => static::formatMoney($vencido),
                'situacao_fin' => $this->situacaoFinanceiraLabel($sit),
            ];

            if ($multi) {
                $row['empresa'] = $labels[(int) ($ordem->empresa_id ?? 0)]
                    ?? ReportEmpresaScope::labelEmpresa(null);
            }

            $rowsBuilt[] = $row;
        }

        $totalFiltrado = count($rowsBuilt);
        $rows = array_slice($rowsBuilt, 0, 5000);
        $limitHint = $totalFiltrado > 5000
            ? ' (limite de exibição; totais do rodapé incluem todos os filtros)'
            : '';

        $formaLabel = $forma === 'todos' || $forma === ''
            ? 'TODAS'
            : mb_strtoupper((string) (ContaReceber::formaLabels()[$forma] ?? $forma), 'UTF-8');

        $built = $this->result(
            $this->withEmpresaFilterValue([
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'cliente' => $clienteQ,
                'situacao_fin' => $situacaoFin,
                'forma' => $forma,
                'cols' => $columns,
            ], $request),
            $columns,
            $rows,
            $this->withEmpresaSummary([
                'PERÍODO (DATA FATURAMENTO): '.$this->periodLabel($de, $ate),
                'SITUAÇÃO FINANCEIRA: '.$this->situacaoFinanceiraLabel($situacaoFin === 'todos' ? 'todos' : $situacaoFin),
                'FORMA: '.$formaLabel,
                'OS: '.$totalFiltrado.$limitHint,
                'SEM VÍNCULO CR/CAIXA: '.$qtdSemVinculo.' (documento OS-{n}; sem estimar)',
                'RECEBIDO: CR (valor_recebido) + caixa à vista líquido; exclui caixa de baixa de CR e venda espelho',
            ], $request),
            withTotals: false,
        );

        $built['totals'] = $this->totalsFromAggregate(
            $columns,
            $sumFaturado,
            $sumRecebido,
            $sumPendente,
            $sumVencido,
            $totalFiltrado,
        );

        return $built;
    }

    /**
     * Universo: OS faturadas (finalizada/entregue) no período de data_termino.
     *
     * @return Builder<\App\Models\OrdemServico>
     */
    private function filteredOsQuery(Request $request, string $de, string $ate, string $clienteQ): Builder
    {
        $query = OrdemServico::query()
            ->whereIn('ordens_servico.situacao', [
                OrdemServico::SITUACAO_FINALIZADA,
                OrdemServico::SITUACAO_ENTREGUE,
            ])
            ->whereNotNull('ordens_servico.data_termino')
            ->whereDate('ordens_servico.data_termino', '>=', $de)
            ->whereDate('ordens_servico.data_termino', '<=', $ate);

        ReportEmpresaScope::applyToQuery($query, $request, 'ordens_servico.empresa_id');

        if ($clienteQ !== '') {
            $like = '%'.$clienteQ.'%';
            $query->where(function (Builder $q) use ($like, $clienteQ): void {
                $q->where('ordens_servico.nome', 'like', $like)
                    ->orWhereHas('cliente', function (Builder $cliente) use ($like, $clienteQ): void {
                        $cliente->where(function (Builder $inner) use ($like, $clienteQ): void {
                            $inner->where('nome_razao', 'like', $like);

                            if (ctype_digit($clienteQ)) {
                                $inner->orWhere('id', (int) $clienteQ)
                                    ->orWhere('codigo', 'like', $like);
                            }
                        });
                    });
            });
        }

        return $query;
    }

    /**
     * @param  Collection<int, OrdemServico>  $ordens
     * @return array<int, array{recebido: float, pendente: float, vencido: float, tem_vinculo: bool, formas: list<string>}>
     */
    private function aggregateFinance(Collection $ordens, string $hoje): array
    {
        $out = [];
        $index = [];

        foreach ($ordens as $ordem) {
            $osId = (int) $ordem->id;
            $out[$osId] = $this->emptyFinance();
            $doc = $this->documentoBaseFromNumero((string) ($ordem->numero ?? ''));

            if ($doc === null) {
                continue;
            }

            $empresaId = (int) ($ordem->empresa_id ?? 0);
            $clienteId = (int) ($ordem->cliente_id ?? 0);
            $index['doc|'.$doc.'|emp|'.$empresaId.'|cli|'.$clienteId] = $osId;
            $index['doc|'.$doc.'|emp|'.$empresaId][] = $osId;
            $numero = trim((string) $ordem->numero);
            $digits = substr($doc, 3);
            $index['hist|'.$numero.'|emp|'.$empresaId] = $osId;
            $index['hist|'.$digits.'|emp|'.$empresaId] = $osId;
        }

        if ($ordens->isEmpty()) {
            return $out;
        }

        $empresaIds = $ordens->pluck('empresa_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        $clienteIds = $ordens->pluck('cliente_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

        $crs = ContaReceber::query()
            ->when($empresaIds !== [], fn (Builder $q) => $q->whereIn('empresa_id', $empresaIds))
            ->when($clienteIds !== [], fn (Builder $q) => $q->whereIn('cliente_id', $clienteIds))
            ->where(function (Builder $q): void {
                $q->where('documento', 'like', 'OS-%')
                    ->orWhere('historico', 'like', 'OS %');
            })
            ->select([
                'id',
                'empresa_id',
                'cliente_id',
                'documento',
                'historico',
                'forma',
                'valor_recebido',
                'saldo',
                'vencimento',
            ])
            ->get();

        foreach ($crs as $conta) {
            $osId = $this->resolveOsIdForCr($conta, $index);

            if ($osId === null) {
                continue;
            }

            $recebido = round((float) ($conta->valor_recebido ?? 0), 2);
            $saldo = round((float) ($conta->saldo ?? 0), 2);
            $out[$osId]['recebido'] = round($out[$osId]['recebido'] + max(0, $recebido), 2);
            $out[$osId]['pendente'] = round($out[$osId]['pendente'] + max(0, $saldo), 2);
            $out[$osId]['tem_vinculo'] = true;

            $vencimento = $conta->vencimento;
            $vencimentoStr = $vencimento instanceof \Carbon\CarbonInterface
                ? $vencimento->toDateString()
                : trim((string) ($vencimento ?? ''));

            if ($saldo > self::EPS && $vencimentoStr !== '' && $vencimentoStr < $hoje) {
                $out[$osId]['vencido'] = round($out[$osId]['vencido'] + $saldo, 2);
            }

            $forma = mb_strtolower(trim((string) ($conta->forma ?? '')), 'UTF-8');

            if ($forma !== '') {
                $out[$osId]['formas'][] = $forma;
            }
        }

        $caixaQuery = CaixaLancamento::query()
            ->when($empresaIds !== [], fn (Builder $q) => $q->whereIn('empresa_id', $empresaIds))
            ->where(function (Builder $q): void {
                $q->where('documento', 'like', 'OS-%')
                    ->orWhere('historico', 'like', 'OS %')
                    ->orWhere('historico', 'like', 'ESTORNO OS %');
            })
            ->select([
                'id',
                'empresa_id',
                'documento',
                'historico',
                'entrada',
                'saida',
            ]);

        foreach ($caixaQuery->cursor() as $lancamento) {
            if ($this->isCaixaDeBaixaCr((string) ($lancamento->historico ?? ''))) {
                continue;
            }

            $osId = $this->resolveOsIdForCaixa($lancamento, $index);

            if ($osId === null) {
                continue;
            }

            $liquido = round((float) ($lancamento->entrada ?? 0) - (float) ($lancamento->saida ?? 0), 2);
            $out[$osId]['recebido'] = round($out[$osId]['recebido'] + $liquido, 2);
            $out[$osId]['tem_vinculo'] = true;

            $formaHist = $this->formaFromHistoricoOs((string) ($lancamento->historico ?? ''));

            if ($formaHist !== null) {
                $out[$osId]['formas'][] = $formaHist;
            }
        }

        foreach ($out as $osId => $fin) {
            $out[$osId]['recebido'] = round(max(0, $fin['recebido']), 2);
            $out[$osId]['formas'] = array_values(array_unique($fin['formas']));
        }

        return $out;
    }

    /**
     * @return array{recebido: float, pendente: float, vencido: float, tem_vinculo: bool, formas: list<string>}
     */
    private function emptyFinance(): array
    {
        return [
            'recebido' => 0.0,
            'pendente' => 0.0,
            'vencido' => 0.0,
            'tem_vinculo' => false,
            'formas' => [],
        ];
    }

    /**
     * @param  array<string, int|list<int>>  $index
     */
    private function resolveOsIdForCr(ContaReceber $conta, array $index): ?int
    {
        $empresaId = (int) ($conta->empresa_id ?? 0);
        $clienteId = (int) ($conta->cliente_id ?? 0);
        $docBase = $this->documentoBaseFromDocumento((string) ($conta->documento ?? ''));

        if ($docBase !== null) {
            $key = 'doc|'.$docBase.'|emp|'.$empresaId.'|cli|'.$clienteId;

            if (isset($index[$key]) && is_int($index[$key])) {
                return $index[$key];
            }
        }

        return $this->resolveOsIdFromHistorico((string) ($conta->historico ?? ''), $empresaId, $index);
    }

    /**
     * @param  array<string, int|list<int>>  $index
     */
    private function resolveOsIdForCaixa(CaixaLancamento $lancamento, array $index): ?int
    {
        $empresaId = (int) ($lancamento->empresa_id ?? 0);
        $fromHist = $this->resolveOsIdFromHistorico((string) ($lancamento->historico ?? ''), $empresaId, $index);

        if ($fromHist !== null) {
            return $fromHist;
        }

        $docBase = $this->documentoBaseFromDocumento((string) ($lancamento->documento ?? ''));

        if ($docBase === null) {
            return null;
        }

        $candidates = $index['doc|'.$docBase.'|emp|'.$empresaId] ?? null;

        if (is_array($candidates) && count($candidates) === 1) {
            return (int) $candidates[0];
        }

        return null;
    }

    /**
     * @param  array<string, int|list<int>>  $index
     */
    private function resolveOsIdFromHistorico(string $historico, int $empresaId, array $index): ?int
    {
        $historico = trim($historico);

        if ($historico === '') {
            return null;
        }

        if (preg_match('/^(?:ESTORNO\s+)?OS\s+(\S+)/iu', $historico, $m)) {
            $token = trim((string) $m[1]);
            $key = 'hist|'.$token.'|emp|'.$empresaId;

            if (isset($index[$key]) && is_int($index[$key])) {
                return $index[$key];
            }

            $digits = preg_replace('/\D/', '', $token) ?? '';

            if ($digits !== '') {
                $keyDigits = 'hist|'.$digits.'|emp|'.$empresaId;

                if (isset($index[$keyDigits]) && is_int($index[$keyDigits])) {
                    return $index[$keyDigits];
                }
            }
        }

        return null;
    }

    private function isCaixaDeBaixaCr(string $historico): bool
    {
        $h = mb_strtolower(trim($historico), 'UTF-8');

        return str_starts_with($h, 'recebimento conta a receber')
            || str_starts_with($h, 'estorno recebimento');
    }

    private function documentoBaseFromNumero(string $numero): ?string
    {
        $digits = preg_replace('/\D/', '', $numero) ?? '';

        return $digits !== '' ? 'OS-'.$digits : null;
    }

    private function documentoBaseFromDocumento(string $documento): ?string
    {
        $documento = trim($documento);

        if ($documento === '') {
            return null;
        }

        if (preg_match('/^(OS-\d+)(?:-\d+)?$/i', $documento, $m)) {
            return 'OS-'.(preg_replace('/\D/', '', substr($m[1], 3)) ?? '');
        }

        return null;
    }

    private function formaFromHistoricoOs(string $historico): ?string
    {
        if (! preg_match('/\(([^)]+)\)/', $historico, $m)) {
            return null;
        }

        $label = mb_strtoupper(trim((string) $m[1]), 'UTF-8');

        return match (true) {
            str_contains($label, 'PIX') => ContaReceber::FORMA_PIX,
            str_contains($label, 'BOLETO') => ContaReceber::FORMA_BOLETO,
            str_contains($label, 'CHEQUE') || str_contains($label, 'CHQ') => ContaReceber::FORMA_CHEQUE,
            str_contains($label, 'CART') || str_contains($label, 'TEF') || str_contains($label, 'POS') => ContaReceber::FORMA_CARTAO,
            str_contains($label, 'CREDI') || str_contains($label, 'PRAZO') || str_contains($label, 'CARTEIRA') => ContaReceber::FORMA_CARTEIRA,
            str_contains($label, 'DEP') => 'deposito',
            str_contains($label, 'DINHEIRO') || str_contains($label, 'ESPÉCIE') || str_contains($label, 'ESPECIE') => 'dinheiro',
            default => null,
        };
    }

    /**
     * @param  array{recebido: float, pendente: float, vencido: float, tem_vinculo: bool, formas: list<string>}  $fin
     */
    private function osTemForma(OrdemServico $ordem, array $fin, string $forma): bool
    {
        $forma = mb_strtolower(trim($forma), 'UTF-8');

        if (in_array($forma, $fin['formas'], true)) {
            return true;
        }

        $snapshot = $ordem->faturamento_pagamentos;

        if (! is_array($snapshot)) {
            return false;
        }

        foreach ($snapshot as $item) {
            if (! is_array($item)) {
                continue;
            }

            $desc = mb_strtoupper(trim((string) ($item['forma'] ?? $item['descricao'] ?? '')), 'UTF-8');
            $mapped = $this->formaFromHistoricoOs('OS X ('.$desc.')');

            if ($mapped === $forma) {
                return true;
            }

            if (mb_strtolower($desc, 'UTF-8') === $forma) {
                return true;
            }
        }

        return false;
    }

    private function situacaoFinanceira(
        float $faturado,
        float $recebido,
        float $pendente,
        float $vencido,
        bool $temVinculo,
    ): string {
        if (! $temVinculo) {
            return 'sem_vinculo';
        }

        if ($vencido > self::EPS) {
            return 'vencido';
        }

        if ($pendente <= self::EPS && $recebido + self::EPS >= $faturado) {
            return 'quitada';
        }

        if ($recebido > self::EPS && $pendente > self::EPS) {
            return 'parcial';
        }

        if ($pendente > self::EPS && $recebido <= self::EPS) {
            return 'em_aberto';
        }

        if ($pendente <= self::EPS && $recebido > self::EPS) {
            return 'quitada';
        }

        return 'em_aberto';
    }

    private function situacaoFinanceiraLabel(string $codigo): string
    {
        return match ($codigo) {
            'quitada' => 'QUITADA',
            'parcial' => 'PARCIAL',
            'em_aberto' => 'EM ABERTO',
            'vencido' => 'VENCIDO',
            'sem_vinculo' => 'SEM VÍNCULO',
            'todos' => 'TODAS',
            default => mb_strtoupper($codigo, 'UTF-8'),
        };
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, string>
     */
    private function totalsFromAggregate(
        array $columns,
        float $faturado,
        float $recebido,
        float $pendente,
        float $vencido,
        int $qtdOs,
    ): array {
        $totals = [];
        $labelPlaced = false;

        foreach ($columns as $column) {
            if ($column === 'faturado') {
                $totals[$column] = static::formatMoney($faturado);

                continue;
            }

            if ($column === 'recebido') {
                $totals[$column] = static::formatMoney($recebido);

                continue;
            }

            if ($column === 'pendente') {
                $totals[$column] = static::formatMoney($pendente);

                continue;
            }

            if ($column === 'vencido') {
                $totals[$column] = static::formatMoney($vencido);

                continue;
            }

            if (! $labelPlaced) {
                $totals[$column] = 'TOTAL ('.$qtdOs.' OS)';
                $labelPlaced = true;

                continue;
            }

            $totals[$column] = '';
        }

        return $totals;
    }

    /**
     * @return array<string, string>
     */
    private function formaOptions(): array
    {
        $options = ['todos' => 'Todas'];

        foreach (ContaReceber::formaLabels() as $key => $label) {
            $options[(string) $key] = $label;
        }

        return $options;
    }
}
