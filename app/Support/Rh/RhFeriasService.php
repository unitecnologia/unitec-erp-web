<?php

namespace App\Support\Rh;

use App\Models\RhAnexo;
use App\Models\RhFeriasGozo;
use App\Models\RhFeriasGozoLog;
use App\Models\RhFeriasPeriodo;
use App\Models\RhFuncionario;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RhFeriasService
{
    public const MAX_FRACOES = 3;

    public const MIN_DIAS_FRACAO = 5;

    public const MIN_DIAS_FRACAO_PRINCIPAL = 14;

    public function gerarPeriodoAquisitivo(RhFuncionario $funcionario, ?int $diasDireito = null, ?string $observacao = null): RhFeriasPeriodo
    {
        if (! $funcionario->data_admissao) {
            throw new InvalidArgumentException('Informe a data de admissão do funcionário para gerar o período aquisitivo.');
        }

        $diasDireito = $diasDireito ?? 30;
        if ($diasDireito < 1 || $diasDireito > 365) {
            throw new InvalidArgumentException('Dias de direito inválidos.');
        }

        $ultimo = RhFeriasPeriodo::query()
            ->where('funcionario_id', $funcionario->getKey())
            ->where('ativo', true)
            ->where('situacao', '!=', RhFeriasPeriodo::SIT_CANCELADO)
            ->orderByDesc('aquisitivo_fim')
            ->first();

        if ($ultimo) {
            $inicio = $ultimo->aquisitivo_fim->copy()->addDay();
        } else {
            $inicio = Carbon::parse($funcionario->data_admissao)->startOfDay();
        }

        $fimAquisitivo = $inicio->copy()->addYear()->subDay();
        $inicioConcessivo = $fimAquisitivo->copy()->addDay();
        $fimConcessivo = $inicioConcessivo->copy()->addYear()->subDay();

        $duplicado = RhFeriasPeriodo::query()
            ->where('funcionario_id', $funcionario->getKey())
            ->whereDate('aquisitivo_inicio', $inicio->toDateString())
            ->exists();
        if ($duplicado) {
            throw new InvalidArgumentException('Já existe período aquisitivo com esta data de início.');
        }

        return DB::transaction(function () use ($funcionario, $inicio, $fimAquisitivo, $inicioConcessivo, $fimConcessivo, $diasDireito, $observacao): RhFeriasPeriodo {
            return RhFeriasPeriodo::query()->create([
                'codigo' => RhFeriasPeriodo::nextCodigo(),
                'funcionario_id' => (int) $funcionario->getKey(),
                'aquisitivo_inicio' => $inicio->toDateString(),
                'aquisitivo_fim' => $fimAquisitivo->toDateString(),
                'concessivo_inicio' => $inicioConcessivo->toDateString(),
                'concessivo_fim' => $fimConcessivo->toDateString(),
                'dias_direito' => $diasDireito,
                'dias_saldo' => $diasDireito,
                'situacao' => RhFeriasPeriodo::SIT_ABERTO,
                'observacao' => $this->blank($observacao),
                'user_id' => Auth::id(),
                'ativo' => true,
            ]);
        });
    }

    /**
     * @param  array{
     *   gozo_inicio: string,
     *   gozo_fim: string,
     *   aviso_em?: string|null,
     *   observacao?: string|null,
     *   ajuste_admin?: bool,
     *   motivo_ajuste?: string|null
     * }  $data
     */
    public function programarGozo(RhFeriasPeriodo $periodo, array $data, ?UploadedFile $aviso = null): RhFeriasGozo
    {
        if (! $periodo->ativo || $periodo->situacao === RhFeriasPeriodo::SIT_CANCELADO) {
            throw new InvalidArgumentException('Período indisponível para programação.');
        }

        $inicio = $this->parseDate($data['gozo_inicio'] ?? null)
            ?? throw new InvalidArgumentException('Data inicial do gozo é obrigatória.');
        $fim = $this->parseDate($data['gozo_fim'] ?? null)
            ?? throw new InvalidArgumentException('Data final do gozo é obrigatória.');

        if ($fim < $inicio) {
            throw new InvalidArgumentException('Data final deve ser igual ou posterior à inicial.');
        }

        $dias = $this->diasCorridos($inicio, $fim);
        if ($dias < 1) {
            throw new InvalidArgumentException('Quantidade de dias inválida.');
        }

        $ajusteAdmin = (bool) ($data['ajuste_admin'] ?? false);
        $motivo = $this->blank($data['motivo_ajuste'] ?? null);

        if ($dias > (int) $periodo->dias_saldo) {
            throw new InvalidArgumentException('Saldo insuficiente no período (restam '.$periodo->dias_saldo.' dia(s)).');
        }

        $this->assertGozoDentroConcessivo($periodo, $inicio, $fim, $ajusteAdmin, $motivo);
        $this->assertSemSobreposicao((int) $periodo->funcionario_id, $inicio, $fim, null);
        $this->assertFracionamento($periodo, $dias, $ajusteAdmin, $motivo);

        if ($ajusteAdmin && $motivo === null) {
            throw new InvalidArgumentException('Informe o motivo do ajuste administrativo.');
        }

        $avisoEm = $this->parseDate($data['aviso_em'] ?? null);
        $fracao = $this->proximaFracao($periodo);

        return DB::transaction(function () use ($periodo, $inicio, $fim, $dias, $avisoEm, $data, $ajusteAdmin, $motivo, $fracao, $aviso): RhFeriasGozo {
            $gozo = RhFeriasGozo::query()->create([
                'codigo' => RhFeriasGozo::nextCodigo(),
                'periodo_id' => (int) $periodo->getKey(),
                'funcionario_id' => (int) $periodo->funcionario_id,
                'gozo_inicio' => $inicio,
                'gozo_fim' => $fim,
                'dias' => $dias,
                'fracao' => $fracao,
                'aviso_em' => $avisoEm,
                'situacao' => RhFeriasGozo::SIT_PROGRAMADA,
                'ajuste_admin' => $ajusteAdmin,
                'observacao' => $this->blank($data['observacao'] ?? null),
                'user_id' => Auth::id(),
                'ativo' => true,
            ]);

            $periodo->dias_saldo = max(0, (int) $periodo->dias_saldo - $dias);
            $this->atualizarSituacaoPeriodo($periodo);
            $periodo->save();

            $this->logGozo($gozo, 'programar', null, $this->snapshotGozo($gozo), $motivo);

            if ($aviso instanceof UploadedFile) {
                $this->anexarAviso($gozo, $aviso);
            }

            return $gozo;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function alterarGozo(RhFeriasGozo $gozo, array $data, ?UploadedFile $aviso = null): RhFeriasGozo
    {
        if ($gozo->situacao === RhFeriasGozo::SIT_CANCELADA) {
            throw new InvalidArgumentException('Gozo cancelado não pode ser alterado.');
        }

        $antes = $this->snapshotGozo($gozo);
        $periodo = $gozo->periodo ?? RhFeriasPeriodo::query()->find($gozo->periodo_id);
        if (! $periodo) {
            throw new InvalidArgumentException('Período vinculado não encontrado.');
        }

        $ajusteAdmin = array_key_exists('ajuste_admin', $data)
            ? (bool) $data['ajuste_admin']
            : (bool) $gozo->ajuste_admin;
        $motivo = $this->blank($data['motivo_ajuste'] ?? null);

        $inicio = array_key_exists('gozo_inicio', $data)
            ? ($this->parseDate($data['gozo_inicio']) ?? throw new InvalidArgumentException('Data inicial inválida.'))
            : $gozo->gozo_inicio?->toDateString();
        $fim = array_key_exists('gozo_fim', $data)
            ? ($this->parseDate($data['gozo_fim']) ?? throw new InvalidArgumentException('Data final inválida.'))
            : $gozo->gozo_fim?->toDateString();

        if ($fim < $inicio) {
            throw new InvalidArgumentException('Data final deve ser igual ou posterior à inicial.');
        }

        $diasNovos = $this->diasCorridos($inicio, $fim);
        $delta = $diasNovos - (int) $gozo->dias;
        $saldoEfetivo = (int) $periodo->dias_saldo - max(0, $delta);
        if ($saldoEfetivo < 0) {
            throw new InvalidArgumentException('Saldo insuficiente para esta alteração.');
        }

        if ($inicio !== $gozo->gozo_inicio?->toDateString() || $fim !== $gozo->gozo_fim?->toDateString()) {
            $this->assertSemSobreposicao((int) $gozo->funcionario_id, $inicio, $fim, (int) $gozo->getKey());
            $this->assertGozoDentroConcessivo($periodo, $inicio, $fim, $ajusteAdmin, $motivo);
        }

        if ($diasNovos !== (int) $gozo->dias) {
            $this->assertFracionamento($periodo, $diasNovos, $ajusteAdmin, $motivo, ignorarGozoId: (int) $gozo->getKey());
        }

        if ($ajusteAdmin && $motivo === null && ! $gozo->ajuste_admin) {
            throw new InvalidArgumentException('Informe o motivo do ajuste administrativo.');
        }

        return DB::transaction(function () use ($gozo, $periodo, $inicio, $fim, $diasNovos, $delta, $data, $ajusteAdmin, $motivo, $antes, $aviso): RhFeriasGozo {
            if ($delta !== 0) {
                $periodo->dias_saldo = max(0, (int) $periodo->dias_saldo - $delta);
                $this->atualizarSituacaoPeriodo($periodo);
                $periodo->save();
            }

            if (array_key_exists('aviso_em', $data)) {
                $gozo->aviso_em = $this->parseDate($data['aviso_em']);
            }
            if (array_key_exists('observacao', $data)) {
                $gozo->observacao = $this->blank($data['observacao']);
            }

            $gozo->gozo_inicio = $inicio;
            $gozo->gozo_fim = $fim;
            $gozo->dias = $diasNovos;
            $gozo->ajuste_admin = $ajusteAdmin;
            $gozo->save();

            if ($aviso instanceof UploadedFile) {
                $this->anexarAviso($gozo, $aviso, substituir: true);
            }

            $this->logGozo($gozo, 'alterar', $antes, $this->snapshotGozo($gozo), $motivo);

            return $gozo->fresh(['periodo', 'funcionario']);
        });
    }

    public function marcarEmAndamento(RhFeriasGozo $gozo, ?string $motivo = null): RhFeriasGozo
    {
        if ($gozo->situacao !== RhFeriasGozo::SIT_PROGRAMADA) {
            throw new InvalidArgumentException('Somente gozo programado pode entrar em andamento.');
        }

        $antes = $this->snapshotGozo($gozo);
        $gozo->update(['situacao' => RhFeriasGozo::SIT_EM_ANDAMENTO]);
        $this->logGozo($gozo, 'em_andamento', $antes, $this->snapshotGozo($gozo->fresh()), $motivo);

        return $gozo->fresh();
    }

    public function concluir(RhFeriasGozo $gozo, ?string $motivo = null): RhFeriasGozo
    {
        if (! in_array($gozo->situacao, [RhFeriasGozo::SIT_PROGRAMADA, RhFeriasGozo::SIT_EM_ANDAMENTO], true)) {
            throw new InvalidArgumentException('Gozo não está ativo para conclusão.');
        }

        $antes = $this->snapshotGozo($gozo);
        $gozo->update(['situacao' => RhFeriasGozo::SIT_CONCLUIDA]);
        $this->logGozo($gozo, 'concluir', $antes, $this->snapshotGozo($gozo->fresh()), $motivo);

        return $gozo->fresh();
    }

    public function cancelar(RhFeriasGozo $gozo, ?string $motivo = null): RhFeriasGozo
    {
        if ($gozo->situacao === RhFeriasGozo::SIT_CANCELADA) {
            return $gozo;
        }

        if ($motivo === null || trim($motivo) === '') {
            throw new InvalidArgumentException('Informe o motivo do cancelamento.');
        }

        $antes = $this->snapshotGozo($gozo);
        $periodo = $gozo->periodo ?? RhFeriasPeriodo::query()->find($gozo->periodo_id);

        $diasDevolver = (int) $gozo->dias;

        return DB::transaction(function () use ($gozo, $periodo, $motivo, $antes, $diasDevolver): RhFeriasGozo {
            $gozo->update([
                'situacao' => RhFeriasGozo::SIT_CANCELADA,
                'ativo' => false,
            ]);

            if ($periodo) {
                $periodo->dias_saldo = min((int) $periodo->dias_direito, (int) $periodo->dias_saldo + $diasDevolver);
                $this->atualizarSituacaoPeriodo($periodo);
                $periodo->save();
            }

            $this->logGozo($gozo, 'cancelar', $antes, $this->snapshotGozo($gozo->fresh()), $motivo);

            return $gozo->fresh();
        });
    }

    public function ajustarSaldoAdmin(RhFeriasPeriodo $periodo, int $novoSaldo, ?string $motivo): RhFeriasPeriodo
    {
        if ($motivo === null || trim($motivo) === '') {
            throw new InvalidArgumentException('Informe o motivo do ajuste de saldo.');
        }

        if ($novoSaldo < 0 || $novoSaldo > (int) $periodo->dias_direito) {
            throw new InvalidArgumentException('Saldo deve estar entre 0 e '.$periodo->dias_direito.'.');
        }

        $antes = [
            'dias_saldo' => $periodo->dias_saldo,
            'situacao' => $periodo->situacao,
        ];

        $periodo->dias_saldo = $novoSaldo;
        $this->atualizarSituacaoPeriodo($periodo);
        $periodo->save();

        $gozoRef = RhFeriasGozo::query()
            ->where('periodo_id', $periodo->getKey())
            ->orderByDesc('id')
            ->first();

        if ($gozoRef) {
            $this->logGozo($gozoRef, 'ajuste_saldo_periodo', $antes, [
                'dias_saldo' => $periodo->dias_saldo,
                'situacao' => $periodo->situacao,
                'periodo_id' => $periodo->getKey(),
            ], $motivo);
        }

        return $periodo->fresh();
    }

    public function anexarAviso(RhFeriasGozo $gozo, UploadedFile $file, bool $substituir = false): RhAnexo
    {
        $service = app(RhAnexoService::class);

        if ($substituir) {
            foreach ($gozo->anexos()->where('categoria', RhAnexo::CAT_FERIAS)->get() as $anexo) {
                $service->delete($anexo);
            }
        }

        $anexo = $service->store($gozo, $file, [
            'titulo' => 'Aviso férias '.$gozo->codigo,
            'categoria' => RhAnexo::CAT_FERIAS,
            'emitido_em' => $gozo->aviso_em?->toDateString(),
            'observacao' => 'Aviso de férias',
        ]);

        $this->logGozo($gozo, 'anexar_aviso', null, [
            'anexo_id' => $anexo->getKey(),
        ], null);

        return $anexo;
    }

    public function registrarRealizacao(RhFeriasGozo $gozo): RhFeriasGozo
    {
        $hoje = Carbon::today()->toDateString();

        if ($gozo->situacao === RhFeriasGozo::SIT_PROGRAMADA && $gozo->gozo_inicio && $gozo->gozo_inicio->toDateString() <= $hoje) {
            return $this->marcarEmAndamento($gozo);
        }

        if (in_array($gozo->situacao, [RhFeriasGozo::SIT_PROGRAMADA, RhFeriasGozo::SIT_EM_ANDAMENTO], true)
            && $gozo->gozo_fim && $gozo->gozo_fim->toDateString() < $hoje) {
            return $this->concluir($gozo);
        }

        return $gozo;
    }

    public function atualizarSituacaoPeriodo(RhFeriasPeriodo $periodo): void
    {
        if ($periodo->situacao === RhFeriasPeriodo::SIT_CANCELADO) {
            return;
        }

        $hoje = Carbon::today();
        $saldo = (int) $periodo->dias_saldo;

        if ($saldo <= 0) {
            $periodo->situacao = RhFeriasPeriodo::SIT_GOZADO;
        } elseif ($saldo < (int) $periodo->dias_direito) {
            $periodo->situacao = RhFeriasPeriodo::SIT_PARCIAL;
        } else {
            $periodo->situacao = RhFeriasPeriodo::SIT_ABERTO;
        }

        if ($saldo > 0 && $periodo->concessivo_fim && $periodo->concessivo_fim->lt($hoje)) {
            $periodo->situacao = RhFeriasPeriodo::SIT_VENCIDO;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotGozo(RhFeriasGozo $gozo): array
    {
        return [
            'codigo' => $gozo->codigo,
            'periodo_id' => $gozo->periodo_id,
            'funcionario_id' => $gozo->funcionario_id,
            'gozo_inicio' => $gozo->gozo_inicio?->toDateString(),
            'gozo_fim' => $gozo->gozo_fim?->toDateString(),
            'dias' => $gozo->dias,
            'fracao' => $gozo->fracao,
            'aviso_em' => $gozo->aviso_em?->toDateString(),
            'situacao' => $gozo->situacao,
            'ajuste_admin' => $gozo->ajuste_admin,
            'observacao' => $gozo->observacao,
            'ativo' => $gozo->ativo,
        ];
    }

    private function proximaFracao(RhFeriasPeriodo $periodo): int
    {
        $max = (int) RhFeriasGozo::query()
            ->where('periodo_id', $periodo->getKey())
            ->where('situacao', '!=', RhFeriasGozo::SIT_CANCELADA)
            ->max('fracao');

        return $max + 1;
    }

    private function assertFracionamento(
        RhFeriasPeriodo $periodo,
        int $diasNovos,
        bool $ajusteAdmin,
        ?string $motivo,
        ?int $ignorarGozoId = null,
    ): void {
        $query = RhFeriasGozo::query()
            ->where('periodo_id', $periodo->getKey())
            ->where('situacao', '!=', RhFeriasGozo::SIT_CANCELADA);

        if ($ignorarGozoId) {
            $query->whereKeyNot($ignorarGozoId);
        }

        $diasLista = $query->pluck('dias')->map(fn ($d): int => (int) $d)->all();
        $diasLista[] = $diasNovos;

        if (count($diasLista) > self::MAX_FRACOES) {
            if (! $ajusteAdmin) {
                throw new InvalidArgumentException('Máximo de '.self::MAX_FRACOES.' frações por período.');
            }
        }

        if ($ajusteAdmin) {
            return;
        }

        foreach ($diasLista as $d) {
            if ($d < self::MIN_DIAS_FRACAO) {
                throw new InvalidArgumentException('Cada fração deve ter no mínimo '.self::MIN_DIAS_FRACAO.' dias.');
            }
        }

        $maior = max($diasLista);
        if ($maior < self::MIN_DIAS_FRACAO_PRINCIPAL) {
            throw new InvalidArgumentException('Ao menos uma fração deve ter '.self::MIN_DIAS_FRACAO_PRINCIPAL.' dias ou mais.');
        }
    }

    private function assertSemSobreposicao(int $funcionarioId, string $inicio, string $fim, ?int $ignorarGozoId): void
    {
        $query = RhFeriasGozo::query()
            ->where('funcionario_id', $funcionarioId)
            ->where('situacao', '!=', RhFeriasGozo::SIT_CANCELADA)
            ->where('ativo', true)
            ->whereDate('gozo_inicio', '<=', $fim)
            ->whereDate('gozo_fim', '>=', $inicio);

        if ($ignorarGozoId) {
            $query->whereKeyNot($ignorarGozoId);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException('Conflito de datas com outro gozo do mesmo funcionário.');
        }
    }

    private function assertGozoDentroConcessivo(
        RhFeriasPeriodo $periodo,
        string $inicio,
        string $fim,
        bool $ajusteAdmin,
        ?string $motivo,
    ): void {
        if ($ajusteAdmin) {
            return;
        }

        if ($periodo->concessivo_inicio && $inicio < $periodo->concessivo_inicio->toDateString()) {
            throw new InvalidArgumentException('Gozo antes do início do período concessivo.');
        }

        if ($periodo->concessivo_fim && $fim > $periodo->concessivo_fim->toDateString()) {
            throw new InvalidArgumentException('Gozo após o fim do período concessivo.');
        }
    }

    private function diasCorridos(string $inicio, string $fim): int
    {
        $a = Carbon::parse($inicio)->startOfDay();
        $b = Carbon::parse($fim)->startOfDay();

        return (int) $a->diffInDays($b) + 1;
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function blank(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $depois
     */
    private function logGozo(RhFeriasGozo $gozo, string $acao, ?array $antes, ?array $depois, ?string $motivo): void
    {
        RhFeriasGozoLog::query()->create([
            'gozo_id' => $gozo->getKey(),
            'user_id' => Auth::id(),
            'acao' => $acao,
            'antes' => $antes,
            'depois' => $depois,
            'motivo' => $this->blank($motivo),
            'created_at' => now(),
        ]);
    }
}
