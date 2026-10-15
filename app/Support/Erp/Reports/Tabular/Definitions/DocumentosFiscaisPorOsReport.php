<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\Nfe;
use App\Models\Nfse;
use App\Models\NfseItem;
use App\Models\OrdemServico;
use App\Support\Erp\Reports\ReportEmpresaScope;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Documentos fiscais por OS (NF-e de peças + NFS-e de serviços).
 *
 * Uma linha por OS. Vínculos reais: nfes.ordem_servico_id e
 * nfses.ordem_servico_id / nfse_itens.ordem_servico_id.
 * Valores comerciais vêm do cabeçalho da OS; totais fiscais só de docs autorizados.
 */
class DocumentosFiscaisPorOsReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'documentos-fiscais-por-os';
    }

    public function title(): string
    {
        return 'DOCUMENTOS FISCAIS POR OS (NF-e / NFS-e)';
    }

    public function permission(): string
    {
        return 'ordens_servico.print';
    }

    public function columns(): array
    {
        return [
            'numero' => 'OS',
            'data' => 'DATA',
            'empresa' => 'EMPRESA',
            'cliente' => 'CLIENTE',
            'servicos' => 'SERVIÇOS',
            'pecas' => 'PEÇAS',
            'total' => 'TOTAL',
            'nfe' => 'NF-e',
            'nfe_sit' => 'SIT. NF-e',
            'nfse' => 'NFS-e',
            'nfse_sit' => 'SIT. NFS-e',
            'sit_consol' => 'SIT. OS',
        ];
    }

    public function columnWidthWeights(): array
    {
        return [
            'numero' => 3,
            'data' => 7,
            'empresa' => 8,
            'cliente' => 18,
            'servicos' => 7,
            'pecas' => 7,
            'total' => 7,
            'nfe' => 8,
            'nfe_sit' => 9,
            'nfse' => 7,
            'nfse_sit' => 9,
            'sit_consol' => 8,
        ];
    }

    public function nowrapColumns(): array
    {
        return ['numero', 'data', 'servicos', 'pecas', 'total', 'nfe', 'nfe_sit', 'nfse', 'nfse_sit', 'sit_consol'];
    }

    public function defaultColumns(): array
    {
        return [
            'numero',
            'data',
            'cliente',
            'servicos',
            'pecas',
            'total',
            'nfe',
            'nfe_sit',
            'nfse',
            'nfse_sit',
            'sit_consol',
        ];
    }

    public function numericColumns(): array
    {
        return ['servicos', 'pecas', 'total'];
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
                'key' => 'tipo_doc',
                'label' => 'Tipo de documento',
                'type' => 'select',
                'options' => [
                    'todos' => 'Todos',
                    'nfe' => 'Com NF-e',
                    'nfse' => 'Com NFS-e',
                    'ambos' => 'NF-e e NFS-e',
                ],
            ],
            [
                'key' => 'sit_fiscal',
                'label' => 'Situação fiscal (OS)',
                'type' => 'select',
                'options' => [
                    'todos' => 'Todas',
                    'autorizado' => 'Autorizado',
                    'pendente' => 'Pendente',
                    'rejeitado' => 'Rejeitado',
                    'cancelado' => 'Cancelado',
                    'sem_nota' => 'Sem nota',
                ],
            ],
            [
                'key' => 'somente_sem_nota',
                'label' => 'Somente sem nota',
                'type' => 'select',
                'options' => [
                    'nao' => 'Não',
                    'sim' => 'Sim',
                ],
            ],
        ]));
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->columnsForEmpresaScope(
            $this->resolveColumns($request->query('cols')),
            $request,
            after: 'data',
        );
        $clienteQ = trim((string) $request->query('cliente', ''));
        $tipoDoc = (string) $request->query('tipo_doc', 'todos');
        $sitFiscal = (string) $request->query('sit_fiscal', 'todos');
        $somenteSemNota = (string) $request->query('somente_sem_nota', 'nao') === 'sim';
        $multi = $this->isMultiEmpresaScope($request);

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
                'ordens_servico.data_termino',
                'ordens_servico.cliente_id',
                'ordens_servico.nome',
                'ordens_servico.total_servicos',
                'ordens_servico.total_produtos',
                'ordens_servico.total_geral',
            ])
            ->orderBy('ordens_servico.data_termino')
            ->orderBy('ordens_servico.numero')
            ->orderBy('ordens_servico.id')
            ->get();

        $docsByOs = $this->loadDocsByOsId(
            $ordens->pluck('id')->map(fn ($id) => (int) $id)->all()
        );
        $labels = $multi ? ReportEmpresaScope::labelsById() : [];

        $rowsBuilt = [];
        $sumServicos = 0.0;
        $sumPecas = 0.0;
        $sumTotal = 0.0;
        $docsAutorizados = 0;
        $valorFiscalAutorizado = 0.0;
        $osSemNota = 0;

        foreach ($ordens as $ordem) {
            $osId = (int) $ordem->id;
            $bundle = $docsByOs[$osId] ?? $this->emptyDocs();
            $sitConsol = $this->situacaoConsolidada($bundle);

            if ($somenteSemNota && $sitConsol !== 'sem_nota') {
                continue;
            }

            if ($sitFiscal !== 'todos' && $sitConsol !== $sitFiscal) {
                continue;
            }

            if (! $this->matchTipoDoc($tipoDoc, $bundle)) {
                continue;
            }

            $servicos = round((float) ($ordem->total_servicos ?? 0), 2);
            $pecas = round((float) ($ordem->total_produtos ?? 0), 2);
            $total = round((float) ($ordem->total_geral ?? 0), 2);

            $sumServicos += $servicos;
            $sumPecas += $pecas;
            $sumTotal += $total;

            if ($sitConsol === 'sem_nota') {
                $osSemNota++;
            }

            $nfeAut = $this->documentoAutorizadoNfe($bundle['nfes']);
            $nfseAut = $this->documentoAutorizadoNfse($bundle['nfses']);

            if ($nfeAut !== null) {
                $docsAutorizados++;
                $valorFiscalAutorizado += round((float) ($nfeAut['total'] ?? 0), 2);
            }

            if ($nfseAut !== null) {
                $docsAutorizados++;
                $valorFiscalAutorizado += round((float) ($nfseAut['total'] ?? 0), 2);
            }

            $row = [
                'numero' => (string) ($ordem->numero ?? ''),
                'data' => static::formatDate($ordem->data_termino),
                'cliente' => $ordem->clienteNome(),
                'servicos' => static::formatMoney($servicos),
                'pecas' => static::formatMoney($pecas),
                'total' => static::formatMoney($total),
                'nfe' => $this->formatDocNumeros($bundle['nfes'], 'nfe'),
                'nfe_sit' => $this->formatDocSituacoes($bundle['nfes'], 'nfe'),
                'nfse' => $this->formatDocNumeros($bundle['nfses'], 'nfse'),
                'nfse_sit' => $this->formatDocSituacoes($bundle['nfses'], 'nfse'),
                'sit_consol' => $this->situacaoConsolidadaLabel($sitConsol),
            ];

            if ($multi) {
                $row['empresa'] = $labels[(int) ($ordem->empresa_id ?? 0)]
                    ?? ReportEmpresaScope::labelEmpresa(null);
            }

            $rowsBuilt[] = $row;
        }

        $qtdOs = count($rowsBuilt);
        $rows = array_slice($rowsBuilt, 0, 5000);
        $limitHint = $qtdOs > 5000
            ? ' (limite de exibição; totais do rodapé incluem todos os filtros)'
            : '';

        $tipoLabel = match ($tipoDoc) {
            'nfe' => 'COM NF-e',
            'nfse' => 'COM NFS-e',
            'ambos' => 'NF-e E NFS-e',
            default => 'TODOS',
        };

        $built = $this->result(
            $this->withEmpresaFilterValue([
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'cliente' => $clienteQ,
                'tipo_doc' => $tipoDoc,
                'sit_fiscal' => $sitFiscal,
                'somente_sem_nota' => $somenteSemNota ? 'sim' : 'nao',
                'cols' => $columns,
            ], $request),
            $columns,
            $rows,
            $this->withEmpresaSummary([
                'PERÍODO (DATA FATURAMENTO): '.$this->periodLabel($de, $ate),
                'TIPO DOC.: '.$tipoLabel,
                'SITUAÇÃO FISCAL: '.$this->situacaoConsolidadaLabel($sitFiscal === 'todos' ? 'todos' : $sitFiscal),
                'SOMENTE SEM NOTA: '.($somenteSemNota ? 'SIM' : 'NÃO'),
                'OS: '.$qtdOs.$limitHint.' | SEM NOTA: '.$osSemNota.' | DOCS AUTORIZADOS: '.$docsAutorizados,
                'VALOR FISCAL AUTORIZADO: '.static::formatMoney($valorFiscalAutorizado).' (NF-e transmitida + NFS-e autorizada; sem canceladas/duplicatas)',
                'COMERCIAIS: cabeçalho da OS | FISCAIS: vínculo ordem_servico_id (rastreia canceladas/reemissões na linha)',
            ], $request),
            withTotals: false,
        );

        $built['totals'] = $this->totalsFromAggregate(
            $columns,
            $sumServicos,
            $sumPecas,
            $sumTotal,
            $qtdOs,
            $docsAutorizados,
            $osSemNota,
            $valorFiscalAutorizado,
        );

        return $built;
    }

    /**
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
     * @param  list<int>  $osIds
     * @return array<int, array{nfes: list<array{id: int, numero: string, status: string, total: float}>, nfses: list<array{id: int, numero: string, status: string, total: float}>}>
     */
    private function loadDocsByOsId(array $osIds): array
    {
        $out = [];

        foreach ($osIds as $osId) {
            $out[$osId] = $this->emptyDocs();
        }

        if ($osIds === []) {
            return $out;
        }

        $nfes = Nfe::query()
            ->whereIn('ordem_servico_id', $osIds)
            ->select(['id', 'ordem_servico_id', 'numero', 'status', 'total'])
            ->orderByDesc('id')
            ->get();

        foreach ($nfes as $nfe) {
            $osId = (int) $nfe->ordem_servico_id;
            $out[$osId]['nfes'][] = [
                'id' => (int) $nfe->id,
                'numero' => $this->formatNfeNumero((string) ($nfe->numero ?? '')),
                'status' => (string) ($nfe->status ?? ''),
                'total' => round((float) ($nfe->total ?? 0), 2),
            ];
        }

        $headerNfses = Nfse::query()
            ->whereIn('ordem_servico_id', $osIds)
            ->select(['id', 'ordem_servico_id', 'numero_nfse', 'status', 'total', 'valor_servicos'])
            ->orderByDesc('id')
            ->get();

        /** @var array<int, array{id: int, numero: string, status: string, total: float}> $nfseById */
        $nfseById = [];

        foreach ($headerNfses as $nfse) {
            $row = [
                'id' => (int) $nfse->id,
                'numero' => trim((string) ($nfse->numero_nfse ?? '')),
                'status' => (string) ($nfse->status ?? ''),
                'total' => round((float) (($nfse->total ?? null) !== null ? $nfse->total : ($nfse->valor_servicos ?? 0)), 2),
            ];
            $nfseById[$row['id']] = $row;
            $out[(int) $nfse->ordem_servico_id]['nfses'][$row['id']] = $row;
        }

        $itemLinks = NfseItem::query()
            ->whereIn('ordem_servico_id', $osIds)
            ->whereNotNull('nfse_id')
            ->select(['ordem_servico_id', 'nfse_id'])
            ->get();

        $missingIds = $itemLinks->pluck('nfse_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn (int $id) => isset($nfseById[$id]))
            ->values()
            ->all();

        if ($missingIds !== []) {
            foreach (Nfse::query()->whereIn('id', $missingIds)->select(['id', 'numero_nfse', 'status', 'total', 'valor_servicos'])->get() as $nfse) {
                $nfseById[(int) $nfse->id] = [
                    'id' => (int) $nfse->id,
                    'numero' => trim((string) ($nfse->numero_nfse ?? '')),
                    'status' => (string) ($nfse->status ?? ''),
                    'total' => round((float) (($nfse->total ?? null) !== null ? $nfse->total : ($nfse->valor_servicos ?? 0)), 2),
                ];
            }
        }

        foreach ($itemLinks as $item) {
            $nfseId = (int) $item->nfse_id;
            $osId = (int) $item->ordem_servico_id;

            if (! isset($nfseById[$nfseId])) {
                continue;
            }

            $out[$osId]['nfses'][$nfseId] = $nfseById[$nfseId];
        }

        foreach ($out as $osId => $bundle) {
            $nfses = array_values($bundle['nfses']);
            usort($nfses, fn (array $a, array $b): int => $this->compareDocs($a, $b, 'nfse'));
            $nfesSorted = $bundle['nfes'];
            usort($nfesSorted, fn (array $a, array $b): int => $this->compareDocs($a, $b, 'nfe'));
            $out[$osId]['nfes'] = $nfesSorted;
            $out[$osId]['nfses'] = $nfses;
        }

        return $out;
    }

    /**
     * @return array{nfes: list<array{id: int, numero: string, status: string, total: float}>, nfses: list<array{id: int, numero: string, status: string, total: float}>}
     */
    private function emptyDocs(): array
    {
        return ['nfes' => [], 'nfses' => []];
    }

    /**
     * @param  array{id: int, numero: string, status: string, total: float}  $a
     * @param  array{id: int, numero: string, status: string, total: float}  $b
     */
    private function compareDocs(array $a, array $b, string $tipo): int
    {
        $sa = $this->statusPriority($a['status'], $tipo);
        $sb = $this->statusPriority($b['status'], $tipo);

        if ($sa !== $sb) {
            return $sa <=> $sb;
        }

        return $b['id'] <=> $a['id'];
    }

    private function statusPriority(string $status, string $tipo): int
    {
        if ($tipo === 'nfe') {
            return match ($status) {
                Nfe::STATUS_TRANSMITIDA => 0,
                Nfe::STATUS_CONTINGENCIA => 1,
                Nfe::STATUS_ABERTA => 2,
                Nfe::STATUS_DUPLICIDADE => 3,
                Nfe::STATUS_DENEGADA => 4,
                Nfe::STATUS_CANCELADA => 5,
                Nfe::STATUS_INUTILIZADA => 6,
                default => 9,
            };
        }

        return match ($status) {
            Nfse::STATUS_AUTORIZADA => 0,
            Nfse::STATUS_TRANSMITIDA => 1,
            Nfse::STATUS_CONTINGENCIA => 2,
            Nfse::STATUS_ABERTA => 3,
            Nfse::STATUS_REJEITADA => 4,
            Nfse::STATUS_CANCELADA => 5,
            Nfse::STATUS_SUBSTITUIDA => 6,
            default => 9,
        };
    }

    /**
     * @param  array{nfes: list<array{id: int, numero: string, status: string, total: float}>, nfses: list<array{id: int, numero: string, status: string, total: float}>}  $bundle
     */
    private function matchTipoDoc(string $tipoDoc, array $bundle): bool
    {
        $hasNfe = $bundle['nfes'] !== [];
        $hasNfse = $bundle['nfses'] !== [];

        return match ($tipoDoc) {
            'nfe' => $hasNfe,
            'nfse' => $hasNfse,
            'ambos' => $hasNfe && $hasNfse,
            default => true,
        };
    }

    /**
     * @param  array{nfes: list<array{id: int, numero: string, status: string, total: float}>, nfses: list<array{id: int, numero: string, status: string, total: float}>}  $bundle
     */
    private function situacaoConsolidada(array $bundle): string
    {
        $hasAutorizado = $this->documentoAutorizadoNfe($bundle['nfes']) !== null
            || $this->documentoAutorizadoNfse($bundle['nfses']) !== null;

        if ($hasAutorizado) {
            return 'autorizado';
        }

        $statuses = array_merge(
            array_column($bundle['nfes'], 'status'),
            array_column($bundle['nfses'], 'status'),
        );

        if ($statuses === []) {
            return 'sem_nota';
        }

        foreach ($statuses as $status) {
            if (in_array($status, [
                Nfe::STATUS_ABERTA,
                Nfe::STATUS_CONTINGENCIA,
                Nfse::STATUS_ABERTA,
                Nfse::STATUS_TRANSMITIDA,
                Nfse::STATUS_CONTINGENCIA,
            ], true)) {
                return 'pendente';
            }
        }

        foreach ($statuses as $status) {
            if (in_array($status, [
                Nfe::STATUS_DENEGADA,
                Nfe::STATUS_DUPLICIDADE,
                Nfse::STATUS_REJEITADA,
            ], true)) {
                return 'rejeitado';
            }
        }

        foreach ($statuses as $status) {
            if (in_array($status, [
                Nfe::STATUS_CANCELADA,
                Nfe::STATUS_INUTILIZADA,
                Nfse::STATUS_CANCELADA,
                Nfse::STATUS_SUBSTITUIDA,
            ], true)) {
                return 'cancelado';
            }
        }

        return 'pendente';
    }

    /**
     * @param  list<array{id: int, numero: string, status: string, total: float}>  $docs
     * @return array{id: int, numero: string, status: string, total: float}|null
     */
    private function documentoAutorizadoNfe(array $docs): ?array
    {
        foreach ($docs as $doc) {
            if ($doc['status'] === Nfe::STATUS_TRANSMITIDA) {
                return $doc;
            }
        }

        return null;
    }

    /**
     * @param  list<array{id: int, numero: string, status: string, total: float}>  $docs
     * @return array{id: int, numero: string, status: string, total: float}|null
     */
    private function documentoAutorizadoNfse(array $docs): ?array
    {
        foreach ($docs as $doc) {
            if ($doc['status'] === Nfse::STATUS_AUTORIZADA) {
                return $doc;
            }
        }

        return null;
    }

    /**
     * @param  list<array{id: int, numero: string, status: string, total: float}>  $docs
     */
    private function formatDocNumeros(array $docs, string $tipo): string
    {
        if ($docs === []) {
            return '—';
        }

        $parts = [];

        foreach ($docs as $doc) {
            $num = trim($doc['numero']);

            if ($num === '') {
                $num = '#'.$doc['id'];
            }

            $parts[] = $num;
        }

        return implode('; ', $parts);
    }

    /**
     * @param  list<array{id: int, numero: string, status: string, total: float}>  $docs
     */
    private function formatDocSituacoes(array $docs, string $tipo): string
    {
        if ($docs === []) {
            return '—';
        }

        $parts = [];

        foreach ($docs as $doc) {
            $parts[] = $this->statusLabel($doc['status'], $tipo);
        }

        return implode('; ', $parts);
    }

    private function statusLabel(string $status, string $tipo): string
    {
        $labels = $tipo === 'nfe' ? Nfe::statusLabels() : Nfse::statusLabels();

        return mb_strtoupper((string) ($labels[$status] ?? $status), 'UTF-8');
    }

    private function formatNfeNumero(string $numero): string
    {
        $n = ltrim(trim($numero), '0');

        return $n !== '' ? $n : trim($numero);
    }

    private function situacaoConsolidadaLabel(string $codigo): string
    {
        return match ($codigo) {
            'autorizado' => 'AUTORIZADO',
            'pendente' => 'PENDENTE',
            'rejeitado' => 'REJEITADO',
            'cancelado' => 'CANCELADO',
            'sem_nota' => 'SEM NOTA',
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
        float $servicos,
        float $pecas,
        float $total,
        int $qtdOs,
        int $docsAutorizados,
        int $osSemNota,
        float $valorFiscalAutorizado,
    ): array {
        $totals = [];
        $labelPlaced = false;
        $fiscalPlaced = false;

        foreach ($columns as $column) {
            if ($column === 'servicos') {
                $totals[$column] = static::formatMoney($servicos);

                continue;
            }

            if ($column === 'pecas') {
                $totals[$column] = static::formatMoney($pecas);

                continue;
            }

            if ($column === 'total') {
                $totals[$column] = static::formatMoney($total);

                continue;
            }

            if ($column === 'sit_consol') {
                $totals[$column] = 'AUT. '.$docsAutorizados.' | SEM '.$osSemNota;

                continue;
            }

            if (in_array($column, ['nfe', 'nfse'], true) && ! $fiscalPlaced) {
                $totals[$column] = 'FISC. '.static::formatMoney($valorFiscalAutorizado);
                $fiscalPlaced = true;

                continue;
            }

            if (! $labelPlaced && ! in_array($column, ['nfe_sit', 'nfse_sit', 'nfe', 'nfse'], true)) {
                $totals[$column] = 'TOTAL ('.$qtdOs.' OS)';
                $labelPlaced = true;

                continue;
            }

            $totals[$column] = '';
        }

        return $totals;
    }
}
