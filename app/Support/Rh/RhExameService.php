<?php

namespace App\Support\Rh;

use App\Models\RhAnexo;
use App\Models\RhExame;
use App\Models\RhExameLog;
use App\Models\RhFuncionario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RhExameService
{
    /**
     * @param  array{
     *   funcionario_id: int,
     *   tipo: string,
     *   realizado_em: string,
     *   proximo_em?: string|null,
     *   medico_nome?: string|null,
     *   medico_crm?: string|null,
     *   conclusao?: string|null,
     *   observacao?: string|null
     * }  $data
     */
    public function criar(array $data, ?UploadedFile $aso = null): RhExame
    {
        $funcionario = RhFuncionario::query()->find((int) $data['funcionario_id']);
        if (! $funcionario) {
            throw new InvalidArgumentException('Funcionário inválido.');
        }

        $tipo = (string) ($data['tipo'] ?? '');
        if (! array_key_exists($tipo, RhExame::tipos())) {
            throw new InvalidArgumentException('Tipo de exame inválido.');
        }

        $conclusao = (string) ($data['conclusao'] ?? RhExame::CONCL_NAO_INFORMADA);
        if (! array_key_exists($conclusao, RhExame::conclusoes())) {
            throw new InvalidArgumentException('Conclusão ASO inválida.');
        }

        return DB::transaction(function () use ($data, $funcionario, $tipo, $conclusao, $aso): RhExame {
            $exame = RhExame::query()->create([
                'codigo' => RhExame::nextCodigo(),
                'funcionario_id' => $funcionario->getKey(),
                'tipo' => $tipo,
                'realizado_em' => $this->parseDate($data['realizado_em'] ?? null)
                    ?? throw new InvalidArgumentException('Data do exame é obrigatória.'),
                'proximo_em' => $this->parseDate($data['proximo_em'] ?? null),
                'medico_nome' => $this->blankUpper($data['medico_nome'] ?? null),
                'medico_crm' => $this->blank($data['medico_crm'] ?? null),
                'conclusao' => $conclusao,
                'observacao' => $this->blank($data['observacao'] ?? null),
                'user_id' => Auth::id(),
                'ativo' => true,
            ]);

            if ($aso instanceof UploadedFile) {
                $this->anexarAso($exame, $aso);
            }

            $this->log($exame, 'criar', null, $this->snapshot($exame), null);

            return $exame;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function alterar(RhExame $exame, array $data, ?UploadedFile $aso = null, ?string $motivo = null): RhExame
    {
        $antes = $this->snapshot($exame);

        if (filled($data['funcionario_id'] ?? null)) {
            $func = RhFuncionario::query()->find((int) $data['funcionario_id']);
            if (! $func) {
                throw new InvalidArgumentException('Funcionário inválido.');
            }
            $exame->funcionario_id = (int) $func->getKey();
        }

        if (filled($data['tipo'] ?? null)) {
            $tipo = (string) $data['tipo'];
            if (! array_key_exists($tipo, RhExame::tipos())) {
                throw new InvalidArgumentException('Tipo de exame inválido.');
            }
            $exame->tipo = $tipo;
        }

        if (array_key_exists('realizado_em', $data)) {
            $exame->realizado_em = $this->parseDate($data['realizado_em'])
                ?? throw new InvalidArgumentException('Data do exame é obrigatória.');
        }

        if (array_key_exists('proximo_em', $data)) {
            $exame->proximo_em = $this->parseDate($data['proximo_em']);
        }

        if (array_key_exists('medico_nome', $data)) {
            $exame->medico_nome = $this->blankUpper($data['medico_nome']);
        }

        if (array_key_exists('medico_crm', $data)) {
            $exame->medico_crm = $this->blank($data['medico_crm']);
        }

        if (array_key_exists('conclusao', $data)) {
            $conclusao = (string) ($data['conclusao'] ?: RhExame::CONCL_NAO_INFORMADA);
            if (! array_key_exists($conclusao, RhExame::conclusoes())) {
                throw new InvalidArgumentException('Conclusão ASO inválida.');
            }
            $exame->conclusao = $conclusao;
        }

        if (array_key_exists('observacao', $data)) {
            $exame->observacao = $this->blank($data['observacao']);
        }

        if (array_key_exists('ativo', $data)) {
            $exame->ativo = (bool) $data['ativo'];
        }

        $exame->save();

        if ($aso instanceof UploadedFile) {
            $this->anexarAso($exame, $aso, substituir: true);
        }

        $this->log($exame, 'alterar', $antes, $this->snapshot($exame), $motivo);

        return $exame;
    }

    public function inativar(RhExame $exame, ?string $motivo = null): RhExame
    {
        if (! $exame->ativo) {
            return $exame;
        }

        $antes = $this->snapshot($exame);
        $exame->update(['ativo' => false]);
        $this->log($exame, 'inativar', $antes, $this->snapshot($exame->fresh()), $motivo);

        return $exame->fresh();
    }

    public function anexarAso(RhExame $exame, UploadedFile $file, bool $substituir = false): RhAnexo
    {
        $service = app(RhAnexoService::class);

        if ($substituir) {
            $atuais = $exame->anexos()
                ->where('categoria', RhAnexo::CAT_EXAME)
                ->get();
            foreach ($atuais as $anexo) {
                $service->delete($anexo);
            }
        }

        $anexo = $service->store($exame, $file, [
            'titulo' => 'ASO '.$exame->codigo,
            'categoria' => RhAnexo::CAT_EXAME,
            'emitido_em' => $exame->realizado_em?->toDateString(),
            'valido_ate' => $exame->proximo_em?->toDateString(),
            'observacao' => 'ASO anexado (cópia do documento emitido)',
        ]);

        $this->log($exame, 'anexar', null, [
            'anexo_id' => $anexo->getKey(),
            'titulo' => $anexo->titulo,
        ], null);

        return $anexo;
    }

    public function registrarAcessoAnexo(RhExame $exame, RhAnexo $anexo): void
    {
        $this->log($exame, 'acessar_anexo', null, [
            'anexo_id' => $anexo->getKey(),
            'titulo' => $anexo->titulo,
        ], null);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(RhExame $exame): array
    {
        return [
            'codigo' => $exame->codigo,
            'funcionario_id' => $exame->funcionario_id,
            'tipo' => $exame->tipo,
            'realizado_em' => $exame->realizado_em?->toDateString(),
            'proximo_em' => $exame->proximo_em?->toDateString(),
            'medico_nome' => $exame->medico_nome,
            'medico_crm' => $exame->medico_crm,
            'conclusao' => $exame->conclusao,
            'observacao' => $exame->observacao,
            'ativo' => $exame->ativo,
        ];
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

    private function blankUpper(mixed $value): ?string
    {
        $v = $this->blank($value);

        return $v === null ? null : mb_strtoupper($v, 'UTF-8');
    }

    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $depois
     */
    private function log(RhExame $exame, string $acao, ?array $antes, ?array $depois, ?string $motivo): void
    {
        RhExameLog::query()->create([
            'exame_id' => $exame->getKey(),
            'user_id' => Auth::id(),
            'acao' => $acao,
            'antes' => $antes,
            'depois' => $depois,
            'motivo' => $this->blank($motivo),
            'created_at' => now(),
        ]);
    }
}
