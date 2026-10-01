<?php

namespace App\Support\Erp\Pdv;

use App\Models\Empresa;
use App\Support\Erp\ErpContext;

final class PdvErpPolicy
{
    public static function habilitado(?Empresa $empresa = null): bool
    {
        $empresa ??= ErpContext::currentEmpresa();

        if ($empresa === null) {
            return true;
        }

        $raw = $empresa->getRawOriginal('param_geral_usar_pdv_erp');

        if ($raw === null) {
            return true;
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }
}
