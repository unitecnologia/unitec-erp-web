<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\AjusteEstoque;
use App\Models\EstoqueMovimentacao;
use App\Models\InventarioContagemItem;
use App\Models\Product;
use App\Models\User;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpSchema;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes manuais / inventário / transferência / fabricação / estorno de ajuste.
 * Fonte: estoque_movimentacoes (extrato). Exclui venda, compra e OS.
 *
 * Valorização omitida: o extrato não grava custo no momento do ajuste
 * (preço de cadastro atual não é custo histórico confiável).
 */
class AjustesEstoqueReport extends AbstractTabularReport
{
    private const ROW_LIMIT = 5000;

    private const EPS = 0.0005;

    public function slug(): string
    {
        return 'ajustes-estoque';
    }

    public function title(): string
    {
        return 'AJUSTES DE ESTOQUE';
    }

    public function permission(): string
    {
        return 'produtos.print';
    }

    public function requiresExplicitGenerate(): bool
    {
        return true;
    }

    public function locksCurrentEmpresa(): bool
    {
        return true;
    }

    public function columns(): array
    {
        return [
            'data_hora' => 'DATA/HORA',
            'codigo' => 'CÓDIGO',
            'descricao' => 'PRODUTO',
            'grupo' => 'GRUPO',
            'unidade' => 'UND',
            'qtd_anterior' => 'QTD ANTERIOR',
            'ajuste' => 'AJUSTE',
            'qtd_posterior' => 'QTD POSTERIOR',
            'tipo' => 'TIPO',
            'usuario' => 'USUÁRIO',
            'motivo' => 'MOTIVO',
            'origem' => 'ORIGEM',
        ];
    }

    public function defaultColumns(): array
    {
        return [
            'data_hora',
            'codigo',
            'descricao',
            'qtd_anterior',
            'ajuste',
            'qtd_posterior',
            'tipo',
            'usuario',
            'motivo',
            'origem',
        ];
    }

    public function numericColumns(): array
    {
        // Só o delta entra no rodapé; saldos anteriores/posteriores não são somáveis entre linhas.
        return ['ajuste'];
    }

    public function columnWidthWeights(): array
    {
        return [
            'data_hora' => 11,
            'codigo' => 7,
            'descricao' => 18,
            'grupo' => 8,
            'unidade' => 4,
            'qtd_anterior' => 7,
            'ajuste' => 7,
            'qtd_posterior' => 7,
            'tipo' => 16,
            'usuario' => 8,
            'motivo' => 10,
            'origem' => 9,
        ];
    }

    public function nowrapColumns(): array
    {
        return ['data_hora', 'codigo', 'unidade', 'qtd_anterior', 'ajuste', 'qtd_posterior', 'tipo', 'usuario', 'origem'];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField([
            ...$this->periodFilterFields(),
            [
                'key' => 'q',
                'label' => 'Produto (código/descrição)',
                'type' => 'text',
                'placeholder' => 'Todos',
            ],
            [
                'key' => 'grupo',
                'label' => 'Grupo',
                'type' => 'text',
                'placeholder' => 'Todos',
            ],
            [
                'key' => 'usuario',
                'label' => 'Usuário',
                'type' => 'select',
                'options' => $this->usuarioOptions(),
            ],
            [
                'key' => 'tipo',
                'label' => 'Tipo de ajuste',
                'type' => 'select',
                'options' => $this->tipoOptions(),
                'value' => 'todos',
            ],
        ]);
    }

