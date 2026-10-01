<?php

namespace App\Support\Erp\Nfse;

interface NfseSefinEnvio
{
    public function enviar(NfseSefinRequisicao $requisicao): NfseSefinResposta;
}
