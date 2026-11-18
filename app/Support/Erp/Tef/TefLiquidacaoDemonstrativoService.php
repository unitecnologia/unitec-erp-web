<?php

namespace App\Support\Erp\Tef;

use App\Models\TefIntencao;
use App\Models\TefLiquidacaoLote;
use App\Models\TefLiquidacaoLoteExtrato;
use App\Models\TefLiquidacaoLoteItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C5D — Demonstrativo de liquidações TEF (somente consulta).
 * Não cria caixa/CR/baixas; não altera SuperTEF/Scope; sem auto-conciliação.
 *
 * Provedor: nesta fase exibe apenas SuperTEF (lotes sem coluna provedor —
 * isolamento via intenções; Scope nunca misturado).
 */
final class TefLiquidacaoDemonstrativoService
{
    public const PER_PAGE_DEFAULT = 25;

    public const PER_PAGE_MAX = 100;

    public const DETALHE_ITENS_MAX = 100;

    /**
     * @param  array{
     *   periodo_de?: string|null,
     *   periodo_ate?: string|null,
     *   terminal_id?: int|null,
     *   situacao?: string|null,
     *   page?: int,
     *   per_page?: int
     * }  $filtros
     * @return array{
     *   rows: list<array<string, mixed>>,
     *   totais: array<string, mixed>,
     *   meta: array<string, mixed>,
     *   page: int,
     *   per_page: int,
     *   total: int,
     *   last_page: int
     * }
     */
    public function consultar(int $empresaId, array $filtros = []): array
    {
        if ($empresaId <= 0) {
            return $this->emptyResult($filtros);
        }

        if (! Schema::hasTable('tef_liquidacao_lotes')) {
            return $this->emptyResult($filtros);
        }

        $page = max(1, (int) ($filtros['page'] ?? 1));
        $perPage = min(self::PER_PAGE_MAX, max(1, (int) ($filtros['per_page'] ?? self::PER_PAGE_DEFAULT)));

        $base = $this->baseQuery($empresaId, $filtros);

        $totais = $this->agregarTotais(clone $base);

        $listQuery = (clone $base)
            ->with([
                'terminal:id,nome,numero_logico_terminal',
                'caixaConta:id,codigo,nome',
            ])
            ->withCount('itens');
        $listQuery = $this->withOfxSums($listQuery);

        $paginator = $listQuery
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $tiposPorLote = $this->mapTiposPorLote(
            $paginator->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all()
        );

        $rows = [];
        foreach ($paginator as $lote) {
            /** @var TefLiquidacaoLote $lote */
            $rows[] = $this->mapRow($lote, $tiposPorLote[(int) $lote->id] ?? []);
        }

        return [
            'rows' => $rows,
            'totais' => $totais,
            'meta' => [
                'provedor' => TefProviderId::SUPERTEF,
                'provedor_label' => TefProviderId::label(TefProviderId::SUPERTEF),
                'empresa_id' => $empresaId,
                'contabilizacao' => 'controle',
                'aviso' => 'Somente controle — sem lançamento no Livro Caixa, CR ou DRE.',
            ],
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    /**
     * Detalhe sob demanda (itens do lote) — sem N+1 na grade.
     *
     * @return array{lote: array<string, mixed>|null, itens: list<array<string, mixed>>}
     */
    public function detalhe(int $empresaId, int $loteId): array
    {
        if ($empresaId <= 0 || $loteId <= 0 || ! Schema::hasTable('tef_liquidacao_lotes')) {
            return ['lote' => null, 'itens' => []];
        }

        $loteQuery = $this->baseQuery($empresaId, ['situacao' => 'todos'])
            ->whereKey($loteId)
            ->with(['terminal:id,nome', 'caixaConta:id,nome'])
            ->withCount('itens');
        $lote = $this->withOfxSums($loteQuery)->first();

        if (! $lote) {
            return ['lote' => null, 'itens' => []];
        }

        $tipos = $this->mapTiposPorLote([(int) $lote->id]);
        $row = $this->mapRow($lote, $tipos[(int) $lote->id] ?? []);

        $itens = TefLiquidacaoLoteItem::query()
            ->where('lote_id', (int) $lote->id)
            ->orderBy('id')
            ->limit(self::DETALHE_ITENS_MAX)
            ->get([
                'id', 'tef_intencao_id', 'valor_bruto', 'valor_taxa', 'valor_liquido',
                'nsu', 'bandeira', 'transaction_type', 'installment_count',
                'parcela_numero', 'pago_em',
            ]);

        $intencaoIds = $itens->pluck('tef_intencao_id')->map(fn ($id) => (int) $id)->all();
        $statusMap = [];
        if ($intencaoIds !== [] && Schema::hasTable('tef_intencoes')) {
            $statusMap = TefIntencao::query()
                ->whereIn('id', $intencaoIds)
                ->pluck('status', 'id')
                ->all();
        }

        $itensOut = [];
        foreach ($itens as $item) {
            $status = (string) ($statusMap[(int) $item->tef_intencao_id] ?? '');
            $itensOut[] = [
                'id' => (int) $item->id,
                'intencao_id' => (int) $item->tef_intencao_id,
                'tipo' => $this->labelTipo((string) ($item->transaction_type ?? '')),
                'bruto' => TefMoney::normalize($item->valor_bruto),
                'taxa' => TefMoney::normalize($item->valor_taxa),
                'liquido' => TefMoney::normalize($item->valor_liquido),
                'nsu' => (string) ($item->nsu ?? '—'),
                'bandeira' => (string) ($item->bandeira ?? '—'),
                'parcelas' => (int) ($item->installment_count ?: 1),
                'parcela' => (int) ($item->parcela_numero ?: 1),
                'pago_em' => $item->pago_em?->format('d/m/Y H:i') ?? '—',
                'intencao_status' => $status !== '' ? $status : '—',
                'orfao_ou_cancelada' => in_array($status, [
                    TefIntencao::STATUS_CANCELADA,
                    TefIntencao::STATUS_ESTORNADA,
                    TefIntencao::STATUS_FALHA,
                ], true),
            ];
        }

        return ['lote' => $row, 'itens' => $itensOut];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<TefLiquidacaoLote>
     */
    public function baseQuery(int $empresaId, array $filtros = []): Builder
    {
        $query = TefLiquidacaoLote::query()->where('empresa_id', $empresaId);

        // Isolamento multiprovedor sem coluna no lote: exclui lotes com intenção Scope.
        if (Schema::hasTable('tef_intencoes') && Schema::hasColumn('tef_intencoes', 'provedor')) {
            $query->whereNotExists(function ($sub): void {
                $sub->selectRaw('1')
                    ->from('tef_liquidacao_lote_itens as i')
                    ->join('tef_intencoes as ti', 'ti.id', '=', 'i.tef_intencao_id')
                    ->whereColumn('i.lote_id', 'tef_liquidacao_lotes.id')
                    ->where('ti.provedor', TefProviderId::SCOPE);
            });
        }

        $situacao = (string) ($filtros['situacao'] ?? 'ativos');
        $query = match ($situacao) {
            'cancelados' => $query->where('situacao', TefLiquidacaoLote::SIT_CANCELADO),
            'conferidos' => $query->where('situacao', TefLiquidacaoLote::SIT_CONFERIDO),
            'aguardando' => $query->where('situacao', TefLiquidacaoLote::SIT_AGUARDANDO),
            'todos' => $query,
            // Liquidações normais: sem cancelados (órfãos/cancelados fora do padrão).
            default => $query->whereIn('situacao', [
                TefLiquidacaoLote::SIT_PREPARACAO,
                TefLiquidacaoLote::SIT_AGUARDANDO,
                TefLiquidacaoLote::SIT_CONFERIDO,
            ]),
        };

        if (! empty($filtros['periodo_de'])) {
            $query->whereDate('periodo_ate', '>=', $filtros['periodo_de']);
        }
        if (! empty($filtros['periodo_ate'])) {
            $query->whereDate('periodo_de', '<=', $filtros['periodo_ate']);
        }

        $terminalId = (int) ($filtros['terminal_id'] ?? 0);
        if ($terminalId > 0) {
            $query->where('terminal_id', $terminalId);
        }

        return $query;
    }

    /**
     * @param  Builder<TefLiquidacaoLote>  $base
     * @return array<string, mixed>
     */
    private function agregarTotais(Builder $base): array
    {
        $agg = (clone $base)
            ->toBase()
            ->selectRaw('COUNT(*) as qtd_lotes')
            ->selectRaw('COALESCE(SUM(valor_bruto), 0) as bruto')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN taxa_origem = ? THEN valor_taxas ELSE 0 END), 0) as taxas_informadas',
                [TefLiquidacaoLote::TAXA_INFORMADA]
            )
            ->selectRaw(
                'SUM(CASE WHEN taxa_origem = ? THEN 1 ELSE 0 END) as lotes_taxa_nao_informada',
                [TefLiquidacaoLote::TAXA_NAO_INFORMADA]
            )
            ->selectRaw('COALESCE(SUM(valor_liquido), 0) as liquido')
            ->first();

        $loteIdsSub = (clone $base)->select('tef_liquidacao_lotes.id');

        $ofx = ['vinculado' => '0.00', 'diferencas' => '0.00'];
        if (Schema::hasTable('tef_liquidacao_lote_extratos')) {
            $ofxRow = DB::table('tef_liquidacao_lote_extratos')
                ->where('status', TefLiquidacaoLoteExtrato::STATUS_ATIVO)
                ->whereIn('lote_id', $loteIdsSub)
                ->selectRaw('COALESCE(SUM(valor_vinculado), 0) as vinculado')
                ->selectRaw('COALESCE(SUM(CASE WHEN diferenca > 0 THEN diferenca ELSE 0 END), 0) as diferencas')
                ->first();
            if ($ofxRow) {
                $ofx['vinculado'] = TefMoney::normalize($ofxRow->vinculado);
                $ofx['diferencas'] = TefMoney::normalize($ofxRow->diferencas);
            }
        }

        $bruto = TefMoney::normalize($agg->bruto ?? 0);
        $taxas = TefMoney::normalize($agg->taxas_informadas ?? 0);
        $liquido = TefMoney::normalize($agg->liquido ?? 0);
        $vinculado = $ofx['vinculado'];
        $pendente = TefMoney::sub($liquido, $vinculado);
        if (TefMoney::cmp($pendente, '0') === -1) {
            $pendente = '0.00';
        }

        return [
            'qtd_lotes' => (int) ($agg->qtd_lotes ?? 0),
            'bruto' => $bruto,
            'taxas_informadas' => $taxas,
            'lotes_taxa_nao_informada' => (int) ($agg->lotes_taxa_nao_informada ?? 0),
            'liquido_previsto' => $liquido,
            'vinculado_ofx' => $vinculado,
            'diferencas_justificadas' => $ofx['diferencas'],
            'pendente_ofx' => $pendente,
            'contabilizado' => '0.00',
        ];
    }

