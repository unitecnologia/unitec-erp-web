<?php

namespace App\Support\Erp;

/**
 * Pendência técnica do extrato de estoque (`estoque_movimentacoes`).
 *
 * Nesta 1ª entrega o log só ocorre via {@see ProductEstoqueSaldoService}.
 * Os serviços abaixo alteram saldo fora do hub e ficam sem movimentação
 * até uma entrega futura (sem mudar a regra de negócio deles agora):
 *
 * - {@see ZeraEstoqueNegativoService} — UPDATE direto em products.estoque
 * - {@see Import\ProdutosPlanilhaImportService} — create/update de estoque + updateOrCreate em product_estoque_saldos
 *
 * Não instanciar; apenas registro da dívida técnica.
 */
final class EstoqueMovimentacaoBypassesPendencia
{
    private function __construct() {}
}
