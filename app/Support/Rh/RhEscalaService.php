<?php

namespace App\Support\Rh;

use App\Models\RhEscala;
use App\Models\RhEscalaAjuste;
use App\Models\RhEscalaLog;
use App\Models\RhEscalaVinculo;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Regras de escala:
 * - Tipos 5x2, 6x1 e personalizada usam dias da semana (0=dom … 6=sáb) em dias_trabalho/dias_folga.
 * - 12×36: ciclo contínuo a partir de inicio_em do vínculo — dia 1 do ciclo = trabalho 12h,
 *   dia 2 = folga, dia 3 = trabalho, … (índice ímpar = trabalho, par = folga).
 * - Ajustes pontuais (rh_escala_ajustes) sobrescrevem o template no dia.
 */
final class RhEscalaService
{
    /**
     * @return array{dias_trabalho: list<int>, dias_folga: list<int>}
     */
    public function diasPadraoParaTipo(string $tipo): array
    {
        return match ($tipo) {
            RhEscala::TIPO_5X2 => [
                'dias_trabalho' => [1, 2, 3, 4, 5],
                'dias_folga' => [0, 6],
            ],
            RhEscala::TIPO_6X1 => [
                'dias_trabalho' => [1, 2, 3, 4, 5, 6],
                'dias_folga' => [0],
            ],
            RhEscala::TIPO_12X36 => [
                'dias_trabalho' => [],
                'dias_folga' => [],
            ],
            default => [
                'dias_trabalho' => [1, 2, 3, 4, 5],
                'dias_folga' => [0, 6],
            ],
        };
    }

    /**
     * @param  array{
     *   codigo?: string|null,
     *   nome: string,
     *   tipo: string,
     *   hora_entrada?: string|null,
     *   hora_saida?: string|null,
     *   intervalo_minutos?: int|null,
     *   dias_trabalho?: list<int>|null,
     *   dias_folga?: list<int>|null,
     *   vigencia_inicio?: string|null,
     *   vigencia_fim?: string|null,
     *   ativo?: bool
     * }  $data
     */
    public function salvarEscala(?RhEscala $escala, array $data): RhEscala
    {
        $tipo = (string) ($data['tipo'] ?? RhEscala::TIPO_PERSONALIZADA);
        if (! array_key_exists($tipo, RhEscala::tipos())) {
            throw new InvalidArgumentException('Tipo de escala inválido.');
        }

        $padrao = $this->diasPadraoParaTipo($tipo);
        $diasTrabalho = $this->normalizeDiasSemana($data['dias_trabalho'] ?? $padrao['dias_trabalho']);
        $diasFolga = $this->normalizeDiasSemana($data['dias_folga'] ?? $padrao['dias_folga']);

        if ($tipo !== RhEscala::TIPO_12X36 && $diasTrabalho === [] && $diasFolga === []) {
            throw new InvalidArgumentException('Informe dias de trabalho ou folga.');
        }

        $payload = [
            'nome' => mb_strtoupper(trim((string) ($data['nome'] ?? '')), 'UTF-8'),
            'tipo' => $tipo,
            'hora_entrada' => $this->blankTime($data['hora_entrada'] ?? null),
            'hora_saida' => $this->blankTime($data['hora_saida'] ?? null),
            'intervalo_minutos' => filled($data['intervalo_minutos'] ?? null) ? (int) $data['intervalo_minutos'] : null,
            'dias_trabalho' => $diasTrabalho,
            'dias_folga' => $diasFolga,
            'vigencia_inicio' => $this->parseDate($data['vigencia_inicio'] ?? null),
            'vigencia_fim' => $this->parseDate($data['vigencia_fim'] ?? null),
            'ativo' => (bool) ($data['ativo'] ?? true),
        ];

        if (trim($payload['nome']) === '') {
            throw new InvalidArgumentException('Informe o nome da escala.');
        }

        return DB::transaction(function () use ($escala, $data, $payload): RhEscala {
            $antes = $escala ? $this->snapshotEscala($escala) : null;

            if ($escala) {
                $escala->fill($payload);
                $escala->save();
            } else {
                $escala = RhEscala::query()->create(array_merge($payload, [
                    'codigo' => trim((string) ($data['codigo'] ?? RhEscala::nextCodigo())),
                ]));
            }

            $this->logEscala($escala, $antes === null ? 'incluir' : 'alterar', $antes, $this->snapshotEscala($escala->fresh()), null);

            return $escala->fresh();
        });
    }

