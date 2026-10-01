<?php

namespace App\Support\Erp\Boleto\Api\Drivers;

use App\Models\Boleto;
use App\Models\Empresa;
use App\Services\Sicredi\SicrediCobrancaClient;
use App\Support\Erp\Boleto\Api\BoletoApiDriver;
use App\Support\Erp\EmpresaParametros;
use App\Support\Erp\ErpContext;
use Carbon\CarbonInterface;
use RuntimeException;

/**
 * Driver Sicredi (COMPE 748) — baixa e alteração de vencimento via API.
 */
final class SicrediBoletoDriver implements BoletoApiDriver
{
    public function __construct(private readonly SicrediCobrancaClient $client)
    {
    }

    public function supports(Empresa $empresa): bool
    {
        if (! filter_var($empresa->param_boleto_habilitar ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $banco = preg_replace('/\D/', '', (string) ($empresa->param_boleto_banco ?? '')) ?? '';

        return $banco === EmpresaParametros::BOLETO_BANCO_SICREDI;
    }

    public function baixar(Boleto $boleto, ?Empresa $empresa = null): bool
    {
        if ($boleto->status !== Boleto::STATUS_ABERTO) {
            return false;
        }

        $empresa = $this->resolverEmpresa($boleto, $empresa);
        $this->assertSuportada($empresa);

        $this->client->baixarBoleto($empresa, $this->resolverNossoNumero($boleto));

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

        return $this->baixar($boleto, $empresa);
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
        $this->client->alterarVencimento(
            $empresa,
            $this->resolverNossoNumero($boleto),
            $data->toDateString(),
        );

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
            throw new RuntimeException('Empresa não encontrada para operação de boleto Sicredi.');
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
                'API Boleto Sicredi indisponível. Ative em Empresa > Parâmetros > API Boleto (banco 748).'
            );
        }
    }

    /**
     * @throws RuntimeException
     */
    private function resolverNossoNumero(Boleto $boleto): string
    {
        foreach ([(string) ($boleto->nosso_numero ?? ''), (string) ($boleto->id_externo ?? '')] as $raw) {
            $digits = preg_replace('/\D/', '', $raw) ?? '';
            if ($digits !== '' && $digits !== '0') {
                return $digits;
            }
        }

        throw new RuntimeException(
            'Boleto #'.$boleto->id.' sem nosso número / id externo para API Sicredi.'
        );
    }
}