    /**
     * @param  list<int>  $loteIds
     * @return array<int, list<string>>
     */
    private function mapTiposPorLote(array $loteIds): array
    {
        if ($loteIds === [] || ! Schema::hasTable('tef_liquidacao_lote_itens')) {
            return [];
        }

        $rows = DB::table('tef_liquidacao_lote_itens')
            ->whereIn('lote_id', $loteIds)
            ->select('lote_id', 'transaction_type')
            ->distinct()
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->lote_id][] = (string) ($row->transaction_type ?? '');
        }

        return $map;
    }

    /**
     * @param  list<string>  $tipos
     * @return array<string, mixed>
     */
    private function mapRow(TefLiquidacaoLote $lote, array $tipos): array
    {
        $bruto = TefMoney::normalize($lote->valor_bruto);
        $liquido = TefMoney::normalize($lote->valor_liquido);
        $taxaInformada = (string) $lote->taxa_origem === TefLiquidacaoLote::TAXA_INFORMADA;
        $taxas = $taxaInformada ? TefMoney::normalize($lote->valor_taxas) : null;
        $vinculado = TefMoney::normalize($lote->ofx_vinculado ?? 0);
        $diferenca = TefMoney::normalize($lote->ofx_diferenca ?? 0);
        $pendente = TefMoney::sub($liquido, $vinculado);
        if (TefMoney::cmp($pendente, '0') === -1) {
            $pendente = '0.00';
        }

        $de = $lote->periodo_de?->format('d/m/Y') ?? '—';
        $ate = $lote->periodo_ate?->format('d/m/Y') ?? '—';

        return [
            'id' => (int) $lote->id,
            'empresa_id' => (int) $lote->empresa_id,
            'provedor' => TefProviderId::SUPERTEF,
            'provedor_label' => TefProviderId::label(TefProviderId::SUPERTEF),
            'terminal' => (string) ($lote->terminal?->nome ?? '—'),
            'adquirente' => filled($lote->acquirer_cnpj) ? (string) $lote->acquirer_cnpj : 'NÃO IDENT.',
            'conta_banco' => (string) ($lote->caixaConta?->nome ?? '—'),
            'periodo' => $de.' a '.$ate,
            'tipo' => $this->labelTiposLote($tipos),
            'qtd_transacoes' => (int) ($lote->itens_count ?? 0),
            'bruto' => $bruto,
            'taxas' => $taxas,
            'taxas_label' => $taxaInformada
                ? number_format((float) $taxas, 2, ',', '.')
                : 'não informada',
            'taxa_origem' => (string) $lote->taxa_origem,
            'liquido_previsto' => $liquido,
            'vinculado_ofx' => $vinculado,
            'pendente_ofx' => $pendente,
            'diferencas_justificadas' => $diferenca,
            'situacao' => (string) $lote->situacao,
            'situacao_label' => TefLiquidacaoLote::situacaoLabel((string) $lote->situacao),
            'ofx_situacao' => (string) ($lote->ofx_situacao ?: TefLiquidacaoLote::OFX_PENDENTE),
            'ofx_situacao_label' => TefLiquidacaoLote::ofxSituacaoLabel(
                (string) ($lote->ofx_situacao ?: TefLiquidacaoLote::OFX_PENDENTE)
            ),
            'contabilizacao' => 'não contabilizado',
        ];
    }

    /**
     * @param  list<string>  $tipos
     */
    private function labelTiposLote(array $tipos): string
    {
        $norm = [];
        foreach ($tipos as $t) {
            $norm[$this->labelTipo($t)] = true;
        }
        unset($norm['—']);
        $keys = array_keys($norm);
        if ($keys === []) {
            return '—';
        }
        if (count($keys) === 1) {
            return $keys[0];
        }

        return 'Misto';
    }

    private function labelTipo(string $transactionType): string
    {
        return match (trim($transactionType)) {
            '2' => 'Crédito',
            '1' => 'Débito',
            default => $transactionType !== '' ? 'Tipo '.$transactionType : '—',
        };
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    /**
     * @return \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>
     */
    private function subSumVinculado(): Builder
    {
        return TefLiquidacaoLoteExtrato::query()
            ->selectRaw('COALESCE(SUM(valor_vinculado), 0)')
            ->whereColumn('lote_id', 'tef_liquidacao_lotes.id')
            ->where('status', TefLiquidacaoLoteExtrato::STATUS_ATIVO);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>
     */
    private function subSumDiferenca(): Builder
    {
        return TefLiquidacaoLoteExtrato::query()
            ->selectRaw('COALESCE(SUM(CASE WHEN diferenca > 0 THEN diferenca ELSE 0 END), 0)')
            ->whereColumn('lote_id', 'tef_liquidacao_lotes.id')
            ->where('status', TefLiquidacaoLoteExtrato::STATUS_ATIVO);
    }

    /**
     * @param  Builder<TefLiquidacaoLote>  $query
     * @return Builder<TefLiquidacaoLote>
     */
    private function withOfxSums(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select('tef_liquidacao_lotes.*');
        }

        if (! Schema::hasTable('tef_liquidacao_lote_extratos')) {
            return $query
                ->selectRaw('0 as ofx_vinculado')
                ->selectRaw('0 as ofx_diferenca');
        }

        return $query->addSelect([
            'ofx_vinculado' => $this->subSumVinculado(),
            'ofx_diferenca' => $this->subSumDiferenca(),
        ]);
    }

    private function emptyResult(array $filtros): array
    {
        $perPage = min(self::PER_PAGE_MAX, max(1, (int) ($filtros['per_page'] ?? self::PER_PAGE_DEFAULT)));

        return [
            'rows' => [],
            'totais' => [
                'qtd_lotes' => 0,
                'bruto' => '0.00',
                'taxas_informadas' => '0.00',
                'lotes_taxa_nao_informada' => 0,
                'liquido_previsto' => '0.00',
                'vinculado_ofx' => '0.00',
                'diferencas_justificadas' => '0.00',
                'pendente_ofx' => '0.00',
                'contabilizado' => '0.00',
            ],
            'meta' => [
                'provedor' => TefProviderId::SUPERTEF,
                'provedor_label' => TefProviderId::label(TefProviderId::SUPERTEF),
                'empresa_id' => 0,
                'contabilizacao' => 'controle',
                'aviso' => 'Somente controle — sem lançamento no Livro Caixa, CR ou DRE.',
            ],
            'page' => 1,
            'per_page' => $perPage,
            'total' => 0,
            'last_page' => 1,
        ];
    }
}
