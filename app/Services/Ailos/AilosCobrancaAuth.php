<?php

namespace App\Services\Ailos;

use App\Models\Empresa;

/**
 * Contrato mínimo de autenticação usado pelo cliente de cobrança Ailos.
 */
interface AilosCobrancaAuth
{
    /**
     * @return array{access_token: string, jwt: string}
     */
    public function credentialsForCobranca(Empresa $empresa): array;

    public function hostForEmpresa(Empresa $empresa): string;
}
