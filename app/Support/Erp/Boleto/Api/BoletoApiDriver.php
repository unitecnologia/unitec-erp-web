<?php

namespace App\Support\Erp\Boleto\Api;

use App\Models\Boleto;
use App\Models\Empresa;
use Carbon\CarbonInterface;
use RuntimeException;

/**
 * Driver de API de boleto por banco (COMPE).
 * Ailos (085) é o único implementado hoje; Sicredi etc. entram aqui depois.
 */
interface BoletoApiDriver
{
    public function supports(Empresa $empresa): bool;

    /**
     * Solicita baixa do boleto no banco e atualiza o registro no ERP.
     *
     * @throws RuntimeException
     */
    public function baixar(Boleto $boleto, ?Empresa $empresa = null): bool;

    /**
     * Solicita a baixa e só grava o boleto como baixado depois da confirmação do banco.
     *
     * @throws RuntimeException
     */
    public function baixarComConfirmacao(Boleto $boleto, ?Empresa $empresa = null): bool;

    /**
     * Solicita alteração de vencimento no banco e atualiza o boleto no ERP.
     * Não persiste a Conta a Receber — isso fica a cargo do chamador após sucesso.
     *
     * @throws RuntimeException
     */
    public function alterarVencimento(
        Boleto $boleto,
        CarbonInterface $novoVencimento,
        ?Empresa $empresa = null,
    ): void;
}
