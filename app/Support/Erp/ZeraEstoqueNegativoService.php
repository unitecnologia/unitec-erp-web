<?php

namespace App\Support\Erp;

/**
 * Compatibilidade com o modal da Empresa e a tela legada.
 * A zeragem real passa por {@see ZeraEstoqueService} → {@see AjusteEstoqueService}.
 */
final class ZeraEstoqueNegativoService
{
    public function __construct(
        private readonly ZeraEstoqueService $zera = new ZeraEstoqueService(),
    ) {}

    public function countNegativos(): int
    {
        return $this->zera->countNegativos();
    }

    /**
     * Zera o saldo dos produtos com estoque negativo no depósito da empresa atual.
     *
     * @return int Quantidade de produtos ajustados
     */
    public function zerarTodos(): int
    {
        return $this->zera->zerarNegativosEmpresa();
    }
}
