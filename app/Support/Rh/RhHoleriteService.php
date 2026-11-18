<?php

namespace App\Support\Rh;

use App\Models\RhAnexo;
use App\Models\RhFuncionario;
use App\Models\RhHolerite;
use App\Models\RhHoleriteLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RhHoleriteService
{
    /**
     * @param  array{
     *   funcionario_id: int,
     *   competencia_ano: int,
     *   competencia_mes: int,
     *   pago_em?: string|null,
     *   observacao?: string|null
     * }  $data
     */
    public function criar(array $data, UploadedFile $pdf): RhHolerite
    {
        $funcionario = RhFuncionario::query()->find((int) $data['funcionario_id']);
        if (! $funcionario) {
            throw new InvalidArgumentException('Funcionário inválido.');
        }

        [$ano, $mes] = $this->parseCompetencia($data);

        $existente = $this->findPorCompetencia((int) $funcionario->getKey(), $ano, $mes);
        if ($existente) {
            if ($existente->ativo) {
                throw new InvalidArgumentException(
                    'Já existe holerite ativo para esta competência. Confirme a substituição informando o motivo.'
                );
            }

            throw new InvalidArgumentException(
                'Já existe holerite inativo para esta competência. Use substituição para republicar o arquivo.'
            );
        }

        return DB::transaction(function () use ($data, $funcionario, $ano, $mes, $pdf): RhHolerite {
            $holerite = RhHolerite::query()->create([
                'codigo' => RhHolerite::nextCodigo(),
                'funcionario_id' => $funcionario->getKey(),
                'competencia_ano' => $ano,
                'competencia_mes' => $mes,
                'pago_em' => $this->parseDate($data['pago_em'] ?? null),
                'observacao' => $this->blank($data['observacao'] ?? null),
                'user_id' => Auth::id(),
                'ativo' => true,
            ]);

            $this->anexarPdf($holerite, $pdf);
            $this->log($holerite, 'criar', null, $this->snapshot($holerite), null);

            return $holerite;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function substituir(RhHolerite $holerite, array $data, ?UploadedFile $pdf, string $motivo): RhHolerite
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new InvalidArgumentException('Informe o motivo da substituição.');
        }

        $antes = $this->snapshot($holerite);

        if (filled($data['funcionario_id'] ?? null)) {
            $func = RhFuncionario::query()->find((int) $data['funcionario_id']);
            if (! $func) {
                throw new InvalidArgumentException('Funcionário inválido.');
            }
            $holerite->funcionario_id = (int) $func->getKey();
        }

        if (array_key_exists('competencia_ano', $data) || array_key_exists('competencia_mes', $data)) {
            [$ano, $mes] = $this->parseCompetencia(array_merge([
                'competencia_ano' => $holerite->competencia_ano,
                'competencia_mes' => $holerite->competencia_mes,
            ], $data));

            $outro = RhHolerite::query()
                ->where('funcionario_id', $holerite->funcionario_id)
                ->where('competencia_ano', $ano)
                ->where('competencia_mes', $mes)
                ->whereKeyNot($holerite->getKey())
                ->exists();

            if ($outro) {
                throw new InvalidArgumentException('Outro holerite já utiliza esta competência para o funcionário.');
            }

            $holerite->competencia_ano = $ano;
            $holerite->competencia_mes = $mes;
        }

        if (array_key_exists('pago_em', $data)) {
            $holerite->pago_em = $this->parseDate($data['pago_em']);
        }

        if (array_key_exists('observacao', $data)) {
            $holerite->observacao = $this->blank($data['observacao']);
        }

        $holerite->ativo = true;
        $holerite->save();

        if ($pdf instanceof UploadedFile) {
            $this->anexarPdf($holerite, $pdf, substituir: true);
        }

        $this->log($holerite, 'substituir', $antes, $this->snapshot($holerite->fresh()), $motivo);

        return $holerite->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function alterar(RhHolerite $holerite, array $data, ?UploadedFile $pdf = null, ?string $motivo = null): RhHolerite
    {
        $antes = $this->snapshot($holerite);

        if (filled($data['funcionario_id'] ?? null)) {
            $func = RhFuncionario::query()->find((int) $data['funcionario_id']);
            if (! $func) {
                throw new InvalidArgumentException('Funcionário inválido.');
            }
            $holerite->funcionario_id = (int) $func->getKey();
        }

        if (array_key_exists('competencia_ano', $data) || array_key_exists('competencia_mes', $data)) {
            [$ano, $mes] = $this->parseCompetencia(array_merge([
                'competencia_ano' => $holerite->competencia_ano,
                'competencia_mes' => $holerite->competencia_mes,
            ], $data));

            $outro = RhHolerite::query()
                ->where('funcionario_id', $holerite->funcionario_id)
                ->where('competencia_ano', $ano)
                ->where('competencia_mes', $mes)
                ->whereKeyNot($holerite->getKey())
                ->exists();

            if ($outro) {
                throw new InvalidArgumentException('Outro holerite já utiliza esta competência para o funcionário.');
            }

            $holerite->competencia_ano = $ano;
            $holerite->competencia_mes = $mes;
        }

        if (array_key_exists('pago_em', $data)) {
            $holerite->pago_em = $this->parseDate($data['pago_em']);
        }

        if (array_key_exists('observacao', $data)) {
            $holerite->observacao = $this->blank($data['observacao']);
        }

        if (array_key_exists('ativo', $data)) {
            $holerite->ativo = (bool) $data['ativo'];
        }

        $holerite->save();

        if ($pdf instanceof UploadedFile) {
            $this->anexarPdf($holerite, $pdf, substituir: true);
        }

        $this->log($holerite, 'alterar', $antes, $this->snapshot($holerite->fresh()), $motivo);

        return $holerite->fresh();
    }

    public function inativar(RhHolerite $holerite, ?string $motivo = null): RhHolerite
    {
        if (! $holerite->ativo) {
            return $holerite;
        }

        $antes = $this->snapshot($holerite);
        $holerite->update(['ativo' => false]);
        $this->log($holerite, 'inativar', $antes, $this->snapshot($holerite->fresh()), $motivo);

        return $holerite->fresh();
    }

    public function cancelar(RhHolerite $holerite, ?string $motivo = null): RhHolerite
    {
        $antes = $this->snapshot($holerite);
        $holerite->update(['ativo' => false]);
        $this->log($holerite, 'cancelar', $antes, $this->snapshot($holerite->fresh()), $motivo);

        return $holerite->fresh();
    }

    /**
     * @param  list<array{linha?: int, funcionario_id: int, competencia_ano: int, competencia_mes: int, arquivo: UploadedFile}>  $itens
     * @return array{valido: bool, linhas: list<array{linha: int, ok: bool, mensagem: string, funcionario?: string, competencia?: string}>}
     */
    public function conferirLote(array $itens): array
    {
        $linhas = [];

        foreach ($itens as $index => $item) {
            $linha = (int) ($item['linha'] ?? ($index + 1));
            $linhas[] = $this->validarItemLote($linha, $item);
        }

        $valido = collect($linhas)->every(fn (array $row): bool => (bool) ($row['ok'] ?? false));

        return [
            'valido' => $valido,
            'linhas' => $linhas,
        ];
    }

    /**
     * @param  list<array{linha?: int, funcionario_id: int, competencia_ano: int, competencia_mes: int, arquivo: UploadedFile}>  $itens
     * @return array{importados: int, erros: list<array{linha: int, mensagem: string}>}
     */
    public function importarLote(array $itens): array
    {
        $conferencia = $this->conferirLote($itens);
        if (! $conferencia['valido']) {
            $erros = [];
            foreach ($conferencia['linhas'] as $row) {
                if (! ($row['ok'] ?? false)) {
                    $erros[] = [
                        'linha' => (int) $row['linha'],
                        'mensagem' => (string) $row['mensagem'],
                    ];
                }
            }

            return ['importados' => 0, 'erros' => $erros];
        }

        $importados = 0;

        DB::transaction(function () use ($itens, &$importados): void {
            foreach ($itens as $item) {
                $funcionarioId = (int) $item['funcionario_id'];
                [$ano, $mes] = $this->parseCompetencia($item);
                /** @var UploadedFile $arquivo */
                $arquivo = $item['arquivo'];

                $existente = $this->findPorCompetencia($funcionarioId, $ano, $mes);
                if ($existente) {
                    $this->substituir($existente, [
                        'funcionario_id' => $funcionarioId,
                        'competencia_ano' => $ano,
                        'competencia_mes' => $mes,
                    ], $arquivo, 'Importação em lote');
                } else {
                    $this->criar([
                        'funcionario_id' => $funcionarioId,
                        'competencia_ano' => $ano,
                        'competencia_mes' => $mes,
                    ], $arquivo);
                }

                $importados++;
            }
        });

        return ['importados' => $importados, 'erros' => []];
    }

    public function anexarPdf(RhHolerite $holerite, UploadedFile $file, bool $substituir = false): RhAnexo
    {
        $service = app(RhAnexoService::class);

        if ($substituir) {
            $atuais = $holerite->anexos()
                ->where('categoria', RhAnexo::CAT_HOLERITE)
                ->get();
            foreach ($atuais as $anexo) {
                $service->delete($anexo);
            }
        }

        $anexo = $service->store($holerite, $file, [
            'titulo' => 'Holerite '.$holerite->competenciaLabel(),
            'categoria' => RhAnexo::CAT_HOLERITE,
            'emitido_em' => $holerite->pago_em?->toDateString(),
            'observacao' => 'Holerite PDF (armazenamento privado)',
        ]);

        $this->log($holerite, 'anexar', null, [
            'anexo_id' => $anexo->getKey(),
            'titulo' => $anexo->titulo,
        ], null);

        return $anexo;
    }

    public function registrarAcessoAnexo(RhHolerite $holerite, RhAnexo $anexo): void
    {
        $this->log($holerite, 'acessar_anexo', null, [
            'anexo_id' => $anexo->getKey(),
            'titulo' => $anexo->titulo,
        ], null);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(RhHolerite $holerite): array
    {
        return [
            'codigo' => $holerite->codigo,
            'funcionario_id' => $holerite->funcionario_id,
            'competencia_ano' => $holerite->competencia_ano,
            'competencia_mes' => $holerite->competencia_mes,
            'pago_em' => $holerite->pago_em?->toDateString(),
            'observacao' => $holerite->observacao,
            'ativo' => $holerite->ativo,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{linha: int, ok: bool, mensagem: string, funcionario?: string, competencia?: string}
     */
    private function validarItemLote(int $linha, array $item): array
    {
        $funcionarioId = (int) ($item['funcionario_id'] ?? 0);
        if ($funcionarioId <= 0) {
            return [
                'linha' => $linha,
                'ok' => false,
                'mensagem' => 'Funcionário (ID) é obrigatório — associação automática por nome não é permitida.',
            ];
        }

        $funcionario = RhFuncionario::query()->find($funcionarioId, ['id', 'codigo', 'nome']);
        if (! $funcionario) {
            return [
                'linha' => $linha,
                'ok' => false,
                'mensagem' => 'Funcionário ID '.$funcionarioId.' não encontrado.',
            ];
        }

        try {
            [$ano, $mes] = $this->parseCompetencia($item);
        } catch (InvalidArgumentException $e) {
            return [
                'linha' => $linha,
                'ok' => false,
                'mensagem' => $e->getMessage(),
                'funcionario' => trim($funcionario->codigo.' — '.$funcionario->nome),
            ];
        }

        $arquivo = $item['arquivo'] ?? null;
        if (! $arquivo instanceof UploadedFile) {
            return [
                'linha' => $linha,
                'ok' => false,
                'mensagem' => 'Arquivo PDF obrigatório nesta linha.',
                'funcionario' => trim($funcionario->codigo.' — '.$funcionario->nome),
                'competencia' => sprintf('%02d/%d', $mes, $ano),
            ];
        }

        $ext = strtolower((string) $arquivo->getClientOriginalExtension());
        if ($ext !== 'pdf' && $arquivo->getMimeType() !== 'application/pdf') {
            return [
                'linha' => $linha,
                'ok' => false,
                'mensagem' => 'Informe arquivo PDF do holerite.',
                'funcionario' => trim($funcionario->codigo.' — '.$funcionario->nome),
                'competencia' => sprintf('%02d/%d', $mes, $ano),
            ];
        }

        if ((int) $arquivo->getSize() > RhAnexoService::MAX_BYTES) {
            return [
                'linha' => $linha,
                'ok' => false,
                'mensagem' => 'Arquivo maior que 5 MB.',
                'funcionario' => trim($funcionario->codigo.' — '.$funcionario->nome),
                'competencia' => sprintf('%02d/%d', $mes, $ano),
            ];
        }

        return [
            'linha' => $linha,
            'ok' => true,
            'mensagem' => 'OK — pronto para importar.',
            'funcionario' => trim($funcionario->codigo.' — '.$funcionario->nome),
            'competencia' => sprintf('%02d/%d', $mes, $ano),
        ];
    }

    private function findPorCompetencia(int $funcionarioId, int $ano, int $mes): ?RhHolerite
    {
        return RhHolerite::query()
            ->where('funcionario_id', $funcionarioId)
            ->where('competencia_ano', $ano)
            ->where('competencia_mes', $mes)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: int, 1: int}
     */
    private function parseCompetencia(array $data): array
    {
        $ano = (int) ($data['competencia_ano'] ?? 0);
        $mes = (int) ($data['competencia_mes'] ?? 0);

        if ($ano < 2000 || $ano > 2100) {
            throw new InvalidArgumentException('Ano de competência inválido.');
        }

        if ($mes < 1 || $mes > 12) {
            throw new InvalidArgumentException('Mês de competência inválido.');
        }

        return [$ano, $mes];
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
    private function log(RhHolerite $holerite, string $acao, ?array $antes, ?array $depois, ?string $motivo): void
    {
        RhHoleriteLog::query()->create([
            'holerite_id' => $holerite->getKey(),
            'user_id' => Auth::id(),
            'acao' => $acao,
            'antes' => $antes,
            'depois' => $depois,
            'motivo' => $this->blank($motivo),
            'created_at' => now(),
        ]);
    }
}
