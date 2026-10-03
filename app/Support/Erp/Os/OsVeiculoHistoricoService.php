<?php

namespace App\Support\Erp\Os;

use App\Models\Empresa;
use App\Models\OrdemServico;
use App\Models\OrdemServicoItem;
use App\Models\OsVeiculo;
use App\Models\Vendedor;
use App\Support\Erp\ErpMoney;
use Illuminate\Database\Eloquent\Builder;

/**
 * Histórico de manutenções a partir das OS já gravadas.
 * A listagem de veículos não usa esta consulta.
 */
final class OsVeiculoHistoricoService
{
    public const POR_PAGINA = 12;

    /**
     * Placa do cadastro e a forma com hífen, para OS antigas.
     * A placa alternativa do mesmo veículo entra no mesmo critério.
     *
     * @return list<string>
     */
    public static function placasConsulta(OsVeiculo $veiculo): array
    {
        $variantes = [];

        foreach ([
            (string) $veiculo->placa,
            (string) ($veiculo->placa_alternativa ?? ''),
        ] as $bruta) {
            $placa = OsVeiculo::normalizarPlaca($bruta);
            if ($placa === '') {
                continue;
            }

            $variantes[] = $placa;
            if (strlen($placa) === 7) {
                $variantes[] = substr($placa, 0, 3).'-'.substr($placa, 3);
                $variantes[] = substr($placa, 0, 3).' '.substr($placa, 3);
            }
        }

        return array_values(array_unique($variantes));
    }

