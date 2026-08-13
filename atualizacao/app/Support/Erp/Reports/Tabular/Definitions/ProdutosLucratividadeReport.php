<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

class ProdutosLucratividadeReport extends AbstractLucratividadeReport
{
    public function slug(): string
    {
        return 'produtos-lucratividade';
    }

    public function title(): string
    {
        return 'PRODUTOS — LUCRATIVIDADE';
    }

    protected function sortOrder(): string
    {
        return 'desc';
    }
}
