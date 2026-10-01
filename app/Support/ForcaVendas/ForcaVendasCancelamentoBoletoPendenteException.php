<?php

namespace App\Support\ForcaVendas;

use RuntimeException;

/**
 * O pedido tem boleto bancário ativo e o usuário ainda não autorizou a baixa.
 */
final class ForcaVendasCancelamentoBoletoPendenteException extends RuntimeException
{
    public function __construct(public readonly string $numeroPedido)
    {
        parent::__construct(
            'O pedido '.$numeroPedido.' possui boleto emitido no banco. '
            .'Ao continuar será enviada a solicitação de baixa do boleto. Deseja continuar?'
        );
    }
}
