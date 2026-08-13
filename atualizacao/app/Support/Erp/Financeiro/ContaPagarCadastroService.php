<?php

namespace App\Support\Erp\Financeiro;

use App\Models\ContaPagar;
use App\Support\Erp\ErpMoney;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ContaPagarCadastroService
{
    /**
     * @param  array{
     *     emissao: string,
     *     documento?: string|null,
     *     fornecedor_id: int,
     *     vencimento: string,
     *     historico?: string|null,
     *     valor: float|string,
     *     parcelas?: int
     * }  $dados
     * @return list<ContaPagar>
     */
    public function criar(array $dados): array
    {
        $fornecedorId = (int) ($dados['fornecedor_id'] ?? 0);
        $parcelas = max(1, min(120, (int) ($dados['parcelas'] ?? 1)));
        $valorTotal = ErpMoney::parseBr($dados['valor'] ?? 0);

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

        $valores = $this->distribuirValor($valorTotal, $parcelas);

        return DB::transaction(function () use (
            $parcelas,
            $valores,
            $emissao,
            $vencimentoBase,
            $historico,
            $documentoBase,
            $fornecedorId,
        ): array {
            $criadas = [];

            for ($i = 0; $i < $parcelas; $i++) {
                $documento = $documentoBase;
                if ($parcelas > 1 && $documento !== '') {
                    $documento .= '-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
                } elseif ($parcelas > 1) {
                    $documento = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT).'/'.$parcelas;
                }

                $criadas[] = ContaPagar::query()->create([
                    'numero' => ContaPagar::nextNumero(),
                    'emissao' => $emissao->toDateString(),
                    'documento' => $documento !== '' ? $documento : null,
                    'fornecedor_id' => $fornecedorId,
                    'vencimento' => $vencimentoBase->copy()->addMonthsNoOverflow($i)->toDateString(),
                    'produto' => $historico !== '' ? $historico : null,
                    'valor' => $valores[$i],
                    'desconto' => 0,
                    'juros' => 0,
                    'valor_pago' => 0,
                    'pago_em' => null,
                ]);
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
     *     historico?: string|null
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
        $totalParcelas = count($parcelas);

        return DB::transaction(function () use (
            $parcelas,
            $emissao,
            $historico,
            $documentoBase,
            $fornecedorId,
            $totalParcelas,
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

                $criadas[] = ContaPagar::query()->create([
                    'numero' => ContaPagar::nextNumero(),
                    'emissao' => $emissao->toDateString(),
                    'documento' => $documento,
                    'fornecedor_id' => $fornecedorId,
                    'vencimento' => $vencimento->toDateString(),
                    'produto' => $historico !== '' ? $historico : null,
                    'valor' => $valor,
                    'desconto' => 0,
                    'juros' => 0,
                    'valor_pago' => 0,
                    'pago_em' => null,
                ]);
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

        $conta->fill([
            'emissao' => Carbon::parse((string) $dados['emissao'])->toDateString(),
            'documento' => $documento !== '' ? $documento : null,
            'fornecedor_id' => $fornecedorId,
            'vencimento' => Carbon::parse((string) $dados['vencimento'])->toDateString(),
            'produto' => $historico !== '' ? $historico : null,
            'valor' => $valor,
        ]);
        $conta->save();

        return $conta->fresh();
    }

    /**
     * @return list<float>
     */
    private function distribuirValor(float $valorTotal, int $parcelas): array
    {
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
}
