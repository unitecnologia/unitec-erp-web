<?php

namespace App\Support\Erp\Financeiro;

use App\Models\ContaReceber;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ContaReceberCadastroService
{
    /**
     * Tipos do lançamento avulso (legado + PIX/DINHEIRO). Sem depósito.
     *
     * @return array<string, string>
     */
    public static function tiposAvulso(): array
    {
        return [
            ContaReceber::FORMA_CARTEIRA => 'CARTEIRA',
            ContaReceber::FORMA_CHEQUE => 'CHEQUE',
            ContaReceber::FORMA_CARTAO => 'CARTÃO',
            ContaReceber::FORMA_BOLETO => 'BOLETO',
            ContaReceber::FORMA_PIX => 'PIX',
            'dinheiro' => 'DINHEIRO',
        ];
    }

    /**
     * @param  array{
     *     emissao: string,
     *     documento?: string|null,
     *     cliente_id: int,
     *     vencimento: string,
     *     historico?: string|null,
     *     valor: float|string,
     *     forma?: string|null,
     *     parcelas?: int
     * }  $dados
     * @return list<ContaReceber>
     */
    public function criar(array $dados): array
    {
        $clienteId = (int) ($dados['cliente_id'] ?? 0);
        $parcelas = max(1, min(120, (int) ($dados['parcelas'] ?? 1)));
        $valorTotal = ErpMoney::parseBr($dados['valor'] ?? 0);
        $forma = $this->normalizarForma($dados['forma'] ?? ContaReceber::FORMA_CARTEIRA);

        if ($clienteId <= 0) {
            throw new InvalidArgumentException('Selecione o cliente.');
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
            $clienteId,
            $forma,
        ): array {
            $criadas = [];

            for ($i = 0; $i < $parcelas; $i++) {
                $documento = $documentoBase;
                if ($parcelas > 1 && $documento !== '') {
                    $documento .= '-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
                } elseif ($parcelas > 1) {
                    $documento = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT).'/'.$parcelas;
                }

                $criadas[] = ContaReceber::query()->create([
                    'empresa_id' => ErpContext::currentEmpresaId(),
                    'numero' => ContaReceber::nextNumero(),
                    'emissao' => $emissao->toDateString(),
                    'historico' => $historico !== '' ? $historico : null,
                    'documento' => $documento !== '' ? $documento : null,
                    'cliente_id' => $clienteId,
                    'vencimento' => $vencimentoBase->copy()->addMonthsNoOverflow($i)->toDateString(),
                    'valor' => $valores[$i],
                    'desconto' => 0,
                    'juros' => 0,
                    'valor_recebido' => 0,
                    'recebido_em' => null,
                    'forma' => $forma,
                ]);
            }

            return $criadas;
        });
    }

    /**
     * @param  array{
     *     emissao: string,
     *     documento?: string|null,
     *     cliente_id: int,
     *     vencimento: string,
     *     historico?: string|null,
     *     valor: float|string,
     *     forma?: string|null
     * }  $dados
     */
    public function atualizar(int $contaId, array $dados): ContaReceber
    {
        $conta = ContaReceber::query()->whereKey($contaId)->first();

        if (! $conta) {
            throw new InvalidArgumentException('Conta não encontrada.');
        }

        if ((float) $conta->valor_recebido > 0) {
            throw new InvalidArgumentException('Conta já possui baixa. Estorne antes de alterar.');
        }

        $exclusao = app(ContaReceberExclusaoService::class);

        if (! $exclusao->podeExcluir($conta)) {
            throw new InvalidArgumentException($exclusao->motivoBloqueio($conta) ?? 'Não é possível alterar esta conta.');
        }

        $clienteId = (int) ($dados['cliente_id'] ?? 0);
        $valor = ErpMoney::parseBr($dados['valor'] ?? 0);
        $forma = $this->normalizarForma($dados['forma'] ?? ContaReceber::FORMA_CARTEIRA);

        if ($clienteId <= 0) {
            throw new InvalidArgumentException('Selecione o cliente.');
        }

        if ($valor <= 0) {
            throw new InvalidArgumentException('Informe um valor maior que zero.');
        }

        $historico = mb_strtoupper(trim((string) ($dados['historico'] ?? '')), 'UTF-8');
        $documento = mb_strtoupper(trim((string) ($dados['documento'] ?? '')), 'UTF-8');

        $conta->fill([
            'emissao' => Carbon::parse((string) $dados['emissao'])->toDateString(),
            'documento' => $documento !== '' ? $documento : null,
            'cliente_id' => $clienteId,
            'vencimento' => Carbon::parse((string) $dados['vencimento'])->toDateString(),
            'historico' => $historico !== '' ? $historico : null,
            'valor' => $valor,
            'forma' => $forma,
        ]);
        $conta->save();

        return $conta->fresh();
    }

    public function normalizarForma(?string $forma): string
    {
        $key = mb_strtolower(trim((string) $forma), 'UTF-8');

        if (! array_key_exists($key, self::tiposAvulso())) {
            throw new InvalidArgumentException('Tipo inválido.');
        }

        return $key;
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