    /**
     * @param  list<string>  $placas
     */
    public function consulta(int $empresaId, array $placas): Builder
    {
        $placas = array_values(array_filter($placas, static fn (string $placa): bool => $placa !== ''));

        $query = OrdemServico::query()->where('empresa_id', $empresaId);

        if ($empresaId <= 0 || $placas === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $sub) use ($placas): void {
            $sub->whereIn('placa', $placas)
                ->orWhereIn('placa_veiculo', $placas);
        });
    }

    /**
     * @param  list<string>  $placas
     * @return array{linhas: list<array<string, mixed>>, pagina: int, ultima: int, total: int}
     */
    public function pagina(int $empresaId, array $placas, int $pagina): array
    {
        $pagina = max(1, $pagina);
        $consulta = $this->consulta($empresaId, $placas);
        $total = (clone $consulta)->count();
        $ultima = max(1, (int) ceil($total / self::POR_PAGINA));
        $pagina = min($pagina, $ultima);

        $ordens = (clone $consulta)
            ->orderByDesc('data_inicio')
            ->orderByDesc('id')
            ->forPage($pagina, self::POR_PAGINA)
            ->get([
                'id',
                'numero',
                'data_inicio',
                'nome',
                'km',
                'problema',
                'situacao',
                'total_produtos',
                'total_servicos',
                'total_geral',
            ]);

        $tecnicos = $this->tecnicosPorOs($ordens->pluck('id')->all());

        $linhas = $ordens->map(function (OrdemServico $ordem) use ($tecnicos): array {
            $problema = trim((string) ($ordem->problema ?? ''));

            return [
                'id' => (int) $ordem->id,
                'numero' => (string) ($ordem->numero ?: $ordem->id),
                'data' => $ordem->data_inicio?->format('d/m/Y') ?: '—',
                'cliente' => trim((string) ($ordem->nome ?? '')) ?: '—',
                'km' => $ordem->km !== null && $ordem->km !== '' ? (string) $ordem->km : '—',
                'tecnico' => $tecnicos[(int) $ordem->id] ?? '—',
                'problema' => $problema !== '' ? $problema : '—',
                'situacao' => $ordem->situacaoLabel(),
                'pecas' => $this->dinheiro($ordem->total_produtos),
                'servicos' => $this->dinheiro($ordem->total_servicos),
                'total' => $this->dinheiro($ordem->total_geral),
            ];
        })->all();

        return [
            'linhas' => $linhas,
            'pagina' => $pagina,
            'ultima' => $ultima,
            'total' => $total,
        ];
    }

    /**
     * @param  list<string>  $placas
     * @return array<string, mixed>|null
     */
    public function detalhe(int $empresaId, array $placas, int $ordemId): ?array
    {
        if ($ordemId <= 0) {
            return null;
        }

        $ordem = $this->consulta($empresaId, $placas)
            ->whereKey($ordemId)
            ->first([
                'id',
                'numero',
                'situacao',
                'data_inicio',
                'hora_inicio',
                'previsao_entrega',
                'data_termino',
                'hora_termino',
                'data_entrega',
                'hora_entrega',
                'nome',
                'km',
                'descricao',
                'modelo',
                'ano',
                'placa',
                'cor_veiculo',
                'chassi_veiculo',
                'problema',
                'laudo',
                'observacoes',
                'total_produtos',
                'total_servicos',
                'total_geral',
            ]);

        if ($ordem === null) {
            return null;
        }

        return $this->montarOs($ordem, $this->itensDaOs([(int) $ordem->id])[(int) $ordem->id] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function relatorio(int $empresaId, OsVeiculo $veiculo, ?Empresa $empresa): array
    {
        $placas = self::placasConsulta($veiculo);
        $ordens = $this->consulta($empresaId, $placas)
            ->orderBy('data_inicio')
            ->orderBy('id')
            ->get([
                'id',
                'numero',
                'situacao',
                'data_inicio',
                'nome',
                'km',
                'problema',
                'total_produtos',
                'total_servicos',
                'total_geral',
            ]);

        $tecnicos = $this->tecnicosPorOs($ordens->pluck('id')->all());
        $somaPecas = 0.0;
        $somaServicos = 0.0;
        $somaGeral = 0.0;
        $manutencoes = $ordens->map(function (OrdemServico $ordem) use ($tecnicos, &$somaPecas, &$somaServicos, &$somaGeral): array {
            $problema = trim((string) ($ordem->problema ?? ''));
            $somaPecas += (float) $ordem->total_produtos;
            $somaServicos += (float) $ordem->total_servicos;
            $somaGeral += (float) $ordem->total_geral;

            return [
                'numero' => (string) ($ordem->numero ?: $ordem->id),
                'data' => $ordem->data_inicio?->format('d/m/Y') ?: '—',
                'cliente' => trim((string) ($ordem->nome ?? '')) ?: '—',
                'km' => $ordem->km !== null && $ordem->km !== '' ? (string) $ordem->km : '—',
                'tecnico' => $tecnicos[(int) $ordem->id] ?? '—',
                'problema' => $problema !== '' ? $problema : '—',
                'situacao' => $ordem->situacaoLabel(),
                'pecas' => $this->dinheiro($ordem->total_produtos),
                'servicos' => $this->dinheiro($ordem->total_servicos),
                'total' => $this->dinheiro($ordem->total_geral),
            ];
        })->all();

        return [
            'empresa' => trim((string) ($empresa?->fantasia ?: $empresa?->razao_social ?: $empresa?->nome ?: '')),
            'veiculo' => [
                'placa' => (string) $veiculo->placa,
                'placa_alternativa' => trim((string) ($veiculo->placa_alternativa ?? '')),
                'descricao' => trim((string) ($veiculo->descricao ?: $veiculo->marca ?: '')),
                'modelo' => trim((string) ($veiculo->modelo ?? '')),
                'ano' => $veiculo->anoLista(),
            ],
            'manutencoes' => $manutencoes,
            'totais' => [
                'quantidade' => count($manutencoes),
                'pecas' => $this->dinheiro($somaPecas),
                'servicos' => $this->dinheiro($somaServicos),
                'geral' => $this->dinheiro($somaGeral),
            ],
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function tecnicosPorOs(array $ids): array
    {
        $itens = $this->itensDaOs($ids);
        $nomes = [];

        foreach ($itens as $ordemId => $grupos) {
            $lista = [];
            foreach ([...($grupos['pecas'] ?? []), ...($grupos['servicos'] ?? [])] as $item) {
                if ($item['tecnico'] !== '' && ! in_array($item['tecnico'], $lista, true)) {
                    $lista[] = $item['tecnico'];
                }
            }
            if ($lista !== []) {
                $nomes[$ordemId] = implode(', ', $lista);
            }
        }

        return $nomes;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{pecas: list<array<string, string>>, servicos: list<array<string, string>>}>
     */
    private function itensDaOs(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        $itens = OrdemServicoItem::query()
            ->whereIn('ordem_servico_id', $ids)
            ->orderBy('id')
            ->get(['id', 'ordem_servico_id', 'tipo', 'discriminacao', 'qtd', 'total', 'funcionario_id']);

        $funcionarios = $itens->pluck('funcionario_id')->filter()->unique()->values();
        $nomes = $funcionarios->isEmpty()
            ? collect()
            : Vendedor::query()->whereIn('id', $funcionarios)->pluck('nome', 'id');

        $grupos = [];
        foreach ($itens as $item) {
            $ordemId = (int) $item->ordem_servico_id;
            $tecnico = trim((string) ($nomes[$item->funcionario_id] ?? ''));
            $linha = [
                'descricao' => trim((string) ($item->discriminacao ?? '')) ?: '—',
                'qtd' => $this->quantidade($item->qtd),
                'total' => $item->total !== null ? $this->dinheiro($item->total) : '',
                'tecnico' => $tecnico,
            ];
            $chave = (string) $item->tipo === 'S' ? 'servicos' : 'pecas';
            $grupos[$ordemId][$chave][] = $linha;
        }

        return $grupos;
    }

    /**
     * @param  array{pecas?: list<array<string, string>>, servicos?: list<array<string, string>>}  $itens
     * @return array<string, mixed>
     */
    private function montarOs(OrdemServico $ordem, array $itens): array
    {
        $pecas = $itens['pecas'] ?? [];
        $servicos = $itens['servicos'] ?? [];
        $tecnicos = [];
        foreach ([...$pecas, ...$servicos] as $item) {
            if ($item['tecnico'] !== '' && ! in_array($item['tecnico'], $tecnicos, true)) {
                $tecnicos[] = $item['tecnico'];
            }
        }

        $equipamento = array_values(array_filter([
            trim((string) ($ordem->descricao ?? '')),
            trim((string) ($ordem->modelo ?? '')),
            trim((string) ($ordem->ano ?? '')),
            trim((string) ($ordem->placa ?? '')),
            trim((string) ($ordem->cor_veiculo ?? '')),
            trim((string) ($ordem->chassi_veiculo ?? '')),
        ], static fn (string $parte): bool => $parte !== ''));

        return [
            'id' => (int) $ordem->id,
            'numero' => (string) ($ordem->numero ?: $ordem->id),
            'situacao' => $ordem->situacaoLabel(),
            'data' => $ordem->data_inicio?->format('d/m/Y') ?: '—',
            'cliente' => trim((string) ($ordem->nome ?? '')) ?: '—',
            'km' => $ordem->km !== null && $ordem->km !== '' ? (string) $ordem->km : '—',
            'tecnico' => $tecnicos !== [] ? implode(', ', $tecnicos) : '—',
            'equipamento' => $equipamento !== [] ? implode(' · ', $equipamento) : '—',
            'problema' => trim((string) ($ordem->problema ?? '')) ?: '—',
            'laudo' => trim((string) ($ordem->laudo ?? '')) ?: '—',
            'observacoes' => trim((string) ($ordem->observacoes ?? '')) ?: '—',
            'inicio' => $this->dataHora($ordem->data_inicio?->format('d/m/Y'), $ordem->horaInicioExibicao()),
            'previsao' => $ordem->previsao_entrega?->format('d/m/Y H:i') ?: '—',
            'termino' => $this->dataHora($ordem->data_termino?->format('d/m/Y'), $ordem->horaTerminoExibicao()),
            'entrega' => $this->dataHora($ordem->data_entrega?->format('d/m/Y'), $ordem->hora_entrega ? substr((string) $ordem->hora_entrega, 0, 5) : null),
            'pecas' => $pecas,
            'servicos' => $servicos,
            'total_pecas' => $this->dinheiro($ordem->total_produtos),
            'total_servicos' => $this->dinheiro($ordem->total_servicos),
            'total_geral' => $this->dinheiro($ordem->total_geral),
        ];
    }

    private function dataHora(?string $data, ?string $hora): string
    {
        $data = trim((string) $data);
        $hora = trim((string) $hora);
        if ($data === '') {
            return '—';
        }

        return $hora !== '' ? $data.' '.$hora : $data;
    }

    private function dinheiro(mixed $valor): string
    {
        return 'R$ '.ErpMoney::formatBr((float) $valor);
    }

    private function quantidade(mixed $qtd): string
    {
        $numero = (float) $qtd;
        if (abs($numero - round($numero)) < 0.0001) {
            return (string) (int) round($numero);
        }

        return ErpMoney::formatBr($numero, 3);
    }
}
