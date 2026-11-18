<?php

namespace App\Support\Rh;

use App\Models\RhBeneficio;
use App\Models\RhBeneficioVinculo;
use App\Models\RhBeneficioVinculoLog;
use App\Models\RhFuncionario;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RhBeneficioService
{
    /**
     * @param  array{
     *   codigo?: string,
     *   descricao: string,
     *   tipo: string,
     *   valor_padrao?: mixed,
     *   periodicidade?: string,
     *   ativo?: bool
     * }  $data
     */
    public function criarBeneficio(array $data): RhBeneficio
    {
        $tipo = (string) ($data['tipo'] ?? '');
        if (! in_array($tipo, RhBeneficio::tiposValidos(), true)) {
            throw new InvalidArgumentException('Tipo de benefício inválido.');
        }

        $periodicidade = (string) ($data['periodicidade'] ?? RhBeneficio::PER_MENSAL);
        if (! in_array($periodicidade, RhBeneficio::periodicidadesValidas(), true)) {
            throw new InvalidArgumentException('Periodicidade inválida.');
        }

        return RhBeneficio::query()->create([
            'codigo' => filled($data['codigo'] ?? null) ? trim((string) $data['codigo']) : RhBeneficio::nextCodigo(),
            'descricao' => mb_strtoupper(trim((string) $data['descricao']), 'UTF-8'),
            'tipo' => $tipo,
            'valor_padrao' => $this->parseMoney($data['valor_padrao'] ?? null),
            'periodicidade' => $periodicidade,
            'ativo' => (bool) ($data['ativo'] ?? true),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function alterarBeneficio(RhBeneficio $beneficio, array $data): RhBeneficio
    {
        if (array_key_exists('descricao', $data)) {
            $beneficio->descricao = mb_strtoupper(trim((string) $data['descricao']), 'UTF-8');
        }

        if (array_key_exists('tipo', $data)) {
            $tipo = (string) $data['tipo'];
            if (! in_array($tipo, RhBeneficio::tiposValidos(), true)) {
                throw new InvalidArgumentException('Tipo de benefício inválido.');
            }
            $beneficio->tipo = $tipo;
        }

        if (array_key_exists('valor_padrao', $data)) {
            $beneficio->valor_padrao = $this->parseMoney($data['valor_padrao']);
        }

        if (array_key_exists('periodicidade', $data)) {
            $periodicidade = (string) $data['periodicidade'];
            if (! in_array($periodicidade, RhBeneficio::periodicidadesValidas(), true)) {
                throw new InvalidArgumentException('Periodicidade inválida.');
            }
            $beneficio->periodicidade = $periodicidade;
        }

        if (array_key_exists('ativo', $data)) {
            $beneficio->ativo = (bool) $data['ativo'];
        }

        if (array_key_exists('codigo', $data) && filled($data['codigo'])) {
            $beneficio->codigo = trim((string) $data['codigo']);
        }

        $beneficio->save();

        return $beneficio;
    }

    /**
     * @param  array{
     *   funcionario_id: int,
     *   beneficio_id: int,
     *   inicio_em?: string|null,
     *   termino_em?: string|null,
     *   valor?: mixed,
     *   participacao_funcionario?: mixed,
     *   observacao?: string|null
     * }  $data
     */
    public function vincular(array $data): RhBeneficioVinculo
    {
        $funcionario = RhFuncionario::query()->find((int) $data['funcionario_id']);
        $beneficio = RhBeneficio::query()->find((int) $data['beneficio_id']);

        if (! $funcionario || ! $beneficio) {
            throw new InvalidArgumentException('Funcionário ou benefício inválido.');
        }

        if (! $beneficio->ativo) {
            throw new InvalidArgumentException('Benefício inativo. Reative no catálogo antes de vincular.');
        }

        $valor = $this->parseMoney($data['valor'] ?? null);
        if ($valor === null) {
            $valor = $beneficio->valor_padrao !== null ? (float) $beneficio->valor_padrao : 0.0;
        }

        if ($valor < 0) {
            throw new InvalidArgumentException('Valor do benefício inválido.');
        }

        $inicio = $this->parseDate($data['inicio_em'] ?? null) ?? Carbon::today()->toDateString();

        return DB::transaction(function () use ($data, $funcionario, $beneficio, $valor, $inicio): RhBeneficioVinculo {
            $vinculo = RhBeneficioVinculo::query()->create([
                'codigo' => RhBeneficioVinculo::nextCodigo(),
                'funcionario_id' => $funcionario->getKey(),
                'beneficio_id' => $beneficio->getKey(),
                'inicio_em' => $inicio,
                'termino_em' => $this->parseDate($data['termino_em'] ?? null),
                'valor' => $valor,
                'participacao_funcionario' => $this->parseMoney($data['participacao_funcionario'] ?? null),
                'situacao' => RhBeneficioVinculo::SIT_ATIVO,
                'observacao' => $this->blankToNull($data['observacao'] ?? null),
                'user_id' => Auth::id(),
                'ativo' => true,
            ]);

            $this->log($vinculo, 'vincular', null, $this->snapshot($vinculo), null);

            return $vinculo;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function alterarVinculo(RhBeneficioVinculo $vinculo, array $data, ?string $motivo = null): RhBeneficioVinculo
    {
        if ($vinculo->situacao === RhBeneficioVinculo::SIT_CANCELADO) {
            throw new InvalidArgumentException('Vínculo cancelado não pode ser alterado.');
        }

        if ($vinculo->situacao === RhBeneficioVinculo::SIT_ENCERRADO) {
            throw new InvalidArgumentException('Vínculo encerrado não pode ser alterado.');
        }

        $antes = $this->snapshot($vinculo);
        $valorAntes = (string) $antes['valor'];

        if (filled($data['funcionario_id'] ?? null)) {
            $vinculo->funcionario_id = (int) $data['funcionario_id'];
        }

        if (filled($data['beneficio_id'] ?? null)) {
            $beneficio = RhBeneficio::query()->find((int) $data['beneficio_id']);
            if (! $beneficio || ! $beneficio->ativo) {
                throw new InvalidArgumentException('Benefício inválido ou inativo.');
            }
            $vinculo->beneficio_id = $beneficio->getKey();
        }

        if (array_key_exists('inicio_em', $data)) {
            $vinculo->inicio_em = $this->parseDate($data['inicio_em']) ?? $vinculo->inicio_em?->toDateString();
        }

        if (array_key_exists('termino_em', $data)) {
            $vinculo->termino_em = $this->parseDate($data['termino_em']);
        }

        if (array_key_exists('valor', $data)) {
            $valor = $this->parseMoney($data['valor']);
            if ($valor === null || $valor < 0) {
                throw new InvalidArgumentException('Valor do benefício inválido.');
            }
            $vinculo->valor = $valor;
        }

        if (array_key_exists('participacao_funcionario', $data)) {
            $vinculo->participacao_funcionario = $this->parseMoney($data['participacao_funcionario']);
        }

        if (array_key_exists('observacao', $data)) {
            $vinculo->observacao = $this->blankToNull($data['observacao']);
        }

        $vinculo->save();

        $depois = $this->snapshot($vinculo);
        $acao = (string) $depois['valor'] !== $valorAntes ? 'alterar_valor' : 'alterar';
        $this->log($vinculo, $acao, $antes, $depois, $motivo);

        return $vinculo;
    }

    public function cancelarVinculo(RhBeneficioVinculo $vinculo, ?string $motivo = null): RhBeneficioVinculo
    {
        if ($vinculo->situacao === RhBeneficioVinculo::SIT_CANCELADO) {
            return $vinculo;
        }

        $antes = $this->snapshot($vinculo);
        $vinculo->update([
            'situacao' => RhBeneficioVinculo::SIT_CANCELADO,
            'ativo' => false,
            'observacao' => trim(($vinculo->observacao ? $vinculo->observacao.' | ' : '').'CANCELADO: '.($motivo ?: 'sem motivo')),
        ]);
        $this->log($vinculo, 'cancelar', $antes, $this->snapshot($vinculo->fresh()), $motivo);

        return $vinculo->fresh();
    }

    public function encerrar(RhBeneficioVinculo $vinculo, ?string $terminoEm = null, ?string $motivo = null): RhBeneficioVinculo
    {
        if ($vinculo->situacao !== RhBeneficioVinculo::SIT_ATIVO) {
            throw new InvalidArgumentException('Somente vínculos ativos podem ser encerrados.');
        }

        $antes = $this->snapshot($vinculo);
        $termino = $this->parseDate($terminoEm) ?? Carbon::today()->toDateString();

        $vinculo->update([
            'situacao' => RhBeneficioVinculo::SIT_ENCERRADO,
            'termino_em' => $termino,
            'observacao' => $this->blankToNull($motivo) ?? $vinculo->observacao,
        ]);
        $this->log($vinculo, 'encerrar', $antes, $this->snapshot($vinculo->fresh()), $motivo);

        return $vinculo->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(RhBeneficioVinculo $vinculo): array
    {
        return [
            'codigo' => $vinculo->codigo,
            'funcionario_id' => $vinculo->funcionario_id,
            'beneficio_id' => $vinculo->beneficio_id,
            'inicio_em' => $vinculo->inicio_em?->toDateString(),
            'termino_em' => $vinculo->termino_em?->toDateString(),
            'valor' => $vinculo->valor !== null ? number_format((float) $vinculo->valor, 2, '.', '') : null,
            'participacao_funcionario' => $vinculo->participacao_funcionario !== null
                ? number_format((float) $vinculo->participacao_funcionario, 2, '.', '')
                : null,
            'situacao' => $vinculo->situacao,
            'observacao' => $vinculo->observacao,
            'ativo' => $vinculo->ativo,
        ];
    }

    private function parseMoney(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        $normalized = str_replace(['.', ','], ['', '.'], preg_replace('/[^\d,.-]/', '', $trimmed) ?? $trimmed);
        if (str_contains($trimmed, ',') && ! str_contains($trimmed, '.')) {
            $normalized = str_replace(',', '.', str_replace('.', '', $trimmed));
        }

        return is_numeric($normalized) ? round((float) $normalized, 2) : null;
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $depois
     */
    private function log(RhBeneficioVinculo $vinculo, string $acao, ?array $antes, ?array $depois, ?string $motivo): void
    {
        RhBeneficioVinculoLog::query()->create([
            'vinculo_id' => $vinculo->getKey(),
            'user_id' => Auth::id(),
            'acao' => $acao,
            'antes' => $antes,
            'depois' => $depois,
            'motivo' => $this->blankToNull($motivo),
            'created_at' => now(),
        ]);
    }
}