    /**
     * @param  array{
     *   escala_id: int,
     *   funcionario_id: int,
     *   inicio_em: string,
     *   termino_em?: string|null,
     *   observacao?: string|null,
     *   codigo?: string|null
     * }  $data
     */
    public function vincular(array $data, ?int $ignoreVinculoId = null): RhEscalaVinculo
    {
        $escalaId = (int) ($data['escala_id'] ?? 0);
        $funcionarioId = (int) ($data['funcionario_id'] ?? 0);
        $inicio = $this->parseDate($data['inicio_em'] ?? null);

        if (! $escalaId || ! RhEscala::query()->whereKey($escalaId)->exists()) {
            throw new InvalidArgumentException('Escala inválida.');
        }
        if (! $funcionarioId) {
            throw new InvalidArgumentException('Selecione o funcionário.');
        }
        if ($inicio === null) {
            throw new InvalidArgumentException('Informe o início do vínculo.');
        }

        $termino = $this->parseDate($data['termino_em'] ?? null);
        if ($termino !== null && $termino < $inicio) {
            throw new InvalidArgumentException('Término não pode ser anterior ao início.');
        }

        $conflitos = $this->detectarConflitos($funcionarioId, $inicio, $termino, $ignoreVinculoId);
        if ($conflitos !== []) {
            throw new InvalidArgumentException('Conflito de vigência com outro vínculo do mesmo funcionário.');
        }

        return DB::transaction(function () use ($data, $escalaId, $funcionarioId, $inicio, $termino): RhEscalaVinculo {
            $vinculo = RhEscalaVinculo::query()->create([
                'codigo' => trim((string) ($data['codigo'] ?? RhEscalaVinculo::nextCodigo())),
                'escala_id' => $escalaId,
                'funcionario_id' => $funcionarioId,
                'inicio_em' => $inicio,
                'termino_em' => $termino,
                'observacao' => $this->blankToNull($data['observacao'] ?? null),
                'user_id' => Auth::id(),
                'ativo' => true,
            ]);

            $this->logVinculo($vinculo, 'vincular', null, $this->snapshotVinculo($vinculo), null);

            return $vinculo->fresh(['escala', 'funcionario']);
        });
    }

    public function encerrarVinculo(RhEscalaVinculo $vinculo, ?string $terminoEm = null, ?string $motivo = null): RhEscalaVinculo
    {
        if (! $vinculo->ativo) {
            throw new InvalidArgumentException('Vínculo já encerrado.');
        }

        $termino = $this->parseDate($terminoEm) ?? Carbon::today()->toDateString();
        $inicio = $vinculo->inicio_em?->toDateString();
        if ($inicio !== null && $termino < $inicio) {
            throw new InvalidArgumentException('Término inválido.');
        }

        $antes = $this->snapshotVinculo($vinculo);

        $vinculo->termino_em = $termino;
        $vinculo->ativo = false;
        $vinculo->save();

        $this->logVinculo($vinculo, 'encerrar', $antes, $this->snapshotVinculo($vinculo->fresh()), $motivo);

        return $vinculo->fresh(['escala', 'funcionario']);
    }

    /**
     * @param  array{
     *   funcionario_id: int,
     *   vinculo_id?: int|null,
     *   data_ref: string,
     *   tipo: string,
     *   hora_inicio?: string|null,
     *   hora_fim?: string|null,
     *   motivo?: string|null
     * }  $data
     */
    public function ajustarDia(array $data): RhEscalaAjuste
    {
        $funcionarioId = (int) ($data['funcionario_id'] ?? 0);
        $dataRef = $this->parseDate($data['data_ref'] ?? null);
        $tipo = (string) ($data['tipo'] ?? '');

        if (! $funcionarioId || $dataRef === null) {
            throw new InvalidArgumentException('Funcionário e data são obrigatórios.');
        }

        if (! array_key_exists($tipo, RhEscalaAjuste::tipos())) {
            throw new InvalidArgumentException('Tipo de ajuste inválido.');
        }

        $vinculoId = filled($data['vinculo_id'] ?? null) ? (int) $data['vinculo_id'] : null;
        if ($vinculoId === null) {
            $vinculo = $this->vinculoVigente($funcionarioId, $dataRef);
            $vinculoId = $vinculo?->getKey();
        }

        return DB::transaction(function () use ($data, $funcionarioId, $dataRef, $tipo, $vinculoId): RhEscalaAjuste {
            $ajuste = RhEscalaAjuste::query()->firstOrNew([
                'funcionario_id' => $funcionarioId,
                'data_ref' => $dataRef,
            ]);

            $antes = $ajuste->exists ? $this->snapshotAjuste($ajuste) : null;

            $ajuste->fill([
                'vinculo_id' => $vinculoId,
                'tipo' => $tipo,
                'hora_inicio' => $this->blankTime($data['hora_inicio'] ?? null),
                'hora_fim' => $this->blankTime($data['hora_fim'] ?? null),
                'motivo' => $this->blankToNull($data['motivo'] ?? null),
                'user_id' => Auth::id(),
            ]);
            $ajuste->save();

            if ($vinculoId) {
                $vinculo = RhEscalaVinculo::query()->find($vinculoId);
                if ($vinculo) {
                    $this->logVinculo($vinculo, $antes === null ? 'ajuste_incluir' : 'ajuste_alterar', $antes, $this->snapshotAjuste($ajuste->fresh()), $ajuste->motivo);
                }
            }

            return $ajuste->fresh();
        });
    }

