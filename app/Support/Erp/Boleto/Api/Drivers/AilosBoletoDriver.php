<?php

namespace App\Support\Erp\Boleto\Api\Drivers;

use App\Models\Boleto;
use App\Models\Empresa;
use App\Services\Ailos\AilosCobrancaClient;
use App\Support\Erp\Boleto\Api\BoletoApiDriver;
use App\Support\Erp\EmpresaParametros;
use App\Support\Erp\ErpContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Driver Ailos (COMPE 085) — baixa e alteração de vencimento via API de cobrança.
 */
final class AilosBoletoDriver implements BoletoApiDriver
{
    private const TICKET_TENTATIVAS = 5;

    private const TICKET_SLEEP_US = 400_000;

    public function __construct(private readonly AilosCobrancaClient $client)
    {
    }

    public function supports(Empresa $empresa): bool
    {
        if (! filter_var($empresa->param_boleto_habilitar ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $banco = preg_replace('/\D/', '', (string) ($empresa->param_boleto_banco ?? '')) ?? '';

        return $banco === EmpresaParametros::BOLETO_BANCO_AILOS;
    }

    public function baixar(Boleto $boleto, ?Empresa $empresa = null): bool
    {
        if ($boleto->status !== Boleto::STATUS_ABERTO) {
            return false;
        }

        $empresa = $this->resolverEmpresa($boleto, $empresa);
        $this->assertSuportada($empresa);

        $numeroBoleto = $this->resolverNumeroBoleto($boleto);
        $convenio = $this->client->convenioNumero($empresa);

        $resposta = $this->client->baixarBoletosLote($empresa, [[
            'numeroConvenio' => $convenio,
            'numeroBoleto' => $numeroBoleto,
        ]]);

        $ticket = $this->extrairTicket($resposta);
        if ($ticket !== null) {
            $this->aguardarInstrucaoOk($empresa, $ticket, 'baixa');
        }

        $boleto->forceFill([
            'status' => Boleto::STATUS_BAIXADO,
            'pago_em' => $boleto->pago_em ?? now(),
        ])->save();

        return true;
    }

    public function baixarComConfirmacao(Boleto $boleto, ?Empresa $empresa = null): bool
    {
        if (in_array($boleto->status, [Boleto::STATUS_BAIXADO, Boleto::STATUS_CANCELADO], true)) {
            return true;
        }

        if ($boleto->status !== Boleto::STATUS_ABERTO) {
            throw new RuntimeException(
                'Boleto #'.$boleto->id.' não está aberto para baixa. O pedido não foi cancelado.'
            );
        }

        $empresa = $this->resolverEmpresa($boleto, $empresa);
        $this->assertSuportada($empresa);

        $numeroBoleto = $this->resolverNumeroBoleto($boleto);
        $convenio = $this->client->convenioNumero($empresa);

        $resposta = $this->client->baixarBoletosLote($empresa, [[
            'numeroConvenio' => $convenio,
            'numeroBoleto' => $numeroBoleto,
        ]]);

        if ($this->instrucaoComErro($resposta)) {
            throw new RuntimeException(
                'O banco recusou a baixa do boleto. O pedido não foi cancelado.'
            );
        }

        $ticket = $this->extrairTicket($resposta);

        if ($ticket !== null) {
            $this->aguardarInstrucaoOk($empresa, $ticket, 'baixa', true);
        } elseif (! $this->respostaBaixaImediata($resposta)) {
            throw new RuntimeException(
                'O banco não confirmou a baixa do boleto. O pedido não foi cancelado.'
            );
        }

        $boleto->forceFill([
            'status' => Boleto::STATUS_BAIXADO,
            'pago_em' => $boleto->pago_em ?? now(),
        ])->save();

        return true;
    }

    public function alterarVencimento(
        Boleto $boleto,
        CarbonInterface $novoVencimento,
        ?Empresa $empresa = null,
    ): void {
        if ($boleto->status !== Boleto::STATUS_ABERTO) {
            throw new RuntimeException(
                'Boleto #'.$boleto->id.' não está aberto; não é possível alterar o vencimento no banco.'
            );
        }

        $empresa = $this->resolverEmpresa($boleto, $empresa);
        $this->assertSuportada($empresa);

        $data = $novoVencimento->copy()->startOfDay();
        $numeroBoleto = $this->resolverNumeroBoleto($boleto);
        $convenio = $this->client->convenioNumero($empresa);

        // Ailos espera ISO; usa meio-dia UTC na data civil para não cruzar o dia por fuso.
        $dataIso = $data->format('Y-m-d').'T12:00:00.000Z';

        $resposta = $this->client->alterarVencimentoLote($empresa, [[
            'numeroConvenio' => $convenio,
            'numeroBoleto' => $numeroBoleto,
            'vencimento' => [
                'dataVencimento' => $dataIso,
            ],
        ]]);

        $ticket = $this->extrairTicket($resposta);
        if ($ticket !== null) {
            $this->aguardarInstrucaoOk($empresa, $ticket, 'vencimento');
        }

        $boleto->forceFill([
            'vencimento' => $data->toDateString(),
        ])->save();
    }

    /**
     * @throws RuntimeException
     */
    private function resolverEmpresa(Boleto $boleto, ?Empresa $empresa): Empresa
    {
        $empresa ??= $boleto->empresa_id
            ? Empresa::query()->find($boleto->empresa_id)
            : ErpContext::currentEmpresa();

        if (! $empresa instanceof Empresa) {
            throw new RuntimeException('Empresa não encontrada para operação de boleto Ailos.');
        }

        return $empresa;
    }

    /**
     * @throws RuntimeException
     */
    private function assertSuportada(Empresa $empresa): void
    {
        if (! $this->supports($empresa)) {
            throw new RuntimeException(
                'API Boleto Ailos indisponível. Ative em Empresa > Parâmetros > API Boleto (banco 085).'
            );
        }
    }

    /**
     * @throws RuntimeException
     */
    private function resolverNumeroBoleto(Boleto $boleto): int|string
    {
        foreach ([(string) ($boleto->nosso_numero ?? ''), (string) ($boleto->id_externo ?? '')] as $raw) {
            $digits = preg_replace('/\D/', '', $raw) ?? '';
            if ($digits !== '' && $digits !== '0') {
                if (strlen($digits) < 18) {
                    return (int) $digits;
                }

                return $digits;
            }
        }

        throw new RuntimeException(
            'Boleto #'.$boleto->id.' sem nosso número / id externo para API Ailos.'
        );
    }

    /**
     * @param  array<string, mixed>  $resposta
     */
    private function extrairTicket(array $resposta): ?string
    {
        foreach (['ticket', 'Ticket', 'idTicket', 'identificadorTicket', 'protocolo'] as $key) {
            $v = $resposta[$key] ?? null;
            if (is_scalar($v) && trim((string) $v) !== '') {
                return trim((string) $v);
            }
        }

        $nested = data_get($resposta, 'data.ticket')
            ?? data_get($resposta, 'resultado.ticket');
        if (is_scalar($nested) && trim((string) $nested) !== '') {
            return trim((string) $nested);
        }

        return null;
    }

    /**
     * @throws RuntimeException
     */
    private function aguardarInstrucaoOk(
        Empresa $empresa,
        string $ticket,
        string $contexto,
        bool $exigirConclusao = false,
    ): void {
        $ultimo = [];

        for ($i = 0; $i < self::TICKET_TENTATIVAS; $i++) {
            if ($i > 0) {
                usleep(self::TICKET_SLEEP_US);
            }

            $ultimo = $this->client->consultarInstrucaoLote($empresa, $ticket);

            if ($this->instrucaoComErro($ultimo)) {
                throw new RuntimeException(
                    'Instrução Ailos de '.$contexto.' rejeitada (ticket '.$ticket.'): '
                    .json_encode($ultimo, JSON_UNESCAPED_UNICODE)
                );
            }

            if ($this->instrucaoConcluida($ultimo)) {
                return;
            }
        }

        if ($exigirConclusao) {
            throw new RuntimeException(
                'O banco não confirmou a baixa do boleto (ticket '.$ticket.'). O pedido não foi cancelado.'
            );
        }

        Log::info('Ailos: ticket de '.$contexto.' sem status final explícito; prosseguindo.', [
            'ticket' => $ticket,
            'ultima_resposta' => $ultimo,
        ]);
    }

    /**
     * @param  array<string, mixed>  $resposta
     */
    private function respostaBaixaImediata(array $resposta): bool
    {
        if ($this->instrucaoConcluida($resposta)) {
            return true;
        }

        return ($resposta['ok'] ?? false) === true && count($resposta) === 1;
    }

    /**
     * @param  array<string, mixed>  $resposta
     */
    private function instrucaoComErro(array $resposta): bool
    {
        $status = mb_strtolower((string) (
            $resposta['status']
            ?? $resposta['situacao']
            ?? $resposta['situacaoProcessamento']
            ?? ''
        ), 'UTF-8');

        if (str_contains($status, 'erro')
            || str_contains($status, 'rejeit')
            || str_contains($status, 'falha')
            || $status === 'error'
            || $status === 'failed') {
            return true;
        }

        $erros = $resposta['erros'] ?? $resposta['errors'] ?? null;
        if (is_array($erros) && $erros !== []) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $resposta
     */
    private function instrucaoConcluida(array $resposta): bool
    {
        $status = mb_strtolower((string) (
            $resposta['status']
            ?? $resposta['situacao']
            ?? $resposta['situacaoProcessamento']
            ?? ''
        ), 'UTF-8');

        if ($status === '') {
            return false;
        }

        return str_contains($status, 'conclu')
            || str_contains($status, 'sucesso')
            || str_contains($status, 'finaliz')
            || str_contains($status, 'processad')
            || $status === 'ok'
            || $status === 'success'
            || $status === 'done';
    }
}
