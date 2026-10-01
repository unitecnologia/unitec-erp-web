<?php

namespace App\Support\Erp;

use App\Models\ClienteCreditoMovimentacao;
use App\Models\Person;
use App\Support\Erp\Audit\ErpOperacaoLogService;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Crédito de loja do cliente. O saldo é a soma dos movimentos da empresa —
 * não existe campo editável e não mistura com contas a receber.
 */
final class ClienteCreditoService
{
    /**
     * Saldo = soma dos movimentos da empresa + cliente. Não existe campo editável.
     */
    public function saldo(int $clienteId, int $empresaId): float
    {
        if ($clienteId <= 0 || $empresaId <= 0) {
            return 0.0;
        }

        return $this->somar($clienteId, $empresaId, lock: false);
    }

    public function podeReceberCredito(?int $clienteId): bool
    {
        if (! $clienteId) {
            return false;
        }

        $person = Person::query()->find($clienteId, ['id', 'codigo', 'nome_razao', 'is_cliente']);

        return $person instanceof Person && $this->pessoaPodeReceber($person);
    }

    public function gerar(
        int $clienteId,
        float $valor,
        int $empresaId,
        string $origemTipo,
        ?int $origemId = null,
        ?string $origemNumero = null,
        ?string $observacao = null,
        ?int $usuarioId = null,
    ): ClienteCreditoMovimentacao {
        $movimento = $this->lancar(
            tipo: ClienteCreditoMovimentacao::TIPO_CREDITO,
            sinal: 1,
            clienteId: $clienteId,
            valor: $valor,
            empresaId: $empresaId,
            origemTipo: $origemTipo,
            origemId: $origemId,
            origemNumero: $origemNumero,
            observacao: $observacao,
            usuarioId: $usuarioId,
        );

        if ($origemTipo === ClienteCreditoMovimentacao::ORIGEM_MANUAL) {
            $this->auditar(
                operacao: 'GERAR_CREDITO_CLIENTE',
                resumo: 'Crédito manual de R$ '.ErpMoney::formatBr($movimento->valor)
                    .($movimento->codigo_publico ? ' ('.$movimento->codigo_publico.')' : ''),
                movimento: $movimento,
            );
        }

        return $movimento;
    }

    public function usar(
        int $clienteId,
        float $valor,
        int $empresaId,
        string $origemTipo,
        ?int $origemId = null,
        ?string $origemNumero = null,
        ?string $observacao = null,
        ?int $usuarioId = null,
    ): ClienteCreditoMovimentacao {
        return $this->lancar(
            tipo: ClienteCreditoMovimentacao::TIPO_DEBITO,
            sinal: -1,
            clienteId: $clienteId,
            valor: $valor,
            empresaId: $empresaId,
            origemTipo: $origemTipo,
            origemId: $origemId,
            origemNumero: $origemNumero,
            observacao: $observacao,
            usuarioId: $usuarioId,
        );
    }

    public function estornar(int $movimentoId, ?int $usuarioId = null, ?string $observacao = null): ClienteCreditoMovimentacao
    {
        return DB::transaction(function () use ($movimentoId, $usuarioId, $observacao): ClienteCreditoMovimentacao {
            $origem = ClienteCreditoMovimentacao::query()->whereKey($movimentoId)->first();

            if (! $origem) {
                throw new DomainException('Lançamento de crédito não encontrado.');
            }

            if ($origem->estorna_id) {
                throw new DomainException('Não é possível estornar um estorno. Lance um novo crédito se precisar.');
            }

            Person::query()->whereKey($origem->cliente_id)->lockForUpdate()->first();

            $origem = ClienteCreditoMovimentacao::query()->whereKey($movimentoId)->lockForUpdate()->first();

            if (! $origem) {
                throw new DomainException('Lançamento de crédito não encontrado.');
            }

            if (ClienteCreditoMovimentacao::query()->where('estorna_id', $origem->id)->exists()) {
                throw new DomainException('Este lançamento já foi estornado.');
            }

            $texto = trim((string) ($observacao ?? ''));
            if ($texto === '') {
                $texto = 'Estorno de '.ClienteCreditoMovimentacao::tipoLabel((string) $origem->tipo);
            }

            $estorno = $this->lancar(
                tipo: ((int) $origem->sinal) < 0
                    ? ClienteCreditoMovimentacao::TIPO_CREDITO
                    : ClienteCreditoMovimentacao::TIPO_DEBITO,
                sinal: ((int) $origem->sinal) * -1,
                clienteId: (int) $origem->cliente_id,
                valor: (float) $origem->valor,
                empresaId: (int) $origem->empresa_id,
                origemTipo: (string) ($origem->origem_tipo ?: ClienteCreditoMovimentacao::ORIGEM_MANUAL),
                origemId: $origem->origem_id ? (int) $origem->origem_id : (int) $origem->id,
                origemNumero: $origem->origem_numero ? (string) $origem->origem_numero : null,
                observacao: $texto,
                usuarioId: $usuarioId,
                estornaId: (int) $origem->id,
                pessoaJaTravada: true,
            );

            $this->auditar(
                operacao: 'ESTORNAR_CREDITO_CLIENTE',
                resumo: 'Estorno de crédito #'.$origem->id.' — R$ '.ErpMoney::formatBr((float) $origem->valor),
                movimento: $estorno,
            );

            return $estorno;
        });
    }