    public function build(Request $request): array
    {
        $columns = $this->resolveColumns($request->query('cols'));
        [$de, $ate] = $this->periodFromRequest($request);
        $q = trim((string) $request->query('q', ''));
        $grupo = trim((string) $request->query('grupo', ''));
        $usuarioId = (int) $request->query('usuario', 0);
        $tipoFiltro = $this->resolveTipoFiltro($request->query('tipo'));

        if (! ErpSchema::hasTable('estoque_movimentacoes')) {
            return $this->result(
                $this->filtersPayload($request, $de, $ate, $q, $grupo, $usuarioId, $tipoFiltro, $columns),
                $columns,
                [],
                ['Extrato de estoque (estoque_movimentacoes) indisponível nesta instalação.'],
                false,
            );
        }

        $movTable = (new EstoqueMovimentacao)->getTable();
        $prodTable = (new Product)->getTable();
        $usersTable = (new User)->getTable();
        $ajusteTable = (new AjusteEstoque)->getTable();
        $temModo = ErpSchema::hasTable($ajusteTable) && ErpSchema::hasColumn($ajusteTable, 'modo');
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        $query = EstoqueMovimentacao::query()
            ->from("{$movTable} as m")
            ->leftJoin("{$prodTable} as p", 'p.id', '=', 'm.produto_id')
            ->leftJoin("{$usersTable} as u", 'u.id', '=', 'm.usuario_id')
            ->whereBetween('m.data_movimentacao', [
                $de->copy()->startOfDay()->format('Y-m-d H:i:s'),
                $ate->copy()->endOfDay()->format('Y-m-d H:i:s'),
            ])
            ->where(function (Builder $scope): void {
                $this->applyEscopoAjustes($scope);
            });

        $select = [
            'm.id',
            'm.data_movimentacao',
            'm.tipo',
            'm.quantidade',
            'm.saldo_anterior',
            'm.saldo_atual',
            'm.origem_tipo',
            'm.origem_id',
            'm.origem_numero',
            'm.usuario_id',
            'm.observacao',
            'p.codigo',
            'p.descricao',
            'p.grupo',
            'p.unidade',
            'u.name as usuario_nome',
        ];

        if ($temModo) {
            // Modo gravado em ajustes_estoque (não deduzido do delta do extrato).
            $query->leftJoin("{$ajusteTable} as ae", function ($join): void {
                $join->on('ae.id', '=', 'm.origem_id')
                    ->where('m.origem_tipo', '=', 'ajuste_estoque');
            });
            $select[] = 'ae.modo as ajuste_modo';
        }

        $query->select($select)
            ->orderBy('m.data_movimentacao')
            ->orderBy('m.id');

        if ($empresaId > 0 && Schema::hasColumn($movTable, 'empresa_id')) {
            $query->where(function (Builder $inner) use ($empresaId): void {
                $inner->where('m.empresa_id', $empresaId)
                    ->orWhereNull('m.empresa_id');
            });
        }

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('p.codigo', 'like', $like)
                    ->orWhere('p.descricao', 'like', $like);
            });
        }

        if ($grupo !== '') {
            $query->where('p.grupo', 'like', '%'.$grupo.'%');
        }

        if ($usuarioId > 0) {
            $query->where('m.usuario_id', $usuarioId);
        }

        $this->applyTipoFiltro($query, $tipoFiltro);

        $totalCount = (clone $query)->toBase()->getCountForPagination();
        $movs = $query->limit(self::ROW_LIMIT)->get();
        $truncated = $totalCount > $movs->count();

        $appAjusteIds = $this->ajusteIdsComOrigemApp(
            $movs->where('origem_tipo', 'ajuste_estoque')
                ->pluck('origem_id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all()
        );

        $rows = [];
        $totaisPorUnidade = [];

        foreach ($movs as $mov) {
            $qtd = round((float) $mov->quantidade, 3);
            $und = trim((string) ($mov->unidade ?? ''));
            if ($und === '') {
                $und = '—';
            }
            $totaisPorUnidade[$und] = ($totaisPorUnidade[$und] ?? 0.0) + $qtd;

            $rows[] = [
                'data_hora' => $this->formatDateTime($mov->data_movimentacao),
                'codigo' => (string) ($mov->codigo ?? ''),
                'descricao' => (string) ($mov->descricao ?? ''),
                'grupo' => (string) ($mov->grupo ?? ''),
                'unidade' => $und === '—' ? '' : $und,
                'qtd_anterior' => $this->formatSaldo($mov->saldo_anterior),
                'ajuste' => $this->formatAjusteSigned($qtd),
                'qtd_posterior' => $this->formatSaldo($mov->saldo_atual),
                'tipo' => $this->tipoExibicao(
                    (string) ($mov->tipo ?? ''),
                    $temModo ? ($mov->ajuste_modo ?? null) : null,
                ),
                'usuario' => $this->usuarioLabel($mov->usuario_id, $mov->usuario_nome ?? null),
                'motivo' => $this->motivoLabel($mov->observacao ?? null),
                'origem' => $this->origemLabel(
                    (string) ($mov->tipo ?? ''),
                    $mov->origem_tipo !== null ? (string) $mov->origem_tipo : null,
                    $mov->origem_id !== null ? (int) $mov->origem_id : null,
                    $mov->origem_numero !== null ? (string) $mov->origem_numero : null,
                    $appAjusteIds,
                ),
            ];
        }

        $summary = [
            'PERÍODO: '.$this->periodLabel($de, $ate),
            'ESCOPO: ajustes manuais, inventário, transferência, fabricação e estorno de ajuste — sem vendas, compras ou OS.',
            'TIPO: SOMOU/SUBSTITUIU só quando o modo foi gravado no ajuste; histórico sem modo não inventa a operação.',
            'VALORIZAÇÃO: custo histórico no momento do ajuste não é gravado no extrato; coluna de valor omitida (sem estimar pelo custo atual do cadastro).',
        ];

        if ($q !== '') {
            $summary[] = 'PRODUTO: '.mb_strtoupper($q, 'UTF-8');
        }
        if ($grupo !== '') {
            $summary[] = 'GRUPO: '.mb_strtoupper($grupo, 'UTF-8');
        }
        if ($usuarioId > 0) {
            $nome = User::query()->whereKey($usuarioId)->value('name');
            $summary[] = 'USUÁRIO: '.mb_strtoupper((string) ($nome ?: '#'.$usuarioId), 'UTF-8');
        }
        if ($tipoFiltro !== 'todos') {
            $summary[] = 'TIPO: '.mb_strtoupper($this->tipoOptions()[$tipoFiltro] ?? $tipoFiltro, 'UTF-8');
        }

        $summary[] = 'REGISTROS: '.number_format($totalCount, 0, ',', '.').($truncated ? ' (exibindo '.number_format(count($rows), 0, ',', '.').')' : '');

        if ($totaisPorUnidade !== []) {
            ksort($totaisPorUnidade, SORT_STRING);
            $partes = [];
            foreach ($totaisPorUnidade as $und => $soma) {
                $partes[] = ($und === '—' ? 'S/UND' : $und).' = '.$this->formatAjusteSigned(round((float) $soma, 3));
            }
            $summary[] = 'TOTAL AJUSTE POR UNIDADE: '.implode(' | ', $partes);
        }

        $result = $this->result(
            $this->filtersPayload($request, $de, $ate, $q, $grupo, $usuarioId, $tipoFiltro, $columns),
            $columns,
            $rows,
            $summary,
            true,
        );

        // Rodapé: não inventar soma de saldos; só o delta (já em numericColumns).
        if (isset($result['totals']['qtd_anterior'])) {
            $result['totals']['qtd_anterior'] = '';
        }
        if (isset($result['totals']['qtd_posterior'])) {
            $result['totals']['qtd_posterior'] = '';
        }

        return $result;
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, mixed>
     */
    private function filtersPayload(
        Request $request,
        Carbon $de,
        Carbon $ate,
        string $q,
        string $grupo,
        int $usuarioId,
        string $tipoFiltro,
        array $columns,
    ): array {
        return $this->withEmpresaFilterValue([
            'de' => $de->toDateString(),
            'ate' => $ate->toDateString(),
            'q' => $q,
            'grupo' => $grupo,
            'usuario' => $usuarioId > 0 ? (string) $usuarioId : 'todos',
            'tipo' => $tipoFiltro,
            'cols' => $columns,
            'gerar' => '1',
        ], $request);
    }

    private function applyEscopoAjustes(Builder $query): void
    {
        $query->where(function (Builder $inner): void {
            $inner->whereIn('m.tipo', [
                EstoqueMovimentacao::TIPO_AJUSTE,
                EstoqueMovimentacao::TIPO_INVENTARIO,
                EstoqueMovimentacao::TIPO_TRANSFERENCIA,
                EstoqueMovimentacao::TIPO_FABRICACAO,
            ])->orWhere(function (Builder $estorno): void {
                // Estorno só do ajuste manual — cancelamento de venda/compra/OS fica de fora.
                $estorno->where('m.tipo', EstoqueMovimentacao::TIPO_CANCELAMENTO_ESTORNO)
                    ->where('m.origem_tipo', 'ajuste_estoque');
            });
        });
    }

    private function applyTipoFiltro(Builder $query, string $tipoFiltro): void
    {
        match ($tipoFiltro) {
            'entrada' => $query->where('m.quantidade', '>', self::EPS),
            'saida' => $query->where('m.quantidade', '<', -self::EPS),
            'ajuste' => $query->where('m.tipo', EstoqueMovimentacao::TIPO_AJUSTE),
            'inventario' => $query->where('m.tipo', EstoqueMovimentacao::TIPO_INVENTARIO),
            'transferencia' => $query->where('m.tipo', EstoqueMovimentacao::TIPO_TRANSFERENCIA),
            'fabricacao' => $query->where('m.tipo', EstoqueMovimentacao::TIPO_FABRICACAO),
            'estorno' => $query->where('m.tipo', EstoqueMovimentacao::TIPO_CANCELAMENTO_ESTORNO)
                ->where('m.origem_tipo', 'ajuste_estoque'),
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    private function tipoOptions(): array
    {
        return [
            'todos' => 'Todos',
            'entrada' => 'Entrada (+)',
            'saida' => 'Saída (−)',
            'ajuste' => 'Ajuste manual',
            'inventario' => 'Inventário',
            'transferencia' => 'Transferência',
            'fabricacao' => 'Fabricação',
            'estorno' => 'Estorno de ajuste',
        ];
    }

    private function resolveTipoFiltro(mixed $raw): string
    {
        $value = trim((string) ($raw ?? 'todos'));
        $allowed = array_keys($this->tipoOptions());

        return in_array($value, $allowed, true) ? $value : 'todos';
    }

    /**
     * @return array<string, string>
     */
    private function usuarioOptions(): array
    {
        $options = ['todos' => 'Todos'];

        if (! ErpSchema::hasTable('estoque_movimentacoes') || ! Schema::hasTable('users')) {
            return $options;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $idsQuery = DB::table('estoque_movimentacoes as m')
            ->whereNotNull('m.usuario_id')
            ->where(function ($scope): void {
                $scope->whereIn('m.tipo', [
                    EstoqueMovimentacao::TIPO_AJUSTE,
                    EstoqueMovimentacao::TIPO_INVENTARIO,
                    EstoqueMovimentacao::TIPO_TRANSFERENCIA,
                    EstoqueMovimentacao::TIPO_FABRICACAO,
                ])->orWhere(function ($estorno): void {
                    $estorno->where('m.tipo', EstoqueMovimentacao::TIPO_CANCELAMENTO_ESTORNO)
                        ->where('m.origem_tipo', 'ajuste_estoque');
                });
            })
            ->when($empresaId > 0, function ($q) use ($empresaId): void {
                $q->where(function ($inner) use ($empresaId): void {
                    $inner->where('m.empresa_id', $empresaId)->orWhereNull('m.empresa_id');
                });
            })
            ->distinct()
            ->orderBy('m.usuario_id')
            ->limit(200)
            ->pluck('m.usuario_id')
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($idsQuery === []) {
            return $options;
        }

        User::query()
            ->whereIn('id', $idsQuery)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->each(function (User $u) use (&$options): void {
                $options[(string) $u->id] = (string) ($u->name ?: ('#'.$u->id));
            });

        return $options;
    }

    /**
     * @param  list<int>  $ajusteIds
     * @return array<int, true>
     */
    private function ajusteIdsComOrigemApp(array $ajusteIds): array
    {
        if ($ajusteIds === [] || ! ErpSchema::hasTable((new InventarioContagemItem)->getTable())) {
            return [];
        }

        $ids = InventarioContagemItem::query()
            ->whereIn('ajuste_estoque_id', $ajusteIds)
            ->whereNotNull('ajuste_estoque_id')
            ->pluck('ajuste_estoque_id')
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->all();

        $map = [];
        foreach ($ids as $id) {
            $map[$id] = true;
        }

        return $map;
    }

    /**
     * @param  array<int, true>  $appAjusteIds
     */
    private function origemLabel(
        string $tipo,
        ?string $origemTipo,
        ?int $origemId,
        ?string $origemNumero,
        array $appAjusteIds,
    ): string {
        $doc = EstoqueMovimentacao::documentoLabel($origemTipo, $origemNumero);
        if ($origemTipo === 'ajuste_estoque' && $origemId && isset($appAjusteIds[$origemId])) {
            return $doc !== '—' ? $doc.' (App)' : 'App';
        }

        if ($origemTipo === 'ajuste_estoque') {
            return $doc !== '—' ? $doc.' (ERP)' : 'ERP';
        }

        if ($doc !== '—') {
            return $doc;
        }

        return match ($tipo) {
            EstoqueMovimentacao::TIPO_AJUSTE => 'ERP',
            EstoqueMovimentacao::TIPO_INVENTARIO => 'Inventário',
            EstoqueMovimentacao::TIPO_TRANSFERENCIA => 'Transferência',
            EstoqueMovimentacao::TIPO_FABRICACAO => 'Fabricação',
            EstoqueMovimentacao::TIPO_CANCELAMENTO_ESTORNO => 'Estorno',
            default => '',
        };
    }

    private function usuarioLabel(mixed $usuarioId, mixed $nome): string
    {
        if ($usuarioId === null || (int) $usuarioId <= 0) {
            return '';
        }

        $label = trim((string) ($nome ?? ''));

        return $label !== '' ? $label : '#'.(int) $usuarioId;
    }

    private function motivoLabel(mixed $observacao): string
    {
        $obs = trim((string) ($observacao ?? ''));

        return $obs;
    }

    /**
     * Ajuste manual: inclui SOMOU/SUBSTITUIU só com modo gravado em ajustes_estoque.
     * Não deduz pelo delta do extrato.
     */
    private function tipoExibicao(string $tipo, mixed $modo): string
    {
        if ($tipo === EstoqueMovimentacao::TIPO_AJUSTE) {
            $sufixo = $this->modoSufixoTipo($modo);
            if ($sufixo !== null) {
                return 'AJUSTE MANUAL — '.$sufixo;
            }

            return 'AJUSTE MANUAL';
        }

        if ($tipo === EstoqueMovimentacao::TIPO_CANCELAMENTO_ESTORNO) {
            return 'ESTORNO DE AJUSTE';
        }

        return mb_strtoupper(EstoqueMovimentacao::tipoLabel($tipo), 'UTF-8');
    }

    private function modoSufixoTipo(mixed $modo): ?string
    {
        $norm = mb_strtolower(trim((string) ($modo ?? '')), 'UTF-8');

        return match ($norm) {
            AjusteEstoque::MODO_SOMAR => 'SOMOU',
            AjusteEstoque::MODO_SUBSTITUIR => 'SUBSTITUIU',
            default => null,
        };
    }

    private function formatDateTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->format('d/m/Y H:i:s');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function formatSaldo(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return static::formatQuantity((float) $value);
    }

    private function formatAjusteSigned(float $qtd): string
    {
        $fmt = static::formatQuantity(abs($qtd));
        if ($qtd > self::EPS) {
            return '+'.$fmt;
        }
        if ($qtd < -self::EPS) {
            return '-'.$fmt;
        }

        return $fmt;
    }

    protected function formatTotalColumn(string $column, float $value): string
    {
        if ($column === 'ajuste') {
            return $this->formatAjusteSigned(round($value, 3));
        }

        return parent::formatTotalColumn($column, $value);
    }

    protected function parseNumeric(mixed $value): float
    {
        if (is_string($value) && preg_match('/^[+\-]/', $value) === 1) {
            $normalized = str_replace(['.', ' '], ['', ''], $value);
            $normalized = str_replace(',', '.', $normalized);

            return is_numeric($normalized) ? (float) $normalized : 0.0;
        }

        return parent::parseNumeric($value);
    }
}
