<?php

namespace App\Support\Erp\Nfse;

enum NfseEmitente: string
{
    case Prestador = '1';
    case Tomador = '2';
    case Intermediario = '3';
}