    /**
     * @return list<array{
     *   data: string,
     *   dia: int,
     *   dow: int,
     *   tipo: string,
     *   label: string,
     *   hora_inicio: ?string,
     *   hora_fim: ?string,
     *   ajuste: bool,
     *   vinculo_id: ?int,
     *   escala_nome: ?string
     * }>
     */
    public function calendarioMensal(int $funcionarioId, int $ano, int $mes): array
    {
        if ($funcionarioId < 1 || $mes < 1 || $mes > 12) {
            return [];
        }

        $inicioMes = Carbon::createFromDate($ano, $mes, 1)->startOfDay();
        $fimMes = $inicioMes->copy()->endOfMonth();

        $vinculos = RhEscalaVinculo::query()
            ->with('escala')
            ->where('funcionario_id', $funcionarioId)
            ->where('inicio_em', '<=', $fimMes->toDateString())
            ->where(function ($q) use ($inicioMes): void {
                $q->whereNull('termino_em')->orWhere('termino_em', '>=', $inicioMes->toDateString());
            })
            ->orderBy('inicio_em')
            ->get();

        $ajustes = RhEscalaAjuste::query()
            ->where('funcionario_id', $funcionarioId)
            ->whereBetween('data_ref', [$inicioMes->toDateString(), $fimMes->toDateString()])
            ->get()
            ->keyBy(fn (RhEscalaAjuste $a): string => $a->data_ref->toDateString());

        $dias = [];
        $cursor = $inicioMes->copy();

        while ($cursor->lte($fimMes)) {
            $dataStr = $cursor->toDateString();
            $vinculo = $this->escolherVinculo($vinculos, $dataStr);
            $ajuste = $ajustes->get($dataStr);

            $resolvido = $this->resolverDiaComVinculo($vinculo, $cursor, $ajuste);

            $dias[] = [
                'data' => $dataStr,
                'dia' => (int) $cursor->day,
                'dow' => (int) $cursor->dayOfWeek,
                'tipo' => $resolvido['tipo'],
                'label' => $resolvido['label'],
                'hora_inicio' => $resolvido['hora_inicio'],
                'hora_fim' => $resolvido['hora_fim'],
                'ajuste' => $ajuste !== null,
                'vinculo_id' => $vinculo?->getKey(),
                'escala_nome' => $vinculo?->escala?->nome,
            ];

            $cursor->addDay();
        }

        return $dias;
    }

