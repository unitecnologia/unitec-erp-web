<?php

namespace App\Support\Rh;

use App\Models\RhAnexo;
use App\Models\RhFuncionario;
use App\Models\RhOcorrencia;
use App\Models\RhOcorrenciaLog;
use App\Support\Erp\ErpAccess;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RhOcorrenciaService
{
    /**
     * @param  array{
     *   funcionario_id: int,
     *   tipo: string,
     *   ocorrido_em: string,
     *   descricao: string,
     *   responsavel?: string|null,
     *   acesso_restrito?: bool
     * }  $data
     */
    public function criar(array $data, ?UploadedFile $anexo = null): RhOcorrencia
    {
        $funcionario = RhFuncionario::query()->find((int) $data['funcionario_id']);
        if (! $funcionario) {
            throw new InvalidArgumentException('Funcionário inválido.');
        }

        $tipo = (string) ($data['tipo'] ?? '');
        if (! array_key_exists($tipo, RhOcorrencia::tipos())) {
            throw new InvalidArgumentException('Tipo de ocorrência inválido.');
        }

        $descricao = $this->blank($data['descricao'] ?? null);
        if ($descricao === null) {
            throw new InvalidArgumentException('Descrição é obrigatória.');
        }

        return DB::transaction(function () use ($data, $funcionario, $tipo, $descricao, $anexo): RhOcorrencia {
            $ocorrencia = RhOcorrencia::query()->create([
                'codigo' => RhOcorrencia::nextCodigo(),
                'funcionario_id' => $funcionario->getKey(),
                'tipo' => $tipo,
                'ocorrido_em' => $this->parseDate($data['ocorrido_em'] ?? null)
                    ?? throw new InvalidArgumentException('Data da ocorrência é obrigatória.'),
                'descricao' => $descricao,
                'responsavel' => $this->blankUpper($data['responsavel'] ?? null),
                'situacao' => RhOcorrencia::SIT_REGISTRADA,
                'acesso_restrito' => (bool) ($data['acesso_restrito'] ?? false),
                'user_id' => Auth::id(),
                'ativo' => true,
            ]);

            if ($anexo instanceof UploadedFile) {
                $this->anexar($ocorrencia, $anexo);
            }

            $this->log($ocorrencia, 'criar', null, $this->snapshot($ocorrencia), null);

            return $ocorrencia;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function alterar(RhOcorrencia $ocorrencia, array $data, ?UploadedFile $anexo = null, ?string $motivo = null): RhOcorrencia
    {
        if ($ocorrencia->isCancelada()) {
            throw new InvalidArgumentException('Ocorrência cancelada não pode ser alterada.');
        }

        $antes = $this->snapshot($ocorrencia);
        $situacaoAnterior = (string) $ocorrencia->situacao;

        if (filled($data['funcionario_id'] ?? null)) {
            $func = RhFuncionario::query()->find((int) $data['funcionario_id']);
            if (! $func) {
                throw new InvalidArgumentException('Funcionário inválido.');
            }
            $ocorrencia->funcionario_id = (int) $func->getKey();
        }

        if (filled($data['tipo'] ?? null)) {
            $tipo = (string) $data['tipo'];
            if (! array_key_exists($tipo, RhOcorrencia::tipos())) {
                throw new InvalidArgumentException('Tipo de ocorrência inválido.');
            }
            $ocorrencia->tipo = $tipo;
        }

        if (array_key_exists('ocorrido_em', $data)) {
            $ocorrencia->ocorrido_em = $this->parseDate($data['ocorrido_em'])
                ?? throw new InvalidArgumentException('Data da ocorrência é obrigatória.');
        }

        if (array_key_exists('descricao', $data)) {
            $descricao = $this->blank($data['descricao']);
            if ($descricao === null) {
                throw new InvalidArgumentException('Descrição é obrigatória.');
            }
            $ocorrencia->descricao = $descricao;
        }

        if (array_key_exists('responsavel', $data)) {
            $ocorrencia->responsavel = $this->blankUpper($data['responsavel']);
        }

        if (array_key_exists('acesso_restrito', $data)) {
            $ocorrencia->acesso_restrito = (bool) $data['acesso_restrito'];
        }

        if ($situacaoAnterior === RhOcorrencia::SIT_REGISTRADA) {
            $ocorrencia->situacao = RhOcorrencia::SIT_CORRIGIDA;
        }

        $ocorrencia->save();

        if ($anexo instanceof UploadedFile) {
            $this->anexar($ocorrencia, $anexo, substituir: true);
        }

        $acao = $situacaoAnterior === RhOcorrencia::SIT_REGISTRADA ? 'corrigir' : 'alterar';
        $this->log($ocorrencia, $acao, $antes, $this->snapshot($ocorrencia->fresh()), $motivo);

        return $ocorrencia->fresh();
    }

    public function cancelar(RhOcorrencia $ocorrencia, ?string $motivo = null): RhOcorrencia
    {
        if ($ocorrencia->isCancelada()) {
            return $ocorrencia;
        }

        $antes = $this->snapshot($ocorrencia);
        $ocorrencia->update([
            'situacao' => RhOcorrencia::SIT_CANCELADA,
            'ativo' => false,
        ]);
        $this->log($ocorrencia, 'cancelar', $antes, $this->snapshot($ocorrencia->fresh()), $motivo);

        return $ocorrencia->fresh();
    }

    public function anexar(RhOcorrencia $ocorrencia, UploadedFile $file, bool $substituir = false): RhAnexo
    {
        $service = app(RhAnexoService::class);

        if ($substituir) {
            $atuais = $ocorrencia->anexos()
                ->where('categoria', RhAnexo::CAT_OCORRENCIA)
                ->get();
            foreach ($atuais as $item) {
                $service->delete($item);
            }
        }

        $anexo = $service->store($ocorrencia, $file, [
            'titulo' => 'OCORRÊNCIA '.$ocorrencia->codigo,
            'categoria' => RhAnexo::CAT_OCORRENCIA,
            'emitido_em' => $ocorrencia->ocorrido_em?->toDateString(),
            'observacao' => 'Anexo opcional do registro de ocorrência',
        ]);

        $this->log($ocorrencia, 'anexar', null, [
            'anexo_id' => $anexo->getKey(),
            'titulo' => $anexo->titulo,
        ], null);

        return $anexo;
    }

    public function registrarAcessoAnexo(RhOcorrencia $ocorrencia, RhAnexo $anexo): void
    {
        $this->log($ocorrencia, 'acessar_anexo', null, [
            'anexo_id' => $anexo->getKey(),
            'titulo' => $anexo->titulo,
        ], null);
    }

    public static function podeVerConteudoRestrito(): bool
    {
        return ErpAccess::currentCan('rh.ocorrencias.access');
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(RhOcorrencia $ocorrencia, bool $incluirDescricaoSensivel = true): array
    {
        $podeVer = $incluirDescricaoSensivel && self::podeVerConteudoRestrito();

        return [
            'codigo' => $ocorrencia->codigo,
            'funcionario_id' => $ocorrencia->funcionario_id,
            'tipo' => $ocorrencia->tipo,
            'ocorrido_em' => $ocorrencia->ocorrido_em?->toDateString(),
            'descricao' => ($ocorrencia->acesso_restrito && ! $podeVer)
                ? null
                : $ocorrencia->descricao,
            'responsavel' => $ocorrencia->responsavel,
            'situacao' => $ocorrencia->situacao,
            'acesso_restrito' => $ocorrencia->acesso_restrito,
            'ativo' => $ocorrencia->ativo,
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
    private function log(RhOcorrencia $ocorrencia, string $acao, ?array $antes, ?array $depois, ?string $motivo): void
    {
        RhOcorrenciaLog::query()->create([
            'ocorrencia_id' => $ocorrencia->getKey(),
            'user_id' => Auth::id(),
            'acao' => $acao,
            'antes' => $antes,
            'depois' => $depois,
            'motivo' => $this->blank($motivo),
            'created_at' => now(),
        ]);
    }
}
