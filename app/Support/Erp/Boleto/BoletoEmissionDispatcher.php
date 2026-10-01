<?php

namespace App\Support\Erp\Boleto;

use App\Models\Boleto;
use App\Models\BoletoContaApi;
use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Services\Ailos\AilosBoletoEmissionService;
use App\Services\Sicredi\SicrediBoletoEmissionService;
use App\Support\Erp\EmpresaParametros;
use App\Support\Erp\ErpContext;
use RuntimeException;

/**
 * Emite boleto pela conta API escolhida (banco travado após emissão).
 */
final class BoletoEmissionDispatcher
{
    public function __construct(private readonly BoletoContaApiResolver $resolver)
    {
    }

    /**
     * @throws RuntimeException
     */
    public function emitirParaContaReceber(
        ContaReceber $conta,
        ?BoletoContaApi $boletoConta = null,
        ?Empresa $empresa = null,
    ): Boleto {
        $empresa ??= $conta->empresa_id
            ? Empresa::query()->find($conta->empresa_id)
            : ErpContext::currentEmpresa();

        if (! $empresa instanceof Empresa) {
            throw new RuntimeException('Empresa não encontrada para emitir boleto.');
        }

        if (! filter_var($empresa->param_boleto_habilitar ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException(
                'API Boleto desabilitada. Ative em Empresa > Parâmetros > API Boleto.'
            );
        }

        $boletoConta ??= $this->resolver->padraoOuUnica($empresa);
        if (! $boletoConta instanceof BoletoContaApi) {
            throw new RuntimeException(
                'Nenhuma conta de cobrança API cadastrada. Cadastre Ailos ou Sicredi em Empresa > API Boleto.'
            );
        }

        if ((int) $boletoConta->empresa_id !== (int) $empresa->id || ! $boletoConta->ativo) {
            throw new RuntimeException('Conta de cobrança API inválida para esta empresa.');
        }

        $overlay = $boletoConta->asEmpresaOverlay($empresa);
        $banco = $boletoConta->bancoCompe();

        $boleto = match ($banco) {
            EmpresaParametros::BOLETO_BANCO_AILOS => app(AilosBoletoEmissionService::class)
                ->emitirParaContaReceber($conta, $overlay),
            EmpresaParametros::BOLETO_BANCO_SICREDI => app(SicrediBoletoEmissionService::class)
                ->emitirParaContaReceber($conta, $overlay),
            default => throw new RuntimeException(
                'Emissão via API não disponível para o banco '.$banco.'. '
                .'Suportados: Ailos (085) e Sicredi (748).'
            ),
        };

        // Trava a conta API na 1ª emissão; nunca troca em reabertura/idempotência.
        if ((int) ($boleto->boleto_conta_api_id ?? 0) <= 0) {
            $boleto->forceFill(['boleto_conta_api_id' => $boletoConta->id])->save();
        }

        return $boleto->fresh() ?? $boleto;
    }
}
