<?php

namespace App\Support\Erp\Financeiro;

use App\Models\Compra;
use App\Models\ContaPagar;
use App\Models\PlanoConta;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class ContaPagarCadastroService
{
    public const INTERVALO_MESES = 'meses';

    public const INTERVALO_DIAS = 'dias';

    /**
     * @param  array{
     *     emissao: string,
     *     documento?: string|null,
     *     fornecedor_id: int,
     *     vencimento: string,
     *     historico?: string|null,
     *     valor: float|string,
     *     parcelas?: int,
     *     intervalo?: int,
     *     intervalo_unidade?: string,
     *     compra_id?: int
     * }  $dados
     * @return list<ContaPagar>
     */
    public function criar(array $dados): array
    {
        $fornecedorId = (int) ($dados['fornecedor_id'] ?? 0);
        $parcelas = max(1, min(120, (int) ($dados['parcelas'] ?? 1)));
        $valorTotal = ErpMoney::parseBr($dados['valor'] ?? 0);
        $intervalo = max(1, min(3650, (int) ($dados['intervalo'] ?? 1)));
        $unidade = $this->normalizarIntervaloUnidade($dados['intervalo_unidade'] ?? self::INTERVALO_MESES);

        if ($fornecedorId <= 0) {
            throw new InvalidArgumentException('Selecione o fornecedor.');
        }

        if ($valorTotal <= 0) {
            throw new InvalidArgumentException('Informe um valor maior que zero.');
        }

        $emissao = Carbon::parse((string) $dados['emissao'])->startOfDay();
        $vencimentoBase = Carbon::parse((string) $dados['vencimento'])->startOfDay();
        $historico = mb_strtoupper(trim((string) ($dados['historico'] ?? '')), 'UTF-8');
        $documentoBase = mb_strtoupper(trim((string) ($dados['documento'] ?? '')), 'UTF-8');
        $compraId = (int) ($dados['compra_id'] ?? 0);
        $empresaId = $this->resolveEmpresaId($compraId);
        $competencia = $this->normalizarCompetencia($dados['competencia'] ?? null);
        $planoContaId = $this->normalizarPlanoContaId($dados['plano_conta_id'] ?? null);

        $preview = $this->montarParcelas(
            valorTotal: $valorTotal,
            parcelas: $parcelas,
            vencimentoBase: $vencimentoBase->toDateString(),
            intervalo: $intervalo,
            unidade: $unidade,
        );

        return DB::transaction(function () use (
            $preview,
            $emissao,
            $historico,
            $documentoBase,
            $fornecedorId,
            $compraId,
            $empresaId,
            $parcelas,
            $competencia,
            $planoContaId,
        ): array {
            $criadas = [];

            foreach ($preview as $i => $parcela) {
                $documento = $documentoBase;
                if ($parcelas > 1 && $documento !== '') {
                    $documento .= '-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
                } elseif ($parcelas > 1) {
                    $documento = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT).'/'.$parcelas;
                }

                $payload = [
                    'empresa_id' => $empresaId,
                    'numero' => ContaPagar::nextNumero(),
                    'emissao' => $emissao->toDateString(),
                    'documento' => $documento !== '' ? $documento : null,
                    'fornecedor_id' => $fornecedorId,
                    'vencimento' => $parcela['vencimento'],
                    'produto' => $historico !== '' ? $historico : null,
                    'valor' => $parcela['valor'],
                    'desconto' => 0,
                    'juros' => 0,
                    'multa' => 0,
                    'valor_pago' => 0,
                    'pago_em' => null,
                ];
                $payload = $this->aplicarCamposDre($payload, $competencia, $planoContaId);

                if ($compraId > 0) {
                    $payload['compra_id'] = $compraId;
                }

                $criadas[] = ContaPagar::query()->create($payload);
            }

            return $criadas;
        });
    }

    /**
     * Cria contas a pagar a partir de uma lista explícita de parcelas (XML / tela Contas a Pagar).
     *
     * @param  array{
     *     emissao: string,
     *     documento?: string|null,
     *     fornecedor_id: int,
     *     historico?: string|null,
     *     compra_id?: int
     * }  $dados
     * @param  list<array{documento?: string, vencimento: string, valor: float|string}>  $parcelas
     * @return list<ContaPagar>
     */
    public function criarDeLista(array $dados, array $parcelas): array
    {
        $fornecedorId = (int) ($dados['fornecedor_id'] ?? 0);

        if ($fornecedorId <= 0) {
            throw new InvalidArgumentException('Selecione o fornecedor.');
        }

        if ($parcelas === []) {
            throw new InvalidArgumentException('Informe ao menos uma parcela.');
        }

        $emissao = Carbon::parse((string) $dados['emissao'])->startOfDay();
        $historico = mb_strtoupper(trim((string) ($dados['historico'] ?? '')), 'UTF-8');
        $documentoBase = mb_strtoupper(trim((string) ($dados['documento'] ?? '')), 'UTF-8');
        $compraId = (int) ($dados['compra_id'] ?? 0);
        $empresaId = $this->resolveEmpresaId($compraId);
        $competencia = $this->normalizarCompetencia($dados['competencia'] ?? null);
        $planoContaId = $this->normalizarPlanoContaId($dados['plano_conta_id'] ?? null);
        $totalParcelas = count($parcelas);

        return DB::transaction(function () use (
            $parcelas,
            $emissao,
            $historico,
            $documentoBase,
            $fornecedorId,
            $compraId,
            $empresaId,
            $totalParcelas,
            $competencia,
            $planoContaId,
        ): array {
            $criadas = [];

            foreach ($parcelas as $i => $parcela) {
                $valor = ErpMoney::parseBr($parcela['valor'] ?? 0);

                if ($valor <= 0) {
                    throw new InvalidArgumentException('Parcela '.($i + 1).' com valor inválido.');
                }

                $documento = mb_strtoupper(trim((string) ($parcela['documento'] ?? '')), 'UTF-8');
                if ($documento === '') {
                    $documento = $documentoBase !== ''
                        ? $documentoBase.'-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)
                        : ($i + 1).'/'.$totalParcelas;
                }

                $vencimentoRaw = trim((string) ($parcela['vencimento'] ?? ''));
                if ($vencimentoRaw === '') {
                    throw new InvalidArgumentException('Parcela '.($i + 1).' sem vencimento.');
                }

                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $vencimentoRaw) === 1) {
                    $vencimento = Carbon::createFromFormat('d/m/Y', $vencimentoRaw)->startOfDay();
                } else {
                    $vencimento = Carbon::parse($vencimentoRaw)->startOfDay();
                }

                $payload = [
                    'empresa_id' => $empresaId,
                    'numero' => ContaPagar::nextNumero(),
                    'emissao' => $emissao->toDateString(),
                    'documento' => $documento,
                    'fornecedor_id' => $fornecedorId,
                    'vencimento' => $vencimento->toDateString(),
                    'produto' => $historico !== '' ? $historico : null,
                    'valor' => $valor,
                    'desconto' => 0,
                    'juros' => 0,
                    'multa' => 0,
                    'valor_pago' => 0,
                    'pago_em' => null,
                ];
                $payload = $this->aplicarCamposDre($payload, $competencia, $planoContaId);

                if ($compraId > 0) {
                    $payload['compra_id'] = $compraId;
                }

                $criadas[] = ContaPagar::query()->create($payload);
            }

            return $criadas;
        });
    }

    /**
     * @param  array{
     *     emissao: string,
     *     documento?: string|null,
     *     fornecedor_id: int,
     *     vencimento: string,
     *     historico?: string|null,
     *     valor: float|string
     * }  $dados
     */
    public function atualizar(int $contaId, array $dados): ContaPagar
    {
        $conta = ContaPagar::query()->whereKey($contaId)->first();

        if (! $conta) {
            throw new InvalidArgumentException('Conta não encontrada.');
        }

        if ((float) $conta->valor_pago > 0) {
            throw new InvalidArgumentException('Conta já possui baixa. Estorne antes de alterar.');
        }

        $fornecedorId = (int) ($dados['fornecedor_id'] ?? 0);
        $valor = ErpMoney::parseBr($dados['valor'] ?? 0);

        if ($fornecedorId <= 0) {
            throw new InvalidArgumentException('Selecione o fornecedor.');
        }

        if ($valor <= 0) {
            throw new InvalidArgumentException('Informe um valor maior que zero.');
        }

        $historico = mb_strtoupper(trim((string) ($dados['historico'] ?? '')), 'UTF-8');
        $documento = mb_strtoupper(trim((string) ($dados['documento'] ?? '')), 'UTF-8');
        $competencia = $this->normalizarCompetencia($dados['competencia'] ?? null);
        $planoContaId = $this->normalizarPlanoContaId($dados['plano_conta_id'] ?? null);

        $fill = [
            'emissao' => Carbon::parse((string) $dados['emissao'])->toDateString(),
            'documento' => $documento !== '' ? $documento : null,
            'fornecedor_id' => $fornecedorId,
            'vencimento' => Carbon::parse((string) $dados['vencimento'])->toDateString(),
            'produto' => $historico !== '' ? $historico : null,
            'valor' => $valor,
        ];
        $fill = $this->aplicarCamposDre($fill, $competencia, $planoContaId);
        $conta->fill($fill);
        $conta->save();

        return $conta->fresh();
    }

    /**
     * Atualiza só competência/plano DRE — permitido mesmo com baixa (não mexe em valores).
     */
    public function atualizarCompetenciaDre(int $contaId, mixed $competencia, mixed $planoContaId = null): ContaPagar
    {
        $conta = ContaPagar::query()->whereKey($contaId)->first();

        if (! $conta) {
            throw new InvalidArgumentException('Conta não encontrada.');
        }

        if (! Schema::hasColumn('contas_pagar', 'competencia')) {
            throw new InvalidArgumentException('Competência ainda não disponível neste banco.');
        }

        $fill = $this->aplicarCamposDre(
            [],
            $this->normalizarCompetencia($competencia),
            $this->normalizarPlanoContaId($planoContaId),
        );
        $conta->fill($fill);
        $conta->save();

        return $conta->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function aplicarCamposDre(array $payload, ?string $competencia, ?int $planoContaId): array
    {
        if (Schema::hasColumn('contas_pagar', 'competencia')) {
            $payload['competencia'] = $competencia;
        }
        if (Schema::hasColumn('contas_pagar', 'plano_conta_id')) {
            $payload['plano_conta_id'] = $planoContaId;
        }

        return $payload;
    }

    /** MM/AAAA (ou legado) → DATE Y-m-01; vazio → null. Não usa vencimento. */
    private function normalizarCompetencia(mixed $raw): ?string
    {
        return ContaPagarCompetencia::toDateString($raw, strict: true);
    }

    private function normalizarPlanoContaId(mixed $raw): ?int
    {
        $id = (int) ($raw ?? 0);
        if ($id <= 0) {
            return null;
        }
        if (! Schema::hasTable('planos_contas')) {
            return null;
        }
        $ok = PlanoConta::query()->whereKey($id)->where('ativo', true)->exists();

        return $ok ? $id : null;
    }

    /**
     * Prévia de parcelas (vencimento + valor) sem gravar.
     *
     * @return list<array{n: int, vencimento: string, valor: float, valor_br: string}>
     */
    public function montarParcelas(
        float|string $valorTotal,
        int $parcelas,
        string $vencimentoBase,
        int $intervalo = 1,
        string $unidade = self::INTERVALO_MESES,
    ): array {
        $parcelas = max(1, min(120, $parcelas));
        $intervalo = max(1, min(3650, $intervalo));
        $unidade = $this->normalizarIntervaloUnidade($unidade);
        $total = ErpMoney::parseBr($valorTotal);

        if ($total <= 0) {
            return [];
        }

        $valores = $this->distribuirValor($total, $parcelas);
        $base = Carbon::parse($vencimentoBase)->startOfDay();
        $out = [];

        for ($i = 0; $i < $parcelas; $i++) {
            $venc = $this->avancarVencimento($base, $i, $intervalo, $unidade);
            $valor = $valores[$i];
            $out[] = [
                'n' => $i + 1,
                'vencimento' => $venc->toDateString(),
                'valor' => $valor,
                'valor_br' => ErpMoney::formatBr($valor),
            ];
        }

        return $out;
    }

    /**
     * @return list<float>
     */
    public function distribuirValor(float $valorTotal, int $parcelas): array
    {
        $parcelas = max(1, $parcelas);
        $centavos = (int) round($valorTotal * 100);
        $base = intdiv($centavos, $parcelas);
        $resto = $centavos % $parcelas;
        $valores = [];

        for ($i = 0; $i < $parcelas; $i++) {
            $parte = $base + ($i < $resto ? 1 : 0);
            $valores[] = round($parte / 100, 2);
        }

        return $valores;
    }

    public function normalizarIntervaloUnidade(mixed $unidade): string
    {
        $u = mb_strtolower(trim((string) $unidade), 'UTF-8');

        return $u === self::INTERVALO_DIAS ? self::INTERVALO_DIAS : self::INTERVALO_MESES;
    }

    private function avancarVencimento(Carbon $base, int $indice, int $intervalo, string $unidade): Carbon
    {
        if ($indice <= 0) {
            return $base->copy();
        }

        $passo = $intervalo * $indice;

        if ($unidade === self::INTERVALO_DIAS) {
            return $base->copy()->addDays($passo);
        }

        return $base->copy()->addMonthsNoOverflow($passo);
    }

    private function resolveEmpresaId(int $compraId): ?int
    {
        if ($compraId > 0) {
            $fromCompra = Compra::query()->whereKey($compraId)->value('empresa_id');
            if ($fromCompra) {
                return (int) $fromCompra;
            }
        }

        $current = ErpContext::currentEmpresaId();

        return $current ? (int) $current : null;
    }
}
