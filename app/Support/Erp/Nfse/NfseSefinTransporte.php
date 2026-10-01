<?php

namespace App\Support\Erp\Nfse;

interface NfseSefinTransporte
{
    public function enviar(NfseSefinRequisicao $requisicao): void;
}
