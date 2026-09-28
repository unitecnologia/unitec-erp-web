<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\ForcaVendasVisitaSemVenda;
use App\Models\Person;
use App\Models\Vendedor;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Reports\ReportEmpresaScope;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;

class VisitasRealizadasSemVendaReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'visitas-realizadas-sem-venda';
    }

    public function title(): string
    {
        return 'VISITAS REALIZADAS SEM VENDA';
    }

    public function permission(): string
    {
        return 'vendas.print';
    }

    public function columns(): array
    {
        return [
            'data_hora' => 'DATA/HORA',
            'empresa' => 'EMPRESA',
            'vendedor' => 'VENDEDOR',
            'cliente' => 'CLIENTE',
            'cidade' => 'CIDADE',
            'telefone' => 'TELEFONE',
            'motivo' => 'MOTIVO',
            'local' => 'LOCAL',
        ];
    }

    public function defaultColumns(): array
    {
        return [
            'data_hora',
            'vendedor',
            'cliente',
            'cidade',
            'telefone',
            'motivo',
            'local',
        ];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField($this->withEmpresaFilter([
            ...$this->periodFilterFields(),
            [
                'key' => 'vendedor',
                'label' => 'Vendedor',
                'type' => 'select',
                'options' => $this->vendedorOptions(),
            ],
            [
                'key' => 'cliente',
                'label' => 'Cliente',
                'type' => 'text',
            ],
            [
                'key' => 'motivo',
                'label' => 'Motivo',
                'type' => 'text',
            ],
        ]));
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->columnsForEmpresaScope(
            $this->resolveColumns($request->query('cols')),
            $request,
            after: 'data_hora',
        );
        $multi = $this->isMultiEmpresaScope($request);
        $vendedorId = (int) $request->query('vendedor', 0);
        $clienteQ = trim((string) $request->query('cliente', ''));
        $motivoQ = trim((string) $request->query('motivo', ''));
        $forPrint = $request->boolean('pdf') || $request->boolean('csv');

        $inicio = $de->copy()->startOfDay();
        $fim = $ate->copy()->endOfDay();

        $query = ForcaVendasVisitaSemVenda::query()
            ->with([
                'cliente:id,codigo,nome_razao,cidade_nome,uf,fone1,celular1,whatsapp',
                'vendedor:id,codigo,nome',
            ])
            ->where(function ($q) use ($inicio, $fim): void {
                $q->whereBetween('client_created_at', [$inicio, $fim])
                    ->orWhere(function ($q2) use ($inicio, $fim): void {
                        $q2->whereNull('client_created_at')
                            ->whereBetween('created_at', [$inicio, $fim]);
                    });
            })
            ->orderByDesc('client_created_at')
            ->orderByDesc('id');

        ReportEmpresaScope::applyToQuery($query, $request, 'empresa_id');

        if ($vendedorId > 0) {
            $query->where('vendedor_id', $vendedorId);
        }

        if ($clienteQ !== '') {
            $query->whereHas('cliente', function ($q) use ($clienteQ): void {
                $like = '%'.$clienteQ.'%';
                $q->where(function ($inner) use ($like, $clienteQ): void {
                    $inner->where('nome_razao', 'like', $like)
                        ->orWhere('codigo', 'like', $like);

                    if (ctype_digit($clienteQ)) {
                        $inner->orWhere('id', (int) $clienteQ);
                    }
                });
            });
        }

        if ($motivoQ !== '') {
            $query->where('motivo', 'like', '%'.$motivoQ.'%');
        }

        $labels = $multi ? ReportEmpresaScope::labelsById() : [];

        $visitas = $query->limit(5000)->get();

        $rows = $visitas->map(function (ForcaVendasVisitaSemVenda $visita) use ($multi, $labels, $forPrint): array {
            $quando = $visita->client_created_at ?? $visita->created_at;
            $cliente = $visita->cliente;
            $vendedor = $visita->vendedor;

            $mapped = [
                'data_hora' => $quando ? $quando->format('d/m/Y H:i') : '',
                'vendedor' => $this->formatVendedor($vendedor),
                'cliente' => $this->formatCliente($cliente),
                'cidade' => $this->formatCidade($cliente),
                'telefone' => $this->formatTelefone($cliente),
                'motivo' => (string) ($visita->motivo ?? ''),
                'local' => $this->formatLocal($visita, $forPrint),
            ];

            if ($multi) {
                $mapped['empresa'] = $labels[(int) ($visita->empresa_id ?? 0)]
                    ?? ReportEmpresaScope::labelEmpresa(null);
            }

            return $mapped;
        })->all();

        $count = count($rows);
        $totals = array_fill_keys($columns, '');
        if ($columns !== []) {
            $totals[$columns[0]] = 'Total de visitas sem venda: '.$count;
        }

        $built = $this->result(
            $this->withEmpresaFilterValue([
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'vendedor' => $vendedorId > 0 ? (string) $vendedorId : 'todos',
                'cliente' => $clienteQ,
                'motivo' => $motivoQ,
                'cols' => $columns,
            ], $request),
            $columns,
            $rows,
            $this->withEmpresaSummary([
                'PERÍODO: '.$this->periodLabel($de, $ate),
                'TOTAL DE VISITAS SEM VENDA: '.$count,
            ], $request),
            withTotals: false,
        );

        $built['totals'] = $totals;

        return $built;
    }

    /**
     * @return array<string, string>
     */
    private function vendedorOptions(): array
    {
        $options = ['todos' => 'Todos'];
        $empresaId = ErpContext::currentEmpresaId();

        $query = Vendedor::query()
            ->where('ativo', true)
            ->where('efetua_venda', true)
            ->whereHas('rhFuncionario')
            ->with('rhFuncionario');

        if ($empresaId) {
            $query->whereHas(
                'empresas',
                fn ($q) => $q->where('empresas.id', (int) $empresaId)
            );
        }

        $query
            ->get(['id', 'codigo', 'nome'])
            ->sortBy(fn (Vendedor $vendedor): int => (int) preg_replace('/\D/', '', (string) ($vendedor->rhFuncionario?->codigo ?? '0')))
            ->each(function (Vendedor $vendedor) use (&$options): void {
                $options[(string) $vendedor->id] = $this->formatVendedor($vendedor);
            });

        return $options;
    }

    private function formatVendedor(?Vendedor $vendedor): string
    {
        if ($vendedor === null) {
            return '';
        }

        $rh = $vendedor->relationLoaded('rhFuncionario')
            ? $vendedor->rhFuncionario
            : $vendedor->rhFuncionario()->first();

        $codigo = trim((string) ($rh?->codigo ?? $vendedor->codigo ?? ''));
        $nome = trim((string) ($rh?->nome ?? $vendedor->nome ?? ''));

        if ($codigo !== '' && $nome !== '') {
            return $codigo.' - '.$nome;
        }

        return $nome !== '' ? $nome : $codigo;
    }

    private function formatCliente(?Person $cliente): string
    {
        if ($cliente === null) {
            return '';
        }

        $codigo = trim((string) ($cliente->codigo ?? ''));
        $nome = trim((string) ($cliente->nome_razao ?? ''));

        if ($codigo !== '' && $nome !== '') {
            return $codigo.' - '.$nome;
        }

        return $nome !== '' ? $nome : $codigo;
    }

    private function formatCidade(?Person $cliente): string
    {
        if ($cliente === null) {
            return '';
        }

        $cidade = trim((string) ($cliente->cidade_nome ?? ''));
        $uf = trim((string) ($cliente->uf ?? ''));

        if ($cidade !== '' && $uf !== '') {
            return $cidade.'/'.$uf;
        }

        return $cidade !== '' ? $cidade : $uf;
    }

    private function formatTelefone(?Person $cliente): string
    {
        if ($cliente === null) {
            return '';
        }

        foreach (['celular1', 'whatsapp', 'fone1', 'fone2', 'celular2'] as $campo) {
            $valor = trim((string) ($cliente->{$campo} ?? ''));
            if ($valor !== '') {
                return $valor;
            }
        }

        return '';
    }

    private function formatLocal(ForcaVendasVisitaSemVenda $visita, bool $forPrint): string|HtmlString
    {
        $lat = $visita->latitude;
        $lng = $visita->longitude;

        if ($lat === null || $lng === null) {
            return '—';
        }

        $latF = (float) $lat;
        $lngF = (float) $lng;

        if (abs($latF) < 0.0001 && abs($lngF) < 0.0001) {
            return '—';
        }

        if ($forPrint) {
            return number_format($latF, 5, '.', '').', '.number_format($lngF, 5, '.', '');
        }

        $url = 'https://www.google.com/maps?q='.rawurlencode($latF.','.$lngF);

        return new HtmlString(
            '<a href="'.e($url).'" target="_blank" rel="noopener noreferrer"'
            .' title="Abrir no Google Maps" aria-label="Abrir no Google Maps"'
            .' style="display:inline-flex;align-items:center;justify-content:center;'
            .'color:#1d4ed8;text-decoration:none;">'
            .'<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">'
            .'<path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>'
            .'</svg></a>'
        );
    }
}
