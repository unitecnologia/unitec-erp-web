<?php

namespace App\Support\Erp\Financeiro;

use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Support\Erp\Boleto\Api\BoletoApi;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

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
     *     parcelas?: int,
     *     juros_diario_pct?: float|string|null,
     *     carencia_juros_dias?: int|string|null
     * }  $dados
     * @return list<ContaReceber>
     */
    public function criar(array $dados): array
    {
        $clienteId = (int) ($dados['cliente_id'] ?? 0);
        $parcelas = max(1, min(120, (int) ($dados['parcelas'] ?? 1)));
        $valorTotal = ErpMoney::parseBr($dados['valor'] ?? 0);
        $forma = $this->normalizarForma($dados['forma'] ?? ContaReceber::FORMA_CARTEIRA);
        $jurosCarteira = $this->resolverJurosCarteira($forma, $dados);

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
        $planoContaId = filled($dados['plano_conta_id'] ?? null) ? (int) $dados['plano_conta_id'] : null;

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
            $jurosCarteira,
            $planoContaId,
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
                    'juros_diario_pct' => $jurosCarteira['juros_diario_pct'],
                    'carencia_juros_dias' => $jurosCarteira['carencia_juros_dias'],
                    'multa_pct' => $jurosCarteira['multa_pct'],
                    'valor_recebido' => 0,
                    'recebido_em' => null,
                    'forma' => $forma,
                    'plano_conta_id' => $planoContaId,
                ]);
            }

            return $criadas;
        });
    }

    /**
     * @param  array{
     *     emissao?: string,
     *     documento?: string|null,
     *     cliente_id?: int,
     *     vencimento: string,
     *     historico?: string|null,
     *     valor?: float|string,
     *     forma?: string|null,
     *     juros_diario_pct?: float|string|null,
     *     carencia_juros_dias?: int|string|null
     * }  $dados
     * @return array{conta: ContaReceber, boletos_vencimento_api: int}
     */
    public function atualizar(int $contaId, array $dados): array
    {
        $conta = ContaReceber::query()->whereKey($contaId)->first();

        if (! $conta) {
            throw new InvalidArgumentException('Conta não encontrada.');
        }

        if ((float) $conta->valor_recebido > 0) {
            throw new InvalidArgumentException('Conta já possui baixa. Estorne antes de alterar.');
        }

        $exclusao = app(ContaReceberExclusaoService::class);

        if (! $exclusao->podeAlterar($conta)) {
            throw new InvalidArgumentException(
                $exclusao->motivoBloqueioAlteracao($conta) ?? 'Não é possível alterar esta conta.'
            );
        }

        $novoVencimento = Carbon::parse((string) $dados['vencimento'])->startOfDay();
        $vencimentoAnterior = $conta->vencimento
            ? Carbon::parse($conta->vencimento)->startOfDay()->toDateString()
            : null;

        $boletosVencimentoApi = 0;
        if ($vencimentoAnterior !== $novoVencimento->toDateString()) {
            $boletosVencimentoApi = $this->sincronizarVencimentoBoletosApi($conta, $novoVencimento);
        }

        // Pedido (FV / venda): vencimento + tipo (+ juros carteira).
        if ($exclusao->podeAlterarSomenteVencimento($conta)) {
            $forma = $this->normalizarForma($dados['forma'] ?? $conta->forma ?? ContaReceber::FORMA_CARTEIRA);
            $jurosCarteira = $this->resolverJurosCarteira($forma, $dados, $conta);
            $conta->vencimento = $novoVencimento->toDateString();
            $conta->forma = $forma;
            $conta->juros_diario_pct = $jurosCarteira['juros_diario_pct'];
            $conta->carencia_juros_dias = $jurosCarteira['carencia_juros_dias'];
            $conta->multa_pct = $jurosCarteira['multa_pct'];
            $conta->save();

            return [
                'conta' => $conta->fresh() ?? $conta,
                'boletos_vencimento_api' => $boletosVencimentoApi,
            ];
        }

        $clienteId = (int) ($dados['cliente_id'] ?? 0);
        $valor = ErpMoney::parseBr($dados['valor'] ?? 0);
        $forma = $this->normalizarForma($dados['forma'] ?? ContaReceber::FORMA_CARTEIRA);
        $jurosCarteira = $this->resolverJurosCarteira($forma, $dados, $conta);

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
            'vencimento' => $novoVencimento->toDateString(),
            'historico' => $historico !== '' ? $historico : null,
            'valor' => $valor,
            'forma' => $forma,
            'juros_diario_pct' => $jurosCarteira['juros_diario_pct'],
            'carencia_juros_dias' => $jurosCarteira['carencia_juros_dias'],
            'multa_pct' => $jurosCarteira['multa_pct'],
        ]);
        $conta->save();

        return [
            'conta' => $conta->fresh() ?? $conta,
            'boletos_vencimento_api' => $boletosVencimentoApi,
        ];
    }

    /**
     * Se houver boleto aberto com driver de API, altera o vencimento no banco antes de salvar o CR.
     * Falha da API bloqueia o save (não diverge ERP x banco).
     *
     * @return int Quantidade de boletos sincronizados na API
     *
     * @throws InvalidArgumentException
     */
    private function sincronizarVencimentoBoletosApi(ContaReceber $conta, Carbon $novoVencimento): int
    {
        $empresa = $conta->empresa_id
            ? Empresa::query()->find($conta->empresa_id)
            : ErpContext::currentEmpresa();

        try {
            return app(BoletoApi::class)->alterarVencimentoAbertosDaContaReceber(
                (int) $conta->id,
                $novoVencimento,
                $empresa instanceof Empresa ? $empresa : null,
            );
        } catch (RuntimeException $e) {
            throw new InvalidArgumentException(
                'Não foi possível alterar o vencimento no banco: '.$e->getMessage(),
                0,
                $e,
            );
        } catch (Throwable $e) {
            throw new InvalidArgumentException(
                'Não foi possível alterar o vencimento no banco: '.$e->getMessage(),
                0,
                $e,
            );
        }
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
     * Juros %/carência só valem para Carteira. Outros tipos zeram (boleto tem juros na conta API).
     *
     * @param  array<string, mixed>  $dados
     * @return array{juros_diario_pct: float, carencia_juros_dias: int}
     */
    private function resolverJurosCarteira(string $forma, array $dados, ?ContaReceber $conta = null): array
    {
        if ($forma !== ContaReceber::FORMA_CARTEIRA) {
            return [
                'juros_diario_pct' => 0.0,
                'carencia_juros_dias' => 0,
                'multa_pct' => 0.0,
            ];
        }

        $defaults = ContaReceberJurosCarteira::defaultsDaEmpresa(
            $conta?->empresa_id ? (int) $conta->empresa_id : null
        );

        $pctRaw = $dados['juros_diario_pct'] ?? null;
        $carenciaRaw = $dados['carencia_juros_dias'] ?? null;
        $multaRaw = $dados['multa_pct'] ?? null;

        $pct = $pctRaw !== null && $pctRaw !== ''
            ? round((float) (is_string($pctRaw) ? ErpMoney::parseBr($pctRaw) : $pctRaw), 4)
            : ($conta !== null
                ? round((float) ($conta->juros_diario_pct ?? 0), 4)
                : $defaults['juros_diario_pct']);

        $carencia = $carenciaRaw !== null && $carenciaRaw !== ''
            ? max(0, (int) $carenciaRaw)
            : ($conta !== null
                ? max(0, (int) ($conta->carencia_juros_dias ?? 0))
                : $defaults['carencia_juros_dias']);

        $multa = $multaRaw !== null && $multaRaw !== ''
            ? round((float) (is_string($multaRaw) ? ErpMoney::parseBr($multaRaw) : $multaRaw), 4)
            : ($conta !== null
                ? round((float) ($conta->multa_pct ?? 0), 4)
                : $defaults['multa_pct']);

        return [
            'juros_diario_pct' => max(0.0, $pct),
            'carencia_juros_dias' => $carencia,
            'multa_pct' => max(0.0, $multa),
        ];
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