    /**
     * Devolve o crédito usado numa venda (estorno da venda PDV).
     */
    public function estornarUsosDaOrigem(string $origemTipo, int $origemId, ?int $usuarioId = null, ?string $observacao = null): void
    {
        if ($origemId <= 0 || $origemTipo === '') {
            return;
        }

        $ids = ClienteCreditoMovimentacao::query()
            ->where('origem_tipo', $origemTipo)
            ->where('origem_id', $origemId)
            ->whereIn('tipo', [ClienteCreditoMovimentacao::TIPO_DEBITO, 'uso'])
            ->whereNull('estorna_id')
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $id) {
            if (ClienteCreditoMovimentacao::query()->where('estorna_id', $id)->exists()) {
                continue;
            }

            $this->estornar((int) $id, $usuarioId, $observacao);
        }
    }

    /**
     * Devolução estornada: movimento inverso do crédito que ela gerou. Não apaga o histórico.
     */
    public function estornarCreditosDaOrigem(string $origemTipo, int $origemId, ?int $usuarioId = null, ?string $observacao = null): int
    {
        if ($origemId <= 0 || $origemTipo === '') {
            return 0;
        }

        $ids = ClienteCreditoMovimentacao::query()
            ->where('origem_tipo', $origemTipo)
            ->where('origem_id', $origemId)
            ->where('tipo', ClienteCreditoMovimentacao::TIPO_CREDITO)
            ->whereNull('estorna_id')
            ->orderBy('id')
            ->pluck('id');

        $estornados = 0;

        foreach ($ids as $id) {
            if (ClienteCreditoMovimentacao::query()->where('estorna_id', $id)->exists()) {
                continue;
            }

            $this->estornar((int) $id, $usuarioId, $observacao);
            $estornados++;
        }

        return $estornados;
    }

    private function lancar(
        string $tipo,
        int $sinal,
        int $clienteId,
        float $valor,
        int $empresaId,
        string $origemTipo,
        ?int $origemId,
        ?string $origemNumero,
        ?string $observacao,
        ?int $usuarioId,
        ?int $estornaId = null,
        bool $pessoaJaTravada = false,
    ): ClienteCreditoMovimentacao {
        $valor = round($valor, 2);

        if ($valor <= 0) {
            throw new DomainException('Informe um valor maior que zero.');
        }

        if ($empresaId <= 0) {
            throw new DomainException('Empresa não informada para o crédito do cliente.');
        }

        if (! in_array($sinal, [-1, 1], true)) {
            throw new DomainException('Sinal de crédito inválido.');
        }

        return DB::transaction(function () use (
            $tipo,
            $sinal,
            $clienteId,
            $valor,
            $empresaId,
            $origemTipo,
            $origemId,
            $origemNumero,
            $observacao,
            $usuarioId,
            $estornaId,
            $pessoaJaTravada,
        ): ClienteCreditoMovimentacao {
            if (! $pessoaJaTravada) {
                Person::query()->whereKey($clienteId)->lockForUpdate()->first();
            }

            $cliente = Person::query()->find($clienteId, ['id', 'codigo', 'nome_razao', 'is_cliente']);

            if (! $cliente instanceof Person) {
                throw new DomainException('Cliente não encontrado.');
            }

            if ($estornaId === null && ! $this->pessoaPodeReceber($cliente)) {
                throw new DomainException('Consumidor final não recebe crédito. Selecione o cliente.');
            }

            // Trava os movimentos desta empresa+cliente. O lock da pessoa cobre a inserção
            // concorrente quando ainda não existe linha (dois PDVs no mesmo saldo).
            ClienteCreditoMovimentacao::query()
                ->where('cliente_id', $clienteId)
                ->where('empresa_id', $empresaId)
                ->lockForUpdate()
                ->get(['id']);

            $saldoAnterior = $this->somar($clienteId, $empresaId, lock: false);
            $saldoAtual = round($saldoAnterior + ($sinal * $valor), 2);

            if ($saldoAtual < -0.009) {
                throw new DomainException(
                    'Saldo de crédito insuficiente. Disponível: R$ '.ErpMoney::formatBr($saldoAnterior).'.'
                );
            }

            $movimento = null;
            $codigoPublico = null;

            for ($tentativa = 0; $tentativa < 3; $tentativa++) {
                if ($tipo === ClienteCreditoMovimentacao::TIPO_CREDITO && $estornaId === null) {
                    $codigoPublico = $this->proximoCodigoPublico();
                }

                try {
                    $movimento = ClienteCreditoMovimentacao::query()->create([
                        'empresa_id' => $empresaId,
                        'cliente_id' => $clienteId,
                        'data_movimentacao' => ErpTimezone::nowLocal(),
                        'tipo' => $tipo,
                        'valor' => $valor,
                        'sinal' => $sinal,
                        'saldo_anterior' => $saldoAnterior,
                        'saldo_atual' => $saldoAtual,
                        'origem_tipo' => $origemTipo !== '' ? $origemTipo : ClienteCreditoMovimentacao::ORIGEM_MANUAL,
                        'origem_id' => $origemId,
                        'origem_numero' => $origemNumero !== null && trim($origemNumero) !== ''
                            ? mb_substr(trim($origemNumero), 0, 40)
                            : null,
                        'codigo_publico' => $codigoPublico,
                        'estorna_id' => $estornaId,
                        'usuario_id' => $usuarioId ?: Auth::id(),
                        'observacao' => $this->observacao($observacao),
                    ]);

                    break;
                } catch (\Illuminate\Database\QueryException $exception) {
                    $duplicado = str_contains($exception->getMessage(), 'cli_cred_mov_codigo_publico_unq')
                        || str_contains($exception->getMessage(), 'codigo_publico');

                    if (! $duplicado || $tentativa === 2) {
                        throw $exception;
                    }
                }
            }

            if (! $movimento instanceof ClienteCreditoMovimentacao) {
                throw new DomainException('Não foi possível gravar o crédito do cliente.');
            }

            if (! $movimento->origem_id) {
                $movimento->origem_id = (int) $movimento->id;
                $movimento->save();
            }

            return $movimento;
        });
    }

    private function somar(int $clienteId, int $empresaId, bool $lock): float
    {
        $query = ClienteCreditoMovimentacao::query()
            ->where('cliente_id', $clienteId)
            ->where('empresa_id', $empresaId);

        if ($lock) {
            $query->lockForUpdate();
        }

        $total = 0.0;

        foreach ($query->get(['sinal', 'valor']) as $linha) {
            $total += ((int) $linha->sinal) * (float) $linha->valor;
        }

        return round($total, 2);
    }

    /**
     * Identificador público do vale (VT000000123). Não usa o id do banco.
     * O vale não tem saldo próprio: a fonte continua sendo o extrato.
     */
    private function proximoCodigoPublico(): string
    {
        $ultimo = ClienteCreditoMovimentacao::query()
            ->whereNotNull('codigo_publico')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('codigo_publico');

        $sequencia = 1;

        if (is_string($ultimo) && preg_match('/(\d+)$/', $ultimo, $partes) === 1) {
            $sequencia = ((int) $partes[1]) + 1;
        }

        return 'VT'.str_pad((string) $sequencia, 9, '0', STR_PAD_LEFT);
    }

    private function auditar(string $operacao, string $resumo, ClienteCreditoMovimentacao $movimento): void
    {
        (new ErpOperacaoLogService())->registrar(
            operacao: $operacao,
            resumo: $resumo,
            origem: 'cliente_credito',
            documentoTipo: 'cliente_credito',
            documentoId: (int) $movimento->id,
            documentoNumero: $movimento->codigo_publico ?: (string) ($movimento->origem_numero ?: $movimento->id),
            detalhes: [
                'cliente_id' => (int) $movimento->cliente_id,
                'tipo' => $movimento->tipo,
                'valor' => (float) $movimento->valor,
                'origem_tipo' => $movimento->origem_tipo,
                'origem_id' => $movimento->origem_id,
                'codigo_publico' => $movimento->codigo_publico,
                'estorna_id' => $movimento->estorna_id,
            ],
            empresaId: $movimento->empresa_id ? (int) $movimento->empresa_id : null,
        );
    }

    private function pessoaPodeReceber(Person $person): bool
    {
        if (Person::isCodigoConsumidorFinal($person->codigo)) {
            return false;
        }

        $nome = mb_strtoupper(trim((string) $person->nome_razao), 'UTF-8');

        if ($nome === 'CONSUMIDOR FINAL') {
            return false;
        }

        return (bool) $person->is_cliente;
    }

    private function observacao(?string $observacao): ?string
    {
        $texto = trim((string) ($observacao ?? ''));

        if ($texto === '') {
            return null;
        }

        return mb_substr($texto, 0, 500);
    }
}
