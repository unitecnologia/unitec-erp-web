<?php

namespace App\Support\Erp\Financeiro\Conciliacao;

use App\Models\CaixaConta;
use App\Models\ExtratoImportacao;
use App\Models\ExtratoLancamento;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

/**
 * Importação OFX → extrato_* (C2).
 * Não gera baixa, receita, CR/CP, PIX, SuperTEF nem Livro Caixa.
 */
final class OfxImportService
{
    public function __construct(
        private readonly OfxParser $parser = new OfxParser,
    ) {}

    public function maxBytes(): int
    {
        return (int) config('unitec.conciliacao.ofx_max_bytes', 8 * 1024 * 1024);
    }

    public function batchSize(): int
    {
        return (int) config('unitec.conciliacao.ofx_batch_size', 200);
    }

    /**
     * @param  array{empresa_id:int,caixa_conta_id:int,arquivo_nome:string,conteudo:string,user_id?:int|null}  $input
     *
     * @throws InvalidArgumentException
     */
    public function import(array $input): OfxImportResult
    {
        if (! Schema::hasTable('extrato_importacoes') || ! Schema::hasTable('extrato_lancamentos')) {
            throw new InvalidArgumentException('Estrutura de extrato não disponível. Atualize o sistema.');
        }

        $empresaId = (int) ($input['empresa_id'] ?? 0);
        $contaId = (int) ($input['caixa_conta_id'] ?? 0);
        $arquivoNome = trim((string) ($input['arquivo_nome'] ?? 'extrato.ofx'));
        $conteudo = (string) ($input['conteudo'] ?? '');
        $userId = isset($input['user_id']) ? (int) $input['user_id'] : (int) (Auth::id() ?? 0);

        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Empresa não identificada.');
        }
        if ($contaId <= 0) {
            throw new InvalidArgumentException('Selecione a conta bancária antes de importar o OFX.');
        }

        $conta = $this->resolverContaBanco($contaId, $empresaId);
        $this->assertExtensao($arquivoNome);
        $this->assertTamanho($conteudo);

        $sha = hash('sha256', $conteudo);
        $lockKey = 'extrato-ofx:'.$empresaId.':'.$contaId.':'.$sha;
        $lock = Cache::lock($lockKey, 180);

        if (! $lock->get()) {
            throw new InvalidArgumentException('Já existe uma importação deste arquivo em andamento. Aguarde e tente de novo.');
        }

