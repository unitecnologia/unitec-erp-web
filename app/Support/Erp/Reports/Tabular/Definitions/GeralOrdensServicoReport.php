<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\OrdemServico;
use App\Models\Vendedor;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Reports\ReportEmpresaScope;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class GeralOrdensServicoReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'geral-ordens-servico';
    }

    public function title(): string
    {
        return 'GERAL DE ORDENS DE SERVIÇO';
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
            'equipamento' => 'EQUIP./VEÍC.',
            'tecnico' => 'TÉCNICO',
            'situacao' => 'SIT.',
            'servicos' => 'SERV.',
            'pecas' => 'PEÇAS',
            'total' => 'TOTAL',
        ];
    }

    public function columnWidthWeights(): array
    {
        return [
            'numero' => 3,
            'data' => 6,
            'empresa' => 7,
            'cliente' => 20,
            'equipamento' => 28,
            'tecnico' => 8,
            'situacao' => 6,
            'servicos' => 7,
            'pecas' => 7,
            'total' => 7,
        ];
    }

    public function nowrapColumns(): array
    {
        return ['numero', 'data', 'situacao', 'equipamento'];
    }

    public function defaultColumns(): array
    {
        return [
            'numero',
            'data',
            'cliente',
            'equipamento',
            'tecnico',
            'situacao',
            'servicos',
            'pecas',
            'total',
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
                'key' => 'situacao',
                'label' => 'Situação',
                'type' => 'select',
                'options' => [
                    'todos' => 'Todos',
                    'aberta' => 'Abertas',
                    'finalizada' => 'Finalizadas',
                    'cancelada' => 'Canceladas',
                ],
            ],
            [
                'key' => 'cliente',
                'label' => 'Cliente',
                'type' => 'text',
            ],
            [
                'key' => 'tecnico',
                'label' => 'Técnico',
                'type' => 'select',
                'options' => $this->tecnicoOptions(),
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
        $situacao = (string) $request->query('situacao', 'todos');
        $clienteQ = trim((string) $request->query('cliente', ''));
        $tecnicoId = (int) $request->query('tecnico', 0);
        $multi = $this->isMultiEmpresaScope($request);

        $query = OrdemServico::query()
            ->select([
                'id',
                'empresa_id',
                'numero',
                'situacao',
                'data_inicio',
                'cliente_id',
                'atendente_id',
                'nome',
                'descricao',
                'modelo',
                'marca',
                'placa',
                'placa_veiculo',
                'modelo_veiculo',
                'marca_veiculo',
                'total_servicos',
                'total_produtos',
                'total_geral',
            ])
            ->with([
                'cliente:id,nome_razao',
                'atendente:id,codigo,nome',
            ])
            ->whereDate('data_inicio', '>=', $de->toDateString())
            ->whereDate('data_inicio', '<=', $ate->toDateString())
            ->orderBy('data_inicio')
            ->orderBy('numero')
            ->orderBy('id');

        ReportEmpresaScope::applyToQuery($query, $request, 'empresa_id');

        $this->applySituacaoFilter($query, $situacao);

        if ($clienteQ !== '') {
            $like = '%'.$clienteQ.'%';
            $query->where(function (Builder $q) use ($like, $clienteQ): void {
                $q->where('nome', 'like', $like)
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

        if ($tecnicoId > 0) {
            $query->where('atendente_id', $tecnicoId);
        }

        $labels = $multi ? ReportEmpresaScope::labelsById() : [];

        // Totais vêm só do cabeçalho da OS (não da venda espelho / plataforma=os).
        $rows = $query->limit(5000)->get()->map(function (OrdemServico $ordem) use ($multi, $labels): array {
            $row = [
                'numero' => (string) ($ordem->numero ?? ''),
                'data' => static::formatDate($ordem->data_inicio),
                'cliente' => $ordem->clienteNome(),
                'equipamento' => $this->formatEquipamento($ordem),
                'tecnico' => $this->formatTecnico($ordem->atendente),
                'situacao' => mb_strtoupper($ordem->situacaoLabel(), 'UTF-8'),
                'servicos' => static::formatMoney((float) ($ordem->total_servicos ?? 0)),
                'pecas' => static::formatMoney((float) ($ordem->total_produtos ?? 0)),
                'total' => static::formatMoney((float) ($ordem->total_geral ?? 0)),
            ];

            if ($multi) {
                $row['empresa'] = $labels[(int) ($ordem->empresa_id ?? 0)]
                    ?? ReportEmpresaScope::labelEmpresa(null);
            }

            return $row;
        })->all();

        $situacaoLabel = match ($situacao) {
            'aberta' => 'ABERTAS',
            'finalizada' => 'FINALIZADAS',
            'cancelada' => 'CANCELADAS',
            default => 'TODOS',
        };

        $tecnicoLabel = 'TODOS';
        if ($tecnicoId > 0) {
            $tecnico = Vendedor::query()->select(['id', 'codigo', 'nome'])->find($tecnicoId);
            $tecnicoLabel = $this->formatTecnico($tecnico) ?: (string) $tecnicoId;
        }

        return $this->result(
            $this->withEmpresaFilterValue([
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'situacao' => $situacao,
                'cliente' => $clienteQ,
                'tecnico' => $tecnicoId > 0 ? (string) $tecnicoId : 'todos',
                'cols' => $columns,
            ], $request),
            $columns,
            $rows,
            $this->withEmpresaSummary([
                'PERÍODO (DATA INÍCIO): '.$this->periodLabel($de, $ate),
                'SITUAÇÃO: '.$situacaoLabel,
                'TÉCNICO: '.mb_strtoupper($tecnicoLabel, 'UTF-8'),
                'REGISTROS: '.count($rows).(count($rows) >= 5000 ? ' (limite)' : ''),
            ], $request),
        );
    }

    /**
     * @param  Builder<\App\Models\OrdemServico>  $query
     */
    private function applySituacaoFilter(Builder $query, string $situacao): void
    {
        if ($situacao === OrdemServico::SITUACAO_ABERTA) {
            $query->whereIn('situacao', [
                OrdemServico::SITUACAO_ABERTA,
                OrdemServico::SITUACAO_ANDAMENTO,
            ]);

            return;
        }

        if ($situacao === OrdemServico::SITUACAO_FINALIZADA) {
            $query->whereIn('situacao', [
                OrdemServico::SITUACAO_FINALIZADA,
                OrdemServico::SITUACAO_ENTREGUE,
            ]);

            return;
        }

        if ($situacao === OrdemServico::SITUACAO_CANCELADA) {
            $query->where('situacao', OrdemServico::SITUACAO_CANCELADA);
        }
    }

    private function formatEquipamento(OrdemServico $ordem): string
    {
        $marca = trim((string) ($ordem->descricao ?: $ordem->marca ?: $ordem->marca_veiculo ?: ''));
        $modelo = trim((string) ($ordem->modelo ?: $ordem->modelo_veiculo ?: ''));
        $placa = trim((string) ($ordem->placa ?: $ordem->placa_veiculo ?: ''));

        $nome = match (true) {
            $marca !== '' && $modelo !== '' => $marca.' '.$modelo,
            $marca !== '' => $marca,
            $modelo !== '' => $modelo,
            default => '',
        };

        if ($nome !== '' && $placa !== '') {
            return $nome.' / '.$placa;
        }

        return $nome !== '' ? $nome : $placa;
    }

    private function formatTecnico(?Vendedor $tecnico): string
    {
        if ($tecnico === null) {
            return '';
        }

        $codigo = trim((string) ($tecnico->codigo ?? ''));
        $nome = trim((string) ($tecnico->nome ?? ''));

        if ($codigo !== '' && $nome !== '') {
            return $codigo.' - '.$nome;
        }

        return $nome !== '' ? $nome : $codigo;
    }

    /**
     * @return array<string, string>
     */
    private function tecnicoOptions(): array
    {
        $options = ['todos' => 'Todos'];
        $empresaId = ErpContext::currentEmpresaId();

        $query = Vendedor::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->limit(500);

        if ($empresaId) {
            $query->where(function (Builder $q) use ($empresaId): void {
                $q->whereHas('empresas', fn (Builder $e) => $e->where('empresas.id', (int) $empresaId))
                    ->orWhere('empresa_id', (int) $empresaId);
            });
        }

        foreach ($query->get(['id', 'codigo', 'nome']) as $vendedor) {
            $options[(string) $vendedor->id] = $this->formatTecnico($vendedor);
        }

        return $options;
    }
}