    /**
     * @return list<int> IDs de vínculos em conflito
     */
    public function detectarConflitos(int $funcionarioId, string $inicio, ?string $termino = null, ?int $ignoreVinculoId = null): array
    {
        $query = RhEscalaVinculo::query()
            ->where('funcionario_id', $funcionarioId)
            ->where('ativo', true)
            ->when($ignoreVinculoId, fn ($q) => $q->whereKeyNot($ignoreVinculoId));

        $fimNovo = $termino;

        return $query
            ->get()
            ->filter(function (RhEscalaVinculo $v) use ($inicio, $fimNovo): bool {
                $vi = $v->inicio_em?->toDateString() ?? '';
                $vf = $v->termino_em?->toDateString();

                return $this->intervalosSobrepoem($inicio, $fimNovo, $vi, $vf);
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    public function tipoDoDia(int $funcionarioId, Carbon|string $data): ?string
    {
        $ref = $data instanceof Carbon ? $data->toDateString() : $this->parseDate($data);
        if ($ref === null) {
            return null;
        }

        $ajuste = RhEscalaAjuste::query()
            ->where('funcionario_id', $funcionarioId)
            ->whereDate('data_ref', $ref)
            ->first();

        if ($ajuste) {
            return $ajuste->tipo;
        }

        $vinculo = $this->vinculoVigente($funcionarioId, $ref);
        if (! $vinculo) {
            return null;
        }

        return $this->resolverDiaComVinculo($vinculo, Carbon::parse($ref), null)['tipo'];
    }

    /**
     * @return array{folga: int, plantao: int}
     */
    public function contagemHojePorTipo(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('rh_escala_vinculos')) {
            return ['folga' => 0, 'plantao' => 0];
        }

        $hoje = Carbon::today()->toDateString();
        $funcionarioIds = RhEscalaVinculo::query()
            ->where('ativo', true)
            ->where('inicio_em', '<=', $hoje)
            ->where(function ($q) use ($hoje): void {
                $q->whereNull('termino_em')->orWhere('termino_em', '>=', $hoje);
            })
            ->pluck('funcionario_id')
            ->unique();

        $folga = 0;
        $plantao = 0;

        foreach ($funcionarioIds as $fid) {
            $tipo = $this->tipoDoDia((int) $fid, $hoje);
            if ($tipo === RhEscalaAjuste::TIPO_FOLGA) {
                $folga++;
            } elseif ($tipo === RhEscalaAjuste::TIPO_PLANTAO) {
                $plantao++;
            }
        }

        return ['folga' => $folga, 'plantao' => $plantao];
    }

    /**
     * @return array{tipo: string, label: string, hora_inicio: ?string, hora_fim: ?string}
     */
    private function resolverDiaComVinculo(?RhEscalaVinculo $vinculo, Carbon $data, ?RhEscalaAjuste $ajuste): array
    {
        if ($ajuste) {
            return [
                'tipo' => $ajuste->tipo,
                'label' => $ajuste->tipoLabel(),
                'hora_inicio' => $this->formatHora($ajuste->hora_inicio),
                'hora_fim' => $this->formatHora($ajuste->hora_fim),
            ];
        }

        if (! $vinculo || ! $vinculo->escala) {
            return [
                'tipo' => 'sem_escala',
                'label' => '—',
                'hora_inicio' => null,
                'hora_fim' => null,
            ];
        }

        $escala = $vinculo->escala;
        $tipo = $this->tipoTemplateNoDia($escala, $vinculo, $data);

        $horaInicio = $this->formatHora($escala->hora_entrada);
        $horaFim = $this->formatHora($escala->hora_saida);

        if ($escala->tipo === RhEscala::TIPO_12X36 && $tipo === RhEscalaAjuste::TIPO_TRABALHO) {
            $horaInicio = $horaInicio ?? '07:00';
            $horaFim = $horaFim ?? '19:00';
        }

        return [
            'tipo' => $tipo,
            'label' => RhEscalaAjuste::tipos()[$tipo] ?? $tipo,
            'hora_inicio' => $tipo === RhEscalaAjuste::TIPO_FOLGA ? null : $horaInicio,
            'hora_fim' => $tipo === RhEscalaAjuste::TIPO_FOLGA ? null : $horaFim,
        ];
    }

    private function tipoTemplateNoDia(RhEscala $escala, RhEscalaVinculo $vinculo, Carbon $data): string
    {
        if ($escala->tipo === RhEscala::TIPO_12X36) {
            $inicio = $vinculo->inicio_em ?? $data;
            $cicloDia = $inicio->copy()->startOfDay()->diffInDays($data->copy()->startOfDay()) + 1;

            return ($cicloDia % 2) === 1
                ? RhEscalaAjuste::TIPO_TRABALHO
                : RhEscalaAjuste::TIPO_FOLGA;
        }

        $dow = (int) $data->dayOfWeek;
        $folga = $this->normalizeDiasSemana($escala->dias_folga ?? []);
        $trabalho = $this->normalizeDiasSemana($escala->dias_trabalho ?? []);

        if (in_array($dow, $folga, true)) {
            return RhEscalaAjuste::TIPO_FOLGA;
        }

        if (in_array($dow, $trabalho, true)) {
            return RhEscalaAjuste::TIPO_TRABALHO;
        }

        return RhEscalaAjuste::TIPO_FOLGA;
    }

    private function vinculoVigente(int $funcionarioId, string $dataRef): ?RhEscalaVinculo
    {
        return RhEscalaVinculo::query()
            ->with('escala')
            ->where('funcionario_id', $funcionarioId)
            ->where('inicio_em', '<=', $dataRef)
            ->where(function ($q) use ($dataRef): void {
                $q->where('ativo', true)
                    ->orWhere(function ($inner) use ($dataRef): void {
                        $inner->where('ativo', false)
                            ->whereNotNull('termino_em')
                            ->where('termino_em', '>=', $dataRef);
                    });
            })
            ->where(function ($q) use ($dataRef): void {
                $q->whereNull('termino_em')->orWhere('termino_em', '>=', $dataRef);
            })
            ->orderByDesc('inicio_em')
            ->first();
    }

    /**
     * @param  Collection<int, RhEscalaVinculo>  $vinculos
     */
    private function escolherVinculo(Collection $vinculos, string $dataRef): ?RhEscalaVinculo
    {
        return $vinculos
            ->filter(fn (RhEscalaVinculo $v): bool => $v->isVigenteOn($dataRef) || $this->vigenciaHistorica($v, $dataRef))
            ->sortByDesc(fn (RhEscalaVinculo $v): string => $v->inicio_em?->toDateString() ?? '')
            ->first();
    }

    private function vigenciaHistorica(RhEscalaVinculo $v, string $dataRef): bool
    {
        $inicio = $v->inicio_em?->toDateString();
        if ($inicio === null || $dataRef < $inicio) {
            return false;
        }

        $termino = $v->termino_em?->toDateString();

        return $termino === null || $dataRef <= $termino;
    }

    private function intervalosSobrepoem(string $a1, ?string $a2, string $b1, ?string $b2): bool
    {
        $fimA = $a2 ?? '9999-12-31';
        $fimB = $b2 ?? '9999-12-31';

        return $a1 <= $fimB && $b1 <= $fimA;
    }

    /**
     * @param  list<int>|null  $dias
     * @return list<int>
     */
    private function normalizeDiasSemana(?array $dias): array
    {
        if ($dias === null) {
            return [];
        }

        return collect($dias)
            ->map(fn ($d): int => (int) $d)
            ->filter(fn (int $d): bool => $d >= 0 && $d <= 6)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $t = trim((string) $value);

        return $t === '' ? null : $t;
    }

    private function blankTime(mixed $value): ?string
    {
        $t = $this->blankToNull($value);
        if ($t === null) {
            return null;
        }

        if (strlen($t) === 5) {
            return $t.':00';
        }

        return $t;
    }

    private function formatHora(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $s = (string) $value;

        return strlen($s) >= 5 ? substr($s, 0, 5) : $s;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotEscala(RhEscala $escala): array
    {
        return $escala->only([
            'id', 'codigo', 'nome', 'tipo', 'hora_entrada', 'hora_saida',
            'intervalo_minutos', 'dias_trabalho', 'dias_folga', 'ativo',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotVinculo(RhEscalaVinculo $vinculo): array
    {
        return $vinculo->only([
            'id', 'codigo', 'escala_id', 'funcionario_id', 'inicio_em', 'termino_em', 'ativo', 'observacao',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotAjuste(RhEscalaAjuste $ajuste): array
    {
        return $ajuste->only([
            'id', 'vinculo_id', 'funcionario_id', 'data_ref', 'tipo', 'hora_inicio', 'hora_fim', 'motivo',
        ]);
    }

    private function logEscala(RhEscala $escala, string $acao, ?array $antes, ?array $depois, ?string $motivo): void
    {
        RhEscalaLog::query()->create([
            'escala_id' => $escala->getKey(),
            'vinculo_id' => null,
            'user_id' => Auth::id(),
            'acao' => $acao,
            'antes' => $antes,
            'depois' => $depois,
            'motivo' => $motivo,
            'created_at' => now(),
        ]);
    }

    private function logVinculo(RhEscalaVinculo $vinculo, string $acao, ?array $antes, ?array $depois, ?string $motivo): void
    {
        RhEscalaLog::query()->create([
            'escala_id' => $vinculo->escala_id,
            'vinculo_id' => $vinculo->getKey(),
            'user_id' => Auth::id(),
            'acao' => $acao,
            'antes' => $antes,
            'depois' => $depois,
            'motivo' => $motivo,
            'created_at' => now(),
        ]);
    }
}