        try {
            $existente = ExtratoImportacao::query()
                ->where('empresa_id', $empresaId)
                ->where('caixa_conta_id', $contaId)
                ->where('arquivo_sha256', $sha)
                ->first();

            if ($existente && $existente->status === ExtratoImportacao::STATUS_CONCLUIDA) {
                return $this->resultFromImportacao($existente, reimportacao: true);
            }

            $parsed = $this->parser->parse($conteudo, $arquivoNome);
            $contaCheck = $this->avaliarContaBancaria($conta, $parsed);

            if ($contaCheck['status'] === 'incompativel') {
                throw new InvalidArgumentException($contaCheck['mensagem']);
            }

            $warnings = $parsed->warnings;
            if (($contaCheck['status'] === 'dados_incompletos' || $contaCheck['status'] === 'ok')
                && $contaCheck['mensagem'] !== '') {
                $warnings[] = $contaCheck['mensagem'];
            }

            $importacao = $existente ?? new ExtratoImportacao([
                'empresa_id' => $empresaId,
                'caixa_conta_id' => $contaId,
                'arquivo_nome' => mb_substr($arquivoNome, 0, 255, 'UTF-8'),
                'arquivo_sha256' => $sha,
                'formato' => ExtratoImportacao::FORMATO_OFX,
                'status' => ExtratoImportacao::STATUS_PENDENTE,
            ]);

            $importacao->fill([
                'banco_id_arquivo' => $parsed->bankId,
                'agencia_arquivo' => $parsed->branchId,
                'conta_arquivo' => $parsed->accountId,
                'alerta_conta_incompativel' => false,
                'periodo_inicio' => $parsed->periodoInicio,
                'periodo_fim' => $parsed->periodoFim,
                'total_linhas' => count($parsed->transactions),
                'total_creditos' => $parsed->totalCreditos(),
                'total_debitos' => $parsed->totalDebitos(),
                'imported_by_user_id' => $userId > 0 ? $userId : null,
                'status' => ExtratoImportacao::STATUS_PROCESSANDO,
                'mensagem_erro' => null,
            ]);
            $importacao->save();

            return $this->persistirLotes($importacao, $parsed, $warnings);
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (Throwable $e) {
            // Sem conteúdo OFX na mensagem (evita vazar extrato em log/UI).
            throw new InvalidArgumentException('Falha ao importar OFX: '.$this->mensagemSegura($e), 0, $e);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  list<string>  $warningsIniciais
     */
    private function persistirLotes(
        ExtratoImportacao $importacao,
        OfxParseResult $parsed,
        array $warningsIniciais,
    ): OfxImportResult {
        $empresaId = (int) $importacao->empresa_id;
        $contaId = (int) $importacao->caixa_conta_id;
        $batchSize = $this->batchSize();
        $startFrom = (int) $importacao->ultimo_lote_ok;

        $importados = (int) $importacao->total_importados;
        $duplicados = (int) $importacao->total_duplicados;
        $erros = (int) $importacao->total_erros;
        $ambiguos = (int) $importacao->total_ambiguos;
        $warnings = $warningsIniciais;
        $coincidenciaSemFitid = 0;

        $ocorrenciasArquivo = [];
        $dbSoftCountsInitial = $this->carregarContagemSoftKeysSemFitid($empresaId, $contaId, $parsed);
        $dbSoftCounts = $dbSoftCountsInitial;

        $txs = $parsed->transactions;
        $total = count($txs);

        for ($i = 0; $i < $startFrom && $i < $total; $i++) {
            $prev = $txs[$i];
            $sk = ExtratoLancamento::softIdentityKey(
                $prev->dataMovimento,
                $prev->tipo,
                $prev->valor,
                $prev->historico,
                $prev->memo,
                $prev->payee,
                $prev->checkNum,
                $prev->refNum,
            );
            $ocorrenciasArquivo[$sk] = ($ocorrenciasArquivo[$sk] ?? 0) + 1;
        }

        try {
            for ($offset = $startFrom; $offset < $total; $offset += $batchSize) {
                $slice = array_slice($txs, $offset, $batchSize);

                DB::transaction(function () use (
                    $slice,
                    $importacao,
                    $empresaId,
                    $contaId,
                    &$importados,
                    &$duplicados,
                    &$erros,
                    &$ambiguos,
                    &$ocorrenciasArquivo,
                    &$dbSoftCounts,
                    $dbSoftCountsInitial,
                    &$coincidenciaSemFitid,
                    $offset,
                    $batchSize,
                    $total,
                ): void {
                    $prepared = [];

                    foreach ($slice as $tx) {
                        $hasFitid = $tx->fitid !== null && trim($tx->fitid) !== '';
                        $softKey = ExtratoLancamento::softIdentityKey(
                            $tx->dataMovimento,
                            $tx->tipo,
                            $tx->valor,
                            $tx->historico,
                            $tx->memo,
                            $tx->payee,
                            $tx->checkNum,
                            $tx->refNum,
                        );

                        $ocorrenciasArquivo[$softKey] = ($ocorrenciasArquivo[$softKey] ?? 0) + 1;
                        $ocorrencia = $ocorrenciasArquivo[$softKey];

                        if (! $hasFitid) {
                            $dbCount = (int) ($dbSoftCounts[$softKey] ?? 0);
                            if ($ocorrencia <= $dbCount) {
                                // Sobreposição provável sem identidade bancária: não duplica, mas sinaliza.
                                $duplicados++;
                                $ambiguos++;
                                $coincidenciaSemFitid++;

                                continue;
                            }
                        }

                        $fingerprint = ExtratoLancamento::buildFingerprint(
                            fitid: $tx->fitid,
                            dataMovimento: $tx->dataMovimento,
                            tipo: $tx->tipo,
                            valor: $tx->valor,
                            historico: $tx->historico,
                            memo: $tx->memo,
                            ocorrencia: $ocorrencia,
                            payee: $tx->payee,
                            checkNum: $tx->checkNum,
                            refNum: $tx->refNum,
                            empresaId: $empresaId,
                            caixaContaId: $contaId,
                        );

                        // Só marca ambíguo se identidade fraca OU extra além do já existente sem FITID.
                        $initial = (int) ($dbSoftCountsInitial[$softKey] ?? 0);
                        $extraSemFitid = ! $hasFitid && $initial > 0 && $ocorrencia > $initial;
                        $ambiguo = $tx->ambiguo || $extraSemFitid;

                        $prepared[] = [
                            'tx' => $tx,
                            'fingerprint' => $fingerprint,
                            'ambiguo' => $ambiguo,
                            'soft_key' => $softKey,
                            'has_fitid' => $hasFitid,
                        ];
                    }

                    $fps = array_values(array_unique(array_column($prepared, 'fingerprint')));
                    $existing = $fps === []
                        ? []
                        : ExtratoLancamento::query()
                            ->where('empresa_id', $empresaId)
                            ->where('caixa_conta_id', $contaId)
                            ->whereIn('fingerprint', $fps)
                            ->pluck('fingerprint')
                            ->all();
                    $existingMap = array_fill_keys($existing, true);

                    $rows = [];
                    foreach ($prepared as $item) {
                        if (isset($existingMap[$item['fingerprint']])) {
                            $duplicados++;
                            if (! $item['has_fitid']) {
                                $ambiguos++;
                                $coincidenciaSemFitid++;
                            }

                            continue;
                        }

                        $tx = $item['tx'];
                        $rows[] = [
                            'empresa_id' => $empresaId,
                            'caixa_conta_id' => $contaId,
                            'extrato_importacao_id' => (int) $importacao->id,
                            'data_movimento' => $tx->dataMovimento,
                            'data_posted' => $tx->dataPosted,
                            'tipo' => $tx->tipo,
                            'valor' => $tx->valor,
                            'historico' => $tx->historico,
                            'memo' => $tx->memo,
                            'payee' => $tx->payee,
                            'check_num' => $tx->checkNum,
                            'ref_num' => $tx->refNum,
                            'fitid' => $tx->fitid,
                            'fingerprint' => $item['fingerprint'],
                            'situacao' => ExtratoLancamento::SITUACAO_PENDENTE,
                            'ambiguo' => $item['ambiguo'],
                            'origem_tipo' => 'ofx',
                            'origem_id' => (int) $importacao->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        $existingMap[$item['fingerprint']] = true;

                        if (! $item['has_fitid']) {
                            $dbSoftCounts[$item['soft_key']] = (int) ($dbSoftCounts[$item['soft_key']] ?? 0) + 1;
                        }

                        if ($item['ambiguo']) {
                            $ambiguos++;
                        }
                    }

                    if ($rows !== []) {
                        try {
                            ExtratoLancamento::query()->insert($rows);
                            $importados += count($rows);
                        } catch (Throwable $e) {
                            foreach ($rows as $row) {
                                try {
                                    ExtratoLancamento::query()->insert([$row]);
                                    $importados++;
                                } catch (Throwable) {
                                    $duplicados++;
                                }
                            }
                        }
                    }

                    $importacao->forceFill([
                        'total_importados' => $importados,
                        'total_duplicados' => $duplicados,
                        'total_erros' => $erros,
                        'total_ambiguos' => $ambiguos,
                        'ultimo_lote_ok' => min($total, $offset + $batchSize),
                        'status' => ExtratoImportacao::STATUS_PROCESSANDO,
                    ])->save();
                });
            }

            $erros += count($parsed->warnings);

            if ($coincidenciaSemFitid > 0) {
                $warnings[] = $coincidenciaSemFitid.' lançamento(s) sem FITID coincidiram com extrato já importado — confira se não há movimento novo omitido.';
            }

            $importacao->forceFill([
                'status' => ExtratoImportacao::STATUS_CONCLUIDA,
                'total_importados' => $importados,
                'total_duplicados' => $duplicados,
                'total_erros' => $erros,
                'total_ambiguos' => $ambiguos,
                'ultimo_lote_ok' => $total,
                'imported_at' => now(),
                'mensagem_erro' => $warnings !== []
                    ? mb_substr(implode('; ', array_slice($warnings, 0, 10)), 0, 2000, 'UTF-8')
                    : null,
            ])->save();

            return $this->resultFromImportacao($importacao->fresh(), reimportacao: false, warnings: $warnings);
        } catch (Throwable $e) {
            $importacao->forceFill([
                'status' => ExtratoImportacao::STATUS_ERRO,
                'total_importados' => $importados,
                'total_duplicados' => $duplicados,
                'total_erros' => $erros + 1,
                'total_ambiguos' => $ambiguos,
                'mensagem_erro' => mb_substr($this->mensagemSegura($e), 0, 2000, 'UTF-8'),
            ])->save();

            throw new InvalidArgumentException(
                'Importação interrompida após lote '.(int) $importacao->ultimo_lote_ok.'. Reenvie o mesmo arquivo para retomar. '.$this->mensagemSegura($e),
                0,
                $e
            );
        }
    }

    /**
     * Contagens de chave fraca já persistidas (somente linhas sem FITID).
     *
     * @return array<string, int>
     */
    private function carregarContagemSoftKeysSemFitid(int $empresaId, int $contaId, OfxParseResult $parsed): array
    {
        $precisa = false;
        foreach ($parsed->transactions as $tx) {
            if ($tx->fitid === null || trim($tx->fitid) === '') {
                $precisa = true;
                break;
            }
        }

        if (! $precisa) {
            return [];
        }

        $rows = ExtratoLancamento::query()
            ->select(['data_movimento', 'tipo', 'valor', 'historico', 'memo', 'payee', 'check_num', 'ref_num'])
            ->where('empresa_id', $empresaId)
            ->where('caixa_conta_id', $contaId)
            ->where(function ($q): void {
                $q->whereNull('fitid')->orWhere('fitid', '');
            })
            ->limit(30000)
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $key = ExtratoLancamento::softIdentityKey(
                optional($row->data_movimento)?->toDateString() ?? '',
                (string) $row->tipo,
                number_format((float) $row->valor, 2, '.', ''),
                $row->historico,
                $row->memo,
                $row->payee,
                $row->check_num,
                $row->ref_num,
            );
            $map[$key] = ($map[$key] ?? 0) + 1;
        }

        return $map;
    }

    private function resolverContaBanco(int $contaId, int $empresaId): CaixaConta
    {
        $conta = CaixaConta::query()
            ->whereKey($contaId)
            ->where('tipo', CaixaConta::TIPO_BANCO)
            ->where('ativo', true)
            ->visiveisNaEmpresa($empresaId)
            ->first();

        if (! $conta) {
            throw new InvalidArgumentException(
                'Nenhuma conta BANCO ativa válida para esta empresa. Cadastre ou ative a conta no fluxo de Contas (Caixa) antes de importar.'
            );
        }

        return $conta;
    }

    private function assertExtensao(string $nome): void
    {
        $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
        $allowed = config('unitec.conciliacao.ofx_extensions', ['ofx', 'qfx']);
        if (! in_array($ext, $allowed, true)) {
            throw new InvalidArgumentException('Extensão inválida. Use arquivo .ofx (ou .qfx).');
        }
    }

    private function assertTamanho(string $conteudo): void
    {
        $len = strlen($conteudo);
        if ($len === 0) {
            throw new InvalidArgumentException('Arquivo OFX vazio.');
        }
        $max = $this->maxBytes();
        if ($len > $max) {
            throw new InvalidArgumentException(
                'Arquivo excede o limite de '.number_format($max / 1048576, 1, ',', '.').' MB.'
            );
        }
    }

    /**
     * @return array{status: 'ok'|'incompativel'|'dados_incompletos', mensagem: string}
     */
    private function avaliarContaBancaria(CaixaConta $conta, OfxParseResult $parsed): array
    {
        return (new OfxContaEquivalence)->avaliar($conta, $parsed);
    }

    private function mensagemSegura(Throwable $e): string
    {
        $msg = trim($e->getMessage());
        // Evita ecoar blobs grandes (conteúdo OFX) em UI/mensagem_erro.
        if (strlen($msg) > 300) {
            $msg = mb_substr($msg, 0, 300, 'UTF-8').'…';
        }

        return $msg !== '' ? $msg : 'erro interno';
    }

    /**
     * @param  list<string>  $warnings
     */
    private function resultFromImportacao(
        ExtratoImportacao $imp,
        bool $reimportacao,
        array $warnings = [],
    ): OfxImportResult {
        $periodoDe = optional($imp->periodo_inicio)?->toDateString();
        $periodoAte = optional($imp->periodo_fim)?->toDateString();

        if (($periodoDe === null || $periodoAte === null) && (int) $imp->id > 0) {
            $bounds = ExtratoLancamento::query()
                ->where('extrato_importacao_id', (int) $imp->id)
                ->selectRaw('MIN(data_movimento) as dmin, MAX(data_movimento) as dmax')
                ->first();
            $periodoDe = $periodoDe ?: (isset($bounds->dmin) ? (string) $bounds->dmin : null);
            $periodoAte = $periodoAte ?: (isset($bounds->dmax) ? (string) $bounds->dmax : null);
        }

        return new OfxImportResult(
            importacaoId: (int) $imp->id,
            status: (string) $imp->status,
            lidos: (int) $imp->total_linhas,
            importados: (int) $imp->total_importados,
            jaExistentes: (int) $imp->total_duplicados,
            erros: (int) $imp->total_erros,
            ambiguos: (int) $imp->total_ambiguos,
            totalEntradas: number_format((float) $imp->total_creditos, 2, '.', ''),
            totalSaidas: number_format((float) $imp->total_debitos, 2, '.', ''),
            alertaContaIncompativel: (bool) $imp->alerta_conta_incompativel,
            reimportacaoIdempotente: $reimportacao,
            warnings: $warnings,
            mensagem: $imp->mensagem_erro,
            periodoDe: $periodoDe,
            periodoAte: $periodoAte,
        );
    }
}
